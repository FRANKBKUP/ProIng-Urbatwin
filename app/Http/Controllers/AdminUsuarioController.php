<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUsuarioController extends Controller
{
    public function index()
    {
        return view('admin.usuarios', [
            'usuarios' => User::with('rol')->orderBy('id')->get(),
            'roles' => Rol::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre'   => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:120', 'unique:usuarios,email'],
            'rol_id'   => ['required', 'integer', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'nombre.required'   => 'El nombre es obligatorio.',
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'El correo no tiene un formato válido.',
            'email.unique'      => 'Ya existe un usuario con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        User::create($datos + ['activo' => true]); // el cast 'hashed' cifra la contraseña

        return back()->with('ok', 'Usuario creado.');
    }

    public function update(Request $request, int $id)
    {
        $usuario = User::findOrFail($id);

        // Recomendación del equipo (no viene del docente): evita quedarse sin acceso de administrador
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puedes cambiar tu propio rol ni desactivar tu cuenta.']);
        }

        $datos = $request->validate(['rol_id' => ['required', 'integer', 'exists:roles,id']]);
        $usuario->update(['rol_id' => $datos['rol_id'], 'activo' => $request->boolean('activo')]);

        return back()->with('ok', 'Usuario actualizado.');
    }
}