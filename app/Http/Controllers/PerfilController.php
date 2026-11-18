<?php

namespace App\Http\Controllers;

use App\Services\Territorio\AtlasTerritorial;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerfilController extends Controller
{
    public function foto(Request $request): StreamedResponse
    {
        $ruta = DB::table('app.perfiles')->where('usuario_id', $request->user()->id)->value('foto');
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function guardarFoto(Request $request): RedirectResponse
    {
        $datos = $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $disco = Storage::disk('local');
        $anterior = DB::table('app.perfiles')->where('usuario_id', $request->user()->id)->value('foto');
        $ruta = $datos['foto']->store('fotos-perfil', 'local');
        abort_unless($ruta, 500);

        try {
            DB::table('app.perfiles')->updateOrInsert(['usuario_id' => $request->user()->id], ['foto' => $ruta, 'actualizado_en' => now()]);
        } catch (\Throwable $error) {
            $disco->delete($ruta);
            throw $error;
        }
        if ($anterior) {
            $disco->delete($anterior);
        }

        return back()->with('ok', 'Foto de perfil actualizada.');
    }

    public function mostrar(Request $request, AtlasTerritorial $atlas): View
    {
        return view('geo.perfil', [
            'perfil' => DB::table('app.perfiles')->where('usuario_id', $request->user()->id)->first(),
            'departamentos' => $atlas->departamentos(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'ocupacion' => ['nullable', 'string', 'max:90'],
            'descripcion' => ['nullable', 'string', 'max:400'],
            'departamento' => ['nullable', Rule::requiredIf($request->boolean('visible_en_mapa')), 'string', 'max:16', Rule::exists('pgsql.geo.departamentos', 'codigo')],
            'avatar' => ['required', Rule::in(['azul', 'verde', 'uva', 'coral', 'oro'])],
            'tema' => ['required', Rule::in(['sistema', 'claro', 'oscuro'])],
            'visible_en_mapa' => ['sometimes', 'boolean'],
        ]);
        $usuario = $request->user();

        DB::transaction(function () use ($usuario, $datos) {
            $usuario->forceFill(['nombre' => $datos['nombre'], 'tema' => $datos['tema']])->save();
            DB::table('app.perfiles')->updateOrInsert(['usuario_id' => $usuario->id], [
                'departamento' => $datos['departamento'] ?? null,
                'descripcion' => $datos['descripcion'] ?? '',
                'ocupacion' => $datos['ocupacion'] ?? '',
                'avatar' => $datos['avatar'],
                'visible_en_mapa' => (bool) ($datos['visible_en_mapa'] ?? false),
                'actualizado_en' => now(),
            ]);
        });

        return back()->with('ok', 'Perfil y preferencias guardados.');
    }

    public function mapa(Request $request): JsonResponse
    {
        $datos = $request->validate(['departamento' => ['nullable', 'string', 'max:16']]);
        $perfiles = DB::table('app.perfiles as p')
            ->join('app.usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->join('geo.departamentos as d', 'd.codigo', '=', 'p.departamento')
            ->where('p.visible_en_mapa', true)->where('u.activo', 1)
            ->when(! config('territorio.mostrar_perfiles_demo'), fn ($consulta) => $consulta->where('p.demostracion', false))
            ->when($datos['departamento'] ?? null, fn ($consulta, $valor) => $consulta->where('d.codigo', $valor))
            ->select('u.nombre', 'p.avatar', 'p.ocupacion', 'p.demostracion', 'd.codigo', 'd.nombre as departamento')
            ->selectRaw('ST_X(ST_PointOnSurface(d.geom)) AS longitud, ST_Y(ST_PointOnSurface(d.geom)) AS latitud')
            ->orderBy('d.nombre')->orderBy('u.id')->limit(250)->get();

        return response()->json(['perfiles' => $perfiles])->header('Cache-Control', 'private, no-store');
    }

    public function cambiarContrasena(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', 'different:current_password', 'regex:/\p{Lu}/u', 'regex:/\p{Ll}/u', 'regex:/[\p{N}\p{P}\p{S}]/u'],
        ], [
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.min' => 'Usa al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas nuevas deben coincidir.',
            'password.different' => 'Elige una contraseña diferente de la actual.',
            'password.regex' => 'Incluye mayúsculas, minúsculas y un número o carácter especial.',
        ]);
        if (strlen($datos['password']) > 72) {
            throw ValidationException::withMessages(['password' => 'Usa una contraseña de hasta 72 bytes.']);
        }
        $request->user()->update(['password' => $datos['password']]);
        $request->session()->regenerate();

        return back()->with('ok', 'Contraseña actualizada.');
    }
}
