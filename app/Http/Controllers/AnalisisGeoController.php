<?php

namespace App\Http\Controllers;

use App\Services\Analisis\ReporteExposicion;
use App\Services\Catalogo\CapasPublicadas;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnalisisGeoController extends Controller
{
    public function index(Request $request, CapasPublicadas $capas): View
    {
        $datos = $request->validate([
            'serie' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'required_with:hasta', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'required_with:desde', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'agrupar' => ['nullable', Rule::in(['automatico', 'diario', 'mensual', 'anual'])],
        ]);
        $series = DB::table('geo.lluvia_series')->orderBy('zona')->orderByDesc('inicio')
            ->get(['id', 'zona', 'producto', 'inicio', 'fin', 'unidad', 'metodo', 'fuente', DB::raw('md5(ST_AsEWKB(geom)) as area_clave')]);
        $seleccion = $request->filled('serie')
            ? $series->firstWhere('id', (int) $request->input('serie'))
            : $series->first();
        abort_if($request->filled('serie') && ! $seleccion, 404);
        $rangos = collect();
        foreach ($series->groupBy(fn ($serie) => hash('sha256', json_encode([
            $serie->zona, $serie->producto, $serie->unidad, $serie->metodo, $serie->area_clave,
        ], JSON_THROW_ON_ERROR))) as $compatibles) {
            $bloque = collect();
            foreach ($compatibles->sortBy('inicio') as $serie) {
                if ($bloque->isNotEmpty() && CarbonImmutable::parse($bloque->last()->fin)->addDay()->format('Y-m-d') !== $serie->inicio) {
                    $rangos->push($bloque);
                    $bloque = collect();
                }
                $bloque->push($serie);
            }
            if ($bloque->isNotEmpty()) {
                $rangos->push($bloque);
            }
        }
        $opciones = $rangos->map(function ($bloque) use ($seleccion) {
            $representante = $seleccion && $bloque->contains('id', $seleccion->id) ? $seleccion : $bloque->first();
            $representante->inicio_disponible = $bloque->first()->inicio;
            $representante->fin_disponible = $bloque->last()->fin;
            $representante->series_compatibles = $bloque->pluck('id')->all();

            return $representante;
        });
        $lluvia = null;
        if ($seleccion) {
            $desde = $datos['desde'] ?? $seleccion->inicio_disponible;
            $hasta = $datos['hasta'] ?? $seleccion->fin_disponible;
            if ($desde < $seleccion->inicio_disponible || $hasta > $seleccion->fin_disponible) {
                throw ValidationException::withMessages(['desde' => 'Selecciona fechas dentro del período disponible.']);
            }
            $cantidadDias = (int) CarbonImmutable::parse($desde)->diffInDays(CarbonImmutable::parse($hasta)) + 1;
            $agrupacionSolicitada = $datos['agrupar'] ?? 'automatico';
            $agrupacion = $agrupacionSolicitada === 'automatico'
                ? ($cantidadDias <= 92 ? 'diario' : ($cantidadDias <= 730 ? 'mensual' : 'anual'))
                : $agrupacionSolicitada;
            if ($agrupacion === 'diario' && $cantidadDias > 366) {
                throw ValidationException::withMessages(['agrupar' => 'Para más de 366 días, elige la agrupación mensual o anual.']);
            }
            $registros = DB::table('geo.lluvia_diaria')->whereIn('serie_id', $seleccion->series_compatibles)
                ->whereBetween('fecha', [$desde, $hasta])
                ->orderBy('fecha')->get(['fecha', 'precipitacion_mm', 'cobertura'])->keyBy('fecha');
            $dias = collect(CarbonPeriod::create($desde, $hasta))->map(function ($fecha) use ($registros) {
                $registro = $registros->get($fecha->format('Y-m-d'));

                return ['fecha' => $fecha->format('Y-m-d'), 'valor' => $registro ? (float) $registro->precipitacion_mm : null];
            });
            $disponibles = $dias->filter(fn (array $dia) => $dia['valor'] !== null);
            $grafico = $dias->groupBy(fn (array $dia) => match ($agrupacion) {
                'anual' => substr($dia['fecha'], 0, 4),
                'mensual' => substr($dia['fecha'], 0, 7),
                default => $dia['fecha'],
            })->map(function ($grupo, string $clave) use ($agrupacion) {
                $valores = $grupo->filter(fn (array $dia) => $dia['valor'] !== null);
                $etiqueta = match ($agrupacion) {
                    'anual' => $clave,
                    'mensual' => CarbonImmutable::parse($clave.'-01')->format('m/Y'),
                    default => CarbonImmutable::parse($clave)->format('d/m/Y'),
                };

                return ['etiqueta' => $etiqueta, 'valor' => $valores->isEmpty() ? null : $valores->sum('valor'),
                    'faltantes' => $grupo->count() - $valores->count()];
            })->values();
            $lluvia = [
                'serie' => $seleccion,
                'dias' => $dias,
                'disponibles' => $disponibles->count(),
                'faltantes' => $dias->count() - $disponibles->count(),
                'total' => $disponibles->isEmpty() ? null : $disponibles->sum('valor'),
                'mayor' => $disponibles->sortByDesc('valor')->first(),
                'grafico' => $grafico,
                'maximo_grafico' => $grafico->max('valor'),
                'agrupacion' => $agrupacion,
                'agrupacion_solicitada' => $agrupacionSolicitada,
                'desde' => $desde,
                'hasta' => $hasta,
                'inicio' => CarbonImmutable::parse($desde)->format('d/m/Y'),
                'fin' => CarbonImmutable::parse($hasta)->format('d/m/Y'),
            ];
        }

        return view('geo.reportes', [
            'seriesLluvia' => $opciones,
            'lluvia' => $lluvia,
            'capas' => $capas->disponibles()->whereIn('c.tipo', ['peligro', 'vias', 'equipamiento'])
                ->orderBy('c.nombre')->get()->groupBy('tipo'),
            'reportes' => $this->reportes()->when(! $request->user()->tieneRol('Administrador'),
                fn (Builder $consulta) => $consulta->where('e.usuario_id', $request->user()->id)
            )->orderByDesc('r.creado_en')->orderByDesc('r.id')->simplePaginate(20),
        ]);
    }

    public function generar(Request $request, ReporteExposicion $analisis): JsonResponse|RedirectResponse
    {
        $datos = $request->validate([
            'peligro_id' => ['required', 'integer', 'min:1'],
            'vias_id' => ['nullable', 'required_without:equipamiento_id', 'integer', 'min:1'],
            'equipamiento_id' => ['nullable', 'required_without:vias_id', 'integer', 'min:1'],
        ]);
        $id = $analisis->generar($request->user()->id, (int) $datos['peligro_id'],
            isset($datos['vias_id']) ? (int) $datos['vias_id'] : null,
            isset($datos['equipamiento_id']) ? (int) $datos['equipamiento_id'] : null);
        $url = route('geo.reportes.mostrar', $id);

        return $request->expectsJson()
            ? response()->json(['id' => $id, 'url' => $url], 201)
            : redirect()->to($url)->with('ok', 'Reporte guardado con las versiones seleccionadas.');
    }

    public function mostrar(Request $request, string $reporte, CapasPublicadas $capas): View
    {
        $registro = $this->reportes()->where('r.id', $reporte)->addSelect(
            'e.peligro_id', 'e.vias_id', 'e.equipamiento_id', 'e.parametros', 's.detalle'
        )->first();
        abort_if(! $registro, 404);
        abort_unless($request->user()->tieneRol('Administrador') || $registro->usuario_id === $request->user()->id, 403);

        return view('geo.reporte', [
            'reporte' => $registro,
            'parametros' => json_decode($registro->parametros, true, 64, JSON_THROW_ON_ERROR),
            'resultados' => json_decode($registro->detalle, true, 64, JSON_THROW_ON_ERROR),
            'versiones' => $capas->versiones()->whereIn('v.id', array_filter([
                $registro->peligro_id, $registro->vias_id, $registro->equipamiento_id,
            ]))->orderBy('c.tipo')->get(),
        ]);
    }

    private function reportes(): Builder
    {
        return DB::table('analisis.reportes as r')
            ->join('analisis.ejecuciones as e', 'e.id', '=', 'r.ejecucion_id')
            ->join('analisis.resultados as s', 's.ejecucion_id', '=', 'e.id')
            ->join('catalogo.capas_versiones as v', 'v.id', '=', 'e.peligro_id')
            ->join('catalogo.capas as c', 'c.id', '=', 'v.capa_id')
            ->select('r.id', 'r.creado_en', 'e.usuario_id', 's.total', 'c.nombre as peligro', 'v.numero');
    }
}
