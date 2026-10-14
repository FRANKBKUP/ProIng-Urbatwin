<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InfraestructuraImportService
{
    private const MAX_ELEMENTOS = 5000;

    public function __construct(private GeoJsonService $geo)
    {
    }

    /**
     * @return array{insertados:int, omitidos:int, motivos:string[]}
     */
    public function importar(string $texto, int $zonaId, int $tipoId): array
    {
        $dato = json_decode($texto, true);
        if (!is_array($dato) || ($dato['type'] ?? null) !== 'FeatureCollection' || !is_array($dato['features'] ?? null)) {
            throw new InvalidArgumentException('El archivo debe ser un GeoJSON de tipo FeatureCollection.');
        }
        if (count($dato['features']) > self::MAX_ELEMENTOS) {
            throw new InvalidArgumentException('Máximo ' . self::MAX_ELEMENTOS . ' elementos por archivo.');
        }

        $res = ['insertados' => 0, 'omitidos' => 0, 'motivos' => []];

        DB::transaction(function () use ($dato, $zonaId, $tipoId, &$res) {
            foreach ($dato['features'] as $i => $f) {
                $n = $i + 1;

                try {
                    $geom = $this->geo->geometria(is_array($f) ? ($f['geometry'] ?? null) : null);

                    // Solo entra lo que toca la zona (detecta también lat/lon invertidas)
                    $dentro = DB::selectOne(
                        'SELECT ST_Intersects(ST_GeomFromGeoJSON(?), limite) AS ok FROM zonas WHERE id = ?',
                        [$geom, $zonaId]
                    );
                    if (!$dentro || !$dentro->ok) {
                        throw new InvalidArgumentException('queda fuera de la zona elegida.');
                    }

                    $props = $f['properties'] ?? [];
                    $nombre = trim((string) ($props['nombre'] ?? $props['name'] ?? '')) ?: "Sin nombre $n";

                    DB::insert(
                        'INSERT INTO infraestructuras (zona_id, tipo_id, nombre, geometria)
                         VALUES (?, ?, ?, ST_GeomFromGeoJSON(?))',
                        [$zonaId, $tipoId, mb_substr($nombre, 0, 120), $geom]
                    );
                    $res['insertados']++;
                } catch (InvalidArgumentException $e) {
                    $res['omitidos']++;
                    $res['motivos'][] = "Elemento $n: " . $e->getMessage();
                } catch (QueryException) {
                    $res['omitidos']++;
                    $res['motivos'][] = "Elemento $n: MariaDB no pudo interpretar la geometría.";
                }
            }
        });

        $res['motivos'] = array_slice($res['motivos'], 0, 20); // no inundar la pantalla
        return $res;
    }
}