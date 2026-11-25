<?php

namespace App\Services\Territorio;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PronosticoRegional
{
    private const PRONOSTICO = 'https://api.open-meteo.com/v1/forecast';

    private const ARCHIVO = 'https://archive-api.open-meteo.com/v1/archive';

    private const ZONA_HORARIA = 'America/Lima';

    public function consultar(string $codigo): array
    {
        $region = $this->referencia($codigo);

        return $this->diario($region, $codigo, 3) + ['departamento' => $region->nombre];
    }

    public function pronostico(string $codigo): array
    {
        return $this->diario($this->referencia($codigo), $codigo, 7);
    }

    public function historico(string $codigo): array
    {
        $lugar = $this->referencia($codigo);
        $ultimo = now(self::ZONA_HORARIA)->year - 1;
        $primero = $ultimo - 9;
        $clave = "lluvia:era5:v1:$codigo:$ultimo";

        return $this->conCache($clave, 'anios', 43200, function () use ($lugar, $primero, $ultimo) {
            $datos = $this->pedir(self::ARCHIVO, $this->coordenadas($lugar) + [
                'models' => 'era5',
                'daily' => 'precipitation_sum',
                'start_date' => "$primero-01-01",
                'end_date' => "$ultimo-12-31",
            ])['daily'] ?? [];

            $totales = [];
            foreach ($datos['time'] ?? [] as $indice => $fecha) {
                $mm = $datos['precipitation_sum'][$indice] ?? null;
                if (is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha) && is_numeric($mm) && $mm >= 0) {
                    $totales[(int) substr($fecha, 0, 4)][$fecha] = (float) $mm;
                }
            }
            $anios = [];
            foreach (range($primero, $ultimo) as $anio) {
                $dias = $totales[$anio] ?? [];
                $esperados = Carbon::createFromDate($anio, 1, 1)->isLeapYear() ? 366 : 365;
                $anios[] = ['anio' => $anio, 'mm' => count($dias) === $esperados ? round(array_sum($dias), 1) : null,
                    'dias' => count($dias), 'esperados' => $esperados];
            }

            return ['lugar' => $lugar->nombre, 'punto' => $this->coordenadas($lugar), 'fuente' => 'ERA5 · ECMWF/Copernicus vía Open-Meteo',
                'disponible' => collect($anios)->contains(fn (array $anio) => $anio['mm'] !== null), 'anios' => $anios];
        });
    }

    public function mapa(string $codigo): array
    {
        abort_unless($codigo === 'PE' || DB::table('geo.departamentos')->where('codigo', $codigo)->exists(), 404);

        return $this->conCache("lluvia:mapa:v2:$codigo", 'pasos', 20, function () use ($codigo) {
            $puntos = $this->puntos($codigo === 'PE' ? 'geo.departamentos' : 'geo.provincias')
                ->when($codigo !== 'PE', fn (Builder $consulta) => $consulta->where('departamento', $codigo))
                ->orderBy('codigo')->limit(26)->get();
            if ($puntos->isEmpty() && $codigo !== 'PE') {
                $puntos = collect([$this->referencia($codigo)]);
            }
            if ($puntos->isEmpty()) {
                return ['disponible' => false, 'pasos' => [], 'puntos' => 0];
            }

            $coordenadas = $puntos->map(fn (object $punto) => $this->coordenadas($punto));
            $respuesta = $this->pedir(self::PRONOSTICO, [
                'latitude' => $coordenadas->pluck('latitude')->implode(','),
                'longitude' => $coordenadas->pluck('longitude')->implode(','),
                'hourly' => 'precipitation',
                'forecast_hours' => 24,
            ]);

            $lugares = isset($respuesta['hourly']) ? [$respuesta] : $respuesta;
            $pasos = [];
            foreach ($puntos as $indice => $punto) {
                $horas = $lugares[$indice]['hourly'] ?? [];
                foreach (array_slice($horas['time'] ?? [], 0, 24, true) as $hora => $fecha) {
                    $mm = $horas['precipitation'][$hora] ?? null;
                    if (is_string($fecha) && is_numeric($mm) && $mm >= 0) {
                        $pasos[$fecha][] = ['codigo' => $punto->codigo, 'nombre' => $punto->nombre,
                            'lon' => (float) $punto->longitud, 'lat' => (float) $punto->latitud, 'mm' => (float) $mm];
                    }
                }
            }
            ksort($pasos);
            $serie = [];
            foreach ($pasos as $fecha => $muestras) {
                $serie[] = ['fecha' => $fecha, 'muestras' => $muestras];
            }

            return ['disponible' => $serie !== [], 'pasos' => $serie, 'puntos' => $puntos->count(),
                'consultado_en' => now(self::ZONA_HORARIA)->toIso8601String()];
        });
    }

    private function diario(object $lugar, string $codigo, int $horizonte): array
    {
        return $this->conCache("lluvia:diaria:v1:$codigo:$horizonte", 'dias', 20, function () use ($lugar, $horizonte) {
            $diario = $this->pedir(self::PRONOSTICO, $this->coordenadas($lugar) + [
                'daily' => 'precipitation_sum,precipitation_probability_max',
                'forecast_days' => $horizonte,
            ])['daily'] ?? [];

            $dias = [];
            foreach (array_slice($diario['time'] ?? [], 0, $horizonte, true) as $indice => $fecha) {
                $mm = $diario['precipitation_sum'][$indice] ?? null;
                $probabilidad = $diario['precipitation_probability_max'][$indice] ?? null;
                if (is_string($fecha) && is_numeric($mm) && $mm >= 0) {
                    $dias[] = ['fecha' => $fecha, 'mm' => (float) $mm,
                        'probabilidad' => is_numeric($probabilidad) ? (int) $probabilidad : null];
                }
            }

            return ['lugar' => $lugar->nombre, 'punto' => $this->coordenadas($lugar), 'disponible' => $dias !== [],
                'dias' => $dias, 'consultado_en' => now(self::ZONA_HORARIA)->toIso8601String()];
        });
    }

    private function referencia(string $codigo): object
    {
        $tabla = str_starts_with($codigo, 'PE-') ? 'geo.departamentos' : 'geo.provincias';
        $lugar = $this->puntos($tabla)->where('codigo', $codigo)->first();
        abort_if($lugar === null, 404);

        return $lugar;
    }

    private function puntos(string $tabla): Builder
    {
        return DB::table($tabla)->select(['codigo', 'nombre'])
            ->selectRaw('ST_X(ST_PointOnSurface(geom)) AS longitud, ST_Y(ST_PointOnSurface(geom)) AS latitud');
    }

    private function coordenadas(object $punto): array
    {
        return ['latitude' => round((float) $punto->latitud, 2), 'longitude' => round((float) $punto->longitud, 2)];
    }

    private function conCache(string $clave, string $serie, int $minutos, callable $cargar): array
    {
        $anterior = Cache::get($clave);
        if ($anterior !== null) {
            return $anterior;
        }

        $resultado = $cargar();
        $disponible = $resultado['disponible'] && $resultado[$serie] !== [];
        if ($disponible) {
            Cache::put($clave.':ultimo', $resultado, now()->addMinutes($minutos * 6));
        } else {
            $ultimo = Cache::get($clave.':ultimo');
            if ($ultimo !== null) {
                $resultado = $ultimo + ['desactualizado' => true];
            }
        }
        Cache::put($clave, $resultado, now()->addMinutes($disponible ? $minutos : 1));

        return $resultado;
    }

    private function pedir(string $url, array $parametros): array
    {
        $http = Http::acceptJson()->connectTimeout(3)->timeout(9);
        $autoridades = config('territorio.pronostico_ca');
        if (is_string($autoridades) && is_readable($autoridades)) {
            $http->withOptions(['verify' => $autoridades]);
        }

        try {
            $respuesta = $http->get($url, $parametros + ['timezone' => self::ZONA_HORARIA]);

            return $respuesta->successful() ? (array) $respuesta->json() : [];
        } catch (ConnectionException) {
            return [];
        }
    }
}
