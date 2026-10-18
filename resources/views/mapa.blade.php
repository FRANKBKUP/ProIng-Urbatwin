@extends('layouts.app')
@section('titulo', 'Mapa y análisis')
@section('modo', 'modo-mapa')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
@endpush

@section('contenido')
    @php $puedeAnalizar = auth()->user()->tieneRol('Administrador', 'Analista'); @endphp
    <h1>Mapa y análisis de exposición</h1>

    <div class="mapa-grid">
        <section class="glass" aria-label="Controles del análisis">
            <h3>Zona</h3>
            <select id="zona" aria-label="Zona"></select>

            <h3 style="margin-top:20px">Escenario</h3>
            <div id="escenarios">Cargando…</div>

            @if($puedeAnalizar)
                <button class="btn btn-bloque" id="btnAnalizar" disabled>Analizar exposición</button>
                <button class="btn btn-alerta btn-bloque" id="btnReporte" disabled>Generar reporte</button>
            @else
                <p class="muted" style="margin-top:16px">Tu rol permite visualizar el mapa y los reportes, pero no ejecutar análisis.</p>
            @endif
        </section>

        <div class="mapa-caja">
            <div id="map"></div>
            <div class="insignia" id="insignia" hidden></div>
            <div class="leyenda">
                <div><i style="background:#2F9BFF"></i> Área inundada</div>
                <div><i style="background:#FF7A45"></i> Expuesto</div>
                <div><i style="background:#9DB8C2"></i> No expuesto</div>
            </div>
        </div>

        <section class="glass" aria-label="Resultado del análisis" aria-live="polite">
            <h3>Resultado del análisis</h3>
            <div class="cifra" id="total">–</div>
            <p class="muted" style="margin:6px 0 14px">infraestructuras expuestas</p>
            <div id="detalle"></div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
const api = (url) => fetch(url, {headers: {'Accept': 'application/json'}})
    .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); });

const map = L.map('map');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; contribuidores de OpenStreetMap'
}).addTo(map);

let zonaId = null, escenarioId = null;
let capaLimite = null, capaInfra = null, capaEscenario = null, capaExpuesta = null;
const escenariosPorId = new Map();

const estiloLinea = {color: '#9DB8C2', weight: 3};
const puntoGris = (f, ll) => L.circleMarker(ll, {radius: 6, color: '#0A2A38', weight: 1.5, fillColor: '#9DB8C2', fillOpacity: 1});
const puntoRojo = (f, ll) => L.circleMarker(ll, {radius: 9, color: '#fff', weight: 2, fillColor: '#FF7A45', fillOpacity: 1, className: 'expuesto'});

// El popup se arma con textContent: un nombre con HTML no se ejecuta (evita XSS).
const popup = (f, layer) => layer.bindPopup(() => {
    const d = document.createElement('div');
    d.textContent = `${f.properties.nombre} (${f.properties.tipo})`;
    return d;
});

function mostrarTotal(n) {
    const el = document.getElementById('total');
    if (n === 0 || matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = n; return; }
    let t0 = null;
    const paso = (t) => {
        t0 ??= t;
        const p = Math.min((t - t0) / 600, 1);
        el.textContent = Math.round(n * p);
        if (p < 1) requestAnimationFrame(paso);
    };
    requestAnimationFrame(paso);
}

function limpiarResultado() {
    document.getElementById('total').textContent = '–';
    document.getElementById('detalle').textContent = '';
    ['btnAnalizar', 'btnReporte'].forEach(id => {
        const b = document.getElementById(id);
        if (b) b.disabled = true;
    });
}

async function iniciar() {
    const zonas = await api('/api/zonas');
    const sel = document.getElementById('zona');
    if (zonas.length === 0) {
        document.getElementById('escenarios').textContent = 'No hay zonas registradas.';
        return;
    }
    zonas.forEach(z => {
        const o = document.createElement('option');
        o.value = z.id; o.textContent = z.nombre;
        sel.append(o);
    });
    sel.addEventListener('change', () => cargarZona(zonas.find(z => String(z.id) === sel.value)));
    await cargarZona(zonas[0]);
}

