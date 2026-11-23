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
            CREATE TABLE geo.lluvia_series (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                codigo varchar(100) NOT NULL UNIQUE,
                zona varchar(100) NOT NULL,
                producto varchar(120) NOT NULL,
                inicio date NOT NULL,
                fin date NOT NULL CHECK (fin >= inicio),
                unidad varchar(10) NOT NULL,
                metodo text NOT NULL,
                fuente text NOT NULL,
                procedencia jsonb NOT NULL,
                geom geometry(MultiPolygon, 4326) NOT NULL,
                CHECK (NOT ST_IsEmpty(geom) AND ST_IsValid(geom))
            );
            CREATE TABLE geo.lluvia_diaria (
                serie_id bigint NOT NULL REFERENCES geo.lluvia_series(id) ON DELETE CASCADE,
                fecha date NOT NULL,
                precipitacion_mm double precision NOT NULL CHECK (precipitacion_mm >= 0),
                cobertura double precision NOT NULL CHECK (cobertura BETWEEN 0 AND 1),
                archivo varchar(255) NOT NULL,
                sha256 char(64) NOT NULL,
                PRIMARY KEY (serie_id, fecha)
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TABLE geo.lluvia_diaria, geo.lluvia_series');
        }
    }
};
