<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCaseConBD;

class UrbanTwinPostgisTest extends TestCaseConBD
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['territorio.mostrar_simulaciones' => true]);
    }

    public function test_el_catalogo_registra_cobertura_y_capa_desde_el_formulario(): void
    {
        $this->actingAs($this->admin());
        $this->get('/catalogo')->assertOk();
        $this->post('/catalogo/coberturas', [
            'nombre' => 'Talara · pruebas',
            'geojson' => '{"type":"Polygon","coordinates":[[[-81.285,-4.595],[-81.255,-4.595],[-81.255,-4.565],[-81.285,-4.565],[-81.285,-4.595]]]}',
        ])->assertSessionHasNoErrors();
        $cobertura = DB::table('geo.coberturas')->where('nombre', 'Talara · pruebas')->value('id');

        $this->post('/catalogo/capas', ['nombre' => 'Inundación', 'tipo' => 'peligro', 'cobertura_id' => $cobertura])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('catalogo.capas', ['nombre' => 'Inundación', 'version_vigente_id' => null]);
    }

    public function test_conserva_originales_publica_idempotente_y_registra_errores_sin_desplazar_la_version(): void
    {
        $disco = Storage::fake('local');
        $capa = $this->capa('peligro');
        $this->actingAs($this->admin());

        $primera = $this->importar($capa, 'talara-peligro-solapado')->assertCreated()->json();
        $this->importar($capa, 'talara-peligro-solapado')->assertOk()
            ->assertJsonPath('version_id', $primera['version_id'])->assertJsonPath('duplicada', true);
        $rechazo = $this->importar($capa, 'talara-peligro-autointerseccion')->assertUnprocessable()
            ->assertJsonPath('estado', 'rechazada')->json('id');
        $this->get("/catalogo/importaciones/$rechazo")->assertOk()
            ->assertSee('Elemento 1')->assertSee('Self-intersection');
        $this->get("/catalogo/importaciones/$rechazo/original")->assertDownload("original-$rechazo.geojson");

        $this->assertDatabaseCount('catalogo.capas_versiones', 1);
        $this->assertDatabaseCount('geo.peligro', 2);
        $this->assertDatabaseCount('catalogo.importaciones', 2);
        $this->assertDatabaseHas('catalogo.capas', ['id' => $capa, 'version_vigente_id' => $primera['version_id']]);
        $archivos = DB::table('catalogo.archivos')->pluck('ruta', 'sha256');
        $this->assertCount(2, $archivos);
        foreach ($archivos as $hash => $ruta) {
            $this->assertSame($hash, hash('sha256', $disco->get($ruta)));
        }
    }

    public function test_el_ciudadano_inicia_sesion_y_distingue_los_cuatro_estados(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $capa = $this->capa('peligro');
        $version = $this->actingAs($this->admin())->importar($capa, 'talara-peligro-solapado')->assertCreated()->json('version_id');
        $this->post('/logout')->assertRedirect('/login');
        $ciudadano = $this->usuario('Consulta');

        $this->get('/visor')->assertRedirect('/login');
        $this->post('/login', ['email' => $ciudadano->email, 'password' => 'Clave12345!'])->assertRedirect('/visor');
        $this->get('/visor')->assertOk()->assertSee('Centrar y consultar');
        $this->getJson("/api/geo/capas/$capa")->assertOk()->assertJsonPath('peligro.features.0.geometry.type', 'MultiPolygon');

        foreach ([
            [-81.2725, 'dentro'], [-81.273, 'borde'], [-81.279, 'fuera'], [-81.30, 'sin_cobertura'],
        ] as [$longitud, $estado]) {
            $this->postJson("/api/geo/capas/$capa/consulta", ['version_id' => $version, 'longitud' => $longitud, 'latitud' => -4.580])
                ->assertOk()->assertJsonPath('estado', $estado)->assertJsonPath('capa.numero', 1)
                ->assertJsonPath('capa.fuente', 'Fixture Talara')->assertJsonPath('capa.fecha_dato', '2026-11-14')
                ->assertJsonMissingPath('longitud');
        }
        $this->postJson("/api/geo/capas/$capa/consulta", ['version_id' => $version, 'longitud' => 181, 'latitud' => -4.580])
            ->assertUnprocessable()->assertJsonValidationErrors('longitud');
        $this->importar($capa, 'talara-peligro-v2')->assertForbidden();
        $this->get('/catalogo/importaciones/1/original')->assertForbidden();
    }

    public function test_publica_una_curva_con_cota_en_metros_y_protege_su_geometria(): void
    {
        Storage::fake('local');
        $capa = $this->capa('curvas');
        $id = $this->actingAs($this->admin())->importar($capa, 'talara-curva')->assertCreated()->json('version_id');
        $curva = DB::table('geo.curvas')->where('version_id', $id)
            ->selectRaw('cota_m::float8 AS cota, ST_GeometryType(geom) AS tipo, ST_SRID(geom) AS srid')->first();

        $this->assertSame(45.5, $curva->cota);
        $this->assertSame('ST_MultiLineString', $curva->tipo);
        $this->assertSame(4326, $curva->srid);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('La versión publicada no admite entidades nuevas');
        DB::insert('INSERT INTO geo.curvas (version_id, elemento, geom, propiedades, cota_m)
            SELECT version_id, 2, geom, propiedades, cota_m FROM geo.curvas WHERE version_id = ?', [$id]);
    }

    public function test_mide_el_tramo_una_vez_y_el_reporte_privado_conserva_el_resultado_y_las_versiones(): void
    {
        Storage::fake('local');
        $peligro = $this->capa('peligro');
        $vias = $this->capa('vias');
        $this->actingAs($this->admin());
        $v1 = $this->importar($peligro, 'talara-peligro-solapado')->assertCreated()->json('version_id');
        $vVias = $this->importar($vias, 'talara-via-cruce')->assertCreated()->json('version_id');
        $analista = $this->usuario('Analista');
        $this->actingAs($analista)->get('/analisis/reportes')->assertOk()->assertSee('Ejecutar análisis');
        $parametros = ['peligro_id' => $v1, 'vias_id' => $vVias];
        $reporte = $this->postJson('/analisis/reportes', $parametros)->assertCreated()->json('id');
        $ejecucion = DB::table('analisis.reportes')->where('id', $reporte)->value('ejecucion_id');
        $resultado = DB::table('analisis.resultados')->where('ejecucion_id', $ejecucion)->value('detalle');
        $tramo = json_decode($resultado, true, 64, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $tramo);
        $this->assertEqualsWithDelta(332.77, $tramo[0]['longitud_m'], 0.25);
        $this->assertContains($tramo[0]['geometria']['type'], ['LineString', 'MultiLineString']);

        $this->actingAs($this->admin())->importar($peligro, 'talara-peligro-v2')->assertCreated();
        $this->importar($peligro, 'talara-peligro-solapado')->assertOk()->assertJsonPath('version_id', $v1);
        $this->getJson("/api/geo/capas/$peligro")->assertOk()->assertJsonPath('capa.numero', 2);
        $this->postJson("/api/geo/capas/$peligro/consulta", ['version_id' => $v1, 'longitud' => -81.272, 'latitud' => -4.580])
            ->assertConflict();
        $this->actingAs($analista)->get("/analisis/reportes/$reporte")->assertOk()->assertSee('v1 (ID '.$v1.')');
        $this->assertDatabaseHas('analisis.ejecuciones', ['id' => $ejecucion, ...$parametros]);
        $this->assertSame($resultado, DB::table('analisis.resultados')->where('ejecucion_id', $ejecucion)->value('detalle'));

        $this->actingAs($this->usuario('Analista'))->get("/analisis/reportes/$reporte")->assertForbidden();
        $this->actingAs($this->usuario('Consulta'))->get("/analisis/reportes/$reporte")->assertForbidden();
        $this->postJson('/analisis/reportes', $parametros)->assertForbidden();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Registro histórico inmutable');
        DB::table('analisis.resultados')->where('ejecucion_id', $ejecucion)->update(['detalle' => '[]']);
    }

    public function test_la_demo_reproduce_las_exposiciones_2_5_y_7_en_ambos_flujos(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->post('/login', ['email' => 'analista@urbantwin.test', 'password' => 'Cambiar123!'])->assertRedirect('/visor');
        $versiones = DB::table('catalogo.capas')->pluck('version_vigente_id', 'nombre');

        foreach (['Lluvia leve' => 2, 'Lluvia moderada' => 5, 'Lluvia extrema' => 7] as $nombre => $total) {
            $escenario = DB::table('escenarios')->where('nombre', $nombre)->value('id');
            $this->getJson("/api/escenarios/$escenario/exposicion")->assertOk()->assertJsonPath('total', $total);
            $id = $this->postJson('/analisis/reportes', [
                'peligro_id' => $versiones[$nombre],
                'vias_id' => $versiones['Vías de Talara · demo'],
                'equipamiento_id' => $versiones['Equipamiento de Talara · demo'],
            ])->assertCreated()->json('id');
            $this->get("/analisis/reportes/$id")->assertOk()->assertSee("$total exposiciones");
        }
    }

    private function capa(string $tipo): int
    {
        $cobertura = DB::table('geo.coberturas')->where('nombre', 'Talara · pruebas')->value('id');
        $cobertura ??= DB::selectOne(<<<'SQL'
            INSERT INTO geo.coberturas (nombre, creado_por, geom)
            VALUES ('Talara · pruebas', ?, ST_Multi(ST_MakeEnvelope(-81.285, -4.595, -81.255, -4.565, 4326)))
            RETURNING id
            SQL, [$this->admin()->id])->id;

        return DB::table('catalogo.capas')->insertGetId(['nombre' => $tipo, 'tipo' => $tipo, 'cobertura_id' => $cobertura]);
    }

    private function importar(int $capa, string $fixture): TestResponse
    {
        return $this->post('/catalogo/importaciones', [
            'capa_id' => $capa,
            'archivo' => UploadedFile::fake()->createWithContent($fixture.'.geojson',
                file_get_contents(base_path('tests/Fixtures/'.$fixture.'.geojson'))),
            'fuente' => 'Fixture Talara', 'licencia' => 'Demostración', 'origen' => 'simulado', 'fecha_dato' => '2026-11-14',
        ], ['Accept' => 'application/json']);
    }
}
