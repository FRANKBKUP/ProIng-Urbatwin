<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCaseConBD;

class AtlasTerritorialTest extends TestCaseConBD
{
    public function test_cada_usuario_puede_actualizar_solo_su_foto_de_perfil(): void
    {
        Storage::fake('local');
        $duena = $this->usuario('Consulta');
        $otra = $this->usuario('Consulta');
        $foto = new UploadedFile(public_path('img/icono.png'), 'foto.png', 'image/png', null, true);

        $this->post('/perfil/foto')->assertRedirect('/login');
        $this->actingAs($duena)->post('/perfil/foto', ['foto' => $foto])->assertSessionHasNoErrors();
        $ruta = DB::table('app.perfiles')->where('usuario_id', $duena->id)->value('foto');
        Storage::disk('local')->assertExists($ruta);
        $this->get('/perfil/foto')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($otra)->get('/perfil/foto')->assertNotFound();

        $this->actingAs($duena)->post('/perfil/foto', ['foto' => new UploadedFile(public_path('img/login.png'), 'nueva.png', 'image/png', null, true)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($ruta);
        Storage::disk('local')->assertExists(DB::table('app.perfiles')->where('usuario_id', $duena->id)->value('foto'));
    }

    public function test_rechaza_archivos_que_no_son_imagenes_de_perfil(): void
    {
        Storage::fake('local');
        $usuario = $this->usuario('Consulta');

        $this->actingAs($usuario)->post('/perfil/foto', ['foto' => UploadedFile::fake()->create('archivo.txt', 1, 'text/plain')])
            ->assertSessionHasErrors('foto');
        $this->assertDatabaseMissing('app.perfiles', ['usuario_id' => $usuario->id]);
        Storage::disk('local')->assertDirectoryEmpty('fotos-perfil');
    }

    public function test_publica_teselas_con_la_categoria_y_resume_distritos_en_el_panel(): void
    {
        $this->withoutVite();
        $this->region();
        DB::table('geo.temas')->insert([
            'codigo' => 'riesgo-validacion', 'tipo' => 'riesgo', 'titulo' => 'Riesgo distrital',
            'periodo' => 'Período de prueba', 'fuente' => 'Referencia de prueba', 'ficha_url' => 'https://example.test/ficha',
            'archivo_ruta' => 'prueba.zip', 'archivo_sha256' => str_repeat('a', 64), 'notas' => '',
            'leyenda' => json_encode([['clase' => 3, 'nombre' => 'Alto', 'color' => '#ee8538']], JSON_THROW_ON_ERROR),
        ]);
        DB::insert(<<<'SQL'
            INSERT INTO geo.zonas_tematicas (tema, clase, distrito, departamento, ubigeo, km2, geom)
            SELECT 'riesgo-validacion', 3, 'Distrito piloto', nombre, '200101', 200, geom
            FROM geo.departamentos WHERE codigo = 'PE-PIU'
            SQL);
        $this->actingAs($this->usuario('Consulta'));

        $this->get('/visor')->assertOk()->assertSee('Riesgo distrital')->assertSee('Cómo usar');
        $this->get('/panel')->assertOk()->assertViewHas('resumenAtlas', fn (array $temas) => $temas['riesgo-validacion']['total'] === 1);
        $tesela = $this->get('/api/geo/atlas/riesgo-validacion/5/8/16.pbf')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.mapbox-vector-tile');
        $this->assertStringContainsString('Distrito piloto', $tesela->getContent());
        $this->get('/api/geo/atlas/riesgo-validacion/5/80/16.pbf')->assertNotFound();
        $consulta = '/api/geo/atlas/riesgo-validacion/consulta';
        $this->postJson($consulta, ['longitud' => -80.4, 'latitud' => -5.4])->assertOk()
            ->assertJsonPath('zona.clase', 3)->assertJsonPath('zona.ubigeo', '200101');
        $this->postJson($consulta, ['longitud' => -74, 'latitud' => -12])->assertOk()->assertJsonPath('zona', null);
        $this->postJson($consulta, ['longitud' => -181, 'latitud' => -5])->assertUnprocessable()->assertJsonValidationErrors('longitud');

        $this->actingAs($this->admin())->patch('/catalogo/gestion/tema/riesgo-validacion', ['accion' => 'retirar'])
            ->assertSessionHasNoErrors();
        $this->get('/visor')->assertOk()->assertDontSee('Riesgo distrital');
        $this->get('/api/geo/atlas/riesgo-validacion/5/8/16.pbf')->assertNotFound();
        $this->postJson($consulta, ['longitud' => -80.4, 'latitud' => -5.4])->assertNotFound();
        $this->patch('/catalogo/gestion/tema/riesgo-validacion', ['accion' => 'restaurar'])->assertSessionHasNoErrors();
        $this->get('/api/geo/atlas/riesgo-validacion/5/8/16.pbf')->assertOk();
    }

    public function test_encuadra_una_provincia_consultada_por_una_cuenta_activa(): void
    {
        $this->region();
        DB::insert(<<<'SQL'
            INSERT INTO geo.provincias (codigo, departamento, nombre, archivo_sha256, geom)
            VALUES ('PIU01', 'PE-PIU', 'Provincia de referencia', repeat('a', 64), ST_Multi(ST_MakeEnvelope(-80.9, -5.6, -80.4, -5.2, 4326)))
            SQL);

        $url = '/api/geo/atlas/provincias/PIU01';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->usuario('Consulta'))->getJson($url)->assertOk()
            ->assertJsonPath('departamento', 'PE-PIU')->assertJsonPath('geometria.type', 'MultiPolygon')
            ->assertJsonPath('limites', [[-80.9, -5.6], [-80.4, -5.2]]);
        $this->getJson('/api/geo/atlas/provincias/OTRA')->assertNotFound();
    }

    public function test_el_directorio_muestra_referencias_urbanas_y_reserva_la_lectura_a_cuentas_activas(): void
    {
        $url = '/api/geo/atlas/equipo';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->usuario('Consulta', false))->getJson($url)->assertForbidden();
        $this->actingAs($this->usuario('Consulta'))->getJson($url)->assertOk()->assertJsonCount(5, 'personas')
            ->assertJsonPath('personas.0.ciudad', 'Tambogrande')
            ->assertJsonPath('personas.1.ciudad', 'Sullana')
            ->assertJsonPath('personas.2.ciudad', 'Talara')
            ->assertJsonPath('personas.3.ciudad', 'Sechura')
            ->assertJsonPath('personas.4.ciudad', 'Piura')
            ->assertJsonMissingPath('personas.0.usuario_id')->assertJsonMissingPath('personas.0.correo');
    }

