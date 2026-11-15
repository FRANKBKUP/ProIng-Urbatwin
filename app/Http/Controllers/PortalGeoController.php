<?php

namespace App\Http\Controllers;

use App\Services\Catalogo\CapasPublicadas;
use App\Services\Territorio\AtlasTerritorial;
use App\Services\Territorio\EspaciosCiudadanos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortalGeoController extends Controller
{
    public function __construct(private CapasPublicadas $capas) {}

    public function index(Request $request, EspaciosCiudadanos $espacios, AtlasTerritorial $atlas): View
    {
        $grupos = $espacios->grupos($request->user()->id)->orderBy('g.nombre')->get()->keyBy('id');
        $grupo = $request->query('grupo');
        if ($grupo !== null) {
            abort_unless(is_string($grupo) && $grupos->has($grupo), 404);
        }
        $temas = $atlas->temas();
        $vista = $request->query('vista');
        if (! is_string($vista) || ($vista !== 'huella' && ! $temas->has($vista))) {
            $vista = $request->hasAny(['capa', 'lugar', 'grupo']) || $temas->isEmpty()
                ? 'huella'
                : $temas->keys()->first();
        }

        return view('geo.portal', [
            'capas' => $this->capas->ciudadanas()->orderByDesc('i.fecha_dato')->orderBy('c.id')->get(),
            'seleccion' => $request->integer('capa'),
            'grupos' => $grupos,
            'grupo' => $grupo,
            'lugar' => Str::isUuid($request->query('lugar')) ? $request->query('lugar') : '',
            'temasAtlas' => $temas,
            'departamentosAtlas' => $atlas->departamentos(),
            'provinciasAtlas' => $atlas->provincias(),
            'vistaAtlas' => $vista,
        ]);
    }

    public function capa(int $capa): JsonResponse
    {
        return response()->json($this->capas->mapa($capa))->header('Cache-Control', 'private, no-store');
    }

    public function consultar(Request $request, int $capa): JsonResponse
    {
        $datos = $request->validate([
            'version_id' => ['required', 'integer', 'min:1'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
        ]);

        try {
            $resultado = $this->capas->consultar(
                $capa, (int) $datos['version_id'], (float) $datos['longitud'], (float) $datos['latitud']
            );
        } catch (QueryException) {
            // Una excepción SQL contiene parámetros enlazados, aquí coordenadas privadas.
            abort(503, 'La consulta no está disponible en este momento.');
        }

        return response()->json($resultado)->header('Cache-Control', 'no-store');
    }
}
