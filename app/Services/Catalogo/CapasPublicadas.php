<?php

namespace App\Services\Catalogo;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CapasPublicadas
{
    public function vigentes(): Builder
    {
        return $this->versiones(usarEtiqueta: true)->whereNull('c.retirada_en')->whereColumn('c.version_vigente_id', 'v.id');
    }

    public function ciudadanas(): Builder
    {
        return $this->disponibles()->where('c.tipo', 'peligro');
    }

    public function disponibles(): Builder
    {
        return $this->vigentes()->when(! config('territorio.mostrar_simulaciones'),
            fn (Builder $consulta) => $consulta->where('f.origen', 'observado'));
    }

    public function versiones(bool $usarEtiqueta = false): Builder
    {
        return DB::table('catalogo.capas_versiones as v')
            ->join('catalogo.capas as c', 'c.id', '=', 'v.capa_id')
            ->join('catalogo.importaciones as i', 'i.id', '=', 'v.importacion_id')
            ->join('catalogo.fuentes as f', 'f.id', '=', 'i.fuente_id')
            ->join('catalogo.archivos as a', 'a.id', '=', 'i.archivo_id')
            ->leftJoin('catalogo.paquetes_fuente as paquete', 'paquete.id', '=', 'a.paquete_id')
            ->join('geo.coberturas as g', 'g.id', '=', 'c.cobertura_id')
            ->whereNotNull('v.publicada_en')
            ->select('c.id as capa_id', $usarEtiqueta ? DB::raw('coalesce(c.etiqueta, c.nombre) AS nombre') : 'c.nombre',
                'c.tipo', 'c.cobertura_id', 'g.nombre as cobertura',
                'v.id as version_id', 'v.numero', 'v.publicada_en', 'i.fecha_dato',
                'f.nombre as fuente', 'f.licencia', 'f.origen', 'paquete.ficha_url', 'paquete.producto', 'paquete.escala');
    }

    public function mapa(int $capaId): array
    {
        $version = $this->ciudadanas()->where('c.id', $capaId)
            ->selectRaw('ST_AsGeoJSON(g.geom, 9) AS limite')->first();
        abort_if(! $version, 404);

        $limite = json_decode($version->limite);
        unset($version->limite);
        $geometrias = Cache::remember('visor:geometrias:v3:'.$version->version_id, now()->addHours(6), function () use ($version) {
            return DB::table('geo.peligro')->where('version_id', $version->version_id)->orderBy('elemento')
                ->selectRaw('ST_AsGeoJSON(ST_Multi(ST_SimplifyPreserveTopology(geom, 0.000025)), 8) AS geometria')
                ->pluck('geometria')->map(fn (string $geom) => [
                    'type' => 'Feature', 'geometry' => json_decode($geom), 'properties' => (object) [],
                ])->all();
        });

        return [
            'capa' => $version,
            'cobertura' => $limite,
            'peligro' => ['type' => 'FeatureCollection', 'features' => $geometrias],
        ];
    }

    public function consultar(int $capaId, int $versionId, float $lon, float $lat): array
    {
        $capa = $this->ciudadanas()->where('c.id', $capaId)
            ->selectRaw(<<<'SQL'
                CASE
                    WHEN NOT ST_Covers(g.geom, punto.geom) THEN 'sin_cobertura'
                    WHEN ST_Contains(peligro.geom, punto.geom) THEN 'dentro'
                    WHEN ST_Covers(peligro.geom, punto.geom) THEN 'borde'
                    ELSE 'fuera'
                END AS estado
                SQL)
            ->crossJoin(DB::raw('(SELECT ST_SetSRID(ST_MakePoint(?::float8, ?::float8), 4326) geom) punto'))
            ->addBinding([$lon, $lat], 'join')
            ->joinLateral(DB::table('geo.peligro as p')
                ->selectRaw('ST_Union(p.geom) AS geom')
                ->whereColumn('p.version_id', 'v.id')
                ->whereRaw('ST_Intersects(p.geom, punto.geom)'), 'peligro')
            ->first();
        abort_if(! $capa, 404);
        abort_if((int) $capa->version_id !== $versionId, 409, 'La capa cambió. Vuelve a seleccionarla.');

        $estado = $capa->estado;
        unset($capa->estado);

        return ['estado' => $estado, 'capa' => $capa];
    }
}
