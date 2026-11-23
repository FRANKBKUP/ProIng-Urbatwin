<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportarLluviaPiura extends Command
{
    protected $signature = 'territorio:importar-lluvia-piura {directorio : Directorio preparado de lluvia diaria}';

    protected $description = 'Importa un mes de lluvia diaria de Piura y conserva su procedencia';

    public function handle(): int
    {
        $directorio = rtrim($this->argument('directorio'), '/\\');
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Se requiere PostgreSQL con PostGIS.');

            return self::FAILURE;
        }
        $manifiesto = json_decode(file_get_contents($directorio.'/manifiesto.json'), true, 512, JSON_THROW_ON_ERROR);
        $geometria = file_get_contents($directorio.'/piura.geojson');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])-01$/', $manifiesto['inicio'] ?? '')) {
            throw new RuntimeException('La serie debe comenzar el primer día de un mes válido.');
        }
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $manifiesto['inicio']);
        if (! $inicio || $inicio->format('Y-m-d') !== $manifiesto['inicio'] || $inicio->year < 1981
            || $manifiesto['fin'] !== $inicio->endOfMonth()->format('Y-m-d')
            || $manifiesto['zona'] !== 'PE-PIU' || $manifiesto['producto'] !== 'CHIRPS v3.0 final RNL'
            || count($manifiesto['archivos']) !== $inicio->daysInMonth
            || hash_file('sha256', $directorio.'/originales/departamentos.geojson') !== $manifiesto['limite_sha256']) {
            throw new RuntimeException('El manifiesto o el límite no corresponden a la serie esperada.');
        }
        $fechas = [];
        foreach ($manifiesto['archivos'] as $fila) {
            $fecha = $fila['fecha'];
            $archivo = $fila['archivo'];
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
                || $fecha < $inicio->format('Y-m-d') || $fecha > $inicio->endOfMonth()->format('Y-m-d')
                || $archivo !== 'chirps-'.$fecha.'.tif' || isset($fechas[$fecha])
                || ! is_numeric($fila['precipitacion_mm']) || $fila['precipitacion_mm'] < 0
                || ! is_numeric($fila['cobertura']) || $fila['cobertura'] < 0.999 || $fila['cobertura'] > 1
                || hash_file('sha256', $directorio.'/originales/'.$archivo) !== $fila['sha256']) {
                throw new RuntimeException('El archivo diario o sus resultados no son válidos: '.$fecha);
            }
            $fechas[$fecha] = $fila;
        }
        DB::transaction(function () use ($manifiesto, $geometria, $fechas, $inicio): void {
            DB::statement(<<<'SQL'
                INSERT INTO geo.departamentos (codigo, nombre, geom)
                VALUES ('PE-PIU', 'Piura', ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))
                ON CONFLICT (codigo) DO NOTHING
                SQL, [$geometria]);
            $codigo = 'piura-chirps-v3-rnl-'.$inicio->format('Y-m');
            if (DB::table('geo.lluvia_series')->where('codigo', $codigo)->exists()) {
                throw new RuntimeException('La serie ya está importada; no se sobrescribieron sus datos.');
            }
            $serie = DB::selectOne(<<<'SQL'
                INSERT INTO geo.lluvia_series (codigo, zona, producto, inicio, fin, unidad, metodo, fuente, procedencia, geom)
                VALUES (?, ?, ?, ?, ?, 'mm', ?, ?, ?::jsonb, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))) RETURNING id
                SQL, [$codigo, 'Piura', $manifiesto['producto'], $manifiesto['inicio'], $manifiesto['fin'], $manifiesto['metodo'], $manifiesto['fuente'], json_encode($manifiesto, JSON_THROW_ON_ERROR), $geometria]);
            foreach ($fechas as $fila) {
                DB::table('geo.lluvia_diaria')->insert(['serie_id' => $serie->id, 'fecha' => $fila['fecha'], 'precipitacion_mm' => $fila['precipitacion_mm'], 'cobertura' => $fila['cobertura'], 'archivo' => $fila['archivo'], 'sha256' => $fila['sha256']]);
            }
        });
        $this->info('Piura: '.count($fechas).' valores diarios importados para '.$inicio->format('Y-m').'.');

        return self::SUCCESS;
    }
}
