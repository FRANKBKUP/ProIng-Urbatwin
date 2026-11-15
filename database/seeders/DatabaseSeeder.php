<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Las cuentas de demostración solo se crean en local o testing.');
        }

        $usuarios = [
            ['Administrador', 'admin@urbantwin.test'],
            ['Analista', 'analista@urbantwin.test'],
            ['Consulta', 'consulta@urbantwin.test'],
        ];

        foreach ($usuarios as [$rol, $email]) {
            DB::table('usuarios')->insertOrIgnore(
                [
                    'email' => $email,
                    'nombre' => "Usuario $rol",
                    'password' => Hash::make('Cambiar123!'),
                    'rol_id' => DB::table('roles')->where('nombre', $rol)->value('id'),
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $this->call(DemoGeoSeeder::class);
        if (DB::getDriverName() === 'pgsql') {
            $this->call(DemoVersionadaSeeder::class);
        }
    }
}
