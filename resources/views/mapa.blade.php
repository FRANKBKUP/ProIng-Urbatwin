@extends('layouts.app')
@section('titulo', 'Mapa y análisis')
@section('modo', 'modo-mapa')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
@endpush

@section('contenido')
    @php $puedeAnalizar = auth()->user()->tieneRol('Administrador', 'Analista'); @endphp

    <div class="cabecera">
        <h1>Mapa y análisis de exposición</h1>
        <p class="muted">Elige una zona y un escenario para ver el área inundada{{ $puedeAnalizar ? ' y analizar qué infraestructura queda expuesta' : '' }}.</p>
    </div>

    <div class="mapa-grid">
        <section class="panel" aria-label="Controles del análisis">
            <label for="zona" style="margin-top:0">Zona</label>
            <select id="zona"></select>

            <h3 id="lblEsc">Escenario</h3>
            <div id="escenarios" role="radiogroup" aria-labelledby="lblEsc">Cargando…</div>

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
                <div><i style="background:#2B7FD4;opacity:.6"></i> Área inundada</div>
                <div><i style="background:#E4580B"></i> Expuesto</div>
                <div><i style="background:#5B6B76"></i> No expuesto</div>
            </div>
        </div>

        <section class="panel" aria-label="Resultado del análisis" aria-live="polite">
            <h3>Resultado</h3>
            <p class="vacio" id="vacio">{{ $puedeAnalizar ? 'Elige un escenario y pulsa «Analizar exposición».' : 'Elige un escenario para ver el área inundada.' }}</p>

            <div id="resultado" hidden>
                <div class="cifra" id="total"></div>
                <p class="suave" id="rotulo" style="margin:6px 0 16px"></p>
                <div id="detalle"></div>
                <div id="seccionLista">
                    <h3 style="margin-top:20px">Elementos expuestos</h3>
                    <ul class="lista-expuestos" id="lista"></ul>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
const api = (url) => fetch(url, {headers: {'Accept': 'application/json'}})
    .then(r => { if (!r.ok) throw new Error('Error ' + r.status); return r.json(); });

const map = L.map('map', {zoomControl: false});
L.control.zoom({position: 'topright'}).addTo(map);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    subdomains: 'abcd', maxZoom: 20,
    attribution: '&copy; contribuidores de <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

let zonaId = null, escenarioId = null;
let capaLimite = null, capaInfra = null, capaEscenario = null, capaExpuesta = null;
const escenariosPorId = new Map();
const capaPorId = new Map();

const estiloLinea = {color: '#5B6B76', weight: 3};
const estiloExpuesta = {color: '#E4580B', weight: 5};
const puntoNormal = (f, ll) => L.circleMarker(ll, {radius: 5, color: '#fff', weight: 1.5, fillColor: '#5B6B76', fillOpacity: 1});
const puntoExpuesto = (f, ll) => L.circleMarker(ll, {radius: 8, color: '#fff', weight: 2, fillColor: '#E4580B', fillOpacity: 1});

// Los popups se arman con textContent: un nombre con HTML no se ejecuta (evita XSS).
const popup = (f, layer) => layer.bindPopup(() => {
    const d = document.createElement('div');
    const n = document.createElement('strong');
    n.textContent = f.properties.nombre;
    d.append(n, document.createElement('br'), f.properties.tipo);
    return d;
});

const $ = (id) => document.getElementById(id);

function limpiarResultado() {
    $('resultado').hidden = true;
    $('vacio').hidden = false;
    ['btnAnalizar', 'btnReporte'].forEach(id => { const b = $(id); if (b) b.disabled = true; });
}

