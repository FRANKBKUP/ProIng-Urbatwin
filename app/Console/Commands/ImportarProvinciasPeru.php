<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImportarProvinciasPeru extends Command
{
    private const SHA256 = '58d0720bc01abd27bd02fb73d2ee8443fc6067352d97b752d100a18e3b97bb58';

    protected $signature = 'territorio:importar-provincias {archivo : GeoJSON original de geoBoundaries PER ADM2}';

    protected $description = 'Registra las 196 provincias del Perú y conserva el GeoJSON de origen';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (DB::getDriverName() !== 'pgsql' || ! is_readable($archivo) || hash_file('sha256', $archivo) !== self::SHA256) {
            $this->error('Se necesita PostGIS y el GeoJSON PER ADM2 que consta en el README.');

            return self::FAILURE;
        }

        $original = file_get_contents($archivo);
        $ruta = 'originales/atlas/'.self::SHA256.'.geojson';
        $disco = Storage::disk('local');
        if ((! $disco->exists($ruta) && ! $disco->put($ruta, $original))
            || hash_file('sha256', $disco->path($ruta)) !== self::SHA256) {
            throw new RuntimeException('No se pudo conservar íntegro el archivo de origen.');
        }

        DB::transaction(function () use ($original) {
            if (DB::table('geo.departamentos')->count() < 25) {
                throw new RuntimeException('Importa primero los departamentos con territorio:importar-atlas.');
            }

            DB::statement(<<<'SQL'
                INSERT INTO geo.provincias (codigo, departamento, nombre, archivo_sha256, geom)
                SELECT p.properties->>'shapeID', departamento.codigo, p.properties->>'shapeName', ?, provincia.geom
                FROM jsonb_to_recordset((?::jsonb)->'features') AS p(properties jsonb, geometry jsonb)
                CROSS JOIN LATERAL (SELECT ST_Multi(ST_GeomFromGeoJSON(p.geometry)) AS geom) provincia
                LEFT JOIN LATERAL (
                    SELECT d.codigo FROM geo.departamentos d
                    WHERE ST_Covers(d.geom, ST_PointOnSurface(provincia.geom))
                    ORDER BY ST_Area(d.geom), d.codigo LIMIT 1
                ) departamento ON true
                ON CONFLICT (codigo) DO UPDATE
                SET departamento = EXCLUDED.departamento, nombre = EXCLUDED.nombre,
                    archivo_sha256 = EXCLUDED.archivo_sha256, geom = EXCLUDED.geom
                SQL, [self::SHA256, $original]);
            if (DB::table('geo.provincias')->where('archivo_sha256', self::SHA256)->count() !== 196) {
                throw new RuntimeException('La fuente provincial debe producir sus 196 provincias.');
            }
        });

        $this->info('196 provincias disponibles en el visor.');

        return self::SUCCESS;
    }
}
