<?php

namespace App\Services;

use InvalidArgumentException;

class GeoJsonService
{
    /**
     * Recibe el texto pegado por el usuario (Feature, FeatureCollection o geometría)
     * y devuelve el JSON de un Polygon/MultiPolygon listo para MariaDB.
     */
    public function poligono(string $texto): string
    {
        $dato = json_decode($texto, true);
        if (!is_array($dato)) {
            throw new InvalidArgumentException('El texto no es un JSON válido.');
        }

        $geom = match ($dato['type'] ?? null) {
            'FeatureCollection' => $dato['features'][0]['geometry'] ?? null, // se usa el primer elemento
            'Feature' => $dato['geometry'] ?? null,
            default => $dato,
        };

        $tipo = is_array($geom) ? ($geom['type'] ?? null) : null;
        if (!in_array($tipo, ['Polygon', 'MultiPolygon'], true)) {
            throw new InvalidArgumentException('Debe contener un Polygon o MultiPolygon (un área, no un punto ni una línea).');
        }

        // Polygon: anillos > posiciones; MultiPolygon: un nivel más
        $this->revisar($geom['coordinates'] ?? null, $tipo === 'Polygon' ? 2 : 3);

        return json_encode(['type' => $tipo, 'coordinates' => $geom['coordinates']]);
    }

    private function revisar(mixed $nodo, int $nivel): void
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

        if ($nivel === 1) { // un anillo: mínimo 4 posiciones y cerrado
            $n = count($nodo);
            if ($n < 4 || $nodo[0] !== $nodo[$n - 1]) {
                throw new InvalidArgumentException('Cada anillo debe tener al menos 4 puntos y terminar donde empieza.');
            }
        }

        foreach ($nodo as $hijo) {
            $this->revisar($hijo, $nivel - 1);
        }
    }
}