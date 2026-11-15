<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SuperUsuariosController extends Controller
{
    public function index(Request $request): View
    {
        $plazas = DB::table('app.superusuarios')->orderBy('plaza')->get(['plaza', 'nombre', 'activado_en', 'vence_en']);

        return view('admin.superusuarios', [
            'plazas' => $plazas,
            'puedeInvitar' => $request->user()->tieneRol('Superusuario'),
            'inicioDisponible' => $plazas->first()->activado_en === null,
        ]);
    }

    public function invitar(Request $request, int $plaza): Response
    {
        $datos = $request->validate(['correo' => ['required', 'email', 'max:120']]);
        $correo = Str::lower($datos['correo']);
        $codigo = Str::random(48);
        try {
            $nombre = DB::transaction(function () use ($request, $plaza, $correo, $codigo) {
                $plazas = DB::table('app.superusuarios');
                $primera = (clone $plazas)->where('plaza', 1)->lockForUpdate()->first();
                abort_unless($request->user()->tieneRol('Superusuario') || ($plaza === 1 && $primera->usuario_id === null), 403);
                $destino = $plaza === 1 ? $primera : (clone $plazas)->where('plaza', $plaza)->lockForUpdate()->first();
                abort_if($destino === null, 404);
                if ($destino->usuario_id !== null) {
                    throw ValidationException::withMessages(['correo' => 'La plaza ya está activada.']);
                }
                $plazas->where('plaza', $plaza)->update([
                    'correo' => $correo,
                    'hash' => hash('sha256', $codigo),
                    'vence_en' => now()->addDay(),
                ]);
                $this->auditar($request->user()->id, $plaza, 'invitar_superusuario');

                return $destino->nombre;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['correo' => 'Ese correo ya está asociado a otra plaza.']);
        }

        return response()->view('admin.invitacion-superusuario', compact('nombre', 'correo', 'codigo'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function activar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['codigo_superusuario' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{48}\z/']], [
            'codigo_superusuario.regex' => 'Ingresa el código de invitación completo.',
        ]);
        DB::transaction(function () use ($request, $datos) {
            $plazas = DB::table('app.superusuarios');
            $plaza = (clone $plazas)->where('hash', hash('sha256', $datos['codigo_superusuario']))
                ->whereNull('usuario_id')->where('vence_en', '>', now())->lockForUpdate()->first();
            $usuario = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! $plaza || ! $usuario->activo || $plaza->correo !== Str::lower($usuario->email)
                || $usuario->tieneRol('Superusuario')) {
                throw ValidationException::withMessages(['codigo_superusuario' => 'El código no está disponible para esta cuenta.']);
            }

            $rol = Rol::where('nombre', 'Superusuario')->firstOrFail();
            $usuario->update(['rol_id' => $rol->id]);
            $plazas->where('plaza', $plaza->plaza)->update([
                'usuario_id' => $usuario->id, 'activado_en' => now(), 'hash' => null, 'vence_en' => null,
            ]);
            $this->auditar($usuario->id, $plaza->plaza, 'activar_superusuario');
        });
        $request->session()->regenerate();

        return back()->with('ok', 'Acceso de superusuario activado.');
    }

    private function auditar(int $usuario, int $plaza, string $accion): void
    {
        DB::table('app.auditoria')->insert(['usuario_id' => $usuario, 'accion' => $accion, 'objeto' => 'superusuario/'.$plaza]);
    }
}
