<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

abstract class TestCaseConBD extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private abrirTransaccion;
    }

    private ?User $adminBase = null;

    public function beginDatabaseTransaction(): void
    {
        $conexion = config('database.connections.pgsql');
        if (config('database.default') !== 'pgsql' || $conexion['database'] !== 'urbantwin_v2_test'
            || filled($conexion['url']) || $conexion['host'] !== '127.0.0.1') {
            throw new \RuntimeException('La suite requiere la base PostGIS local aislada urbantwin_v2_test.');
        }

        $this->abrirTransaccion();
    }

    // Rectángulo en WKT. Orden: LONGITUD LATITUD.
    protected static function caja(float $x1, float $y1, float $x2, float $y2): string
    {
        return "POLYGON(($x1 $y1,$x2 $y1,$x2 $y2,$x1 $y2,$x1 $y1))";
    }

    protected function rolId(string $rol): int
    {
        $id = DB::table('roles')->where('nombre', $rol)->value('id');
        $this->assertNotNull($id, "Falta el rol '$rol' en la BD de pruebas: ejecuta las migraciones.");

        return (int) $id;
    }

    protected function usuario(string $rol, bool $activo = true): User
    {
        return User::create([
            'nombre' => "Test $rol",
            'email' => uniqid(strtolower($rol).'_').'@test.local',
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
            'INSERT INTO zonas (nombre, limite) VALUES (?, ST_GeomFromText(?, 4326))',
            ['Zona de prueba', $wkt ?? self::caja(0, 0, 10, 10)]
        );

        return (int) DB::getPdo()->lastInsertId();
    }

    protected function escenario(int $zona, string $nivel, string $wkt, string $nombre = 'Escenario de prueba'): int
    {
        DB::insert(
            "INSERT INTO escenarios (zona_id, creado_por, nombre, nivel, origen, area_inundada)
             VALUES (?, ?, ?, ?, 'simulado', ST_GeomFromText(?, 4326))",
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
             VALUES (?, ?, ?, ST_GeomFromText(?, 4326))',
            [$zona, $tipoId, $nombre, $wkt]
        );

        return (int) DB::getPdo()->lastInsertId();
    }
}