async function cargarZona(zona) {
    zonaId = zona.id;
    escenarioId = null;
    [capaLimite, capaInfra, capaEscenario, capaExpuesta].forEach(c => { if (c) map.removeLayer(c); });
    capaLimite = capaInfra = capaEscenario = capaExpuesta = null;
    document.getElementById('insignia').hidden = true;
    limpiarResultado();

    capaLimite = L.geoJSON(zona.limite, {style: {color: '#3DD6D0', weight: 1.5, fill: false, dashArray: '6'}}).addTo(map);
    map.fitBounds(capaLimite.getBounds());

    const infra = await api(`/api/zonas/${zonaId}/infraestructuras`);
    capaInfra = L.geoJSON(infra, {pointToLayer: puntoGris, style: estiloLinea, onEachFeature: popup}).addTo(map);

    const lista = await api(`/api/zonas/${zonaId}/escenarios`);
    escenariosPorId.clear();
    const cont = document.getElementById('escenarios');
    cont.textContent = lista.length === 0 ? 'Esta zona no tiene escenarios.' : '';
    lista.forEach(e => {
        escenariosPorId.set(e.id, e);
        const label = document.createElement('label');
        label.className = 'escenario-opcion';
        const radio = document.createElement('input');
        radio.type = 'radio'; radio.name = 'esc'; radio.value = e.id;
        radio.addEventListener('change', () => elegir(e.id));
        label.append(radio, e.nombre);
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
    limpiarResultado();

    const area = await api(`/api/escenarios/${id}`);
    capaEscenario = L.geoJSON(area, {
        style: {color: '#7FD3FF', weight: 2, fillColor: '#2F9BFF', fillOpacity: 0.4, className: 'inundacion'}
    }).addTo(map);
    capaEscenario.bringToBack();
    map.fitBounds(capaEscenario.getBounds(), {padding: [60, 60], maxZoom: 17});

    const e = escenariosPorId.get(id);
    const ins = document.getElementById('insignia');
    ins.textContent = `${e.nivel.toUpperCase()} · ${e.origen === 'simulado' ? 'SIMULADO' : 'IMPORTADO'}`;
    ins.hidden = false;

    const btn = document.getElementById('btnAnalizar');
    if (btn) btn.disabled = false;
}

async function analizar() {
    try {
        const r = await api(`/api/escenarios/${escenarioId}/exposicion`);
        if (capaExpuesta) map.removeLayer(capaExpuesta);
        capaExpuesta = L.geoJSON(r.infraestructuras, {
            pointToLayer: puntoRojo, style: {color: '#FF7A45', weight: 6, className: 'expuesto'}, onEachFeature: popup
        }).addTo(map);

        mostrarTotal(r.total);
        const br = document.getElementById('btnReporte');
        if (br) br.disabled = (r.total === 0);

        const det = document.getElementById('detalle');
        det.textContent = '';
        if (r.total === 0) { det.textContent = 'Ninguna infraestructura expuesta.'; return; }

        const max = Math.max(...Object.values(r.por_tipo));
        Object.entries(r.por_tipo).forEach(([tipo, n]) => {
            const fila = document.createElement('div');
            fila.className = 'fila';
            const nombre = document.createElement('span'); nombre.textContent = tipo;
            const barra = document.createElement('div'); barra.className = 'barra-tipo';
            const relleno = document.createElement('i'); barra.append(relleno);
            const cant = document.createElement('b'); cant.textContent = n;
            fila.append(nombre, barra, cant);
            det.append(fila);
            setTimeout(() => { relleno.style.width = (n / max * 100) + '%'; }, 30);
        });
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

const btnA = document.getElementById('btnAnalizar');
if (btnA) btnA.addEventListener('click', analizar);
const btnR = document.getElementById('btnReporte');
if (btnR) btnR.addEventListener('click', guardarReporte);

iniciar().catch(e => { document.getElementById('escenarios').textContent = 'No se pudo cargar: ' + e.message; });
</script>
@endpush