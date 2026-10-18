<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCaseConBD;

class AdministracionTest extends TestCaseConBD
{
    private int $zona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zona = $this->zona(self::caja(0, 20, 10, 30)); // lon 0..10, lat 20..30
        $this->actingAs($this->admin());
    }

    private function poligono(array $anillo): string
    {
        return json_encode(['type' => 'Polygon', 'coordinates' => [$anillo]]);
    }

    private function datosEscenario(string $nombre, string $geojson): array
    {
        return ['nombre' => $nombre, 'zona_id' => $this->zona, 'nivel' => 'leve',
                'origen' => 'simulado', 'geojson' => $geojson];
    }

    public function test_crea_un_escenario_con_coordenadas_correctas(): void
    {
        $geo = $this->poligono([[1, 21], [2, 21], [2, 22], [1, 22], [1, 21]]);

        $this->post('/admin/escenarios', $this->datosEscenario('Nuevo escenario', $geo))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.escenarios.index'));

        $this->assertDatabaseHas('escenarios', ['nombre' => 'Nuevo escenario', 'zona_id' => $this->zona]);
    }

    public function test_rechaza_latitud_y_longitud_invertidas(): void
    {
        $geo = $this->poligono([[21, 1], [21, 2], [22, 2], [22, 1], [21, 1]]); // pares al revés

        $this->post('/admin/escenarios', $this->datosEscenario('Invertido', $geo))
            ->assertSessionHasErrors('geojson');

        $this->assertDatabaseMissing('escenarios', ['nombre' => 'Invertido']);
    }

    public function test_rechaza_un_geojson_que_no_es_un_area(): void
    {
        $punto = json_encode(['type' => 'Point', 'coordinates' => [1, 21]]);

        $this->post('/admin/escenarios', $this->datosEscenario('Es un punto', $punto))
            ->assertSessionHasErrors('geojson');

        $this->assertDatabaseMissing('escenarios', ['nombre' => 'Es un punto']);
    }

    public function test_un_analista_no_puede_crear_escenarios(): void
    {
        $this->actingAs($this->usuario('Analista'));
        $geo = $this->poligono([[1, 21], [2, 21], [2, 22], [1, 22], [1, 21]]);

        $this->post('/admin/escenarios', $this->datosEscenario('Prohibido', $geo))->assertForbidden();

        $this->assertDatabaseMissing('escenarios', ['nombre' => 'Prohibido']);
    }

    public function test_un_escenario_con_reportes_no_se_elimina(): void // RN08
    {
        $e = $this->escenario($this->zona, 'leve', self::caja(1, 21, 2, 22));
        DB::table('reportes')->insert(['escenario_id' => $e, 'usuario_id' => $this->admin()->id, 'total_expuestas' => 1]);

        $this->delete("/admin/escenarios/$e")->assertSessionHasErrors('escenario');

        $this->assertDatabaseHas('escenarios', ['id' => $e]);
    }

    public function test_un_escenario_sin_reportes_si_se_elimina(): void
    {
        $e = $this->escenario($this->zona, 'leve', self::caja(1, 21, 2, 22));

        $this->delete("/admin/escenarios/$e")->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('escenarios', ['id' => $e]);
    }

    public function test_una_infraestructura_que_aparece_en_reportes_no_se_elimina(): void
    {
        $e = $this->escenario($this->zona, 'leve', self::caja(1, 21, 2, 22));
        $i = $this->infra($this->zona, 'Colegio', 'Colegio en reporte', 'POINT(1.5 21.5)');
        $r = DB::table('reportes')->insertGetId(['escenario_id' => $e, 'usuario_id' => $this->admin()->id, 'total_expuestas' => 1]);
        DB::table('reporte_detalles')->insert([
            'reporte_id' => $r, 'infraestructura_id' => $i,
            'nombre_infra' => 'Colegio en reporte', 'tipo_infra' => 'Colegio',
        ]);

        $this->delete("/admin/infraestructuras/$i")->assertSessionHasErrors('infraestructura');

        $this->assertDatabaseHas('infraestructuras', ['id' => $i]);
    }

    public function test_una_zona_con_datos_no_se_elimina(): void
    {
        $this->escenario($this->zona, 'leve', self::caja(1, 21, 2, 22));

        $this->delete("/admin/zonas/{$this->zona}")->assertSessionHasErrors('zona');

        $this->assertDatabaseHas('zonas', ['id' => $this->zona]);
    }

    public function test_el_administrador_no_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->admin();

        $this->put("/admin/usuarios/{$admin->id}", ['rol_id' => $this->rolId('Consulta'), 'activo' => 1])
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('usuarios', ['id' => $admin->id, 'rol_id' => $this->rolId('Administrador')]);
    }

    public function test_no_permite_crear_usuarios_con_correo_repetido(): void
    {
        $this->post('/admin/usuarios', [
            'nombre' => 'Repetido', 'email' => $this->admin()->email,
            'rol_id' => $this->rolId('Consulta'), 'password' => 'Clave12345!',
        ])->assertSessionHasErrors('email');
    }
}