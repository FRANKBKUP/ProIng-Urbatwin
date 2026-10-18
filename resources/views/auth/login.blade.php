<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=Sora:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
</head>
<body class="fondo">
<main class="login">
    <section class="hero">
        <div class="marca" style="margin:0">
            <svg width="34" height="34" viewBox="0 0 28 28" aria-hidden="true">
                <circle cx="11" cy="14" r="9" fill="none" stroke="#3DD6D0" stroke-width="2"/>
                <circle cx="17" cy="14" r="9" fill="rgba(47,155,255,.35)" stroke="#2F9BFF" stroke-width="2"/>
            </svg>
            UrbanTwin Perú
        </div>
        <h1>Anticípate<br>al agua.</h1>
        <p class="muted grande">Gemelo digital 2D para ver qué colegios, centros de salud y vías quedarían expuestos ante una inundación.</p>
        <div class="chips">
            <span class="chip">Escenarios leve · moderado · extremo</span>
            <span class="chip">Exposición automática</span>
            <span class="chip">Reportes de riesgo</span>
        </div>
        <svg viewBox="0 0 460 120" width="100%" style="max-width:460px" aria-hidden="true">
            <path d="M0 70 C60 20 120 110 190 60 C260 10 320 100 390 55 C420 36 440 50 460 44 L460 120 L0 120Z" fill="rgba(47,155,255,.28)"/>
            <path d="M0 90 C70 50 130 120 200 85 C270 50 330 115 400 80 C425 68 445 78 460 74 L460 120 L0 120Z" fill="rgba(61,214,208,.30)"/>
        </svg>
    </section>

    <section class="glass tarjeta">
        <h2 style="margin-top:0">Iniciar sesión</h2>
        <p class="muted" style="margin:0">Accede con tu cuenta asignada.</p>
        <form method="POST" action="/login">
            @csrf
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            @error('email')
                <div class="alerta err" role="alert" style="margin:14px 0 0">{{ $message }}</div>
            @enderror
            <button class="btn btn-bloque" type="submit">Entrar</button>
        </form>
        <p class="muted" style="margin:18px 0 0">Los escenarios mostrados pueden ser simulados y están rotulados como tales.</p>
    </section>
</main>
</body>
</html>