<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 'activo' => 1 impide entrar a usuarios desactivados.
        if (Auth::attempt($credenciales + ['activo' => 1])) {
            $request->session()->regenerate();
            return redirect()->intended('/mapa');
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