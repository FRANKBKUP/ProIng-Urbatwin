<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfraestructuraRequest;
use App\Services\GeoJsonService;
use App\Services\InfraestructuraImportService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminInfraestructuraController extends Controller
{
    public function __construct(
        private GeoJsonService $geo,
        private InfraestructuraImportService $importador,
    ) {
    }

    public function index(Request $request)
    {
        $zonaId = $request->integer('zona_id');
        $tipoId = $request->integer('tipo_id');

        $infra = DB::table('infraestructuras as i')
            ->join('tipos_infraestructura as t', 't.id', '=', 'i.tipo_id')
            ->join('zonas as z', 'z.id', '=', 'i.zona_id')
            ->selectRaw('i.id, i.nombre, i.activo, t.nombre AS tipo, z.nombre AS zona,
                         ST_GeometryType(i.geometria) AS geom,
                         (SELECT COUNT(*) FROM reporte_detalles d WHERE d.infraestructura_id = i.id) AS en_reportes')
            ->when($zonaId, fn ($q) => $q->where('i.zona_id', $zonaId))
            ->when($tipoId, fn ($q) => $q->where('i.tipo_id', $tipoId))
            ->orderBy('i.id')
            ->simplePaginate(15)
            ->withQueryString();

        return view('admin.infraestructuras', [
            'infra' => $infra,
            'zonas' => DB::table('zonas')->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => DB::table('tipos_infraestructura')->orderBy('id')->get(['id', 'nombre']),
            'zonaId' => $zonaId,
            'tipoId' => $tipoId,
        ]);
    }

    public function store(StoreInfraestructuraRequest $request)
    {
        $d = $request->validated();

        try {
            $geom = $this->geo->cualquiera($d['geojson']);
            $dentro = DB::selectOne(
                'SELECT ST_Intersects(ST_GeomFromGeoJSON(?), limite) AS ok FROM zonas WHERE id = ?',
                [$geom, $d['zona_id']]
            );
            if (!$dentro || !$dentro->ok) {
                return back()->withErrors(['geojson' =>
                    'La ubicación queda fuera de la zona elegida. ¿Invertiste latitud y longitud?'
                ])->withInput();
            }

            DB::insert(
                'INSERT INTO infraestructuras (zona_id, tipo_id, nombre, geometria)
                 VALUES (?, ?, ?, ST_GeomFromGeoJSON(?))',
                [$d['zona_id'], $d['tipo_id'], $d['nombre'], $geom]
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['geojson' => $e->getMessage()])->withInput();
        } catch (QueryException) {
            return back()->withErrors(['geojson' =>
                'MariaDB no pudo interpretar la geometría. Revisa el GeoJSON.'
            ])->withInput();
        }

        return back()->with('ok', 'Infraestructura creada.');
    }

    public function importar(Request $request)
    {
        $d = $request->validate([
            'archivo' => ['required', 'file', 'max:5120'], // 5 MB
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'tipo_id' => ['required', 'integer', 'exists:tipos_infraestructura,id'],
        ], [
            'archivo.required' => 'Selecciona un archivo .geojson.',
            'archivo.max'      => 'El archivo no puede pasar de 5 MB.',
        ]);

        try {
            $r = $this->importador->importar(
                file_get_contents($request->file('archivo')->getRealPath()),
                (int) $d['zona_id'],
                (int) $d['tipo_id']
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        return back()
            ->with('ok', "Importación terminada: {$r['insertados']} insertados, {$r['omitidos']} omitidos.")
            ->with('motivos', $r['motivos']);
    }

    public function estado(int $id)
    {
        abort_if(DB::update('UPDATE infraestructuras SET activo = 1 - activo WHERE id = ?', [$id]) === 0, 404);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function destroy(int $id)
    {
        // reporte_detalles referencia a la infraestructura: si aparece en reportes, solo se desactiva
        if (DB::table('reporte_detalles')->where('infraestructura_id', $id)->exists()) {
            return back()->withErrors(['infraestructura' =>
                'Esta infraestructura aparece en reportes guardados: no se elimina, solo se desactiva.'
            ]);
        }

        abort_if(DB::delete('DELETE FROM infraestructuras WHERE id = ?', [$id]) === 0, 404);

        return back()->with('ok', 'Infraestructura eliminada.');
    }
}