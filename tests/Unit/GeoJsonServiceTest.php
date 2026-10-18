<?php

namespace Tests\Unit;

use App\Services\GeoJsonService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeoJsonServiceTest extends TestCase
{
    public static function poligonosInvalidos(): array
    {
        return [
            'texto que no es JSON'         => ['esto no es json'],
            'es un punto, no un área'      => ['{"type":"Point","coordinates":[1,1]}'],
            'anillo sin cerrar'            => ['{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1]]]}'],
            'anillo con menos de 4 puntos' => ['{"type":"Polygon","coordinates":[[[0,0],[1,1],[0,0]]]}'],
            'longitud fuera de rango'      => ['{"type":"Polygon","coordinates":[[[200,0],[201,0],[201,1],[200,0]]]}'],
            'coordenadas vacías'           => ['{"type":"Polygon","coordinates":[]}'],
            'posición con un solo número'  => ['{"type":"Polygon","coordinates":[[[0],[1,0],[1,1],[0]]]}'],
        ];
    }

    #[DataProvider('poligonosInvalidos')]
    public function test_poligono_rechaza_entradas_invalidas(string $texto): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeoJsonService())->poligono($texto);
    }

    public function test_poligono_acepta_un_feature_valido(): void
    {
        $texto = '{"type":"Feature","properties":{},"geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}}';

        $json = (new GeoJsonService())->poligono($texto);

        $this->assertSame('Polygon', json_decode($json, true)['type']);
    }

    public function test_poligono_acepta_un_multipoligono(): void
    {
        $texto = '{"type":"MultiPolygon","coordinates":[[[[0,0],[1,0],[1,1],[0,0]]]]}';

        $json = (new GeoJsonService())->poligono($texto);

        $this->assertSame('MultiPolygon', json_decode($json, true)['type']);
    }

    public function test_un_featurecollection_usa_su_primer_elemento(): void
    {
        $texto = '{"type":"FeatureCollection","features":['
            . '{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}}]}';

        $json = (new GeoJsonService())->poligono($texto);

        $this->assertSame('Polygon', json_decode($json, true)['type']);
    }

    public function test_cualquiera_acepta_puntos_y_lineas(): void
    {
        $servicio = new GeoJsonService();

        $punto = json_decode($servicio->cualquiera('{"type":"Point","coordinates":[-81.27,-4.58]}'), true);
        $linea = json_decode($servicio->cualquiera('{"type":"LineString","coordinates":[[0,0],[1,1]]}'), true);

        $this->assertSame('Point', $punto['type']);
        $this->assertSame('LineString', $linea['type']);
    }

    public function test_cualquiera_rechaza_una_linea_de_un_solo_punto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeoJsonService())->cualquiera('{"type":"LineString","coordinates":[[0,0]]}');
    }

    public function test_cualquiera_rechaza_tipos_no_soportados(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeoJsonService())->cualquiera('{"type":"GeometryCollection","geometries":[]}');
    }
}