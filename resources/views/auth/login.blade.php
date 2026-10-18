<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · UrbanTwin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="{{ asset('css/urbantwin.css') }}">
</head>
<body>
<main class="acceso">

    <section class="acceso-mapa">
        <div id="mapa-acceso" aria-hidden="true"></div>

        <div class="acceso-marca">
            <div class="marca" style="margin:0">
                <svg width="30" height="30" viewBox="0 0 28 28" aria-hidden="true">
                    <circle cx="11" cy="14" r="9" fill="none" stroke="#fff" stroke-width="2"/>
                    <circle cx="17" cy="14" r="9" fill="#2B7FD4" fill-opacity=".55" stroke="#7DB8F0" stroke-width="2"/>
                </svg>
                UrbanTwin Perú
            </div>
            <p class="acceso-titulo">Análisis de exposición ante inundaciones</p>
            <p>Elige un escenario y consulta qué colegios, centros de salud y vías de la zona quedarían afectados.</p>
        </div>

        <div class="acceso-ejemplo" aria-hidden="true">
            <div class="encabezado">
                <span class="etiqueta">Vista de ejemplo</span>
                <span class="tag">Simulado</span>
            </div>
            <div class="escenario">Escenario moderado</div>
            <div class="cifra hay">5</div>
            <p class="suave" style="margin:4px 0 12px">infraestructuras expuestas</p>
            <div class="fila"><span>Colegios</span><div class="barra-tipo"><i style="width:100%"></i></div><b>2</b></div>
            <div class="fila"><span>Centros de salud</span><div class="barra-tipo"><i style="width:50%"></i></div><b>1</b></div>
            <div class="fila"><span>Vías</span><div class="barra-tipo"><i style="width:100%"></i></div><b>2</b></div>
            <p class="nota">Ilustración con datos simulados.</p>
        </div>
    </section>

    <section class="acceso-form">
        <form method="POST" action="/login">
            @csrf
            <h1>Iniciar sesión</h1>
            <p class="muted" style="margin:0 0 8px">Accede con tu cuenta asignada.</p>

            @error('email')
                <div class="alerta err" role="alert" style="margin-top:16px">{{ $message }}</div>
            @enderror

            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>

            <label for="password">Contraseña</label>
            <div class="campo-clave">
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <button type="button" id="verClave" aria-controls="password" aria-pressed="false">Mostrar</button>
            </div>

            <button class="btn btn-bloque" type="submit">Entrar</button>
        </form>

        <div class="acceso-pie">
            <p>Acceso solo para usuarios autorizados. Si no tienes cuenta, solicítala al administrador.</p>
            <p>Los escenarios mostrados pueden ser simulados y están rotulados como tales.</p>
        </div>
    </section>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
// Mostrar / ocultar contraseña
const clave = document.getElementById('password');
const verClave = document.getElementById('verClave');
verClave.addEventListener('click', () => {
    const oculta = clave.type === 'password';
    clave.type = oculta ? 'text' : 'password';
    verClave.textContent = oculta ? 'Ocultar' : 'Mostrar';
    verClave.setAttribute('aria-pressed', oculta);
});

// Mapa decorativo: datos fijos de ilustración, no consulta la base de datos
if (window.L) {
    const mapa = L.map('mapa-acceso', {
        zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
        boxZoom: false, keyboard: false, touchZoom: false
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(mapa);

    // Mancha orgánica: elipse con ondulaciones (ilustración, no es un área real)
    const centro = [-4.5830, -81.2715];
    const contorno = [];
    for (let i = 0; i < 64; i++) {
        const a = (i / 64) * 2 * Math.PI;
        const k = 1 + 0.14 * Math.sin(3 * a + 1) + 0.08 * Math.sin(5 * a + 2) + 0.05 * Math.sin(8 * a);
        contorno.push([centro[0] + 0.0048 * k * Math.sin(a), centro[1] + 0.0075 * k * Math.cos(a)]);
    }
    const inundacion = L.polygon(contorno, {
        color: '#1B5FA8', weight: 2, fillColor: '#2B7FD4', fillOpacity: 0.3, interactive: false
    }).addTo(mapa);

    const naranja = '#E4580B', gris = '#5B6B76';
    const punto = (ll, color, r) => L.circleMarker(ll, {radius: r, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1, interactive: false}).addTo(mapa);

    // Expuestos: 2 colegios, 1 centro de salud, 2 vías (coinciden con la tarjeta de ejemplo)
    punto([-4.5815, -81.2745], naranja, 8);
    punto([-4.5835, -81.2680], naranja, 8);
    L.marker([-4.5800, -81.2705], {icon: L.divIcon({className: 'm-salud', iconSize: [16, 16]}), interactive: false, keyboard: false}).addTo(mapa);
    L.polyline([[-4.5772, -81.2735], [-4.5880, -81.2712]], {color: naranja, weight: 5, interactive: false}).addTo(mapa);
    L.polyline([[-4.5840, -81.2800], [-4.5830, -81.2640]], {color: naranja, weight: 5, interactive: false}).addTo(mapa);

    // No expuestos
    [[-4.5745, -81.2770], [-4.5905, -81.2640], [-4.5775, -81.2610]].forEach(ll => punto(ll, gris, 5));

    const encuadrar = () => {
        mapa.invalidateSize();
        const ancho = window.innerWidth;
        let opciones = {paddingTopLeft: [470, 40], paddingBottomRight: [30, 40]};   // escritorio: libera el costado de las tarjetas
        if (ancho <= 1100) opciones = {paddingTopLeft: [20, 230], paddingBottomRight: [20, 20]};
        if (ancho <= 900) opciones = {paddingTopLeft: [20, 80], paddingBottomRight: [20, 20]};
        mapa.fitBounds(inundacion.getBounds(), {...opciones, maxZoom: 16, animate: false});
    };
    encuadrar();
    let t;
    window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(encuadrar, 150); });
}
</script>
</body>
</html>