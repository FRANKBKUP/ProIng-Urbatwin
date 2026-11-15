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
}
