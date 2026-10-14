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
        .contenedor{max-width:960px;margin:24px auto;padding:0 16px}
        table{width:100%;border-collapse:collapse;background:#fff}
        th,td{text-align:left;padding:10px;border-bottom:1px solid #dde6ea}
        th{background:#eaf1f4}
        .tag{font-size:12px;background:#fde7dc;color:#8a2b05;padding:2px 6px;border-radius:4px}
        .btn{display:inline-block;padding:10px 16px;border:0;border-radius:8px;background:#1f6f8b;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}
        .btn-sec{padding:6px 10px;border:1px solid #1f6f8b;border-radius:6px;background:#fff;color:#1f6f8b;cursor:pointer}
        .peligro{border-color:#b93a0b;color:#b93a0b}
        .formulario label{display:block;margin:12px 0;font-weight:600}
        .formulario input,.formulario select,.formulario textarea{width:100%;box-sizing:border-box;padding:8px;margin-top:4px;font:inherit}
        .alerta{padding:10px 14px;border-radius:8px;margin-bottom:16px}
        .alerta.ok{background:#e0f5ec;color:#0b5b3a}
        .alerta.err{background:#fde7dc;color:#8a2b05}
        .alerta ul{margin:0;padding-left:18px}
        @media print{ header, .no-imprimir{display:none} body{background:#fff} }
    </style>
</head>
<body>
<header>
    <nav>
        <strong style="margin-right:20px">UrbanTwin Perú</strong>
        <a href="/mapa">Mapa</a>
        <a href="/reportes">Reportes</a>
        @if(auth()->user()->tieneRol('Administrador'))
            <a href="{{ route('admin.escenarios.index') }}">Escenarios</a>
            <a href="{{ route('admin.usuarios.index') }}">Usuarios</a>
            <a href="{{ route('admin.infraestructuras.index') }}">Infraestructura</a>
            <a href="{{ route('admin.zonas.index') }}">Zonas</a>
        @endif
    </nav>
    <form method="POST" action="/logout">
        @csrf
        <span>{{ auth()->user()->nombre }} · {{ auth()->user()->rol->nombre }}</span>
        <button type="submit">Cerrar sesión</button>
    </form>
</header>
<main class="contenedor">
    @if(session('ok'))
        <div class="alerta ok">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="alerta err"><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    @yield('contenido')
</main>
</body>
</html>