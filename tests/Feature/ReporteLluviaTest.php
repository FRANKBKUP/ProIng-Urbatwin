<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCaseConBD;

class ReporteLluviaTest extends TestCaseConBD
{
    public function test_agrupa_por_anio_un_periodo_de_varios_anios(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_series')->where('id', $serie)->update(['fin' => '2020-12-31']);
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $serie, 'fecha' => '2017-03-01', 'precipitacion_mm' => 2, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $serie, 'fecha' => '2020-01-01', 'precipitacion_mm' => 3, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
        ]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$serie)
            ->assertOk()->assertSee('Precipitación anual')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['agrupacion'] === 'anual'
                && $lluvia['grafico']->count() === 4 && $lluvia['grafico'][0]['valor'] === 2.0
                && $lluvia['grafico'][3]['valor'] === 3.0);
    }

    public function test_agrupa_automaticamente_un_periodo_largo_por_mes_sin_ocultar_dias_faltantes(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_series')->where('id', $serie)->update(['fin' => '2017-06-30']);
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $serie, 'fecha' => '2017-03-01', 'precipitacion_mm' => 2, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-04-01', 'precipitacion_mm' => 3, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
        ]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$serie)
            ->assertOk()->assertSee('Precipitación mensual')->assertSee('acumulado parcial')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['agrupacion'] === 'mensual'
                && $lluvia['grafico']->count() === 4 && $lluvia['grafico'][0]['valor'] === 2.0
                && $lluvia['grafico'][0]['faltantes'] === 30 && $lluvia['grafico'][2]['valor'] === null);
    }

    public function test_rechaza_una_grafica_diaria_mayor_a_un_ano(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_series')->where('id', $serie)->update(['fin' => '2018-12-31']);

        $this->actingAs($this->usuario('Analista'))->getJson('/analisis/reportes?serie='.$serie.'&agrupar=diario')
            ->assertUnprocessable()->assertJsonValidationErrors('agrupar');
    }

    public function test_consulta_un_rango_que_cruza_meses_compatibles(): void
    {
        $marzo = $this->serie();
        DB::table('geo.lluvia_series')->where('id', $marzo)->update(['fin' => '2017-03-31']);
        $abril = DB::selectOne(<<<'SQL'
            INSERT INTO geo.lluvia_series (codigo, zona, producto, inicio, fin, unidad, metodo, fuente, procedencia, geom)
            SELECT 'reporte-abril', zona, producto, '2017-04-01', '2017-04-30', unidad, metodo, fuente, procedencia, geom
            FROM geo.lluvia_series WHERE id = ? RETURNING id
            SQL, [$marzo])->id;
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $marzo, 'fecha' => '2017-03-31', 'precipitacion_mm' => 2.5, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $abril, 'fecha' => '2017-04-01', 'precipitacion_mm' => 7, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
        ]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$marzo.'&desde=2017-03-31&hasta=2017-04-01')
            ->assertOk()->assertSee('9,50')->assertSee('31/03/2017')->assertSee('01/04/2017')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['faltantes'] === 0
                && $lluvia['disponibles'] === 2 && $lluvia['total'] === 9.5);
    }

    public function test_no_combina_periodos_con_distinta_metodologia(): void
    {
        $marzo = $this->serie();
        DB::table('geo.lluvia_series')->where('id', $marzo)->update(['fin' => '2017-03-31']);
        DB::insert(<<<'SQL'
            INSERT INTO geo.lluvia_series (codigo, zona, producto, inicio, fin, unidad, metodo, fuente, procedencia, geom)
            SELECT 'reporte-abril', zona, producto, '2017-04-01', '2017-04-30', unidad, 'Otro método', fuente, procedencia, geom
            FROM geo.lluvia_series WHERE id = ?
            SQL, [$marzo]);

        $this->actingAs($this->usuario('Analista'))->getJson('/analisis/reportes?serie='.$marzo.'&desde=2017-03-31&hasta=2017-04-01')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
    }

    public function test_permite_elegir_entre_meses_importados(): void
    {
        $this->serie();
        $abril = DB::selectOne(<<<'SQL'
            INSERT INTO geo.lluvia_series (codigo, zona, producto, inicio, fin, unidad, metodo, fuente, procedencia, geom)
            SELECT 'reporte-abril', zona, producto, '2017-04-01', '2017-04-30', unidad, metodo, fuente, procedencia, geom
            FROM geo.lluvia_series WHERE codigo = 'reporte-prueba' RETURNING id
            SQL)->id;
        DB::table('geo.lluvia_diaria')->insert(['serie_id' => $abril, 'fecha' => '2017-04-01', 'precipitacion_mm' => 6, 'cobertura' => 1, 'archivo' => 'abril.tif', 'sha256' => str_repeat('a', 64)]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$abril)
            ->assertOk()->assertSee('01/03/2017')->assertSee('01/04/2017')->assertSee('6,00 mm')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['serie']->id === $abril && $lluvia['disponibles'] === 1);
    }

    public function test_filtra_fechas_inclusivas_y_recalcula_el_resumen(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $serie, 'fecha' => '2017-03-01', 'precipitacion_mm' => 99, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-03-02', 'precipitacion_mm' => 4, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-03-03', 'precipitacion_mm' => 3, 'cobertura' => 1, 'archivo' => 'c.tif', 'sha256' => str_repeat('c', 64)],
        ]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$serie.'&desde=2017-03-02&hasta=2017-03-03')
            ->assertOk()->assertSee('7,00')->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['dias']->count() === 2
                && $lluvia['total'] === 7.0 && $lluvia['mayor']['fecha'] === '2017-03-02');
    }

    public function test_rechaza_rangos_invertidos_o_fuera_de_la_serie(): void
    {
        $serie = $this->serie();
        $this->actingAs($this->usuario('Analista'));

        $this->getJson('/analisis/reportes?serie='.$serie.'&desde=2017-03-03&hasta=2017-03-01')
            ->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson('/analisis/reportes?serie='.$serie.'&desde=2017-02-28&hasta=2017-03-02')
            ->assertUnprocessable()->assertJsonValidationErrors('desde');
    }

    public function test_muestra_un_acumulado_completo_incluyendo_dias_sin_lluvia(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $serie, 'fecha' => '2017-03-01', 'precipitacion_mm' => 0, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-03-02', 'precipitacion_mm' => 4, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-03-03', 'precipitacion_mm' => 3, 'cobertura' => 1, 'archivo' => 'c.tif', 'sha256' => str_repeat('c', 64)],
        ]);

        $this->actingAs($this->usuario('Administrador'))->get('/analisis/reportes?serie='.$serie)
            ->assertOk()->assertSee('Lluvia acumulada')->assertSee('7,00')->assertSee('0,00 mm')
            ->assertDontSee('El acumulado mostrado es parcial')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['faltantes'] === 0
                && $lluvia['disponibles'] === 3 && $lluvia['mayor']['fecha'] === '2017-03-02');
    }

    public function test_reporta_datos_diarios_reales_y_advierte_las_fechas_faltantes(): void
    {
        $serie = $this->serie();
        DB::table('geo.lluvia_diaria')->insert([
            ['serie_id' => $serie, 'fecha' => '2017-03-01', 'precipitacion_mm' => 2.5, 'cobertura' => 1, 'archivo' => 'a.tif', 'sha256' => str_repeat('a', 64)],
            ['serie_id' => $serie, 'fecha' => '2017-03-03', 'precipitacion_mm' => 10, 'cobertura' => 1, 'archivo' => 'b.tif', 'sha256' => str_repeat('b', 64)],
        ]);

        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie='.$serie)
            ->assertOk()->assertSee('Lluvias históricas')->assertSee('Sin datos')->assertSee('12,50')
            ->assertSee('El acumulado mostrado es parcial')
            ->assertViewHas('lluvia', fn (array $lluvia) => $lluvia['faltantes'] === 1
                && $lluvia['disponibles'] === 2 && $lluvia['total'] === 12.5
                && $lluvia['mayor']['fecha'] === '2017-03-03');
    }

    public function test_restringe_el_acceso_y_rechaza_series_inexistentes(): void
    {
        $this->get('/analisis/reportes')->assertRedirect('/login');
        $this->actingAs($this->usuario('Consulta'))->get('/analisis/reportes')->assertForbidden();
        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes?serie=999999999')->assertNotFound();
    }

    public function test_informa_cuando_no_hay_series_disponibles(): void
    {
        $this->actingAs($this->usuario('Analista'))->get('/analisis/reportes')
            ->assertOk()->assertSee('Todavía no hay series de lluvia importadas.');
    }

    private function serie(): int
    {
        return DB::selectOne(<<<'SQL'
            INSERT INTO geo.lluvia_series (codigo, zona, producto, inicio, fin, unidad, metodo, fuente, procedencia, geom)
            VALUES ('reporte-prueba', 'Piura', 'CHIRPS prueba', '2017-03-01', '2017-03-03', 'mm', 'Media espacial', 'https://example.test', '{}'::jsonb,
                ST_Multi(ST_GeomFromText('POLYGON((-81 -5,-80 -5,-80 -4,-81 -4,-81 -5))', 4326))) RETURNING id
            SQL)->id;
    }
}
