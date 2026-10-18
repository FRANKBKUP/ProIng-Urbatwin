<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCaseConBD;

class ExposicionEspacialTest extends TestCaseConBD
{
    private int $zona;
    private array $escenarios = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->zona = $this->zona();
        $this->escenarios = [
            'leve'     => $this->escenario($this->zona, 'leve', self::caja(2, 2, 4, 4)),
            'moderado' => $this->escenario($this->zona, 'moderado', self::caja(1, 1, 6, 6)),
            'extremo'  => $this->escenario($this->zona, 'extremo', self::caja(0, 0, 9, 9)),
        ];

        $this->infra($this->zona, 'Colegio', 'Colegio A', 'POINT(3 3)');
        $this->infra($this->zona, 'Colegio', 'Colegio B', 'POINT(5 5)');
        $this->infra($this->zona, 'Centro de salud', 'Salud C', 'POINT(8 8)');
        $this->infra($this->zona, 'Vía', 'Via D', 'LINESTRING(0.5 3,2.5 3)');
        $this->infra($this->zona, 'Colegio', 'Colegio E', 'POINT(9.5 9.5)');

        $this->actingAs($this->usuario('Analista'));
    }

    public function test_el_cruce_espacial_da_los_totales_esperados(): void
    {
        $esperados = ['leve' => 2, 'moderado' => 3, 'extremo' => 4];

        foreach ($esperados as $nivel => $total) {
            $this->getJson("/api/escenarios/{$this->escenarios[$nivel]}/exposicion")
                ->assertOk()
                ->assertJsonPath('total', $total);
        }
    }

    public function test_una_via_parcialmente_inundada_cuenta_como_expuesta(): void
    {
        // La vía D empieza fuera del escenario leve: por eso se usa ST_Intersects y no ST_Contains
        $r = $this->getJson("/api/escenarios/{$this->escenarios['leve']}/exposicion")->assertOk();

        $nombres = collect($r->json('infraestructuras.features'))->pluck('properties.nombre')->all();

        $this->assertContains('Via D', $nombres);
        $this->assertNotContains('Colegio B', $nombres);
    }

    public function test_agrupa_la_exposicion_por_tipo(): void
    {
        $this->getJson("/api/escenarios/{$this->escenarios['moderado']}/exposicion")
            ->assertOk()
            ->assertJsonPath('por_tipo.Colegio', 2)
            ->assertJsonPath('por_tipo.Vía', 1);
    }

    public function test_una_infraestructura_inactiva_no_cuenta(): void
    {
        DB::update("UPDATE infraestructuras SET activo = 0 WHERE nombre = 'Colegio A'");

        $this->getJson("/api/escenarios/{$this->escenarios['leve']}/exposicion")
            ->assertJsonPath('total', 1);
    }

    public function test_la_infraestructura_de_otra_zona_no_cuenta(): void
    {
        $otraZona = $this->zona(self::caja(50, 50, 60, 60));
        $this->infra($otraZona, 'Colegio', 'Colegio otra zona', 'POINT(3 3)'); // mismo lugar, otra zona

        $this->getJson("/api/escenarios/{$this->escenarios['leve']}/exposicion")
            ->assertJsonPath('total', 2);
    }

    public function test_un_escenario_inexistente_o_desactivado_da_404(): void
    {
        $this->getJson('/api/escenarios/99999999/exposicion')->assertNotFound();

        DB::update('UPDATE escenarios SET activo = 0 WHERE id = ?', [$this->escenarios['leve']]);
        $this->getJson("/api/escenarios/{$this->escenarios['leve']}/exposicion")->assertNotFound();
    }
}