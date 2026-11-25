import { AttributionControl, LngLat, LngLatBounds, Map, Marker, NavigationControl, ScaleControl, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import '../css/geo.css';
import { iniciarAtlas } from './atlas-mapa.js';
import { iniciarPersonas } from './personas-mapa.js';
import { iniciarExploracion } from './geo-exploracion.js';
import { iniciarContextoMapa } from './geo-contexto.js';
import { iniciarLluviaMapa } from './geo-lluvia-mapa.js';

const mapaBase = {
    urbano: {
        type: 'raster',
        tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
        tileSize: 256,
        maxzoom: 19,
        attribution: '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>',
    },
    satelite: {
        type: 'raster',
        tiles: ['https://tiles.maps.eox.at/wmts/1.0.0/s2cloudless-2024_3857/default/g/{z}/{y}/{x}.jpg'],
        tileSize: 256,
        maxzoom: 14,
        attribution: '<a href="https://cloudless.eox.at/license-non-commercial">EOxCloudless by EOX IT Services GmbH (contains modified Copernicus Sentinel data 2024)</a>',
    },
};
const terreno = {
    type: 'raster-dem',
    tiles: ['https://tiles.mapterhorn.com/{z}/{x}/{y}.webp'],
    tileSize: 512,
    encoding: 'terrarium',
    maxzoom: 13,
    attribution: '<a href="https://mapterhorn.com/attribution/">© Mapterhorn</a>',
};
const vacio = { type: 'FeatureCollection', features: [] };
const centroPiura = [-80.64, -5.19];
const visor = document.querySelector('#visor-geo');

if (visor) {
    iniciarVisor();
}

function estiloInicial(satelite) {
    const fuentesBase = satelite ? mapaBase : { urbano: mapaBase.urbano };
    return {
        version: 8,
        glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
        sources: {
            ...fuentesBase,
            cobertura: { type: 'geojson', data: vacio },
            peligro: { type: 'geojson', data: vacio },
            lugares: { type: 'geojson', data: vacio },
            medicion: { type: 'geojson', data: vacio },
        },
        layers: [
            { id: 'fondo', type: 'background', paint: { 'background-color': '#dce7eb' } },
            ...Object.keys(fuentesBase).map(id => ({ id, type: 'raster', source: id, layout: { visibility: 'none' },
                paint: { 'raster-saturation': id === 'urbano' ? -0.45 : 0 } })),
            { id: 'cobertura-borde', type: 'line', source: 'cobertura', paint: {
                'line-color': '#27475d', 'line-width': 2, 'line-dasharray': [3, 2],
            } },
            { id: 'peligro-relleno', type: 'fill', source: 'peligro', paint: { 'fill-color': '#26c2e7', 'fill-opacity': 0.48 } },
            { id: 'peligro-borde', type: 'line', source: 'peligro', paint: { 'line-color': '#075575', 'line-width': 1.1 } },
            { id: 'puntos-lugares', type: 'circle', source: 'lugares', paint: {
                'circle-color': '#7c3cb5', 'circle-stroke-color': '#fff', 'circle-stroke-width': 2, 'circle-radius': 6,
            } },
            { id: 'medicion-linea', type: 'line', source: 'medicion', paint: { 'line-color': '#a64812', 'line-width': 3 } },
            { id: 'medicion-fin', type: 'circle', source: 'medicion', filter: ['==', '$type', 'Point'],
                paint: { 'circle-color': '#a64812', 'circle-radius': 5, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1 } },
        ],
    };
}

function iniciarVisor() {
    const el = id => document.getElementById('geo-' + id);
    const selector = el('capa');
    const selectorTema = el('tema');
    const temas = JSON.parse(document.getElementById('temas-atlas').textContent);
    const formulario = el('form');
    const longitud = el('lon');
    const latitud = el('lat');
    const consultar = el('consultar');
    const ubicacion = el('ubicacion');
    const avisoMapa = el('mapa-estado');
    const resultado = el('resultado');
    const base = el('base');
    const relieve = el('terreno');
    const medir = el('medir');
    const grupo = el('grupo');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const estados = {
        dentro: ['Dentro de la huella observada', 'El punto coincide con el interior del polígono documentado para esta fecha.'],
        borde: ['En el borde de la huella observada', 'El punto coincide con el límite de la geometría publicada.'],
        fuera: ['Sin coincidencia con la huella', 'El punto está en el área analizada. Esta observación histórica no determina el peligro actual.'],
        sin_cobertura: ['Sin cobertura', 'La fuente seleccionada no permite evaluar este punto.'],
    };

    let capa = null;
    let atlas = null;
    let coberturaEncuadrada = null;
    let carga = null;
    let consulta = null;
    let cargaLugares = null;
    let lugares = [];
    let avataresDelGrupo = [];
    let grupoEncuadrado = '';
    let marcador = null;
    let mapaListo = false;
    let mapa = null;
    let puntosMedidos = [];
    let focoPendiente = visor.dataset.lugar;
    let puntoActual = null;
    const lluvia = iniciarLluviaMapa(visor, pedir);
    const exploracion = iniciarExploracion(visor, pedir, lluvia.cambiarRegion);
    const fichaFlotante = el('ficha-dialogo');
    let contextoMapa;
    const panel = el('panel-lectura');
    const botonPanel = el('ampliar');
    const botonPantalla = el('pantalla');
    const fondoPanel = el('panel-fondo');
    const movil = matchMedia('(max-width: 880px)');
    const reducir = matchMedia('(prefers-reduced-motion: reduce)');
    const curvas = el('curvas');
    const mostrarCapa = el('mostrar-capa');
    let panelVisible = !movil.matches;

    function actualizarPanel(visible, moverFoco = false) {
        panelVisible = visible;
        visor.classList.toggle('geo-workspace-ampliado', !visible);
        panel.inert = !visible;
        fondoPanel.hidden = !visible || !movil.matches;
        botonPanel.setAttribute('aria-expanded', String(visible));
        if (moverFoco) (visible ? el('buscar-ambito') : botonPanel).focus();
    }

    actualizarPanel(panelVisible);
    botonPanel.addEventListener('click', () => actualizarPanel(!panelVisible, true));
    fondoPanel.addEventListener('click', () => actualizarPanel(false, true));
    el('cerrar-panel').addEventListener('click', () => actualizarPanel(false, true));
    el('ver-punto').addEventListener('click', abrirFicha);
    el('abrir-ficha').addEventListener('click', abrirFicha);
    function abrirFicha() {
        if (!fichaFlotante.open) fichaFlotante.showModal();
    }
    movil.addEventListener('change', () => actualizarPanel(!movil.matches));
    function vistaCompleta() {
        const activa = document.fullscreenElement === visor || visor.classList.contains('geo-vista-completa');
        botonPantalla.setAttribute('aria-pressed', String(activa));
        const texto = activa ? 'Salir de pantalla completa' : 'Pantalla completa';
        botonPantalla.querySelector('span').textContent = texto;
        botonPantalla.setAttribute('aria-label', texto);
        mapa?.resize();
    }
    botonPantalla.addEventListener('click', async () => {
        if (document.fullscreenElement === visor) {
            await document.exitFullscreen();
        } else if (visor.classList.contains('geo-vista-completa')) {
            visor.classList.remove('geo-vista-completa');
        } else {
            actualizarPanel(false);
            try {
                if (!visor.requestFullscreen) throw new Error();
                await visor.requestFullscreen();
            } catch {
                visor.classList.add('geo-vista-completa');
            }
        }
        vistaCompleta();
    });
    document.addEventListener('fullscreenchange', vistaCompleta);
    visor.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape' || visor.querySelector('dialog[open]')) return;
        if (panelVisible && movil.matches) actualizarPanel(false, true);
        else if (visor.classList.contains('geo-vista-completa')) {
            visor.classList.remove('geo-vista-completa');
            vistaCompleta();
            botonPantalla.focus();
        }
    });
    new ResizeObserver(() => mapa?.resize()).observe(visor.querySelector('.geo-viewport'));

    if ('WebGL2RenderingContext' in window) {
        try {
            setWorkerUrl(workerUrl);
            mapa = new Map({
                container: 'mapa-geo',
                center: centroPiura,
                zoom: 11,
                maxPitch: 65,
                maxZoom: 19,
                renderWorldCopies: false,
                attributionControl: false,
                style: estiloInicial(visor.dataset.satelite === '1'),
            });
            mapa.addControl(new NavigationControl(), 'top-right');
            mapa.addControl(new ScaleControl({ unit: 'metric' }), 'bottom-left');
            mapa.addControl(new AttributionControl({ compact: true }), 'bottom-right');
            mapa.once('load', () => {
                mapaListo = true;
                for (const control of [base, relieve, medir, el('centrar'), mostrarCapa, curvas, el('tema-opacidad')]) {
                    control.disabled = false;
                }
                cambiarBase();
                contextoMapa = iniciarContextoMapa(mapa, visor);
                lluvia.conectar(mapa);
                atlas = iniciarAtlas(mapa, visor, temas, cambiarModo, pedir,
                    punto => contextoMapa(punto) ?? lluvia.inspeccionar(punto));
                exploracion.conectar();
                iniciarPersonas(mapa, visor, (lon, lat) => enfocarPunto(lon, lat, 13, false), pedir);
                dibujarCapa();
                dibujarLugares();
                if (puntoActual) {
                    moverAlPunto(puntoActual);
                    analizarPunto(puntoActual);
                }
            });
            mapa.on('error', ({ error, sourceId }) => {
                if (!mapaListo) {
                    aviso('El mapa no pudo iniciarse. Puedes consultar con las coordenadas.');
                } else if (sourceId === 'elevacion' || error?.url?.includes('mapterhorn.com')) {
                    if (mapa.getTerrain()) {
                        cambiarRelieve();
                        aviso('El proveedor de elevación no respondió. El visor volvió a 2D.');
                    }
                    if (curvas.checked) el('capas-estado').textContent = 'El proveedor de elevación no pudo entregar algunas teselas.';
                } else if (sourceId === base.value || /openstreetmap\.org|maps\.eox\.at/.test(error?.url ?? '')) {
                    aviso('Algunas teselas no están disponibles. La capa de inundación sigue operativa.');
                }
            });
            mapa.on('click', ({ lngLat, point }) => {
                if (medir.getAttribute('aria-pressed') === 'true') {
                    medirPunto(lngLat);
                } else {
                    const elemento = mapa.queryRenderedFeatures(point, { layers: ['puntos-lugares'] })[0];
                    const lugar = elemento && lugares.find(item => item.id === elemento.properties.id);
                    if (lugar) {
                        enfocarLugar(lugar);
                    } else {
                        const objeto = contextoMapa?.(point) ?? lluvia.inspeccionar(point);
                        enfocarPunto(lngLat.lng, lngLat.lat, mapa.getZoom(), true, objeto);
                        if (objeto?.url) abrirFicha();
                    }
                }
            });
        } catch {
            mapa = null;
        }
    }

    if (!mapa) {
        aviso('Mapa no disponible en este navegador. Puedes consultar por coordenadas.');
        el('instruccion').textContent = 'Escribe las coordenadas que deseas consultar.';
    }

    selector.addEventListener('change', cargarCapa);
    formulario.addEventListener('submit', evento => {
        evento.preventDefault();
        enfocarPunto(longitud.valueAsNumber, latitud.valueAsNumber);
    });
    formulario.addEventListener('input', () => {
        puntoActual = null;
        limpiarResultado();
    });
    selectorTema.addEventListener('change', () => {
        if (!mapaListo) cambiarModo(selectorTema.value === 'huella' ? 'huella' : 'atlas');
    });
    base.addEventListener('change', cambiarBase);
    relieve.addEventListener('click', cambiarRelieve);
    el('centrar').addEventListener('click', encuadrar);
    medir.addEventListener('click', alternarMedicion);
    mostrarCapa.addEventListener('change', mostrarSuperposiciones);
    el('tema-opacidad').addEventListener('input', mostrarOpacidadTematica);
    visor.addEventListener('lluvia:visualizacion', mostrarOpacidadTematica);
    curvas.addEventListener('change', cambiarCurvas);
    el('opacidad').addEventListener('input', () => {
        if (mapaListo) mapa.setPaintProperty('peligro-relleno', 'fill-opacity', Number(el('opacidad').value) / 100);
    });
    el('cobertura-visible').addEventListener('change', () => {
        mostrarSuperposiciones();
    });
    grupo.addEventListener('change', cargarLugares);
    el('buscar-lugar').addEventListener('input', mostrarLugares);
    el('form-lugar').addEventListener('submit', guardarLugar);

    if (navigator.geolocation && window.isSecureContext) {
        ubicacion.hidden = false;
        ubicacion.addEventListener('click', () => {
            ubicacion.disabled = true;
            navigator.geolocation.getCurrentPosition(({ coords }) => {
                ubicacion.disabled = false;
                enfocarPunto(coords.longitude, coords.latitude);
            }, () => {
                ubicacion.disabled = false;
                mostrar('error', 'Ubicación no disponible', 'Revisa el permiso del navegador o escribe tus coordenadas.');
            }, { timeout: 8000, maximumAge: 0 });
        });
    }

    if (visor.dataset.modo === 'huella') cargarCapa();
    cargarLugares();

    function aviso(mensaje) {
        avisoMapa.textContent = mensaje;
        avisoMapa.hidden = !mensaje;
    }

    function cambiarBase() {
        for (const id of Object.keys(mapaBase)) {
            if (mapa.getLayer(id)) mapa.setLayoutProperty(id, 'visibility', base.value === id ? 'visible' : 'none');
        }
        aviso('');
    }

    function cambiarModo(modo, inicial = false) {
        const previo = visor.dataset.modo;
        visor.dataset.modo = modo;
        const mostrarHuella = modo === 'huella';
        limpiarResultado();
        mostrarSuperposiciones();
        if (mostrarHuella && previo !== modo) {
            if (!capa && !carga) cargarCapa();
            else dibujarCapa();
        }
        if (puntoActual && !inicial && (!mostrarHuella || capa)) analizarPunto(puntoActual);
    }

    function mostrarSuperposiciones() {
        if (!mapaListo) return;
        const huella = visor.dataset.modo === 'huella';
        const visibilidad = (id, visible) => {
            if (mapa.getLayer(id)) mapa.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none');
        };
        visibilidad('peligro-relleno', huella && mostrarCapa.checked);
        visibilidad('peligro-borde', huella && mostrarCapa.checked);
        visibilidad('cobertura-borde', huella && mostrarCapa.checked && el('cobertura-visible').checked);
        visibilidad('atlas-tema-color', !huella && mostrarCapa.checked);
        visibilidad('atlas-tema-renglones', !huella && mostrarCapa.checked);
        visor.classList.toggle('geo-sin-capa', !mostrarCapa.checked);
    }

    function mostrarOpacidadTematica() {
        if (!mapa?.getLayer('atlas-tema-color')) return;
        const amortiguacion = visor.classList.contains('geo-lluvia-activa') ? .46 : 1;
        mapa.setPaintProperty('atlas-tema-color', 'fill-opacity', Number(el('tema-opacidad').value) * amortiguacion / 100);
    }

    function cambiarCurvas() {
        if (!mapaListo) return;
        if (curvas.checked && !mapa.getLayer('curvas-dem')) {
            if (!mapa.getSource('elevacion')) mapa.addSource('elevacion', terreno);
            const color = ['interpolate', ['linear'], ['elevation'], 0, 'rgba(16,71,63,0)'];
            for (let altura = 200; altura <= 6800; altura += 200) {
                color.push(altura - 22, 'rgba(16,71,63,0)', altura, '#10473f', altura + 22, 'rgba(16,71,63,0)');
            }
            mapa.addLayer({ id: 'curvas-dem', type: 'color-relief', source: 'elevacion', minzoom: 8,
                paint: { 'color-relief-color': color, 'color-relief-opacity': 0.76 } }, 'puntos-lugares');
        }
        if (mapa.getLayer('curvas-dem')) mapa.setLayoutProperty('curvas-dem', 'visibility', curvas.checked ? 'visible' : 'none');
        el('capas-estado').textContent = curvas.checked ? 'Curvas aproximadas a partir del relieve. Visibles desde el zoom 8.' : '';
    }

    function cambiarRelieve() {
        const activado = !mapa.getTerrain();
        if (activado && !mapa.getSource('elevacion')) mapa.addSource('elevacion', terreno);
        mapa.setTerrain(activado ? { source: 'elevacion', exaggeration: 1 } : null);
        mapa.easeTo({ pitch: activado ? 53 : 0, bearing: activado ? -18 : 0, duration: reducir.matches ? 0 : 450 });
        relieve.setAttribute('aria-pressed', String(activado));
    }

    function limpiarResultado() {
        consulta?.abort();
        consultar.disabled = false;
        resultado.hidden = true;
    }

    function enfocarPunto(lon, lat, zoom = 13, mostrarPin = true, objeto = null) {
        puntoActual = { lon, lat, zoom, mostrarPin };
        longitud.value = lon.toFixed(7);
        latitud.value = lat.toFixed(7);
        moverAlPunto(puntoActual);
        analizarPunto(puntoActual);
        el('ver-punto').hidden = false;
        exploracion.seleccionarPunto(lon, lat, objeto);
    }

    function moverAlPunto({ lon, lat, zoom, mostrarPin }) {
        if (!mapaListo) return;
        const centro = [lon, Math.max(-85, Math.min(85, lat))];
        mapa.easeTo({ center: centro, zoom: Math.max(zoom, mapa.getZoom()), duration: reducir.matches ? 0 : 435 });
        if (mostrarPin) dibujarMarcador(...centro);
        else {
            marcador?.remove();
            marcador = null;
        }
    }

    function dibujarMarcador(lon, lat) {
        if (!mapaListo) return;
        if (marcador) marcador.setLngLat([lon, lat]);
        else marcador = new Marker({ color: '#124d66' }).setLngLat([lon, lat]).addTo(mapa);
    }

    function encuadrar() {
        if (!mapaListo) return;
        if (atlas && (visor.dataset.modo === 'atlas' || el('region').value)) {
            atlas.encuadrar();
            return;
        }
        if (!capa) {
            mapa.easeTo({ center: centroPiura, zoom: 11 });
            return;
        }
        const limites = new LngLatBounds();
        for (const posicion of capa.cobertura.coordinates.flat(2)) limites.extend(posicion);
        mapa.fitBounds(limites, { padding: { top: 64, bottom: 42, left: 36, right: 36 }, duration: 0, maxZoom: 16 });
    }

    function dibujarCapa() {
        if (!mapaListo) return;
        mapa.getSource('cobertura').setData(capa?.cobertura ?? vacio);
        mapa.getSource('peligro').setData(capa?.peligro ?? vacio);
        if (capa && visor.dataset.modo === 'huella' && !visor.dataset.lugar && !grupo.value && !puntoActual && !el('region').value
            && coberturaEncuadrada !== capa.capa.cobertura_id) {
            encuadrar();
            coberturaEncuadrada = capa.capa.cobertura_id;
        }
    }

    async function cargarCapa() {
        const opcion = selector.selectedOptions[0];
        if (!opcion?.dataset.mapa) return;
        carga?.abort();
        capa = null;
        limpiarResultado();
        el('detalle-datos').hidden = true;
        el('contexto').hidden = false;
        el('contexto').textContent = 'Cargando observación…';
        dibujarCapa();

        const control = new AbortController();
        carga = control;
        try {
            const datos = await pedir(opcion.dataset.mapa, { signal: control.signal });
            if (control.signal.aborted) return;
            capa = datos;
            const meta = datos.capa;
            el('cobertura').textContent = meta.cobertura;
            el('fuente').textContent = meta.fuente;
            el('version').textContent = 'v' + meta.numero;
            el('fecha').textContent = meta.fecha_dato;
            el('licencia').textContent = meta.licencia;
            el('escala-fila').hidden = !meta.escala;
            el('escala').textContent = meta.escala ? '1:' + Number(meta.escala).toLocaleString('es-PE') : '';
            el('ficha').hidden = !meta.ficha_url?.startsWith('https://');
            if (!el('ficha').hidden) el('ficha').href = meta.ficha_url;
            el('leyenda-peligro').textContent = meta.origen === 'simulado' ? 'Peligro simulado' : 'Huella de inundación';
            el('contexto').textContent = meta.origen === 'simulado' ? 'Escenario simulado' : 'Observación histórica · ' + meta.fecha_dato;
            el('detalle-datos').hidden = false;
            dibujarCapa();
            if (puntoActual && visor.dataset.modo === 'huella') analizarPunto(puntoActual);
        } catch (error) {
            if (error.name !== 'AbortError') el('contexto').textContent = 'No se pudo cargar la observación. Elige otra capa o recarga.';
        } finally {
            if (carga === control) carga = null;
        }
    }

    async function analizarPunto({ lon, lat }) {
        limpiarResultado();
        const tema = temas[selectorTema.value];
        if (!tema && !capa) {
            mostrar('sin_cobertura', 'Punto seleccionado',
                carga ? 'Cargando la observación. La consulta se actualizará cuando esté lista.' : 'Elige una capa disponible para analizarlo.');
            return;
        }
        const control = new AbortController();
        consulta = control;
        consultar.disabled = true;

        try {
            const url = tema ? visor.dataset.teselas + '/' + encodeURIComponent(tema.codigo) + '/consulta'
                : selector.selectedOptions[0].dataset.consulta;
            const parametros = { longitud: lon, latitud: lat };
            if (!tema) parametros.version_id = capa.capa.version_id;
            const datos = await pedir(url, { method: 'POST', signal: control.signal, body: JSON.stringify(parametros) });
            if (control.signal.aborted) return;
            if (tema) {
                if (atlas) {
                    atlas.fijar(datos.zona);
                    return;
                }
                const clase = tema.leyenda.find(item => item.clase === Number(datos.zona?.clase));
                const nombre = [datos.zona?.distrito, datos.zona?.departamento].filter(Boolean).join(' · ');
                if (clase) {
                    const categoria = (tema.tipo === 'riesgo' ? 'Riesgo · ' : 'Precipitación · ') + clase.nombre;
                    mostrar('atlas', categoria, [nombre, tema.periodo].filter(Boolean).join(' · '));
                } else {
                    mostrar('sin_cobertura', 'Sin dato de esta capa', 'La fuente seleccionada no clasifica este punto.');
                }
            } else {
                mostrar(datos.estado, ...estados[datos.estado]);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                if (error.status === 409) {
                    await cargarCapa();
                    mostrar('error', 'Capa actualizada', 'Consulta nuevamente con la versión vigente.');
                } else {
                    mostrar('error', 'Consulta no disponible', 'Revisa tu sesión y vuelve a intentarlo.');
                }
            }
        } finally {
            if (!control.signal.aborted) consultar.disabled = false;
        }
    }

    function mostrar(estado, titulo, mensaje) {
        const encabezado = document.createElement('strong');
        encabezado.textContent = titulo;
        const detalle = document.createElement('p');
        detalle.textContent = mensaje;
        resultado.replaceChildren(encabezado, detalle);
        resultado.dataset.estado = estado;
        resultado.hidden = false;
    }

    function alternarMedicion() {
        const activo = medir.getAttribute('aria-pressed') !== 'true';
        medir.setAttribute('aria-pressed', String(activo));
        puntosMedidos = [];
        mapa.getSource('medicion').setData(vacio);
        el('medida').hidden = !activo;
        el('medida').textContent = 'Marca dos puntos para medir.';
    }

    function medirPunto(punto) {
        if (puntosMedidos.length === 2) puntosMedidos = [];
        puntosMedidos.push(punto.toArray());
        const features = puntosMedidos.map(coordinates => ({
            type: 'Feature', geometry: { type: 'Point', coordinates }, properties: {},
        }));
        if (puntosMedidos.length === 2) {
            features.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: puntosMedidos }, properties: {} });
            const metros = LngLat.convert(puntosMedidos[0]).distanceTo(LngLat.convert(puntosMedidos[1]));
            el('medida').textContent = metros >= 1000 ? (metros / 1000).toFixed(2) + ' km' : Math.round(metros) + ' m';
        } else {
            el('medida').textContent = 'Marca el segundo punto.';
        }
        mapa.getSource('medicion').setData({ type: 'FeatureCollection', features });
    }

    function dibujarLugares() {
        if (!mapaListo) return;
        for (const avatar of avataresDelGrupo) avatar.remove();
        avataresDelGrupo = [];
        mapa.getSource('lugares').setData({
            type: 'FeatureCollection',
            features: lugares.map(lugar => ({
                type: 'Feature',
                properties: { id: lugar.id },
                geometry: { type: 'Point', coordinates: [Number(lugar.longitud), Number(lugar.latitud)] },
            })),
        });
        if (grupo.value) {
            for (const lugar of lugares.slice(0, 250)) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'avatar-persona atlas-avatar';
                boton.dataset.avatar = lugar.avatar ?? 'azul';
                boton.textContent = lugar.persona.trim().split(/\s+/).slice(0, 2).map(parte => parte[0]).join('').toUpperCase();
                boton.setAttribute('aria-label', lugar.nombre + ' · ' + lugar.persona);
                boton.title = lugar.nombre + ' · ' + lugar.persona;
                boton.addEventListener('click', evento => {
                    evento.stopPropagation();
                    enfocarLugar(lugar);
                });
                avataresDelGrupo.push(new Marker({ element: boton, anchor: 'center' })
                    .setLngLat([Number(lugar.longitud), Number(lugar.latitud)]).addTo(mapa));
            }
        }
        if (grupo.value && lugares.length && grupoEncuadrado !== grupo.value) {
            const puntos = new LngLatBounds();
            for (const lugar of lugares) {
                const lat = Math.max(-85, Math.min(85, Number(lugar.latitud)));
                puntos.extend([Number(lugar.longitud), lat]);
            }
            mapa.fitBounds(puntos, { padding: 75, maxZoom: 13, duration: 450 });
            grupoEncuadrado = grupo.value;
        }
        if (focoPendiente) {
            const lugar = lugares.find(item => item.id === focoPendiente);
            if (lugar) {
                focoPendiente = '';
                enfocarLugar(lugar);
            }
        }
    }

    function enfocarLugar(lugar) {
        enfocarPunto(Number(lugar.longitud), Number(lugar.latitud));
    }

    async function cargarLugares() {
        grupoEncuadrado = '';
        cargaLugares?.abort();
        const control = new AbortController();
        cargaLugares = control;
        const url = new URL(visor.dataset.lugares);
        if (grupo.value) url.searchParams.set('grupo', grupo.value);
        const lista = el('lista-lugares');
        lista.replaceChildren();
        lugares = [];
        dibujarLugares();
        try {
            const datos = await pedir(url, { signal: control.signal });
            if (control.signal.aborted) return;
            lugares = datos.lugares;
            mostrarLugares();
            dibujarLugares();
        } catch (error) {
            if (error.name !== 'AbortError') lista.textContent = 'No se pudieron cargar las ubicaciones.';
        }
    }

    function mostrarLugares() {
        const lista = el('lista-lugares');
        const buscar = el('buscar-lugar').value.trim().toLocaleLowerCase('es');
        lista.replaceChildren();
        for (const lugar of lugares) {
            const nombre = grupo.value ? lugar.nombre + ' · ' + lugar.persona : lugar.nombre;
            if (!nombre.toLocaleLowerCase('es').includes(buscar)) continue;
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn-sec';
            boton.textContent = nombre;
            boton.addEventListener('click', () => enfocarLugar(lugar));
            lista.append(boton);
        }
        if (!lista.childElementCount) {
            const texto = document.createElement('p');
            texto.textContent = buscar ? 'Ningún lugar coincide con la búsqueda.'
                : (grupo.value ? 'Todavía no hay lugares compartidos.' : 'Todavía no has guardado lugares.');
            lista.append(texto);
        }
    }

    async function guardarLugar(evento) {
        evento.preventDefault();
        const estado = el('lugar-estado');
        if (!longitud.checkValidity() || !latitud.checkValidity()) {
            estado.textContent = 'Elige primero un punto en el mapa o escribe las coordenadas.';
            return;
        }
        const boton = evento.submitter;
        boton.disabled = true;
        try {
            await pedir(visor.dataset.guardar, { method: 'POST', body: JSON.stringify({
                nombre: el('nombre-lugar').value, longitud: longitud.valueAsNumber, latitud: latitud.valueAsNumber,
            }) });
            el('nombre-lugar').value = '';
            estado.textContent = 'Punto guardado como privado.';
            grupo.value = '';
            cargarLugares();
        } catch {
            estado.textContent = 'No se pudo guardar el punto.';
        } finally {
            boton.disabled = false;
        }
    }

    async function pedir(url, opciones = {}) {
        const respuesta = await fetch(url, {
            ...opciones,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            cache: 'no-store',
        });
        if (!respuesta.ok) throw Object.assign(new Error('Solicitud no disponible'), { status: respuesta.status });
        return respuesta.json();
    }
}
