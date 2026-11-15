<?php

namespace App\Services\Territorio;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PronosticoRegional
{
    public function consultar(string $codigo): array
    {
        $region = DB::table('geo.departamentos')->where('codigo', $codigo)->select('nombre')
            ->selectRaw('ST_X(ST_PointOnSurface(geom)) AS longitud, ST_Y(ST_PointOnSurface(geom)) AS latitud')->first();
        abort_if($region === null, 404);

        $clave = 'pronostico:departamento:v2:'.$codigo;
        $anterior = Cache::get($clave);
        if ($anterior !== null) {
            return $anterior;
        }

        $http = Http::acceptJson()->connectTimeout(3)->timeout(7);
        $autoridades = config('territorio.pronostico_ca');
        if (is_string($autoridades) && is_readable($autoridades)) {
            $http->withOptions(['verify' => $autoridades]);
        }
        $dias = [];
        try {
            $respuesta = $http->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => round((float) $region->latitud, 2),
                'longitude' => round((float) $region->longitud, 2),
                'daily' => 'precipitation_sum,precipitation_probability_max',
                'forecast_days' => 3,
                'timezone' => 'America/Lima',
            ]);
            if ($respuesta->successful()) {
                $diario = $respuesta->json('daily');
                $fechas = is_array($diario['time'] ?? null) ? $diario['time'] : [];
                foreach (array_slice($fechas, 0, 3, preserve_keys: true) as $indice => $fecha) {
                    $lluvia = $diario['precipitation_sum'][$indice] ?? null;
                    $probabilidad = $diario['precipitation_probability_max'][$indice] ?? null;
                    if (is_string($fecha) && is_numeric($lluvia)) {
                        $dias[] = ['fecha' => $fecha, 'mm' => (float) $lluvia,
                            'probabilidad' => is_numeric($probabilidad) ? (int) $probabilidad : null];
                    }
                }
            }
        } catch (ConnectionException) {
            // El visor sigue disponible si falla el proveedor.
        }
        $resultado = ['departamento' => $region->nombre, 'disponible' => $dias !== [], 'dias' => $dias,
            'consultado_en' => now()->setTimezone('America/Lima')->toIso8601String()];
        Cache::put($clave, $resultado, now()->addMinutes($dias === [] ? 1 : 20));

        return $resultado;
    }
}
