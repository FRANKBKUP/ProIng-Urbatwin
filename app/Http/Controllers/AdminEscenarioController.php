<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEscenarioRequest;
use App\Services\GeoJsonService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminEscenarioController extends Controller
{
    public function __construct(private GeoJsonService $geo)
    {
    }

    public function index()
    {
        $escenarios = DB::select(
            'SELECT e.id, e.nombre, e.nivel, e.origen, e.activo, z.nombre AS zona,
                    (SELECT COUNT(*) FROM reportes r WHERE r.escenario_id = e.id) AS reportes
             FROM escenarios e JOIN zonas z ON z.id = e.zona_id
             ORDER BY e.id'
        );
        $zonas = DB::table('zonas')->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.escenarios', compact('escenarios', 'zonas'));
    }

    public function store(StoreEscenarioRequest $request)
    {
        $d = $request->validated();

        try {
            $geom = $this->geo->poligono($d['geojson']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['geojson' => $e->getMessage()])->withInput();
        }

        try {
            // Que el polígono caiga dentro de la zona detecta latitud/longitud invertidas
            $cruza = DB::selectOne(
                'SELECT ST_Intersects(ST_GeomFromGeoJSON(?), limite) AS ok FROM zonas WHERE id = ?',
                [$geom, $d['zona_id']]
            );
            if (!$cruza || !$cruza->ok) {
                return back()->withErrors(['geojson' =>
                    'El polígono no intersecta la zona elegida. ¿Invertiste latitud y longitud?'
                ])->withInput();
            }

            DB::insert(
                'INSERT INTO escenarios (zona_id, creado_por, nombre, nivel, origen, area_inundada)
                 VALUES (?, ?, ?, ?, ?, ST_GeomFromGeoJSON(?))',
                [$d['zona_id'], $request->user()->id, $d['nombre'], $d['nivel'], $d['origen'], $geom]
            );
        } catch (QueryException) {
            return back()->withErrors(['geojson' =>
                'MariaDB no pudo interpretar la geometría. Revisa que el polígono sea válido.'
            ])->withInput();
        }

        return redirect()->route('admin.escenarios.index')->with('ok', 'Escenario creado.');
    }

    public function estado(int $id)
    {
        abort_if(DB::update('UPDATE escenarios SET activo = 1 - activo WHERE id = ?', [$id]) === 0, 404);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function destroy(int $id)
    {
        $tieneReportes = DB::table('reportes')->where('escenario_id', $id)->exists();
        if ($tieneReportes) { // RN08
            return back()->withErrors(['escenario' =>
                'Este escenario tiene reportes asociados: no se elimina, solo se desactiva.'
            ]);
        }

        abort_if(DB::delete('DELETE FROM escenarios WHERE id = ?', [$id]) === 0, 404);

        return back()->with('ok', 'Escenario eliminado.');
    }
}