<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MapaApiController extends Controller
{
    private function feature(string $geojson, array $props): array
    {
        return ['type' => 'Feature', 'properties' => $props, 'geometry' => json_decode($geojson)];
    }

    public function zonas()
    {
        $filas = DB::select('SELECT id, nombre, ST_AsGeoJSON(limite) AS g FROM zonas');
        return response()->json(array_map(fn ($f) => [
            'id' => $f->id,
            'nombre' => $f->nombre,
            'limite' => json_decode($f->g),
        ], $filas));
    }

    public function infraestructuras(int $id)
    {
        $filas = DB::select(
            'SELECT i.id, i.nombre, t.nombre AS tipo, ST_AsGeoJSON(i.geometria) AS g
             FROM infraestructuras i
             JOIN tipos_infraestructura t ON t.id = i.tipo_id
             WHERE i.zona_id = ? AND i.activo = 1',
            [$id]
        );
        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_map(
                fn ($f) => $this->feature($f->g, ['id' => $f->id, 'nombre' => $f->nombre, 'tipo' => $f->tipo]),
                $filas
            ),
        ]);
    }

    public function escenarios(int $id)
    {
        return response()->json(
            DB::table('escenarios')
                ->where('zona_id', $id)->where('activo', 1)
                ->orderByRaw("FIELD(nivel, 'leve', 'moderado', 'extremo')")
                ->get(['id', 'nombre', 'nivel', 'origen'])
        );
    }

    public function escenario(int $id)
    {
        $e = DB::selectOne(
            'SELECT id, nombre, nivel, origen, ST_AsGeoJSON(area_inundada) AS g
             FROM escenarios WHERE id = ? AND activo = 1',
            [$id]
        );
        abort_if(!$e, 404, 'Escenario no encontrado');

        return response()->json($this->feature($e->g, [
            'id' => $e->id, 'nombre' => $e->nombre, 'nivel' => $e->nivel, 'origen' => $e->origen,
        ]));
    }

    public function exposicion(int $id)
    {
        abort_if(!DB::table('escenarios')->where('id', $id)->where('activo', 1)->exists(), 404, 'Escenario no encontrado');

        // El cruce espacial del sistema (RF09 / RN04)
        $filas = DB::select(
            'SELECT i.id, i.nombre, t.nombre AS tipo, ST_AsGeoJSON(i.geometria) AS g
             FROM infraestructuras i
             JOIN tipos_infraestructura t ON t.id = i.tipo_id
             JOIN escenarios e ON e.zona_id = i.zona_id
             WHERE e.id = ? AND i.activo = 1
               AND ST_Intersects(i.geometria, e.area_inundada) = 1',
            [$id]
        );

        return response()->json([
            'total' => count($filas),
            'por_tipo' => collect($filas)->countBy('tipo'),
            'infraestructuras' => [
                'type' => 'FeatureCollection',
                'features' => array_map(
                    fn ($f) => $this->feature($f->g, ['id' => $f->id, 'nombre' => $f->nombre, 'tipo' => $f->tipo]),
                    $filas
                ),
            ],
        ]);
    }
}