async function iniciar() {
    const zonas = await api('/api/zonas');
    const sel = $('zona');
    if (zonas.length === 0) {
        $('escenarios').textContent = 'No hay zonas registradas.';
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
    $('insignia').hidden = true;
    limpiarResultado();

    capaLimite = L.geoJSON(zona.limite, {style: {color: '#5B6B76', weight: 1.5, fill: false, dashArray: '6 6'}}).addTo(map);
    map.fitBounds(capaLimite.getBounds());

    const infra = await api(`/api/zonas/${zonaId}/infraestructuras`);
    capaInfra = L.geoJSON(infra, {pointToLayer: puntoNormal, style: estiloLinea, onEachFeature: popup}).addTo(map);

    const lista = await api(`/api/zonas/${zonaId}/escenarios`);
    escenariosPorId.clear();
    const cont = $('escenarios');
    cont.textContent = lista.length === 0 ? 'Esta zona no tiene escenarios.' : '';
    lista.forEach(e => {
        escenariosPorId.set(e.id, e);
        const label = document.createElement('label');
        label.className = 'escenario-opcion';
        label.style.margin = '6px 0';
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
        style: {color: '#1B5FA8', weight: 2, fillColor: '#2B7FD4', fillOpacity: 0.28}
    }).addTo(map);
    capaEscenario.bringToBack();
    map.fitBounds(capaEscenario.getBounds(), {padding: [60, 60], maxZoom: 17});

    const e = escenariosPorId.get(id);
    const ins = $('insignia');
    ins.textContent = `${e.nivel.toUpperCase()} · ${e.origen === 'simulado' ? 'SIMULADO' : 'IMPORTADO'}`;
    ins.hidden = false;

    const btn = $('btnAnalizar');
    if (btn) btn.disabled = false;
}

function mostrarResultado(r) {
    $('vacio').hidden = true;
    $('resultado').hidden = false;

    const total = $('total');
    total.textContent = r.total;
    total.classList.toggle('hay', r.total > 0);
    $('rotulo').textContent = r.total === 1 ? 'infraestructura expuesta' : 'infraestructuras expuestas';

    const det = $('detalle');
    const lista = $('lista');
    det.textContent = '';
    lista.textContent = '';
    $('seccionLista').hidden = r.total === 0;

    if (r.total === 0) {
        det.textContent = 'Ninguna infraestructura expuesta en este escenario.';
        return;
    }

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

    r.infraestructuras.features.forEach(f => {
        const li = document.createElement('li');
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'item-expuesto';
        const n = document.createElement('span'); n.textContent = f.properties.nombre;
        const t = document.createElement('span'); t.className = 'tipo'; t.textContent = f.properties.tipo;
        b.append(n, t);
        b.addEventListener('click', () => {
            const l = capaPorId.get(f.properties.id);
            if (!l) return;
            if (l.getLatLng) map.setView(l.getLatLng(), Math.max(map.getZoom(), 16));
            else map.fitBounds(l.getBounds(), {maxZoom: 17, padding: [40, 40]});
            l.openPopup();
        });
        li.append(b);
        lista.append(li);
    });
}

async function analizar() {
    const boton = $('btnAnalizar');
    boton.disabled = true;
    boton.textContent = 'Analizando…';
    try {
        const r = await api(`/api/escenarios/${escenarioId}/exposicion`);
        if (capaExpuesta) map.removeLayer(capaExpuesta);
        capaPorId.clear();
        capaExpuesta = L.geoJSON(r.infraestructuras, {
            pointToLayer: puntoExpuesto, style: estiloExpuesta,
            onEachFeature: (f, l) => { popup(f, l); capaPorId.set(f.properties.id, l); }
        }).addTo(map);

        mostrarResultado(r);
        const br = $('btnReporte');
        if (br) br.disabled = (r.total === 0);
    } catch (e) {
        alert('No se pudo analizar: ' + e.message);
    } finally {
        boton.disabled = false;
        boton.textContent = 'Analizar exposición';
    }
}

async function guardarReporte() {
    const boton = $('btnReporte');
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

const btnA = $('btnAnalizar');
if (btnA) btnA.addEventListener('click', analizar);
const btnR = $('btnReporte');
if (btnR) btnR.addEventListener('click', guardarReporte);

iniciar().catch(e => { $('escenarios').textContent = 'No se pudo cargar: ' + e.message; });
</script>
@endpush