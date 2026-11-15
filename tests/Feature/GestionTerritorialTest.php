<?php

namespace Tests\Feature;

use App\Services\Catalogo\ImportarCapa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCaseConBD;

class GestionTerritorialTest extends TestCaseConBD
{
    public function test_la_portada_es_publica_y_los_datos_del_visor_requieren_una_cuenta(): void
    {
        $this->get('/')->assertOk()->assertSee('Lluvia y riesgo, en el mapa')
            ->assertSee('Entrar al visor')->assertSee('Crear cuenta');
        $this->get('/visor')->assertRedirect('/login');
        $this->getJson('/api/geo/lugares')->assertUnauthorized();
    }

    public function test_solo_el_propietario_edita_su_grupo_y_eliminarlo_devuelve_los_lugares_a_privado(): void
    {
        $duena = $this->usuario('Consulta');
        $miembro = $this->usuario('Consulta');
        $this->actingAs($duena)->post('/grupos', ['nombre' => 'Cuenca original'])->assertSessionHasNoErrors();
        $grupo = DB::table('app.grupos')->where('propietario_id', $duena->id)->value('id');
        $this->post("/grupos/$grupo/invitaciones")->assertSessionHas('invitacion');
        $codigo = session('invitacion');

        $this->actingAs($miembro)->post('/grupos/aceptar', ['codigo' => $codigo])->assertSessionHasNoErrors();
        $lugar = $this->postJson('/mis-lugares', ['nombre' => 'Punto inicial', 'longitud' => -71.9, 'latitud' => -13.5])
            ->assertCreated()->json('id');
        $this->patch("/mis-lugares/$lugar", ['grupo_id' => $grupo])->assertSessionHasNoErrors();
        $this->patch("/grupos/$grupo", ['nombre' => 'Edición ajena'])->assertNotFound();
        $datos = ['nombre' => 'Punto corregido', 'longitud' => -71.47, 'latitud' => -13.51];
        $this->patch("/mis-lugares/$lugar/datos", $datos)->assertSessionHasNoErrors();

        $this->actingAs($duena)->patch("/mis-lugares/$lugar/datos", $datos)->assertNotFound();
        $this->patch("/grupos/$grupo", ['nombre' => 'Cuenca actualizada'])->assertSessionHasNoErrors();
        $this->get('/mis-lugares')->assertOk()->assertSee('Cuenca actualizada')->assertSee('Eliminar grupo');
        $this->getJson("/api/geo/lugares?grupo=$grupo")->assertOk()->assertJsonPath('lugares.0.longitud', -71.47);
        $this->delete("/grupos/$grupo")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('app.grupos', ['id' => $grupo]);
        $this->assertDatabaseHas('app.lugares', ['id' => $lugar, 'grupo_id' => null, 'nombre' => 'Punto corregido']);
        $this->getJson("/api/geo/lugares?grupo=$grupo")->assertNotFound();
        $this->actingAs($miembro)->delete("/mis-lugares/$lugar")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('app.lugares', ['id' => $lugar]);
    }

