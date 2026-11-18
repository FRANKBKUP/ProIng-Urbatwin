@extends('layouts.acceso')
@section('titulo', 'Iniciar sesión')

@section('formulario')
    <img class="territorio-login-imagen" src="{{ asset('img/login.png') }}" alt="UrbanTwin Perú">
    <form class="formulario" method="post" action="{{ route('login') }}">
        @csrf
        <h2>Iniciar sesión</h2>
        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <label for="password">Contraseña</label>
        <div class="campo-clave">
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button id="ver-clave" type="button" aria-controls="password" aria-pressed="false">Mostrar</button>
        </div>
        <button class="btn btn-bloque" type="submit">Entrar</button>
    </form>
    @if(\Illuminate\Support\Facades\Route::has('registro'))
        <p>¿Primera vez en UrbanTwin? <a href="{{ route('registro') }}">Crea una cuenta ciudadana</a>.</p>
    @endif
@endsection

@section('scripts')
    <script>
        const clave = document.getElementById('password');
        const mostrarClave = document.getElementById('ver-clave');
        mostrarClave.addEventListener('click', () => {
            const visible = clave.type === 'password';
            clave.type = visible ? 'text' : 'password';
            mostrarClave.textContent = visible ? 'Ocultar' : 'Mostrar';
            mostrarClave.setAttribute('aria-pressed', String(visible));
        });
    </script>
@endsection
