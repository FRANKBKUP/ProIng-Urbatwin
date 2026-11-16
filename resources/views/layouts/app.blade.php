<!doctype html>
<html lang="es" data-tema-cuenta="{{ auth()->user()->tema ?? 'sistema' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/territorio.css') }}?v={{ filemtime(public_path('css/territorio.css')) }}">
    <script src="{{ asset('js/territorio.js') }}"></script>
    @stack('estilos')
</head>
<body class="@yield('modo')">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <nav class="lateral" id="menu-principal" aria-label="Menú principal">
        <div class="marca">
            <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true">
                <circle cx="11" cy="14" r="9" fill="none" stroke="#fff" stroke-width="2"/>
                <circle cx="17" cy="14" r="9" fill="#2B7FD4" fill-opacity=".55" stroke="#7DB8F0" stroke-width="2"/>
            </svg>
            UrbanTwin
        </div>

        @if(\Illuminate\Support\Facades\Route::has('geo.portal'))
            <a class="enlace" href="{{ route('geo.dashboard') }}" @if(request()->routeIs('geo.dashboard')) aria-current="page" @endif>
                <x-icono n="zonas"/> Panel territorial
            </a>
            <a class="enlace" href="{{ route('geo.portal') }}" @if(request()->routeIs('geo.portal')) aria-current="page" @endif>
                <x-icono n="mapa"/> Visor ciudadano
            </a>
            <a class="enlace" href="{{ route('geo.lugares.index') }}" @if(request()->routeIs('geo.lugares.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> Lugares y grupos
            </a>
            <a class="enlace" href="{{ route('geo.perfil.mostrar') }}" @if(request()->routeIs('geo.perfil.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> Perfil y preferencias
            </a>
            @if(auth()->user()->tieneRol('Analista', 'Administrador'))
                <a class="enlace" href="{{ route('geo.reportes.index') }}" @if(request()->routeIs('geo.reportes.*')) aria-current="page" @endif>
                    <x-icono n="reportes"/> Análisis y reportes
                </a>
            @endif
            @if(auth()->user()->tieneRol('Administrador'))
                <a class="enlace" href="{{ route('geo.catalogo.index') }}" @if(request()->routeIs('geo.catalogo.*')) aria-current="page" @endif>
                    <x-icono n="infraestructura"/> Catálogo geoespacial
                </a>
            @endif
        @endif

        @if(! \Illuminate\Support\Facades\Route::has('geo.portal') || config('territorio.mostrar_simulaciones'))
            <div class="grupo">Simulador académico</div>
            <a class="enlace" href="{{ route('mapa') }}" @if(request()->routeIs('mapa')) aria-current="page" @endif>
                <x-icono n="mapa"/> Mapa de escenarios
            </a>
            <a class="enlace" href="{{ route('reportes.index') }}" @if(request()->routeIs('reportes.*')) aria-current="page" @endif>
                <x-icono n="reportes"/> Reportes
            </a>
        @endif

        @if(auth()->user()->tieneRol('Administrador'))
            <div class="grupo">Administración</div>
            @if(! \Illuminate\Support\Facades\Route::has('geo.portal') || config('territorio.mostrar_simulaciones'))
            <a class="enlace" href="{{ route('admin.zonas.index') }}" @if(request()->routeIs('admin.zonas.*')) aria-current="page" @endif>
                <x-icono n="zonas"/> Zonas
            </a>
            <a class="enlace" href="{{ route('admin.escenarios.index') }}" @if(request()->routeIs('admin.escenarios.*')) aria-current="page" @endif>
                <x-icono n="escenarios"/> Escenarios
            </a>
            <a class="enlace" href="{{ route('admin.infraestructuras.index') }}" @if(request()->routeIs('admin.infraestructuras.*')) aria-current="page" @endif>
                <x-icono n="infraestructura"/> Infraestructura
            </a>
            @endif
            <a class="enlace" href="{{ route('admin.usuarios.index') }}" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> Usuarios
            </a>
            @if(Route::has('geo.superusuarios.index'))
                <a class="enlace" href="{{ route('geo.superusuarios.index') }}" @if(request()->routeIs('geo.superusuarios.*')) aria-current="page" @endif>
                    <x-icono n="usuarios"/> Superusuarios
                </a>
            @endif
        @endif

        <div class="usuario-pie">
            <div>
                <div class="nombre">{{ auth()->user()->nombre }}</div>
                <div class="rol">{{ auth()->user()->rol->nombre }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-lateral" type="submit"><x-icono n="salir"/> Cerrar sesión</button>
            </form>
            @unless(request()->routeIs('geo.*'))
                <p class="aviso">Prototipo con datos simulados.</p>
            @endunless
        </div>
    </nav>

    <div class="principal">
        <header class="workbar">
            <button id="alternar-menu" class="btn-sec boton-menu" type="button" aria-controls="menu-principal" aria-expanded="true">
                <span aria-hidden="true">☰</span> Menú
            </button>
            <div class="workbar-titulo">@yield('barra-superior', 'UrbanTwin Perú')</div>
            @if(\Illuminate\Support\Facades\Route::has('geo.perfil.mostrar'))
                <a href="{{ route('geo.perfil.mostrar') }}" class="workbar-perfil">
                    <span class="avatar-persona" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 1)) }}</span>
                    <span>Perfil y ajustes</span>
                </a>
            @endif
        </header>
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
