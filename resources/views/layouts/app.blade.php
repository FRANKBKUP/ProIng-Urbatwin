<!doctype html>
<html lang="es" data-tema-cuenta="{{ auth()->user()->tema ?? 'sistema' }}" data-usuario-tema="{{ auth()->id() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('img/icono.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/territorio.css') }}?v={{ filemtime(public_path('css/territorio.css')) }}">
    <script src="{{ asset('js/territorio.js') }}?v={{ filemtime(public_path('js/territorio.js')) }}"></script>
    @stack('estilos')
</head>
<body class="@yield('modo')">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <nav class="lateral" id="menu-principal" aria-label="Menú principal">
        <div class="lateral-cabecera">
            <div class="marca">
                <img class="marca-logo" src="{{ asset('img/logo.png') }}" alt="UrbanTwin">
                <img class="marca-icono" src="{{ asset('img/icono.png') }}" alt="UrbanTwin">
            </div>
            <div class="lateral-controles">
                <button id="contraer-menu" class="control-lateral" type="button" aria-label="Contraer menú" title="Contraer menú" aria-pressed="false">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M16 9l-3 3 3 3"/></svg>
                </button>
                <button id="ocultar-menu" class="control-lateral" type="button" aria-label="Ocultar menú" title="Ocultar menú">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 5l14 14M19 5L5 19"/></svg>
                </button>
            </div>
        </div>

        @if(\Illuminate\Support\Facades\Route::has('geo.portal'))
            <a class="enlace" href="{{ route('geo.dashboard') }}" @if(request()->routeIs('geo.dashboard')) aria-current="page" @endif>
                <x-icono n="zonas"/> <span class="enlace-texto">Panel territorial</span>
            </a>
            <a class="enlace" href="{{ route('geo.portal') }}" @if(request()->routeIs('geo.portal')) aria-current="page" @endif>
                <x-icono n="mapa"/> <span class="enlace-texto">Visor ciudadano</span>
            </a>
            <a class="enlace" href="{{ route('geo.lugares.index') }}" @if(request()->routeIs('geo.lugares.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> <span class="enlace-texto">Lugares y grupos</span>
            </a>
            <a class="enlace" href="{{ route('geo.perfil.mostrar') }}" @if(request()->routeIs('geo.perfil.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> <span class="enlace-texto">Perfil y preferencias</span>
            </a>
            @if(auth()->user()->tieneRol('Analista', 'Administrador'))
                <a class="enlace" href="{{ route('geo.reportes.index') }}" @if(request()->routeIs('geo.reportes.*')) aria-current="page" @endif>
                    <x-icono n="reportes"/> <span class="enlace-texto">Análisis y reportes</span>
                </a>
            @endif
            @if(auth()->user()->tieneRol('Administrador'))
                <a class="enlace" href="{{ route('geo.catalogo.index') }}" @if(request()->routeIs('geo.catalogo.*')) aria-current="page" @endif>
                    <x-icono n="infraestructura"/> <span class="enlace-texto">Catálogo geoespacial</span>
                </a>
            @endif
        @endif

        @if(! \Illuminate\Support\Facades\Route::has('geo.portal') || config('territorio.mostrar_simulaciones'))
            <div class="grupo">Simulador académico</div>
            <a class="enlace" href="{{ route('mapa') }}" @if(request()->routeIs('mapa')) aria-current="page" @endif>
                <x-icono n="mapa"/> <span class="enlace-texto">Mapa de escenarios</span>
            </a>
            <a class="enlace" href="{{ route('reportes.index') }}" @if(request()->routeIs('reportes.*')) aria-current="page" @endif>
                <x-icono n="reportes"/> <span class="enlace-texto">Reportes</span>
            </a>
        @endif

        @if(auth()->user()->tieneRol('Administrador'))
            <div class="grupo">Administración</div>
            @if(! \Illuminate\Support\Facades\Route::has('geo.portal') || config('territorio.mostrar_simulaciones'))
            <a class="enlace" href="{{ route('admin.zonas.index') }}" @if(request()->routeIs('admin.zonas.*')) aria-current="page" @endif>
                <x-icono n="zonas"/> <span class="enlace-texto">Zonas</span>
            </a>
            <a class="enlace" href="{{ route('admin.escenarios.index') }}" @if(request()->routeIs('admin.escenarios.*')) aria-current="page" @endif>
                <x-icono n="escenarios"/> <span class="enlace-texto">Escenarios</span>
            </a>
            <a class="enlace" href="{{ route('admin.infraestructuras.index') }}" @if(request()->routeIs('admin.infraestructuras.*')) aria-current="page" @endif>
                <x-icono n="infraestructura"/> <span class="enlace-texto">Infraestructura</span>
            </a>
            @endif
            <a class="enlace" href="{{ route('admin.usuarios.index') }}" @if(request()->routeIs('admin.usuarios.*')) aria-current="page" @endif>
                <x-icono n="usuarios"/> <span class="enlace-texto">Usuarios</span>
            </a>
            @if(Route::has('geo.superusuarios.index'))
                <a class="enlace" href="{{ route('geo.superusuarios.index') }}" @if(request()->routeIs('geo.superusuarios.*')) aria-current="page" @endif>
                    <x-icono n="usuarios"/> <span class="enlace-texto">Superusuarios</span>
                </a>
            @endif
        @endif

        <div class="usuario-pie">
            <span class="avatar-persona usuario-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 1)) }}</span>
            <div class="usuario-datos">
                <div class="nombre">{{ auth()->user()->nombre }}</div>
                <div class="rol">{{ auth()->user()->rol->nombre }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-lateral" type="submit" aria-label="Cerrar sesión" title="Cerrar sesión"><x-icono n="salir"/></button>
            </form>
            @unless(request()->routeIs('geo.*'))
                <p class="aviso">Prototipo con datos simulados.</p>
            @endunless
        </div>
    </nav>

    <div class="principal">
        <header class="workbar">
            <button id="mostrar-menu" class="btn-sec boton-menu" type="button" aria-controls="menu-principal" aria-label="Mostrar menú" title="Mostrar menú">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/></svg>
            </button>
            <div class="workbar-titulo">@yield('barra-superior', 'UrbanTwin Perú')</div>
            <button type="button" class="btn-sec" data-tema-rapido aria-pressed="false">Modo oscuro</button>
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
