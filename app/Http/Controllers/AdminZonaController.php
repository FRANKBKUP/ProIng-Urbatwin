<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZonaRequest;
use App\Services\GeoJsonService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminZonaController extends Controller
{
    public function __construct(private GeoJsonService $geo)
    {
    }

    public function index()
    {
        $zonas = DB::select(
            'SELECT z.id, z.nombre, z.descripcion,
                    (SELECT COUNT(*) FROM infraestructuras i WHERE i.zona_id = z.id) AS infra,
                    (SELECT COUNT(*) FROM escenarios e WHERE e.zona_id = z.id) AS escenarios
             FROM zonas z ORDER BY z.id'
        );

        return view('admin.zonas', compact('zonas'));
    }

    public function store(ZonaRequest $request)
    {
        $d = $request->validated();

        try {
            $geom = $this->geo->poligono($d['geojson']);
            DB::insert(
                'INSERT INTO zonas (nombre, descripcion, limite) VALUES (?, ?, ST_GeomFromGeoJSON(?))',
                [$d['nombre'], $d['descripcion'] ?? null, $geom]
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['geojson' => $e->getMessage()])->withInput();
        } catch (QueryException) {
            return back()->withErrors(['geojson' =>
                'MariaDB no pudo interpretar la geometría. Revisa que el polígono sea válido.'
            ])->withInput();
        }

        return redirect()->route('admin.zonas.index')->with('ok', 'Zona creada.');
    }

    public function editar(int $id)
    {
        $zona = DB::table('zonas')->where('id', $id)->first(['id', 'nombre', 'descripcion']);
        abort_if(!$zona, 404, 'Zona no encontrada');

        return view('admin.zonas_editar', compact('zona'));
    }

    public function update(ZonaRequest $request, int $id)
    {
        abort_if(!DB::table('zonas')->where('id', $id)->exists(), 404, 'Zona no encontrada');
        $d = $request->validated();

        try {
            $geom = filled($d['geojson'] ?? null) ? $this->geo->poligono($d['geojson']) : null;

            DB::transaction(function () use ($id, $d, $geom) {
                DB::update('UPDATE zonas SET nombre = ?, descripcion = ? WHERE id = ?',
                    [$d['nombre'], $d['descripcion'] ?? null, $id]);

                if ($geom !== null) {
                    DB::update('UPDATE zonas SET limite = ST_GeomFromGeoJSON(?) WHERE id = ?', [$geom, $id]);
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['geojson' => $e->getMessage()])->withInput();
        } catch (QueryException) {
            return back()->withErrors(['geojson' =>
                'MariaDB no pudo interpretar la geometría. Revisa que el polígono sea válido.'
            ])->withInput();
        }

        return redirect()->route('admin.zonas.index')->with('ok', 'Zona actualizada.');
    }

    public function destroy(int $id)
    {
        $infra = DB::table('infraestructuras')->where('zona_id', $id)->count();
        $esc = DB::table('escenarios')->where('zona_id', $id)->count();

        if ($infra > 0 || $esc > 0) {
            return back()->withErrors(['zona' =>
                "La zona tiene $infra infraestructuras y $esc escenarios: elimínalos primero."
            ]);
        }

        abort_if(DB::delete('DELETE FROM zonas WHERE id = ?', [$id]) === 0, 404);

        return back()->with('ok', 'Zona eliminada.');
    }
}