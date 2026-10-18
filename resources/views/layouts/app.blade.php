<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Sora:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
    @stack('estilos')
</head>
<body class="fondo @yield('modo')">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <nav class="lateral" aria-label="Menú principal">
        <div class="marca">
            <svg width="30" height="30" viewBox="0 0 28 28" aria-hidden="true">
                <circle cx="11" cy="14" r="9" fill="none" stroke="#3DD6D0" stroke-width="2"/>
                <circle cx="17" cy="14" r="9" fill="rgba(47,155,255,.35)" stroke="#2F9BFF" stroke-width="2"/>
            </svg>
            UrbanTwin
        </div>

        <a class="enlace" href="{{ route('mapa') }}" @if(request()->routeIs('mapa')) aria-current="page" @endif>Mapa y análisis</a>
        <a class="enlace" href="{{ route('reportes.index') }}" @if(request()->routeIs('reportes.*')) aria-current="page" @endif>Reportes</a>

        @if(auth()->user()->tieneRol('Administrador'))
            <div class="grupo">Administración</div>
            <a class="enlace" href="{{ route('admin.zonas.index') }}" @if(request()->routeIs('admin.zonas.*')) aria-current="page" @endif>Zonas</a>
            <a class="enlace" href="{{ route('admin.escenarios.index') }}" @if(request()->routeIs('admin.escenarios.*')) aria-current="page" @endif>Escenarios</a>
            <a class="enlace" href="{{ route('admin.infraestructuras.index') }}" @if(request()->routeIs('admin.infraestructuras.*')) aria-current="page" @endif>Infraestructura</a>
            <a class="enlace" href="{{ route('admin.usuarios.index') }}" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif>Usuarios</a>
        @endif

        <div class="pie">
            <div>{{ auth()->user()->nombre }}</div>
            <span class="chip">{{ auth()->user()->rol->nombre }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-sec" type="submit">Cerrar sesión</button>
            </form>
            <small>Prototipo con datos simulados.</small>
        </div>
    </nav>

    <div class="principal">
        <main id="contenido" class="cuerpo">
            @if(session('ok'))
                <div class="alerta ok" role="status">{{ session('ok') }}</div>
            @endif
            @if($errors->any())
                <div class="alerta err" role="alert"><ul>
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul></div>
            @endif
            @yield('contenido')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>