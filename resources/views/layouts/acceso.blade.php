<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('img/icono.png') }}">
    <title>@yield('titulo') · UrbanTwin Perú</title>
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/territorio.css') }}?v={{ filemtime(public_path('css/territorio.css')) }}">
    <script src="{{ asset('js/territorio.js') }}"></script>
</head>
<body class="territorio-auth">
<main class="territorio-auth-layout">
    <section class="territorio-auth-contexto">
        <img class="territorio-auth-imagen" src="{{ asset('img/piura-modis-2017.jpg') }}" alt="Imagen satelital de la costa de Piura y el área inundada, MODIS Aqua, 23 de marzo de 2017">
        <a href="{{ url('/') }}" class="territorio-auth-marca"><x-icono n="mapa"/> UrbanTwin Perú</a>
        <div>
            <p class="territorio-localidad">Atlas de Perú</p>
            <h1>Lluvias y riesgo territorial</h1>
            <p>Explora escenarios oficiales, mapas de precipitación y observaciones históricas. Guarda los lugares que te interesan.</p>
            <a class="credito-imagen" href="https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/" target="_blank" rel="noopener noreferrer">NASA Earth Observatory · MODIS Aqua · 23 mar 2017</a>
        </div>
    </section>
    <section class="territorio-auth-form" aria-label="@yield('titulo')">
        <button type="button" class="btn-sec tema-rapido" data-tema-rapido aria-pressed="false">Modo oscuro</button>
        @if($errors->any())
            <div class="alerta err" role="alert"><ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul></div>
        @endif
        @yield('formulario')
    </section>
</main>
@yield('scripts')
</body>
</html>
