<?php

namespace App\Http\Controllers;

use App\Services\Catalogo\CapasPublicadas;
use App\Services\Territorio\AtlasTerritorial;
use App\Services\Territorio\EspaciosCiudadanos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TerritorioController extends Controller
{
    public function __invoke(Request $request, CapasPublicadas $capas, EspaciosCiudadanos $espacios, AtlasTerritorial $atlas): View
    {
        $usuario = $request->user()->id;
        $observadas = $capas->ciudadanas()->where('f.origen', 'observado')
            ->orderByDesc('i.fecha_dato')->orderBy('c.id');
        $registros = (clone $observadas)->addSelect([
            'km2' => DB::table('geo.peligro as p')->whereColumn('p.version_id', 'v.id')
                ->selectRaw('ST_Area(ST_Union(p.geom)::geography) / 1000000'),
        ])->selectRaw('ST_Area(g.geom::geography) / 1000000 AS cobertura_km2')->simplePaginate(10);

        return view('geo.dashboard', [
            'capas' => $registros,
            'total' => (clone $observadas)->reorder()->count(),
            'lugares' => DB::table('app.lugares')->where('usuario_id', $usuario)->count(),
            'grupos' => $espacios->grupos($usuario)->count(),
            'resumenAtlas' => $atlas->resumen($atlas->temas()),
            'departamentosAtlas' => $atlas->departamentos(),
        ]);
    }
}
