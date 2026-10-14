<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    private const CONSULTA_REPORTE = 'SELECT r.id, r.total_expuestas, r.generado_en,
            e.nombre AS escenario, e.nivel, e.origen, u.nombre AS usuario
        FROM reportes r
        JOIN escenarios e ON e.id = r.escenario_id
        JOIN usuarios u ON u.id = r.usuario_id';

    public function guardar(Request $request, int $id)
    {
        abort_if(
            !DB::table('escenarios')->where('id', $id)->where('activo', 1)->exists(),
            404, 'Escenario no encontrado'
        );

        // El servidor repite el cruce espacial: no confía en lo que envíe el navegador.
        $filas = DB::select(
            'SELECT i.id, i.nombre, t.nombre AS tipo
             FROM infraestructuras i
             JOIN tipos_infraestructura t ON t.id = i.tipo_id
             JOIN escenarios e ON e.zona_id = i.zona_id
             WHERE e.id = ? AND i.activo = 1
               AND ST_Intersects(i.geometria, e.area_inundada) = 1',
            [$id]
        );

        if (count($filas) === 0) {
            return response()->json(
                ['message' => 'No hay infraestructura expuesta; no se genera reporte.'], 422
            );
        }

        $reporteId = DB::transaction(function () use ($id, $request, $filas) {
            $rid = DB::table('reportes')->insertGetId([
                'escenario_id' => $id,
                'usuario_id' => $request->user()->id,
                'total_expuestas' => count($filas),
            ]);

            DB::table('reporte_detalles')->insert(array_map(fn ($f) => [
                'reporte_id' => $rid,
                'infraestructura_id' => $f->id,
                'nombre_infra' => $f->nombre,
                'tipo_infra' => $f->tipo,
            ], $filas));

            return $rid;
        });

        return response()->json(['id' => $reporteId, 'url' => url("/reportes/$reporteId")], 201);
    }

    public function index()
    {
        $reportes = DB::select(self::CONSULTA_REPORTE . ' ORDER BY r.id DESC');
        return view('reportes.index', compact('reportes'));
    }

    public function mostrar(int $id)
    {
        $r = DB::selectOne(self::CONSULTA_REPORTE . ' WHERE r.id = ?', [$id]);
        abort_if(!$r, 404, 'Reporte no encontrado');

        $detalles = DB::table('reporte_detalles')
            ->where('reporte_id', $id)
            ->orderBy('tipo_infra')->orderBy('nombre_infra')
            ->get();

        return view('reportes.mostrar', [
            'r' => $r,
            'detalles' => $detalles,
            'porTipo' => $detalles->countBy('tipo_infra'),
        ]);
    }
}