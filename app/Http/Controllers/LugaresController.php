<?php

namespace App\Http\Controllers;

use App\Services\Territorio\EspaciosCiudadanos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LugaresController extends Controller
{
    public function __construct(private EspaciosCiudadanos $espacios) {}

    public function index(Request $request): View
    {
        $usuario = $request->user()->id;

        return view('geo.lugares', [
            'lugares' => $this->espacios->lugares($usuario)->simplePaginate(20),
            'grupos' => $this->espacios->grupos($usuario)->orderBy('g.nombre')->get()->keyBy('id'),
            'miembros' => $this->espacios->miembros($usuario),
        ]);
    }

    public function mapa(Request $request): JsonResponse
    {
        $datos = $request->validate(['grupo' => ['nullable', 'uuid']]);
        $puntos = $this->espacios->lugares($request->user()->id, $datos['grupo'] ?? null)->limit(500)->get();

        return response()->json(['lugares' => $puntos])->header('Cache-Control', 'private, no-store');
    }

    public function guardar(Request $request): JsonResponse
    {
        $datos = $this->validarLugar($request);
        $id = (string) Str::uuid();
        try {
            DB::insert('INSERT INTO app.lugares (id, usuario_id, nombre, geom)
                VALUES (?, ?, ?, ST_SetSRID(ST_MakePoint(?::float8, ?::float8), 4326))',
                [$id, $request->user()->id, $datos['nombre'], $datos['longitud'], $datos['latitud']]);
        } catch (QueryException) {
            abort(503, 'El lugar no se pudo guardar en este momento.');
        }

        return response()->json(['id' => $id], 201)->header('Cache-Control', 'private, no-store');
    }

    public function editar(Request $request, string $lugar): RedirectResponse
    {
        $datos = $this->validarLugar($request);
        try {
            $filas = DB::update('UPDATE app.lugares SET nombre = ?, geom = ST_SetSRID(ST_MakePoint(?::float8, ?::float8), 4326)
                WHERE id = ? AND usuario_id = ?', [$datos['nombre'], $datos['longitud'], $datos['latitud'], $lugar, $request->user()->id]);
        } catch (QueryException) {
            abort(503, 'El lugar no se pudo actualizar en este momento.');
        }
        abort_if($filas === 0, 404);

        return back()->with('ok', 'Lugar actualizado. Se conservó su visibilidad.');
    }

    public function compartir(Request $request, string $lugar): RedirectResponse
    {
        $datos = $request->validate(['grupo_id' => ['present', 'nullable', 'uuid']]);
        $usuario = $request->user()->id;
        $grupo = $datos['grupo_id'];

        DB::transaction(function () use ($grupo, $usuario, $lugar) {
            if ($grupo !== null) {
                $membresia = DB::table('app.grupo_miembros')->where('grupo_id', $grupo)
                    ->where('usuario_id', $usuario)->sharedLock()->first();
                abort_if($membresia === null, 404);
            }
            $filas = DB::table('app.lugares')->where('id', $lugar)->where('usuario_id', $usuario)
                ->update(['grupo_id' => $grupo]);
            abort_if($filas === 0, 404);
        });

        return back()->with('ok', $grupo === null ? 'El lugar ahora es privado.' : 'Lugar compartido con el grupo.');
    }

    public function eliminar(Request $request, string $lugar): RedirectResponse
    {
        $filas = DB::table('app.lugares')->where('id', $lugar)->where('usuario_id', $request->user()->id)->delete();
        abort_if($filas === 0, 404);

        return back()->with('ok', 'Lugar eliminado.');
    }

    private function validarLugar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
        ]);
    }
}
