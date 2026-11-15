<?php

namespace App\Services\Catalogo;

use App\Services\GeoJsonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

class ImportarCapa
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_ELEMENTOS = 5000;

    public function __construct(private GeoJsonService $geoJson) {}

    public function ejecutar(int $capaId, int $usuarioId, string $nombre, string $original, array $metadatos): array
    {
        if (strlen($original) > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo supera los 5 MiB.');
        }

        return DB::transaction(function () use ($capaId, $usuarioId, $nombre, $original, $metadatos) {
            $capa = DB::table('catalogo.capas')->where('id', $capaId)->lockForUpdate()->first();
            abort_if(! $capa, 404);

            $hash = hash('sha256', $original);
            $previa = DB::table('catalogo.importaciones as i')
                ->join('catalogo.archivos as a', 'a.id', '=', 'i.archivo_id')
                ->leftJoin('catalogo.capas_versiones as v', 'v.importacion_id', '=', 'i.id')
                ->where('i.capa_id', $capaId)->where('a.sha256', $hash)
                ->first(['i.id', 'i.estado', 'v.id as version_id']);

            if ($previa) {
                return ['id' => $previa->id, 'estado' => $previa->estado, 'version_id' => $previa->version_id, 'duplicada' => true];
            }

            $ruta = 'originales/'.Str::uuid().'.geojson';
            if (! Storage::disk('local')->put($ruta, $original)) {
                throw new RuntimeException('No se pudo conservar el archivo original.');
            }

            $archivoId = DB::table('catalogo.archivos')->insertGetId([
                'ruta' => $ruta,
                'sha256' => $hash,
                'nombre' => mb_substr($nombre, 0, 255),
                'bytes' => strlen($original),
                'paquete_id' => $metadatos['paquete_id'] ?? null,
            ]);
            $fuente = [
                'nombre' => $metadatos['fuente'],
                'licencia' => $metadatos['licencia'],
                'origen' => $metadatos['origen'],
            ];
            DB::table('catalogo.fuentes')->insertOrIgnore($fuente);
            $fuenteId = DB::table('catalogo.fuentes')->where($fuente)->value('id');

            [$entidades, $errores] = $this->validar($original, $capa->tipo, $capa->cobertura_id);
            $estado = $errores === [] ? 'publicada' : 'rechazada';
            $importacionId = DB::table('catalogo.importaciones')->insertGetId([
                'capa_id' => $capaId,
                'archivo_id' => $archivoId,
                'usuario_id' => $usuarioId,
                'fuente_id' => $fuenteId,
                'fecha_dato' => $metadatos['fecha_dato'],
                'estado' => $estado,
            ]);

            $versionId = null;
            if ($errores === []) {
                $versionId = $this->publicar($capaId, $capa->tipo, $importacionId, $entidades);
            } else {
                foreach (array_chunk($errores, 500, preserve_keys: true) as $lote) {
                    $filas = [];
                    foreach ($lote as $elemento => $motivo) {
                        $filas[] = ['importacion_id' => $importacionId, 'elemento' => $elemento, 'motivo' => $motivo];
                    }
                    DB::table('catalogo.importacion_errores')->insert($filas);
                }
            }

            DB::table('app.auditoria')->insert([
                'usuario_id' => $usuarioId,
                'accion' => 'importacion_'.$estado,
                'objeto' => 'importacion/'.$importacionId,
                'detalle' => json_encode(['capa_id' => $capaId, 'version_id' => $versionId], JSON_THROW_ON_ERROR),
            ]);

            return ['id' => $importacionId, 'estado' => $estado, 'version_id' => $versionId, 'duplicada' => false];
        });
    }

    private function validar(string $original, string $tipo, int $coberturaId): array
    {
        try {
            $documento = json_decode($original, false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [[], [0 => 'El archivo no contiene JSON válido.']];
        }

        if (($documento->type ?? null) !== 'FeatureCollection' || ! is_array($documento->features ?? null)) {
            return [[], [0 => 'Se requiere un GeoJSON FeatureCollection.']];
        }
        if (property_exists($documento, 'crs')) {
            return [[], [0 => 'Usa GeoJSON RFC 7946 con coordenadas WGS 84 en orden longitud, latitud.']];
        }
        if (count($documento->features) < 1 || count($documento->features) > self::MAX_ELEMENTOS) {
            return [[], [0 => 'La colección debe tener entre 1 y 5000 entidades.']];
        }

        $tipos = match ($tipo) {
            'peligro' => ['Polygon', 'MultiPolygon'],
            'equipamiento' => ['Point'],
            'vias', 'curvas' => ['LineString', 'MultiLineString'],
        };
        $entidades = [];
        $errores = [];
        foreach ($documento->features as $indice => $feature) {
            try {
                $entidades[$indice + 1] = $this->validarEntidad($feature, $tipos, $tipo, $coberturaId);
            } catch (InvalidArgumentException $e) {
                $errores[$indice + 1] = $e->getMessage();
            }
        }

        return [$entidades, $errores];
    }

    private function validarEntidad(mixed $feature, array $tipos, string $tipo, int $coberturaId): array
    {
        $geom = $feature->geometry ?? null;
        if (($feature->type ?? null) !== 'Feature' || ! $geom instanceof stdClass
            || property_exists($geom, 'crs') || ! in_array($geom->type ?? null, $tipos, true)) {
            throw new InvalidArgumentException('La geometría debe ser '.implode(' o ', $tipos).' en CRS84.');
        }
        $propiedades = $feature->properties ?? new stdClass;
        if (! $propiedades instanceof stdClass) {
            throw new InvalidArgumentException('Las propiedades deben ser un objeto o null.');
        }
        $cota = $propiedades->cota_m ?? null;
        if ($tipo === 'curvas' && ((! is_int($cota) && ! is_float($cota)) || ! is_finite($cota) || $cota < -12000 || $cota > 10000)) {
            throw new InvalidArgumentException('La curva requiere cota_m numérica entre -12000 y 10000.');
        }
        $geometria = $this->geoJson->geometria((array) $geom, estricto: true);
        try {
            $propiedadesJson = json_encode($propiedades, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Las propiedades contienen valores fuera del rango JSON admitido.');
        }
        $resultado = DB::selectOne(<<<'SQL'
            SELECT CASE
                WHEN NOT pg_input_is_valid(?::text, 'jsonb') THEN 'Las propiedades no se pueden conservar en JSONB.'
                WHEN ST_IsEmpty(g.geom) THEN 'Geometría vacía.'
                WHEN NOT ST_IsValid(g.geom) THEN ST_IsValidReason(g.geom)
                WHEN NOT ST_IsSimple(g.geom) THEN 'La geometría se cruza consigo misma.'
                WHEN NOT ST_Covers(c.geom, g.geom) THEN 'La geometría excede la cobertura de la capa.'
            END AS motivo
            FROM geo.coberturas c
            CROSS JOIN (SELECT ST_GeomFromGeoJSON(?::text) AS geom) g
            WHERE c.id = ?
            SQL, [$propiedadesJson, $geometria, $coberturaId]);

        if ($resultado->motivo !== null) {
            throw new InvalidArgumentException($resultado->motivo);
        }

        return ['geom' => $geometria, 'propiedades' => $propiedadesJson, 'cota' => $cota];
    }

    private function publicar(int $capaId, string $tipo, int $importacionId, array $entidades): int
    {
        $numero = DB::table('catalogo.capas_versiones')->where('capa_id', $capaId)->max('numero');
        $versionId = DB::table('catalogo.capas_versiones')->insertGetId([
            'capa_id' => $capaId,
            'tipo' => $tipo,
            'importacion_id' => $importacionId,
            'numero' => $numero + 1,
            'crs_origen' => 'OGC:CRS84',
        ]);

        foreach ($entidades as $elemento => $entidad) {
            $valores = [$versionId, $elemento, $entidad['geom'], $entidad['propiedades']];
            if ($tipo === 'curvas') {
                DB::insert('INSERT INTO geo.curvas (version_id, elemento, geom, propiedades, cota_m)
                    VALUES (?, ?, ST_Multi(ST_GeomFromGeoJSON(?::text)), ?::jsonb, ?)', [...$valores, $entidad['cota']]);
            } else {
                $geomSql = $tipo === 'equipamiento' ? 'ST_GeomFromGeoJSON(?::text)' : 'ST_Multi(ST_GeomFromGeoJSON(?::text))';
                DB::insert("INSERT INTO geo.$tipo (version_id, elemento, geom, propiedades)
                    VALUES (?, ?, $geomSql, ?::jsonb)", $valores);
            }
        }

        DB::table('catalogo.capas_versiones')->where('id', $versionId)->update(['publicada_en' => now()]);
        DB::table('catalogo.capas')->where('id', $capaId)->update(['version_vigente_id' => $versionId]);

        return $versionId;
    }
}
