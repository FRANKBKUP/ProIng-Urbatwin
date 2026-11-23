<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCaseConBD;

class ImportarLluviaPiuraTest extends TestCaseConBD
{
    public function test_el_lote_omite_los_meses_ya_importados(): void
    {
        $directorio = $this->prepararSerie();
        $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->assertSuccessful();
        Process::fake();

        $this->artisan('territorio:importar-lluvia-rango', ['desde' => '2017-03', 'hasta' => '2017-03', '--limite' => $directorio.'/originales/departamentos.geojson'])->assertSuccessful();

        Process::assertNothingRan();
        $estado = json_decode(Storage::disk('local')->get('lluvia-piura-lotes/estado.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('ya_importado', $estado['meses']['2017-03']['estado']);
    }

    public function test_el_lote_registra_el_error_y_deja_los_meses_restantes_pendientes(): void
    {
        $directorio = $this->prepararSerie();
        Process::fake(['*' => Process::result(errorOutput: 'Fuente no disponible', exitCode: 1)]);

        $this->artisan('territorio:importar-lluvia-rango', ['desde' => '2017-01', 'hasta' => '2017-02', '--limite' => $directorio.'/originales/departamentos.geojson'])->assertFailed();

        $estado = json_decode(Storage::disk('local')->get('lluvia-piura-lotes/estado.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('error', $estado['meses']['2017-01']['estado']);
        $this->assertSame('pendiente', $estado['meses']['2017-02']['estado']);
        $this->assertDatabaseMissing('geo.lluvia_series', ['codigo' => 'piura-chirps-v3-rnl-2017-01']);
    }

    public function test_importa_un_mes_bisiesto_con_todos_sus_dias(): void
    {
        $directorio = $this->prepararSerie('2020-02');

        $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->assertSuccessful();

        $serie = DB::table('geo.lluvia_series')->where('codigo', 'piura-chirps-v3-rnl-2020-02')->first();
        $this->assertNotNull($serie);
        $this->assertSame(29, DB::table('geo.lluvia_diaria')->where('serie_id', $serie->id)->count());
        $this->assertDatabaseHas('geo.lluvia_diaria', ['serie_id' => $serie->id, 'fecha' => '2020-02-29']);
    }

    public function test_importa_la_serie_y_conserva_la_procedencia(): void
    {
        $directorio = $this->prepararSerie();

        $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->assertSuccessful();

        $serie = DB::table('geo.lluvia_series')->where('codigo', 'piura-chirps-v3-rnl-2017-03')->first();
        $this->assertNotNull($serie);
        $this->assertSame(31, DB::table('geo.lluvia_diaria')->where('serie_id', $serie->id)->count());
        $this->assertDatabaseHas('geo.lluvia_diaria', ['serie_id' => $serie->id, 'fecha' => '2017-03-01', 'precipitacion_mm' => 1]);
        $this->assertDatabaseHas('geo.departamentos', ['codigo' => 'PE-PIU']);
    }

    public function test_rechaza_un_archivo_modificado_sin_guardar_la_serie(): void
    {
        $directorio = $this->prepararSerie();
        file_put_contents($directorio.'/originales/chirps-2017-03-01.tif', 'alterado');

        try {
            $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->run();
            $this->fail('La importación debió rechazar el archivo modificado.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('no son válidos', $error->getMessage());
        }

        $this->assertDatabaseMissing('geo.lluvia_series', ['codigo' => 'piura-chirps-v3-rnl-2017-03']);
    }

    public function test_rechaza_un_mes_incompleto(): void
    {
        $directorio = $this->prepararSerie('2020-02');
        $ruta = $directorio.'/manifiesto.json';
        $manifiesto = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        array_pop($manifiesto['archivos']);
        file_put_contents($ruta, json_encode($manifiesto, JSON_THROW_ON_ERROR));

        try {
            $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->run();
            $this->fail('La importación debió rechazar el mes incompleto.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('no corresponden', $error->getMessage());
        }

        $this->assertDatabaseMissing('geo.lluvia_series', ['codigo' => 'piura-chirps-v3-rnl-2020-02']);
    }

    public function test_no_sobrescribe_una_serie_ya_importada(): void
    {
        $directorio = $this->prepararSerie('2020-02');
        $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->assertSuccessful();

        try {
            $this->artisan('territorio:importar-lluvia-piura', ['directorio' => $directorio])->run();
            $this->fail('La importación debió rechazar una serie duplicada.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('ya está importada', $error->getMessage());
        }

        $this->assertSame(1, DB::table('geo.lluvia_series')->where('codigo', 'piura-chirps-v3-rnl-2020-02')->count());
    }

    private function prepararSerie(string $mes = '2017-03'): string
    {
        Storage::fake('local');
        $disco = Storage::disk('local');
        $disco->put('lluvia/originales/departamentos.geojson', 'limite original');
        $disco->put('lluvia/piura.geojson', json_encode(['type' => 'Polygon', 'coordinates' => [[[-81, -5], [-80, -5], [-80, -4], [-81, -4], [-81, -5]]]], JSON_THROW_ON_ERROR));
        $filas = [];
        $inicio = CarbonImmutable::parse($mes.'-01');
        for ($dia = 1; $dia <= $inicio->daysInMonth; $dia++) {
            $fecha = $inicio->day($dia)->format('Y-m-d');
            $archivo = 'chirps-'.$fecha.'.tif';
            $disco->put('lluvia/originales/'.$archivo, 'datos '.$fecha);
            $filas[] = ['fecha' => $fecha, 'archivo' => $archivo, 'precipitacion_mm' => $dia, 'cobertura' => 1, 'sha256' => hash('sha256', 'datos '.$fecha)];
        }
        $manifiesto = ['zona' => 'PE-PIU', 'producto' => 'CHIRPS v3.0 final RNL', 'inicio' => $inicio->format('Y-m-d'), 'fin' => $inicio->endOfMonth()->format('Y-m-d'), 'metodo' => 'Prueba', 'fuente' => 'Prueba', 'limite_sha256' => hash('sha256', 'limite original'), 'archivos' => $filas];
        $disco->put('lluvia/manifiesto.json', json_encode($manifiesto, JSON_THROW_ON_ERROR));

        return $disco->path('lluvia');
    }
}