    public function test_el_retiro_de_una_capa_excluye_nuevas_consultas_y_conserva_el_reporte(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $admin = $this->admin();
        $cobertura = DB::selectOne("INSERT INTO geo.coberturas (nombre, creado_por, geom)
            VALUES ('Cobertura de gestión', ?, ST_Multi(ST_MakeEnvelope(-81.285, -4.595, -81.255, -4.565, 4326))) RETURNING id",
            [$admin->id])->id;
        $importador = $this->app->make(ImportarCapa::class);
        $versiones = [];
        foreach (['peligro' => 'talara-peligro-solapado', 'vias' => 'talara-via-cruce'] as $tipo => $fixture) {
            $capas[$tipo] = DB::table('catalogo.capas')->insertGetId(['nombre' => $fixture, 'tipo' => $tipo, 'cobertura_id' => $cobertura]);
            $versiones[$tipo] = $importador->ejecutar($capas[$tipo], $admin->id, $fixture.'.geojson',
                file_get_contents(base_path('tests/Fixtures/'.$fixture.'.geojson')), [
                    'fuente' => 'Fuente de prueba', 'licencia' => 'Pruebas', 'origen' => 'observado', 'fecha_dato' => '2017-03-30',
                ])['version_id'];
        }
        $parametros = ['peligro_id' => $versiones['peligro'], 'vias_id' => $versiones['vias']];
        $analista = $this->usuario('Analista');
        $this->actingAs($admin)->get('/catalogo')->assertOk()->assertSee('Cargar un GeoJSON')
            ->assertViewHas('maximoImportacion', fn (int $limite) => $limite > 0 && $limite <= ImportarCapa::MAX_BYTES);
        $reporte = $this->actingAs($analista)->postJson('/analisis/reportes', $parametros)->assertCreated()->json('id');
        $capa = $capas['peligro'];
        $this->patch("/catalogo/gestion/capa/$capa", ['accion' => 'retirar'])->assertForbidden();
        $this->actingAs($admin)->patch("/catalogo/gestion/capa/$capa", ['accion' => 'editar', 'etiqueta' => 'Peligro corregido'])
            ->assertSessionHasNoErrors();
        $this->getJson("/api/geo/capas/$capa")->assertOk()->assertJsonPath('capa.nombre', 'Peligro corregido');
        $this->patch("/catalogo/gestion/capa/$capa", ['accion' => 'retirar'])->assertSessionHasNoErrors();
        $this->getJson("/api/geo/capas/$capa")->assertNotFound();
        $this->actingAs($analista)->postJson('/analisis/reportes', $parametros)->assertUnprocessable();
        $this->get("/analisis/reportes/$reporte")->assertOk()->assertSee('talara-peligro-solapado');
        $this->actingAs($admin)->patch("/catalogo/gestion/capa/$capa", ['accion' => 'restaurar'])->assertSessionHasNoErrors();
        $this->getJson("/api/geo/capas/$capa")->assertOk()->assertJsonPath('capa.version_id', $versiones['peligro']);
        $this->assertDatabaseHas('app.auditoria', ['usuario_id' => $admin->id, 'accion' => 'retirar_capa', 'objeto' => "capa/$capa"]);

        $carga = DB::table('catalogo.capas_versiones')->where('id', $versiones['peligro'])->value('importacion_id');
        $url = "/catalogo/importaciones/$carga";
        $this->get("$url?elemento=2")->assertOk()->assertSee('Entidades de la versión vigente')
            ->assertSee('GeoJSON de la entidad 2')->assertSee('Polígono B');
        $contenido = json_decode(file_get_contents(base_path('tests/Fixtures/talara-peligro-solapado.geojson')), false, flags: JSON_THROW_ON_ERROR);
        $contenido->features[1]->properties->nombre = 'Nombre corregido';
        $respuesta = $this->patch("$url/entidades/2", ['entidad' => json_encode($contenido->features[1])]);
        $editada = DB::table('catalogo.capas_versiones')->where('capa_id', $capa)->where('numero', 2)->value('importacion_id');
        $respuesta->assertRedirect("/catalogo/importaciones/$editada");
        $this->get("/catalogo/importaciones/$editada")->assertOk()->assertSee('Nombre corregido')->assertSee('importación de origen');
        $this->delete("$url/entidades/2")->assertConflict();
        $this->delete("/catalogo/importaciones/$editada/entidades/2")->assertRedirect();
        $this->getJson("/api/geo/capas/$capa")->assertOk()->assertJsonPath('capa.numero', 3)->assertJsonCount(1, 'peligro.features');
        $this->get("/analisis/reportes/$reporte")->assertOk()->assertSee('v1 (ID '.$versiones['peligro'].')');
        $this->assertDatabaseHas('app.auditoria', ['accion' => 'editar_entidad', 'objeto' => "importacion/$editada"]);
    }

    public function test_la_invitacion_de_superusuario_es_nominada_de_un_solo_uso_y_para_el_correo_registrado(): void
    {
        $admin = $this->admin();
        $frank = $this->usuario('Consulta');
        $extra = $this->usuario('Consulta');
        $this->actingAs($admin)->get('/admin/superusuarios')->assertOk()->assertSee('FRANK SUAREZ')
            ->assertSee('GABRIELA REYES')->assertSee('DANIEL HUERTAS')->assertSee('ROSA RUMICHE')->assertSee('ARTURO SANDOVAL');
        $this->post('/admin/superusuarios/2/invitacion', ['correo' => $extra->email])->assertForbidden();
        $this->put("/admin/usuarios/$frank->id", ['rol_id' => $this->rolId('Superusuario'), 'activo' => 1])
            ->assertSessionHasErrors('rol_id');
        $codigo = $this->post('/admin/superusuarios/1/invitacion', ['correo' => $frank->email])
            ->assertOk()->viewData('codigo');
        $this->assertDatabaseHas('app.superusuarios', ['plaza' => 1, 'hash' => hash('sha256', $codigo), 'usuario_id' => null]);

        $this->actingAs($extra)->post('/perfil/credenciales', ['codigo_superusuario' => $codigo])
            ->assertSessionHasErrors('codigo_superusuario');
        $this->assertSame('Consulta', $extra->fresh()->rol->nombre);
        $this->actingAs($frank)->post('/perfil/credenciales', ['codigo_superusuario' => $codigo])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('app.superusuarios', ['plaza' => 1, 'hash' => null, 'usuario_id' => $frank->id]);
        $this->assertSame('Superusuario', $frank->fresh()->rol->nombre);
        $this->post('/perfil/credenciales', ['codigo_superusuario' => $codigo])->assertSessionHasErrors('codigo_superusuario');

        $this->actingAs($frank->fresh())->post('/admin/superusuarios/2/invitacion', ['correo' => $extra->email])->assertOk();
        $this->actingAs($admin)->put("/admin/usuarios/$frank->id", ['rol_id' => $this->rolId('Consulta'), 'activo' => 0])->assertForbidden();
    }
}
