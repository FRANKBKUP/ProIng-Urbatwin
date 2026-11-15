<?php

namespace Tests\Feature;

use App\Services\Catalogo\ImportarCapa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCaseConBD;

class TerritorioCiudadanoTest extends TestCaseConBD
{
    public function test_el_registro_crea_una_cuenta_ciudadana_aunque_se_envie_otro_rol(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Crear cuenta ciudadana');
        $this->post('/registro', [
            'nombre' => 'Vecina de Piura', 'email' => 'vecina@test.local', 'rol_id' => $this->rolId('Administrador'),
            'password' => 'ClavePropia123!', 'password_confirmation' => 'ClavePropia123!',
        ])->assertRedirect('/panel');

        $usuario = $this->app['auth']->user();
        $this->assertSame('Consulta', $usuario->rol->nombre);
        $this->assertTrue(Hash::check('ClavePropia123!', $usuario->password));
        $this->get('/catalogo')->assertForbidden();
        $this->get('/panel')->assertOk()->assertSee('Inundaciones observadas');
    }

    public function test_cambia_su_propia_contrasena_solo_si_la_actual_coincide(): void
    {
        $usuario = $this->usuario('Consulta');
        $otra = $this->usuario('Consulta');
        $nueva = ['password' => 'NuevaClaveLocal123!', 'password_confirmation' => 'NuevaClaveLocal123!'];
        $this->actingAs($usuario)->from('/perfil')
            ->patch('/perfil/contrasena', $nueva + ['current_password' => 'Equivocada'])
            ->assertSessionHasErrors(['current_password' => 'La contraseña actual es incorrecta.']);
        $this->patch('/perfil/contrasena', $nueva + ['current_password' => 'Clave12345!', 'usuario_id' => $otra->id])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check($nueva['password'], $usuario->fresh()->password));
        $this->assertTrue(Hash::check('Clave12345!', $otra->fresh()->password));
    }

    public function test_oculta_simulaciones_de_la_consulta_ciudadana_y_muestra_observaciones(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $admin = $this->admin();
        $cobertura = DB::selectOne("INSERT INTO geo.coberturas (nombre, creado_por, geom)
            VALUES ('Cobertura de prueba', ?, ST_Multi(ST_MakeEnvelope(-81.285, -4.595, -81.255, -4.565, 4326))) RETURNING id",
            [$admin->id])->id;
        $importar = $this->app->make(ImportarCapa::class);
        $original = file_get_contents(base_path('tests/Fixtures/talara-peligro-solapado.geojson'));
        $ids = [];
        foreach (['observado' => 'Observación publicada', 'simulado' => 'Escenario de laboratorio'] as $origen => $nombre) {
            $capa = DB::table('catalogo.capas')->insertGetId(['nombre' => $nombre, 'tipo' => 'peligro', 'cobertura_id' => $cobertura]);
            $importar->ejecutar($capa, $admin->id, 'fixture.geojson', $original, [
                'fuente' => 'Fuente de prueba', 'licencia' => 'Pruebas', 'fecha_dato' => '2017-03-30', 'origen' => $origen,
            ]);
            $ids[$origen] = $capa;
        }

        $this->actingAs($this->usuario('Consulta'));
        $this->get('/visor')->assertOk()->assertSee('Observación publicada')->assertDontSee('Escenario de laboratorio');
        $this->get('/panel')->assertOk()->assertSee('Observación publicada')->assertDontSee('Escenario de laboratorio');
        $this->getJson('/api/geo/capas/'.$ids['simulado'])->assertNotFound();
        $this->getJson('/api/geo/capas/'.$ids['observado'])->assertOk()->assertJsonPath('capa.origen', 'observado');
    }

    public function test_los_lugares_privados_solo_aparecen_en_la_cuenta_de_su_propietario(): void
    {
        $duena = $this->usuario('Consulta');
        $this->actingAs($duena);
        $id = $this->postJson('/mis-lugares', ['nombre' => 'Mi lugar', 'longitud' => -80.63, 'latitud' => -5.20])
            ->assertCreated()->json('id');
        $this->getJson('/api/geo/lugares')->assertOk()->assertJsonPath('lugares.0.id', $id);
        $this->assertDatabaseHas('app.lugares', ['id' => $id, 'usuario_id' => $duena->id, 'grupo_id' => null]);

        $this->actingAs($this->usuario('Consulta'));
        $this->getJson('/api/geo/lugares')->assertOk()->assertJsonCount(0, 'lugares');
        $this->patch("/mis-lugares/$id", ['grupo_id' => null])->assertNotFound();
        $this->delete("/mis-lugares/$id")->assertNotFound();
    }

    public function test_un_grupo_requiere_invitacion_y_solo_ve_puntos_compartidos(): void
    {
        $duena = $this->usuario('Consulta');
        $miembro = $this->usuario('Consulta');
        $ajeno = $this->usuario('Consulta');
        $this->actingAs($duena)->post('/grupos', ['nombre' => 'Familia'])->assertSessionHasNoErrors();
        $grupo = DB::table('app.grupos')->where('propietario_id', $duena->id)->value('id');
        $this->post("/grupos/$grupo/invitaciones")->assertSessionHas('invitacion');
        $codigo = session('invitacion');
        $this->assertDatabaseHas('app.grupo_invitaciones', ['hash' => hash('sha256', $codigo), 'grupo_id' => $grupo]);
        $this->get('/mis-lugares')->assertOk()->assertSee('Familia');

        $this->actingAs($miembro)->getJson("/api/geo/lugares?grupo=$grupo")->assertNotFound();
        $this->post('/grupos/aceptar', ['codigo' => $codigo])->assertSessionHasNoErrors();
        $this->post("/grupos/$grupo/invitaciones")->assertNotFound();
        $this->actingAs($ajeno)->post('/grupos/aceptar', ['codigo' => $codigo])->assertSessionHasErrors('codigo');

        $this->actingAs($miembro);
        $id = $this->postJson('/mis-lugares', ['nombre' => 'Lugar para compartir', 'longitud' => -80.64, 'latitud' => -5.21])
            ->assertCreated()->json('id');
        $this->actingAs($duena)->getJson("/api/geo/lugares?grupo=$grupo")->assertOk()->assertJsonCount(0, 'lugares');
        $this->actingAs($miembro)->patch("/mis-lugares/$id", ['grupo_id' => $grupo])->assertSessionHasNoErrors();
        $this->actingAs($duena)->getJson("/api/geo/lugares?grupo=$grupo")->assertOk()
            ->assertJsonPath('lugares.0.id', $id)->assertJsonMissingPath('lugares.0.email');
        $this->actingAs($ajeno)->getJson("/api/geo/lugares?grupo=$grupo")->assertNotFound();

        $this->actingAs($miembro)->delete("/grupos/$grupo")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('app.lugares', ['id' => $id, 'usuario_id' => $miembro->id, 'grupo_id' => null]);
        $this->actingAs($duena)->getJson("/api/geo/lugares?grupo=$grupo")->assertOk()->assertJsonCount(0, 'lugares');
    }
}
