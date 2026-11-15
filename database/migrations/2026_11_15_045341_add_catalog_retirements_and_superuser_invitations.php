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
            ALTER TABLE catalogo.capas ADD COLUMN etiqueta varchar(120), ADD COLUMN retirada_en timestamptz;
            ALTER TABLE geo.temas ADD COLUMN etiqueta varchar(120), ADD COLUMN retirada_en timestamptz;

            CREATE OR REPLACE FUNCTION catalogo.proteger_capa() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.id <> OLD.id OR NEW.tipo <> OLD.tipo OR NEW.nombre <> OLD.nombre
                    OR NEW.cobertura_id <> OLD.cobertura_id THEN
                    RAISE EXCEPTION 'La identidad de la capa es inmutable';
                END IF;
                IF NEW.version_vigente_id IS DISTINCT FROM OLD.version_vigente_id AND (
                    NEW.version_vigente_id IS NULL OR NOT EXISTS (
                        SELECT 1 FROM catalogo.capas_versiones
                        WHERE id = NEW.version_vigente_id AND publicada_en IS NOT NULL
                    )
                ) THEN
                    RAISE EXCEPTION 'La versión vigente debe estar publicada';
                END IF;
                RETURN NEW;
            END;
            $$;

            INSERT INTO app.roles (nombre) VALUES ('Superusuario');
            CREATE TABLE app.superusuarios (
                plaza smallint PRIMARY KEY CHECK (plaza BETWEEN 1 AND 5),
                nombre varchar(100) NOT NULL,
                usuario_id bigint UNIQUE REFERENCES app.usuarios,
                correo varchar(120),
                hash char(64) UNIQUE,
                vence_en timestamptz,
                activado_en timestamptz,
                CHECK ((usuario_id IS NULL) = (activado_en IS NULL)),
                CHECK ((hash IS NULL) = (vence_en IS NULL)),
                CHECK (hash IS NULL OR (usuario_id IS NULL AND correo IS NOT NULL))
            );
            CREATE UNIQUE INDEX superusuarios_correo_unico ON app.superusuarios (correo) WHERE correo IS NOT NULL;
            INSERT INTO app.superusuarios (plaza, nombre) VALUES
                (1, 'FRANK SUAREZ'), (2, 'GABRIELA REYES'), (3, 'DANIEL HUERTAS'),
                (4, 'ROSA RUMICHE'), (5, 'ARTURO SANDOVAL');
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DELETE FROM app.roles WHERE nombre = 'Superusuario';
            DROP TABLE app.superusuarios;
            ALTER TABLE geo.temas DROP COLUMN etiqueta, DROP COLUMN retirada_en;
            ALTER TABLE catalogo.capas DROP COLUMN etiqueta, DROP COLUMN retirada_en;

            CREATE OR REPLACE FUNCTION catalogo.proteger_capa() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.id <> OLD.id OR NEW.tipo <> OLD.tipo OR NEW.nombre <> OLD.nombre
                    OR NEW.cobertura_id <> OLD.cobertura_id THEN
                    RAISE EXCEPTION 'La identidad de la capa es inmutable';
                END IF;
                IF NEW.version_vigente_id IS NULL OR NOT EXISTS (
                    SELECT 1 FROM catalogo.capas_versiones
                    WHERE id = NEW.version_vigente_id AND publicada_en IS NOT NULL
                ) THEN
                    RAISE EXCEPTION 'La versión vigente debe estar publicada';
                END IF;
                RETURN NEW;
            END;
            $$;
            SQL);
    }
};
