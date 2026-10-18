<?php

namespace Tests\Feature;

use Tests\TestCaseConBD;

class AccesoPorRolTest extends TestCaseConBD
{
    private const RUTAS_ADMIN = ['/admin/escenarios', '/admin/usuarios', '/admin/infraestructuras', '/admin/zonas'];

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/mapa')->assertRedirect('/login');
    }

    public function test_login_correcto_lleva_al_mapa(): void
    {
        $u = $this->usuario('Analista');

        $this->post('/login', ['email' => $u->email, 'password' => 'Clave12345!'])
            ->assertRedirect('/mapa');

        $this->assertAuthenticatedAs($u);
    }

    public function test_contrasena_incorrecta_no_entra(): void
    {
        $u = $this->usuario('Analista');

        $this->post('/login', ['email' => $u->email, 'password' => 'otra-clave'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_entra(): void
    {
        $u = $this->usuario('Analista', false);

        $this->post('/login', ['email' => $u->email, 'password' => 'Clave12345!'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_consulta_no_puede_analizar_ni_guardar_reportes(): void
    {
        $this->actingAs($this->usuario('Consulta'));

        $this->getJson('/api/escenarios/1/exposicion')->assertForbidden();
        $this->postJson('/api/escenarios/1/reportes')->assertForbidden();
    }

    public function test_consulta_si_puede_ver_el_mapa_y_los_reportes(): void
    {
        $this->actingAs($this->usuario('Consulta'));

        $this->get('/mapa')->assertOk();
        $this->get('/reportes')->assertOk();
    }

    public function test_solo_el_administrador_entra_a_la_administracion(): void
    {
        foreach (['Analista', 'Consulta'] as $rol) {
            $this->actingAs($this->usuario($rol));
            foreach (self::RUTAS_ADMIN as $ruta) {
                $this->get($ruta)->assertForbidden();
            }
        }

        $this->actingAs($this->admin());
        foreach (self::RUTAS_ADMIN as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }
}