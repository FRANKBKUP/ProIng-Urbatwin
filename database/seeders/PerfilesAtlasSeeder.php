<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PerfilesAtlasSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing') || DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Los perfiles de ejemplo requieren un PostGIS local o de pruebas.');
        }

        $rol = DB::table('app.roles')->where('nombre', 'Consulta')->value('id');
        foreach ([
            ['Equipo litoral', 'PE-PIU', 'azul', 'Consulta de la cuenca baja de Piura', 'piura'],
            ['Equipo norte', 'PE-LAL', 'verde', 'Seguimiento de lluvias en la costa norte', 'norte'],
            ['Equipo centro', 'PE-LIM', 'uva', 'Lectura territorial del centro del país', 'centro'],
            ['Equipo sierra', 'PE-CUS', 'oro', 'Consulta de escenarios en la sierra sur', 'sierra'],
            ['Equipo amazónico', 'PE-LOR', 'coral', 'Consulta de precipitación en la Amazonía', 'selva'],
        ] as [$nombre, $region, $avatar, $ocupacion, $alias]) {
            if (! DB::table('geo.departamentos')->where('codigo', $region)->exists()) {
                continue;
            }
            $usuario = User::firstOrCreate(['email' => "atlas-$alias@urbantwin.test"], [
                'nombre' => $nombre, 'password' => Str::random(48), 'rol_id' => $rol, 'activo' => true,
            ]);
            DB::table('app.perfiles')->insertOrIgnore([
                'usuario_id' => $usuario->id, 'departamento' => $region, 'avatar' => $avatar, 'ocupacion' => $ocupacion,
                'visible_en_mapa' => true, 'demostracion' => true,
                'descripcion' => 'Perfil de demostración para explorar avatares por territorio.',
            ]);
        }
    }
}
