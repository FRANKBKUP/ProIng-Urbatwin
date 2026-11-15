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
            CREATE TABLE geo.departamentos (
                codigo varchar(16) PRIMARY KEY,
                nombre varchar(90) NOT NULL,
                geom geometry(MultiPolygon, 4326) NOT NULL,
                CHECK (NOT ST_IsEmpty(geom) AND ST_IsValid(geom))
            );
            CREATE INDEX departamentos_geom_idx ON geo.departamentos USING gist (geom);

            CREATE TABLE geo.temas (
                codigo varchar(48) PRIMARY KEY,
                tipo varchar(12) NOT NULL CHECK (tipo IN ('lluvia', 'riesgo')),
                titulo varchar(120) NOT NULL,
                periodo varchar(100) NOT NULL,
                fuente varchar(180) NOT NULL,
                ficha_url text NOT NULL,
                archivo_ruta text NOT NULL,
                archivo_sha256 char(64) NOT NULL CHECK (archivo_sha256 ~ '^[0-9a-f]{64}$'),
                leyenda jsonb NOT NULL,
                notas text NOT NULL,
                registrado_en timestamptz NOT NULL DEFAULT now()
            );
            CREATE TABLE geo.zonas_tematicas (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                tema varchar(48) NOT NULL REFERENCES geo.temas(codigo),
                clase smallint NOT NULL CHECK (clase BETWEEN 0 AND 12),
                distrito varchar(100),
                departamento varchar(90),
                ubigeo varchar(6),
                km2 double precision NOT NULL CHECK (km2 >= 0),
                geom geometry(MultiPolygon, 4326) NOT NULL,
                CHECK (NOT ST_IsEmpty(geom) AND ST_IsValid(geom))
            );
            CREATE INDEX zonas_tematicas_tema_clase_idx ON geo.zonas_tematicas (tema, clase);
            CREATE INDEX zonas_tematicas_geom_idx ON geo.zonas_tematicas USING gist (geom);
            CREATE UNIQUE INDEX zonas_tematicas_ubigeo_idx ON geo.zonas_tematicas (tema, ubigeo) WHERE ubigeo IS NOT NULL;

            ALTER TABLE app.usuarios ADD COLUMN tema varchar(8) NOT NULL DEFAULT 'sistema'
                CHECK (tema IN ('sistema', 'claro', 'oscuro'));
            CREATE TABLE app.perfiles (
                usuario_id bigint PRIMARY KEY REFERENCES app.usuarios ON DELETE CASCADE,
                departamento varchar(16) REFERENCES geo.departamentos(codigo),
                descripcion varchar(400) NOT NULL DEFAULT '',
                ocupacion varchar(90) NOT NULL DEFAULT '',
                avatar varchar(8) NOT NULL DEFAULT 'azul' CHECK (avatar IN ('azul', 'verde', 'uva', 'coral', 'oro')),
                visible_en_mapa boolean NOT NULL DEFAULT false,
                demostracion boolean NOT NULL DEFAULT false,
                actualizado_en timestamptz NOT NULL DEFAULT now(),
                CHECK (NOT visible_en_mapa OR departamento IS NOT NULL)
            );
            CREATE INDEX perfiles_visibles_idx ON app.perfiles (departamento, usuario_id) WHERE visible_en_mapa;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TABLE app.perfiles;
            ALTER TABLE app.usuarios DROP COLUMN tema;
            DROP TABLE geo.zonas_tematicas, geo.temas, geo.departamentos;
            SQL);
    }
};
