<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Sora:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
</head>
<body class="fondo">
<main class="error-pagina">
    <div>
        <div class="codigo">{{ $codigo }}</div>
        <h1 style="margin-top:12px">{{ $titulo }}</h1>
        <p class="muted grande">{{ $mensaje }}</p>
        <p><a class="btn" href="/mapa">Volver al mapa</a></p>
    </div>
</main>
</body>
</html>