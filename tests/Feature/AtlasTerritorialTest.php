<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCaseConBD;

class AtlasTerritorialTest extends TestCaseConBD
{
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
        $ficha = '/api/geo/atlas/ficha';
        $this->postJson($ficha, ['longitud' => -80.4, 'latitud' => -5.4])->assertOk()
            ->assertJsonPath('departamento.nombre', 'Piura')->assertJsonPath('capas.0.categoria', 'Alto')
            ->assertJsonPath('capas.0.ubigeo', '200101');
        $this->postJson($ficha, ['longitud' => -74, 'latitud' => -12])->assertOk()
            ->assertJsonPath('departamento', null)->assertJsonPath('capas.0.categoria', null);

        $this->actingAs($this->admin())->patch('/catalogo/gestion/tema/riesgo-validacion', ['accion' => 'retirar'])
            ->assertSessionHasNoErrors();
        $this->get('/visor')->assertOk()->assertDontSee('Riesgo distrital');
        $this->postJson($ficha, ['longitud' => -80.4, 'latitud' => -5.4])->assertOk()->assertJsonCount(0, 'capas');
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

    public function test_el_historico_distingue_anos_completos_de_anos_con_dias_faltantes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 24));
        $this->region();
        $fechas = [];
        for ($dia = 0; $dia < 366; $dia++) {
            $fechas[] = now()->setDate(2024, 1, 1)->addDays($dia)->toDateString();
        }
        Http::preventStrayRequests();
        Http::fake(['https://archive-api.open-meteo.com/v1/archive*' => Http::response([
            'daily' => ['time' => [...$fechas, '2025-01-01'], 'precipitation_sum' => [...array_fill(0, 366, 1.5), 0]],
        ])]);
        $url = '/api/geo/lluvia/historico/PE-PIU';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->usuario('Consulta'))->getJson($url)->assertOk()
            ->assertJsonPath('disponible', true)->assertJsonCount(10, 'anios')
            ->assertJsonPath('anios.8.anio', 2024)->assertJsonPath('anios.8.mm', 549)
            ->assertJsonPath('anios.9.mm', null)->assertJsonPath('anios.9.dias', 1);
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_el_mapa_horario_muestra_el_cero_y_omite_las_horas_sin_dato(): void
    {
        $this->region();
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::response([
            'hourly' => [
                'time' => ['2026-11-24T06:00', '2026-11-24T07:00', '2026-11-24T08:00', '2026-11-24T09:00', '2026-11-24T10:00', '2026-11-24T11:00', '2026-11-24T12:00'],
                'precipitation' => [0, 2, 1, null, 4, 3, 6.1],
            ],
        ])]);
        $this->actingAs($this->usuario('Consulta'))->getJson('/api/geo/lluvia/mapa/PE')->assertOk()
            ->assertJsonPath('puntos', 1)->assertJsonCount(6, 'pasos')
            ->assertJsonPath('pasos.0.muestras.0.mm', 0)->assertJsonPath('pasos.5.muestras.0.mm', 6.1);
        $this->getJson('/api/geo/lluvia/mapa/PE-AJENO')->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_el_pronostico_provincial_indica_si_el_proveedor_no_responde(): void
    {
        $this->region();
        DB::insert(<<<'SQL'
            INSERT INTO geo.provincias (codigo, departamento, nombre, archivo_sha256, geom)
            VALUES ('PIU01', 'PE-PIU', 'Provincia de referencia', repeat('a', 64), ST_Multi(ST_MakeEnvelope(-80.9, -5.6, -80.4, -5.2, 4326)))
            SQL);
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::response([], 503)]);
        $this->actingAs($this->usuario('Consulta'))->getJson('/api/geo/lluvia/pronostico/PIU01')->assertOk()
            ->assertJsonPath('lugar', 'Provincia de referencia')->assertJsonPath('disponible', false)
            ->assertJsonCount(0, 'dias');
        Http::assertSent(fn ($solicitud) => str_contains($solicitud->url(), 'forecast_days=7'));
    }

    public function test_el_mapa_conserva_la_ultima_serie_al_fallar_la_actualizacion(): void
    {
        $this->region();
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::sequence()
            ->push(['hourly' => ['time' => ['2026-11-24T06:00'], 'precipitation' => [3.2]]])
            ->push([], 429)]);
        $this->actingAs($this->usuario('Consulta'));

        $url = '/api/geo/lluvia/mapa/PE';
        $anterior = $this->getJson($url)->assertOk()->assertJsonPath('pasos.0.muestras.0.mm', 3.2)->json();
        $this->travel(21)->minutes();
        $this->getJson($url)->assertOk()
            ->assertJsonPath('desactualizado', true)
            ->assertJsonPath('consultado_en', $anterior['consultado_en'])
            ->assertJsonPath('pasos.0.muestras.0.mm', 3.2);
        Http::assertSentCount(2);
    }

    public function test_las_teselas_no_agotan_el_limite_de_consultas_meteorologicas(): void
    {
        $this->region();
        DB::table('geo.temas')->insert([
            'codigo' => 'riesgo-limite', 'tipo' => 'riesgo', 'titulo' => 'Riesgo distrital',
            'periodo' => 'Prueba', 'fuente' => 'Prueba', 'ficha_url' => 'https://example.test',
            'archivo_ruta' => 'prueba.zip', 'archivo_sha256' => str_repeat('a', 64), 'notas' => '', 'leyenda' => '[]',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::response([
            'daily' => ['time' => ['2026-11-24'], 'precipitation_sum' => [3.2], 'precipitation_probability_max' => [70]],
        ])]);
        $this->actingAs($this->usuario('Consulta'));

        for ($i = 0; $i < 21; $i++) {
            $this->get('/api/geo/atlas/riesgo-limite/5/8/16.pbf')->assertOk();
        }
        $this->getJson('/api/geo/lluvia/pronostico/PE-PIU')->assertOk()->assertJsonPath('dias.0.mm', 3.2);
        Http::assertSentCount(1);
    }

    private function region(): void
    {
        DB::insert("INSERT INTO geo.departamentos (codigo, nombre, geom)
            VALUES ('PE-PIU', 'Piura', ST_Multi(ST_MakeEnvelope(-81, -6, -80, -5, 4326)))");
    }
}
