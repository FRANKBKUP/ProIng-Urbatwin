<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa · UrbanTwin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <style>
        body{margin:0;font-family:system-ui,sans-serif;color:#12303B}
        header{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:#12303B;color:#fff}
        header form{margin:0} header button{cursor:pointer}
        main{display:flex;height:calc(100vh - 52px)}
        aside{width:290px;padding:16px;overflow:auto;border-right:1px solid #ccd6da;box-sizing:border-box}
        #map{flex:1}
        h3{margin:16px 0 8px}
        label.op{display:block;margin:8px 0}
        .tag{font-size:12px;background:#fde7dc;color:#8a2b05;padding:2px 6px;border-radius:4px}
        button.main{width:100%;min-height:44px;margin-top:12px;border:0;border-radius:8px;background:#1f6f8b;color:#fff;font-weight:600;cursor:pointer}
        #total{font-size:36px;font-weight:700;color:#b93a0b}
    </style>
</head>
<body>
@php $puedeAnalizar = auth()->user()->tieneRol('Administrador', 'Analista'); @endphp
<header>
    <!-- <strong>UrbanTwin Perú</strong> -->
    <span><strong>UrbanTwin Perú</strong> &nbsp; <a href="/reportes" style="color:#fff">Reportes</a></span>
    <form method="POST" action="/logout">
        @csrf
        <span>{{ auth()->user()->nombre }} · {{ auth()->user()->rol->nombre }}</span>
        <button type="submit">Cerrar sesión</button>
    </form>
</header>
<main>
    <aside>
        <h3>Escenario</h3>
        <div id="escenarios">Cargando…</div>
        @if($puedeAnalizar)
            <button class="main" id="btnAnalizar" disabled>Analizar exposición</button>
            <button class="main" id="btnReporte" disabled style="background:#b93a0b">Generar reporte</button>
        @endif
        <h3>Resultado</h3>
        <div id="total">–</div>
        <div id="detalle" style="margin-top:8px"></div>
    </aside>
    <div id="map"></div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
const api = (url) => fetch(url, {headers: {'Accept': 'application/json'}})
    .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); });

const map = L.map('map');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; contribuidores de OpenStreetMap'
}).addTo(map);

let zonaId = null, escenarioId = null;
let capaEscenario = null, capaExpuesta = null;

const estiloGris = {color: '#5E7380', weight: 3};
const puntoGris = (f, ll) => L.circleMarker(ll, {radius: 6, color: '#fff', weight: 1, fillColor: '#5E7380', fillOpacity: 1});
const puntoRojo = (f, ll) => L.circleMarker(ll, {radius: 9, color: '#fff', weight: 2, fillColor: '#ff6a2b', fillOpacity: 1});
const popup = (f, layer) => layer.bindPopup(`${f.properties.nombre} (${f.properties.tipo})`);

async function iniciar() {
    const zonas = await api('/api/zonas');
    zonaId = zonas[0].id;
    const limite = L.geoJSON(zonas[0].limite, {style: {color: '#1f6f8b', weight: 1, fill: false, dashArray: '6'}}).addTo(map);
    map.fitBounds(limite.getBounds());

    const infra = await api(`/api/zonas/${zonaId}/infraestructuras`);
    L.geoJSON(infra, {pointToLayer: puntoGris, style: estiloGris, onEachFeature: popup}).addTo(map);

    const lista = await api(`/api/zonas/${zonaId}/escenarios`);
    const cont = document.getElementById('escenarios');
    cont.textContent = '';
    lista.forEach(e => {
        const label = document.createElement('label');
        label.className = 'op';
        const radio = document.createElement('input');
        radio.type = 'radio'; radio.name = 'esc'; radio.value = e.id;
        radio.addEventListener('change', () => elegir(e.id));
        label.append(radio, ' ' + e.nombre + ' ');
        if (e.origen === 'simulado') {
            const t = document.createElement('span');
            t.className = 'tag'; t.textContent = 'Simulado';
            label.append(t);
        }
        cont.append(label);
    });
}

async function elegir(id) {
    escenarioId = id;
    if (capaEscenario) map.removeLayer(capaEscenario);
    if (capaExpuesta) { map.removeLayer(capaExpuesta); capaExpuesta = null; }
    const br = document.getElementById('btnReporte');
    if (br) br.disabled = true;
    document.getElementById('total').textContent = '–';
    document.getElementById('detalle').textContent = '';
    const area = await api(`/api/escenarios/${id}`);
    capaEscenario = L.geoJSON(area, {style: {color: '#1f5f96', weight: 2, fillColor: '#2f9bff', fillOpacity: 0.35}}).addTo(map);
    capaEscenario.bringToBack();
    const btn = document.getElementById('btnAnalizar');
    if (btn) btn.disabled = false;
}

async function analizar() {
    try {
        const r = await api(`/api/escenarios/${escenarioId}/exposicion`);
        if (capaExpuesta) map.removeLayer(capaExpuesta);
        capaExpuesta = L.geoJSON(r.infraestructuras, {
            pointToLayer: puntoRojo, style: {color: '#ff6a2b', weight: 6}, onEachFeature: popup
        }).addTo(map);
        document.getElementById('total').textContent = r.total;
        const br = document.getElementById('btnReporte');
        if (br) br.disabled = (r.total === 0);
        const det = document.getElementById('detalle');
        det.textContent = '';
        Object.entries(r.por_tipo).forEach(([tipo, n]) => {
            const p = document.createElement('div');
            p.textContent = `${tipo}: ${n}`;
            det.append(p);
        });
        if (r.total === 0) det.textContent = 'Ninguna infraestructura expuesta.';
    } catch (e) {
        alert('No se pudo analizar: ' + e.message);
    }
}

async function guardarReporte() {
    const boton = document.getElementById('btnReporte');
    boton.disabled = true;
    try {
        const res = await fetch(`/api/escenarios/${escenarioId}/reportes`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
            }
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || ('Error ' + res.status));
        window.location.href = data.url;
    } catch (e) {
        alert('No se pudo guardar el reporte: ' + e.message);
        boton.disabled = false;
    }
}
const btnR = document.getElementById('btnReporte');
if (btnR) btnR.addEventListener('click', guardarReporte);

const btn = document.getElementById('btnAnalizar');
if (btn) btn.addEventListener('click', analizar);
iniciar().catch(e => { document.getElementById('escenarios').textContent = 'No se pudo cargar: ' + e.message; });
</script>
</body>
</html>