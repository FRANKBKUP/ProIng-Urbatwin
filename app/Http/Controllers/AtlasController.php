<?php

namespace App\Http\Controllers;

use App\Services\Territorio\AtlasTerritorial;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class AtlasController extends Controller
{
    public function __construct(private AtlasTerritorial $atlas) {}

    public function limites(): JsonResponse
    {
        return response()->json(Cache::remember('atlas:limites:v1', now()->addMinutes(15), fn () => $this->atlas->limites()));
    }

    public function tesela(string $tema, int $z, int $x, int $y): Response
    {
        return response($this->atlas->tesela($tema, $z, $x, $y), 200, [
            'Content-Type' => 'application/vnd.mapbox-vector-tile',
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    public function provincia(string $provincia): JsonResponse
    {
        return response()->json($this->atlas->provincia($provincia));
    }

    public function equipo(): JsonResponse
    {
        return response()->json(['personas' => $this->atlas->equipo()])->header('Cache-Control', 'private, no-store');
    }

    public function consultar(Request $request, string $tema): JsonResponse
    {
        $datos = $request->validate([
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
        ]);
        try {
            $zona = $this->atlas->consultar($tema, (float) $datos['longitud'], (float) $datos['latitud']);
        } catch (QueryException) {
            abort(503, 'La consulta territorial no está disponible en este momento.');
        }

        return response()->json(['zona' => $zona])->header('Cache-Control', 'private, no-store');
    }
}
