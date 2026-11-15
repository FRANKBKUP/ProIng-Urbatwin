@extends('layouts.acceso')
@section('titulo', 'Crear cuenta')

@section('formulario')
    <form class="formulario" method="post" action="{{ route('registro') }}">
        @csrf
        <h2>Crear cuenta ciudadana</h2>
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" maxlength="100" value="{{ old('nombre') }}" autocomplete="name" required autofocus>
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" maxlength="120" value="{{ old('email') }}" autocomplete="email" required>
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required aria-describedby="registro-clave">
        <small id="registro-clave">Al menos 12 caracteres.</small>
        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button class="btn btn-bloque" type="submit">Crear cuenta</button>
    </form>
    <p>¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>.</p>
@endsection
