<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE geo.provincias (
                codigo varchar(40) PRIMARY KEY,
                departamento varchar(16) NOT NULL REFERENCES geo.departamentos,
                nombre varchar(120) NOT NULL,
                archivo_sha256 char(64) NOT NULL CHECK (archivo_sha256 ~ '^[0-9a-f]{64}$'),
                geom geometry(MultiPolygon, 4326) NOT NULL,
                CHECK (NOT ST_IsEmpty(geom) AND ST_IsValid(geom))
            );
            CREATE INDEX provincias_departamento_idx ON geo.provincias(departamento, nombre);
            CREATE INDEX provincias_geom_idx ON geo.provincias USING gist(geom);

            CREATE TABLE app.referencias_equipo (
                plaza smallint PRIMARY KEY REFERENCES app.superusuarios ON DELETE CASCADE,
                ciudad varchar(90) NOT NULL,
                geom geometry(Point, 4326) NOT NULL,
                CHECK (NOT ST_IsEmpty(geom))
            );
            INSERT INTO app.referencias_equipo (plaza, ciudad, geom) VALUES
                (1, 'Tambogrande', ST_SetSRID(ST_MakePoint(-80.34, -4.93), 4326)),
                (2, 'Sullana', ST_SetSRID(ST_MakePoint(-80.69, -4.89), 4326)),
                (3, 'Talara', ST_SetSRID(ST_MakePoint(-81.27, -4.58), 4326)),
                (4, 'Sechura', ST_SetSRID(ST_MakePoint(-80.82, -5.56), 4326)),
                (5, 'Piura', ST_SetSRID(ST_MakePoint(-80.63, -5.19), 4326));
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TABLE app.referencias_equipo, geo.provincias');
        }
    }
};
