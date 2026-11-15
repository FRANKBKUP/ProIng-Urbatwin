<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function formularioRegistro(): View
    {
        return view('auth.registro');
    }

    public function registrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ], [
            'password.min' => 'Usa al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas deben coincidir.',
        ]);
        if (strlen($datos['password']) > 72) {
            throw ValidationException::withMessages(['password' => 'Usa una contraseña de hasta 72 bytes.']);
        }

        $correo = Str::lower($datos['email']);
        $rol = Rol::where('nombre', 'Consulta')->value('id');
        abort_if($rol === null, 503);

        try {
            $usuario = User::create([
                'nombre' => $datos['nombre'],
                'email' => $correo,
                'password' => $datos['password'],
                'rol_id' => $rol,
                'activo' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Ya existe una cuenta con este correo.']);
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('geo.dashboard');
    }

    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        // 'activo' => 1 impide entrar a usuarios desactivados.
        if (Auth::attempt($credenciales + ['activo' => 1])) {
            $request->session()->regenerate();

            return redirect()->intended(config('database.default') === 'pgsql' ? '/visor' : '/mapa');
        }

        return back()
            ->withErrors(['email' => 'Credenciales incorrectas o usuario inactivo.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
