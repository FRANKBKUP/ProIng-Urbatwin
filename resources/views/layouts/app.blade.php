<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · UrbanTwin</title>
    <style>
        body{margin:0;font-family:system-ui,sans-serif;color:#12303B;background:#f4f7f8}
        header{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:#12303B;color:#fff}
        header a{color:#fff;margin-right:16px;text-decoration:none}
        header form{margin:0;display:inline}
        .contenedor{max-width:900px;margin:24px auto;padding:0 16px}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{text-align:left;padding:10px;border-bottom:1px solid #dde6ea}
        th{background:#eaf1f4}
        .tag{font-size:12px;background:#fde7dc;color:#8a2b05;padding:2px 6px;border-radius:4px}
        .btn{display:inline-block;padding:10px 16px;border:0;border-radius:8px;background:#1f6f8b;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}
        @media print{ header, .no-imprimir{display:none} body{background:#fff} }
    </style>
</head>
<body>
<header>
    <nav>
        <strong style="margin-right:20px">UrbanTwin Perú</strong>
        <a href="/mapa">Mapa</a>
        <a href="/reportes">Reportes</a>
    </nav>
    <form method="POST" action="/logout">
        @csrf
        <span>{{ auth()->user()->nombre }} · {{ auth()->user()->rol->nombre }}</span>
        <button type="submit">Cerrar sesión</button>
    </form>
</header>
<main class="contenedor">
    @yield('contenido')
</main>
</body>
</html>