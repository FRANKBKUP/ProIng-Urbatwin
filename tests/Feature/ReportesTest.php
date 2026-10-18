<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCaseConBD;

class ReportesTest extends TestCaseConBD
{
    private int $escenario;
    private int $escenarioVacio;

    protected function setUp(): void
    {
        parent::setUp();

        $zona = $this->zona();
        $this->escenario = $this->escenario($zona, 'moderado', self::caja(1, 1, 6, 6));
        $this->escenarioVacio = $this->escenario($zona, 'leve', self::caja(70, 70, 71, 71));

        $this->infra($zona, 'Colegio', 'Colegio Alfa', 'POINT(3 3)');
        $this->infra($zona, 'Colegio', 'Colegio Beta', 'POINT(5 5)');
        $this->infra($zona, 'Vía', 'Via Delta', 'LINESTRING(0.5 3,2.5 3)');
    }

    public function test_guardar_un_reporte_crea_el_reporte_y_sus_detalles(): void
    {
        $analista = $this->usuario('Analista');
        $this->actingAs($analista);

        $id = $this->postJson("/api/escenarios/{$this->escenario}/reportes")
            ->assertCreated()
            ->assertJsonStructure(['id', 'url'])
            ->json('id');

        $this->assertDatabaseHas('reportes', [
            'id' => $id, 'escenario_id' => $this->escenario,
            'usuario_id' => $analista->id, 'total_expuestas' => 3,
        ]);
        $this->assertSame(3, DB::table('reporte_detalles')->where('reporte_id', $id)->count());
    }

    public function test_sin_exposicion_no_se_genera_reporte(): void // P5
    {
        $this->actingAs($this->usuario('Analista'));

        $this->postJson("/api/escenarios/{$this->escenarioVacio}/reportes")->assertStatus(422);

        $this->assertSame(0, DB::table('reportes')->count());
    }

    public function test_un_reporte_no_cambia_aunque_se_renombre_la_infraestructura(): void // P7 / RN07
    {
        $this->actingAs($this->usuario('Analista'));
        $id = $this->postJson("/api/escenarios/{$this->escenario}/reportes")->json('id');

        DB::update("UPDATE infraestructuras SET nombre = 'Colegio Omega' WHERE nombre = 'Colegio Alfa'");

        $this->actingAs($this->usuario('Consulta'))
            ->get("/reportes/$id")
            ->assertOk()
            ->assertSee('Colegio Alfa')
            ->assertDontSee('Colegio Omega');
    }

    public function test_todos_los_roles_pueden_ver_un_reporte(): void
    {
        $this->actingAs($this->usuario('Analista'));
        $id = $this->postJson("/api/escenarios/{$this->escenario}/reportes")->json('id');

        foreach (['Administrador', 'Analista', 'Consulta'] as $rol) {
            $this->actingAs($this->usuario($rol))->get("/reportes/$id")->assertOk();
        }
    }

    public function test_un_reporte_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario('Consulta'))->get('/reportes/99999999')->assertNotFound();
    }
}