    public function test_el_avatar_regional_requiere_consentimiento_y_las_preferencias_se_guardan(): void
    {
        $this->region();
        $duena = $this->usuario('Consulta');
        $observador = $this->usuario('Consulta');
        $datos = ['nombre' => 'María', 'ocupacion' => 'Consulta territorial', 'descripcion' => 'Presentación privada',
            'avatar' => 'verde', 'tema' => 'oscuro', 'departamento' => 'PE-PIU'];

        $this->actingAs($duena)->patch('/perfil', $datos + ['visible_en_mapa' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('app.usuarios', ['id' => $duena->id, 'tema' => 'oscuro']);
        $this->get('/perfil')->assertOk()->assertSee('Presentación privada');
        $this->actingAs($observador)->getJson('/api/geo/perfiles')->assertOk()->assertJsonCount(0, 'perfiles');

        $this->actingAs($duena)->patch('/perfil', $datos + ['visible_en_mapa' => 1])->assertSessionHasNoErrors();
        $this->actingAs($observador)->getJson('/api/geo/perfiles?departamento=PE-PIU')->assertOk()
            ->assertJsonPath('perfiles.0.nombre', 'María')->assertJsonPath('perfiles.0.avatar', 'verde')
            ->assertJsonPath('perfiles.0.departamento', 'Piura')->assertJsonPath('perfiles.0.longitud', -80.5)
            ->assertJsonMissingPath('perfiles.0.email')->assertJsonMissingPath('perfiles.0.descripcion');
    }

    public function test_el_pronostico_consulta_la_referencia_regional_y_reutiliza_la_respuesta(): void
    {
        $this->region();
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => ['time' => ['2026-11-14'], 'precipitation_sum' => [11.4], 'precipitation_probability_max' => [72]],
        ])]);
        $this->actingAs($this->usuario('Consulta'));

        $this->getJson('/api/geo/pronostico/PE-PIU')->assertOk()->assertJsonPath('dias.0.mm', 11.4);
        $this->getJson('/api/geo/pronostico/PE-PIU')->assertOk()->assertJsonPath('departamento', 'Piura');
        $this->getJson('/api/geo/pronostico/PE-AJENO')->assertNotFound();

        Http::assertSentCount(1);
        Http::assertSent(function ($solicitud) {
            parse_str(parse_url($solicitud->url(), PHP_URL_QUERY), $parametros);

            return (float) $parametros['latitude'] === -5.5 && $parametros['timezone'] === 'America/Lima';
        });
    }

    private function region(): void
    {
        DB::insert("INSERT INTO geo.departamentos (codigo, nombre, geom)
            VALUES ('PE-PIU', 'Piura', ST_Multi(ST_MakeEnvelope(-81, -6, -80, -5, 4326)))");
    }
}
