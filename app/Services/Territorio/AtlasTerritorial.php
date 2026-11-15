<?php

namespace App\Services\Territorio;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AtlasTerritorial
{
    public function temas(): Collection
    {
        return DB::table('geo.temas')->whereNull('retirada_en')->orderByDesc('tipo')->orderBy('codigo')
            ->get(['codigo', 'tipo', DB::raw('coalesce(etiqueta, titulo) AS titulo'), 'periodo', 'fuente', 'ficha_url', 'archivo_sha256', 'leyenda', 'notas'])
            ->map(function (object $tema) {
                $tema->leyenda = json_decode($tema->leyenda, true, 16, JSON_THROW_ON_ERROR);

                return $tema;
            })->keyBy('codigo');
    }

    public function departamentos(): Collection
    {
        return DB::table('geo.departamentos')->orderBy('nombre')->get(['codigo', 'nombre']);
    }

    public function provincias(): Collection
    {
        return DB::table('geo.provincias')->orderBy('nombre')->get(['codigo', 'departamento', 'nombre']);
    }

    public function provincia(string $codigo): array
    {
        $fila = DB::table('geo.provincias')->where('codigo', $codigo)
            ->select('nombre', 'departamento')
            ->selectRaw('ST_AsGeoJSON(ST_Multi(ST_SimplifyPreserveTopology(geom, 0.001)), 6) AS geometria')
            ->selectRaw('ST_XMin(geom) AS oeste, ST_YMin(geom) AS sur, ST_XMax(geom) AS este, ST_YMax(geom) AS norte')
            ->first();
        abort_if($fila === null, 404);

        return [
            'nombre' => $fila->nombre,
            'departamento' => $fila->departamento,
            'limites' => [[$fila->oeste, $fila->sur], [$fila->este, $fila->norte]],
            'geometria' => json_decode($fila->geometria, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    public function equipo(): Collection
    {
        return DB::table('app.referencias_equipo as r')
            ->join('app.superusuarios as s', 's.plaza', '=', 'r.plaza')
            ->orderBy('s.plaza')->select('s.plaza', 's.nombre', 'r.ciudad')
            ->selectRaw('ST_X(r.geom) AS longitud, ST_Y(r.geom) AS latitud')->get();
    }

    public function consultar(string $tema, float $longitud, float $latitud): ?object
    {
        abort_unless(DB::table('geo.temas')->where('codigo', $tema)->whereNull('retirada_en')->exists(), 404);

        return DB::table('geo.zonas_tematicas as z')
            ->crossJoin(DB::raw('(SELECT ST_SetSRID(ST_MakePoint(?::float8, ?::float8), 4326) geom) punto'))
            ->addBinding([$longitud, $latitud], 'join')
            ->where('z.tema', $tema)->whereRaw('ST_Covers(z.geom, punto.geom)')
            ->orderByRaw('ST_Contains(z.geom, punto.geom) DESC')->orderByDesc('z.clase')->orderBy('z.id')
            ->first(['z.clase', 'z.distrito', 'z.departamento', 'z.ubigeo']);
    }

    public function limites(): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => DB::table('geo.departamentos')->orderBy('codigo')
                ->select('codigo', 'nombre')
                ->selectRaw('ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.0025), 5) AS geometria')
                ->get()->map(fn (object $fila) => [
                    'type' => 'Feature',
                    'geometry' => json_decode($fila->geometria, true, 512, JSON_THROW_ON_ERROR),
                    'properties' => ['nombre' => $fila->nombre, 'codigo' => $fila->codigo],
                ])->all(),
        ];
    }

    public function resumen(Collection $temas): array
    {
        $datos = DB::table('geo.zonas_tematicas')->whereIn('tema', $temas->keys())
            ->groupBy('tema', 'clase')->select('tema', 'clase')
            ->selectRaw('count(*) AS distritos, sum(km2) AS superficie')->get()->groupBy('tema');

        return $temas->map(function (object $tema) use ($datos) {
            $clases = $datos->get($tema->codigo, collect())->keyBy('clase');
            $unidad = $tema->tipo === 'riesgo' ? 'distritos' : 'km²';
            $campo = $tema->tipo === 'riesgo' ? 'distritos' : 'superficie';
            $total = $clases->sum($campo);
            $series = collect($tema->leyenda)->map(function (array $leyenda) use ($clases, $campo, $total) {
                $valor = (float) ($clases->get($leyenda['clase'])->{$campo} ?? 0);

                return [...$leyenda, 'valor' => $valor, 'porcentaje' => $total > 0 ? ($valor / $total) * 100 : 0];
            });

            return ['titulo' => $tema->titulo, 'periodo' => $tema->periodo, 'fuente' => $tema->fuente,
                'ficha_url' => $tema->ficha_url, 'unidad' => $unidad, 'total' => $total, 'series' => $series];
        })->all();
    }

    public function tesela(string $tema, int $z, int $x, int $y): string
    {
        $huella = DB::table('geo.temas')->where('codigo', $tema)->whereNull('retirada_en')->value('archivo_sha256');
        abort_if($huella === null, 404);
        abort_if($z > 12 || $x >= 2 ** $z || $y >= 2 ** $z, 404);

        $mvt = Cache::remember('atlas:mvt:'.$tema.':'.$huella.":$z:$x:$y", now()->addMinutes(30),
            fn () => DB::selectOne(<<<'SQL'
                WITH caja AS (
                    SELECT ST_TileEnvelope(?::integer, ?::integer, ?::integer) mvt
                ), limites AS (
                    SELECT mvt, ST_Transform(ST_Expand(mvt, (ST_XMax(mvt)-ST_XMin(mvt))/64), 4326) wgs
                    FROM caja
                ), elementos AS (
                    SELECT s.id, s.clase, s.distrito, s.departamento, s.ubigeo,
                        ST_AsMVTGeom(ST_Transform(s.geom, 3857), l.mvt, 4096, 64) geom
                    FROM geo.zonas_tematicas s CROSS JOIN limites l
                    WHERE s.tema = ? AND s.geom && l.wgs
                )
                SELECT encode(ST_AsMVT(elementos, 'zonas', 4096, 'geom'), 'base64') AS datos
                FROM elementos WHERE geom IS NOT NULL
                SQL, [$z, $x, $y, $tema])->datos ?? '');

        return base64_decode($mvt);
    }
}
