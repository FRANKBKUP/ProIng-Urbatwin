<?php

namespace App\Services;

use InvalidArgumentException;

class GeoJsonService
{
    // Cuántos niveles de arreglos tiene cada tipo hasta llegar a una posición [lon, lat]
    private const PROFUNDIDAD = [
        'Point' => 0, 'MultiPoint' => 1, 'LineString' => 1,
        'MultiLineString' => 2, 'Polygon' => 2, 'MultiPolygon' => 3,
    ];

    /** Acepta Feature, FeatureCollection (usa el primer elemento) o una geometría suelta. */
    public function extraer(string $texto): array
    {
        $dato = json_decode($texto, true);
        if (!is_array($dato)) {
            throw new InvalidArgumentException('El texto no es un JSON válido.');
        }

        $geom = match ($dato['type'] ?? null) {
            'FeatureCollection' => $dato['features'][0]['geometry'] ?? null,
            'Feature' => $dato['geometry'] ?? null,
            default => $dato,
        };

        if (!is_array($geom)) {
            throw new InvalidArgumentException('No se encontró ninguna geometría en el texto.');
        }

        return $geom;
    }

    /** Solo áreas: para escenarios. */
    public function poligono(string $texto): string
    {
        $geom = $this->extraer($texto);
        if (!in_array($geom['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            throw new InvalidArgumentException('Debe contener un Polygon o MultiPolygon (un área, no un punto ni una línea).');
        }

        return $this->geometria($geom);
    }

    /** Cualquier geometría soportada: para infraestructura. */
    public function cualquiera(string $texto): string
    {
        return $this->geometria($this->extraer($texto));
    }

    /** Valida una geometría ya decodificada y devuelve su JSON limpio. */
    public function geometria(?array $geom): string
    {
        $tipo = $geom['type'] ?? null;
        if (!is_string($tipo) || !isset(self::PROFUNDIDAD[$tipo])) {
            throw new InvalidArgumentException('Tipo de geometría no soportado o ausente.');
        }

        $this->revisar($geom['coordinates'] ?? null, self::PROFUNDIDAD[$tipo], $tipo);

        return json_encode(['type' => $tipo, 'coordinates' => $geom['coordinates']]);
    }

    private function revisar(mixed $nodo, int $nivel, string $tipo): void
    {
        if (!is_array($nodo) || $nodo === []) {
            throw new InvalidArgumentException('Las coordenadas están vacías o mal formadas.');
        }

        if ($nivel === 0) { // una posición [longitud, latitud]
            if (count($nodo) < 2 || !is_numeric($nodo[0]) || !is_numeric($nodo[1])) {
                throw new InvalidArgumentException('Hay una posición que no es [longitud, latitud].');
            }
            if (abs($nodo[0]) > 180 || abs($nodo[1]) > 90) {
                throw new InvalidArgumentException('Hay coordenadas fuera de rango (longitud ±180, latitud ±90).');
            }
            return;
        }

        if ($nivel === 1) { // lista de posiciones: un anillo o una línea
            $n = count($nodo);
            if (in_array($tipo, ['Polygon', 'MultiPolygon'], true) && ($n < 4 || $nodo[0] != $nodo[$n - 1])) {
                throw new InvalidArgumentException('Cada anillo debe tener al menos 4 puntos y terminar donde empieza.');
            }
            if (in_array($tipo, ['LineString', 'MultiLineString'], true) && $n < 2) {
                throw new InvalidArgumentException('Una línea necesita al menos 2 puntos.');
            }
        }

        foreach ($nodo as $hijo) {
            $this->revisar($hijo, $nivel - 1, $tipo);
        }
    }
}