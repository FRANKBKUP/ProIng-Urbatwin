<?php

namespace App\Services\Analisis;

use App\Services\Catalogo\CapasPublicadas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReporteExposicion
{
    private const PARAMETROS = [
        'algoritmo' => 'exposicion-v1',
        'srid_metrico' => 32717,
        'via' => 'longitud de la unión de tramos intersectados (m), excluye contacto puntual',
        'equipamiento' => 'intersección con el área de peligro, incluyendo el borde',
    ];

    public function __construct(private CapasPublicadas $capas) {}

    public function generar(int $usuarioId, int $peligroId, ?int $viasId, ?int $equipamientoId): string
    {
        return DB::transaction(function () use ($usuarioId, $peligroId, $viasId, $equipamientoId) {
            $versiones = $this->capas->vigentes()
                ->whereIn('v.id', array_filter([$peligroId, $viasId, $equipamientoId]))
                ->orderBy('c.id')->lock('FOR SHARE OF c')
                ->get()->keyBy('version_id');
            $peligro = $versiones->get($peligroId);

            if ($peligro?->tipo !== 'peligro' || ($viasId === null && $equipamientoId === null)) {
                throw ValidationException::withMessages(['peligro_id' => 'Selecciona un peligro publicado y al menos una capa de infraestructura.']);
            }

            foreach (['vias' => $viasId, 'equipamiento' => $equipamientoId] as $tipo => $id) {
                if ($id === null) {
                    continue;
                }
                $version = $versiones->get($id);
                if ($version?->tipo !== $tipo || $version->cobertura_id !== $peligro->cobertura_id) {
                    throw ValidationException::withMessages([$tipo.'_id' => 'Selecciona una versión publicada del tipo y cobertura correspondientes.']);
                }
            }

            $vias = $viasId === null ? [] : $this->vias($peligroId, $viasId);
            $equipamientos = $equipamientoId === null ? [] : $this->equipamientos($peligroId, $equipamientoId);
            $ejecucion = DB::table('analisis.ejecuciones')->insertGetId([
                'usuario_id' => $usuarioId,
                'peligro_id' => $peligroId,
                'vias_id' => $viasId,
                'equipamiento_id' => $equipamientoId,
                'parametros' => json_encode(self::PARAMETROS, JSON_THROW_ON_ERROR),
            ]);
            DB::table('analisis.resultados')->insert([
                'ejecucion_id' => $ejecucion,
                'detalle' => json_encode([...$vias, ...$equipamientos], JSON_THROW_ON_ERROR),
            ]);
            $reporte = (string) Str::uuid();
            DB::table('analisis.reportes')->insert(['id' => $reporte, 'ejecucion_id' => $ejecucion]);
            DB::table('app.auditoria')->insert([
                'usuario_id' => $usuarioId,
                'accion' => 'generar_reporte',
                'objeto' => 'reporte/'.$reporte,
            ]);

            return $reporte;
        });
    }

    private function vias(int $peligroId, int $viasId): array
    {
        $filas = DB::select(<<<'SQL'
            SELECT v.elemento, v.propiedades->>'nombre' AS nombre, ST_AsGeoJSON(tramo.geom, 9) AS tramo,
                ST_Length(ST_Transform(tramo.geom, ?::integer)) AS longitud_m
            FROM geo.vias v
            CROSS JOIN LATERAL (
                SELECT ST_UnaryUnion(ST_CollectionExtract(ST_Intersection(v.geom, ST_Union(p.geom)), 2)) AS geom
                FROM geo.peligro p
                WHERE p.version_id = ? AND ST_Intersects(v.geom, p.geom)
            ) tramo
            WHERE v.version_id = ? AND NOT ST_IsEmpty(tramo.geom)
            ORDER BY v.elemento
            SQL, [self::PARAMETROS['srid_metrico'], $peligroId, $viasId]);

        return array_map(fn ($fila) => [
            'tipo' => 'Vía',
            'elemento' => $fila->elemento,
            'nombre' => $fila->nombre ?: 'Vía '.$fila->elemento,
            'longitud_m' => (float) $fila->longitud_m,
            'geometria' => json_decode($fila->tramo),
        ], $filas);
    }

    private function equipamientos(int $peligroId, int $equipamientoId): array
    {
        $filas = DB::select(<<<'SQL'
            SELECT e.elemento, e.propiedades->>'nombre' AS nombre,
                e.propiedades->>'tipo' AS tipo, ST_AsGeoJSON(e.geom, 9) AS geometria
            FROM geo.equipamiento e
            WHERE e.version_id = ? AND EXISTS (
                SELECT 1 FROM geo.peligro p WHERE p.version_id = ? AND ST_Intersects(p.geom, e.geom)
            )
            ORDER BY e.elemento
            SQL, [$equipamientoId, $peligroId]);

        return array_map(fn ($fila) => [
            'tipo' => $fila->tipo ?: 'Equipamiento',
            'elemento' => $fila->elemento,
            'nombre' => $fila->nombre ?: 'Equipamiento '.$fila->elemento,
            'longitud_m' => null,
            'geometria' => json_decode($fila->geometria),
        ], $filas);
    }
}
