<?php

namespace Tests\Feature;

use App\Services\InfraestructuraImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCaseConBD;

class ImportacionTest extends TestCaseConBD
{
    private int $zona;

    // Un elemento válido, uno fuera de la zona (0..10) y uno mal formado
    private const ARCHIVO = '{"type":"FeatureCollection","features":['
        . '{"type":"Feature","properties":{"name":"Importado OK"},"geometry":{"type":"Point","coordinates":[5,5]}},'
        . '{"type":"Feature","properties":{"name":"Fuera de zona"},"geometry":{"type":"Point","coordinates":[50,50]}},'
        . '{"type":"Feature","properties":{"name":"Mal formado"},"geometry":{"type":"Point","coordinates":[5]}}'
        . ']}';

    protected function setUp(): void
    {
        parent::setUp();
        $this->zona = $this->zona();
    }

    private function tipoColegio(): int
    {
        return (int) DB::table('tipos_infraestructura')->where('nombre', 'Colegio')->value('id');
    }

    public function test_el_servicio_importa_lo_valido_y_reporta_lo_demas(): void
    {
        $r = app(InfraestructuraImportService::class)->importar(self::ARCHIVO, $this->zona, $this->tipoColegio());

        $this->assertSame(1, $r['insertados']);
        $this->assertSame(2, $r['omitidos']);
        $this->assertCount(2, $r['motivos']);
        $this->assertDatabaseHas('infraestructuras', ['nombre' => 'Importado OK', 'zona_id' => $this->zona]);
        $this->assertDatabaseMissing('infraestructuras', ['nombre' => 'Fuera de zona']);
    }

    public function test_rechaza_un_archivo_que_no_es_featurecollection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(InfraestructuraImportService::class)
            ->importar('{"type":"Point","coordinates":[1,1]}', $this->zona, $this->tipoColegio());
    }

    public function test_el_comando_de_consola_importa(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'geo');
        file_put_contents($ruta, self::ARCHIVO);

        try {
            $this->artisan('infra:importar', ['archivo' => $ruta, '--zona' => $this->zona, '--tipo' => 'Colegio'])
                ->expectsOutputToContain('Insertados: 1')
                ->assertExitCode(0);
        } finally {
            unlink($ruta);
        }

        $this->assertDatabaseHas('infraestructuras', ['nombre' => 'Importado OK']);
    }

    public function test_el_administrador_importa_desde_la_pantalla(): void
    {
        $this->actingAs($this->admin());
        $archivo = UploadedFile::fake()->createWithContent('infra.geojson', self::ARCHIVO);

        $this->post('/admin/infraestructuras/importar', [
            'archivo' => $archivo, 'zona_id' => $this->zona, 'tipo_id' => $this->tipoColegio(),
        ])->assertSessionHas('ok')->assertSessionHas('motivos');

        $this->assertDatabaseHas('infraestructuras', ['nombre' => 'Importado OK']);
    }
}