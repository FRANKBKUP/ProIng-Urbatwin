<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoGeoSeeder extends Seeder
{
    // Rectángulo en formato WKT. Ojo: el orden es LONGITUD LATITUD.
    private function caja(float $x1, float $y1, float $x2, float $y2): string
    {
        return "POLYGON(($x1 $y1,$x2 $y1,$x2 $y2,$x1 $y2,$x1 $y1))";
    }

    public function run(): void
    {
        if (DB::table('zonas')->exists()) {
            return; // evita duplicar datos si lo ejecutas dos veces
        }

        DB::insert(
            'INSERT INTO zonas (nombre, descripcion, limite) VALUES (?, ?, ST_GeomFromText(?))',
            ['Zona piloto (demo)', 'Zona de demostración cerca de Talara; coordenadas ficticias',
             $this->caja(-81.285, -4.595, -81.255, -4.565)]
        );
        $zona = DB::table('zonas')->orderByDesc('id')->value('id');
        $admin = DB::table('usuarios')->where('email', 'admin@urbantwin.test')->value('id');

        // Escenarios simulados, cada uno más grande que el anterior
        $escenarios = [
            ['Lluvia leve',     'leve',     $this->caja(-81.272, -4.582, -81.266, -4.577)],
            ['Lluvia moderada', 'moderado', $this->caja(-81.277, -4.585, -81.261, -4.574)],
            ['Lluvia extrema',  'extremo',  $this->caja(-81.282, -4.590, -81.256, -4.569)],
        ];
        foreach ($escenarios as [$nombre, $nivel, $wkt]) {
            DB::insert(
                "INSERT INTO escenarios (zona_id, creado_por, nombre, nivel, origen, area_inundada)
                 VALUES (?, ?, ?, ?, 'simulado', ST_GeomFromText(?))",
                [$zona, $admin, $nombre, $nivel, $wkt]
            );
        }

        // Infraestructura ficticia
        $tipo = fn ($n) => DB::table('tipos_infraestructura')->where('nombre', $n)->value('id');
        $infra = [
            ['Colegio',         'Colegio ejemplo 1',       'POINT(-81.269 -4.5795)'],
            ['Colegio',         'Colegio ejemplo 2',       'POINT(-81.264 -4.5760)'],
            ['Colegio',         'Colegio ejemplo 3',       'POINT(-81.258 -4.5710)'],
            ['Colegio',         'Colegio ejemplo 4',       'POINT(-81.280 -4.5670)'],
            ['Centro de salud', 'Centro de salud ejemplo 1', 'POINT(-81.2745 -4.5800)'],
            ['Centro de salud', 'Centro de salud ejemplo 2', 'POINT(-81.262 -4.5880)'],
            ['Centro de salud', 'Centro de salud ejemplo 3', 'POINT(-81.283 -4.5930)'],
            ['Vía',             'Vía ejemplo A',           'LINESTRING(-81.280 -4.580,-81.255 -4.580)'],
            ['Vía',             'Vía ejemplo B',           'LINESTRING(-81.2625 -4.570,-81.2625 -4.576)'],
            ['Vía',             'Vía ejemplo C',           'LINESTRING(-81.284 -4.566,-81.255 -4.566)'],
        ];
        foreach ($infra as [$t, $nombre, $wkt]) {
            DB::insert(
                'INSERT INTO infraestructuras (zona_id, tipo_id, nombre, geometria)
                 VALUES (?, ?, ?, ST_GeomFromText(?))',
                [$zona, $tipo($t), $nombre, $wkt]
            );
        }
    }
}