<?php

namespace App\Http\Controllers;

use App\Services\Analisis\ReporteExposicion;
use App\Services\Catalogo\CapasPublicadas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalisisGeoController extends Controller
{
    public function index(Request $request, CapasPublicadas $capas): View
    {
        return view('geo.reportes', [
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
