<?php

namespace App\Http\Controllers;

use App\Services\Territorio\PronosticoRegional;
use Illuminate\Http\JsonResponse;

class PronosticoController extends Controller
{
    public function __invoke(string $departamento, PronosticoRegional $pronostico): JsonResponse
    {
        return response()->json($pronostico->consultar($departamento));
    }

    public function lluvia(string $producto, string $codigo, PronosticoRegional $pronostico): JsonResponse
    {
        $resultado = match ($producto) {
            'mapa' => $pronostico->mapa($codigo),
            'historico' => $pronostico->historico($codigo),
            'pronostico' => $pronostico->pronostico($codigo),
        };

        return response()->json($resultado);
    }
}
