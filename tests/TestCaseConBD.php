<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

abstract class TestCaseConBD extends TestCase
{
    use DatabaseTransactions; // cada prueba se revierte al terminar

    private ?User $adminBase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $bd = (string) config('database.connections.' . config('database.default') . '.database');
        if (!str_ends_with($bd, '_test')) {
            throw new \RuntimeException(
                "PELIGRO: las pruebas deben usar una base cuyo nombre termine en _test (actual: '$bd'). Revisa phpunit.xml."
            );
        }
    }

    // Rectángulo en WKT. Orden: LONGITUD LATITUD.
    protected static function caja(float $x1, float $y1, float $x2, float $y2): string
    {
        return "POLYGON(($x1 $y1,$x2 $y1,$x2 $y2,$x1 $y2,$x1 $y1))";
    }

    protected function rolId(string $rol): int
    {
        $id = DB::table('roles')->where('nombre', $rol)->value('id');
        $this->assertNotNull($id, "Falta el rol '$rol' en la BD de pruebas: ejecuta el INSERT de roles del script.");

        return (int) $id;
    }

    protected function usuario(string $rol, bool $activo = true): User
    {
        return User::create([
            'nombre' => "Test $rol",
            'email' => uniqid(strtolower($rol) . '_') . '@test.local',
            'password' => 'Clave12345!',
            'rol_id' => $this->rolId($rol),
            'activo' => $activo,
        ]);
    }

    protected function admin(): User
    {
        return $this->adminBase ??= $this->usuario('Administrador');
    }

    protected function zona(?string $wkt = null): int
    {
        DB::insert(
            'INSERT INTO zonas (nombre, limite) VALUES (?, ST_GeomFromText(?))',
            ['Zona de prueba', $wkt ?? self::caja(0, 0, 10, 10)]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    protected function escenario(int $zona, string $nivel, string $wkt, string $nombre = 'Escenario de prueba'): int
    {
        DB::insert(
            "INSERT INTO escenarios (zona_id, creado_por, nombre, nivel, origen, area_inundada)
             VALUES (?, ?, ?, ?, 'simulado', ST_GeomFromText(?))",
            [$zona, $this->admin()->id, $nombre, $nivel, $wkt]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    protected function infra(int $zona, string $tipo, string $nombre, string $wkt): int
    {
        $tipoId = DB::table('tipos_infraestructura')->where('nombre', $tipo)->value('id');
        $this->assertNotNull($tipoId, "Falta el tipo '$tipo' en la BD de pruebas.");

        DB::insert(
            'INSERT INTO infraestructuras (zona_id, tipo_id, nombre, geometria)
             VALUES (?, ?, ?, ST_GeomFromText(?))',
            [$zona, $tipoId, $nombre, $wkt]
        );

        return (int) DB::getPdo()->lastInsertId();
    }
}