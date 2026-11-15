<?php

namespace Database\Seeders;

use App\Services\Catalogo\ImportarCapa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoVersionadaSeeder extends Seeder
{
    public function run(ImportarCapa $importador): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Los datos sintéticos de Talara solo se cargan en local o testing.');
        }

        $zona = DB::table('zonas')->where('nombre', 'Zona piloto (demo)')->value('id');
        $admin = DB::table('usuarios')->where('email', 'admin@urbantwin.test')->value('id');
        if (! $zona || ! $admin) {
            throw new RuntimeException('Carga antes las cuentas y la zona original con DatabaseSeeder.');
        }

        DB::insert("INSERT INTO geo.coberturas (nombre, creado_por, geom)
            SELECT 'Talara · zona piloto simulada', ?, ST_Multi(limite) FROM zonas WHERE id = ?
            ON CONFLICT (nombre) DO NOTHING", [$admin, $zona]);
        $cobertura = DB::table('geo.coberturas')->where('nombre', 'Talara · zona piloto simulada')->value('id');

        $escenarios = DB::table('escenarios')->where('zona_id', $zona)->where('origen', 'simulado')
            ->selectRaw('nombre, ST_AsGeoJSON(area_inundada, 12) AS geometria')->orderBy('id')->get();

        foreach ($escenarios as $escenario) {
            $this->publicar($importador, $admin, $cobertura, 'peligro', $escenario->nombre, [$escenario]);
        }

        $infra = DB::table('infraestructuras as i')
            ->join('tipos_infraestructura as t', 't.id', '=', 'i.tipo_id')
            ->where('i.zona_id', $zona)->orderBy('i.id')
            ->selectRaw('i.nombre, t.nombre AS tipo, ST_AsGeoJSON(i.geometria, 12) AS geometria')->get();

        $this->publicar($importador, $admin, $cobertura, 'vias', 'Vías de Talara · demo',
            $infra->where('tipo', 'Vía')->all());
        $this->publicar($importador, $admin, $cobertura, 'equipamiento', 'Equipamiento de Talara · demo',
            $infra->where('tipo', '!=', 'Vía')->all());
    }

    private function publicar(ImportarCapa $importador, int $admin, int $cobertura, string $tipo, string $nombre, array $filas): void
    {
        $identidad = ['nombre' => $nombre, 'cobertura_id' => $cobertura, 'tipo' => $tipo];
        DB::table('catalogo.capas')->insertOrIgnore($identidad);
        $capa = DB::table('catalogo.capas')->where($identidad)->value('id');
        $entidades = [];
        foreach ($filas as $fila) {
            $entidades[] = [
                'type' => 'Feature',
                'geometry' => json_decode($fila->geometria, false, 64, JSON_THROW_ON_ERROR),
                'properties' => ['nombre' => $fila->nombre, 'tipo' => $fila->tipo ?? 'Peligro simulado'],
            ];
        }
        $resultado = $importador->ejecutar(
            $capa, $admin, 'talara-'.$tipo.'.geojson',
            json_encode(['type' => 'FeatureCollection', 'features' => $entidades], JSON_THROW_ON_ERROR),
            ['fuente' => 'UrbanTwin · seeder original de Talara', 'licencia' => 'Datos ficticios para demostración académica',
                'origen' => 'simulado', 'fecha_dato' => '2026-11-14']
        );

        if ($resultado['estado'] !== 'publicada') {
            throw new RuntimeException('No se pudo publicar la capa demo: '.$nombre);
        }
    }
}
