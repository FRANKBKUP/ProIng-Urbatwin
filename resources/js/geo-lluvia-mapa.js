const vacio = { type: 'FeatureCollection', features: [] };
const gibs = 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best';
const imerg = 'IMERG_Precipitation_Rate_30min';
const matriz = 'GoogleMapsCompatible_Level6';
const horaPeru = new Intl.DateTimeFormat('es-PE', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: 'America/Lima' });
const numero = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 });
const capaCalor = 'precipitacion-calor';
const capaMuestras = 'precipitacion-muestras';

export function iniciarLluviaMapa(visor, pedir) {
    const el = id => visor.querySelector('#geo-' + id);
    const abrir = el('lluvia-abrir');
    const cerrar = el('cerrar-tiempo');
    const casilla = el('lluvia-mapa');
    const lineaTiempo = el('tiempo');
    const estado = el('tiempo-estado');
    const hora = el('hora');
    const reproducir = el('reproducir');
    const reintentar = el('intentar-tiempo');
    const opacidad = el('lluvia-opacidad');
    const modos = [...visor.querySelectorAll('[data-lluvia-modo]')];
    const reducir = matchMedia('(prefers-reduced-motion: reduce)');
    let mapa, solicitud, reloj, refresco;
    let modo = 'pronostico';
    let region = 'PE';
    let pasos = [];
    let capasSatelite = [];
    let fotograma = 0;
    let claveCargada = '';

    function conectar(instancia) {
        mapa = instancia;
        mapa.addSource('precipitacion-pronostico', {
            type: 'geojson', data: vacio, attribution: '<a href="https://open-meteo.com/">Open-Meteo · pronóstico</a>',
        });
        mapa.addLayer({
            id: capaCalor, type: 'heatmap', source: 'precipitacion-pronostico',
            layout: { visibility: 'none' },
            paint: {
                'heatmap-weight': ['interpolate', ['linear'], ['get', 'peso'], 0, 0, 2, .33, 12, 1],
                'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 4, 25, 8, 54, 12, 73],
                'heatmap-intensity': 1.25,
                'heatmap-opacity': Number(opacidad.value) / 100,
                'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(23,82,206,0)',
                    .15, '#4365e3', .38, '#18b8dc', .58, '#5bd2a2', .77, '#ffd46b', 1, '#ff6657'],
            },
        }, 'puntos-lugares');
        mapa.addLayer({
            id: capaMuestras, type: 'circle', source: 'precipitacion-pronostico',
            layout: { visibility: 'none' },
            paint: { 'circle-radius': ['interpolate', ['linear'], ['get', 'mm'], 0, 2.7, 10, 6],
                'circle-color': '#f4fcff', 'circle-stroke-width': 1.3, 'circle-stroke-color': '#23578c' },
        }, 'puntos-lugares');
        abrir.disabled = casilla.disabled = false;
    }

    function cambiarRegion(codigo) {
        const siguiente = codigo || 'PE';
        if (region === siguiente) return;
        region = siguiente;
        if (!lineaTiempo.hidden && modo === 'pronostico') cargar();
    }

    function iniciar() {
        if (!mapa) return;
        lineaTiempo.hidden = false;
        casilla.checked = true;
        abrir.setAttribute('aria-expanded', 'true');
        lineaTiempo.dataset.fuente = modo;
        cargar();
        clearInterval(refresco);
        refresco = setInterval(() => { if (!document.hidden) cargar(true); }, 10 * 60_000);
    }

    function detener() {
        solicitud?.abort();
        pausar();
        clearInterval(refresco);
        restablecer();
        lineaTiempo.hidden = true;
        casilla.checked = false;
        abrir.setAttribute('aria-expanded', 'false');
    }

    function restablecer() {
        pasos = [];
        fotograma = 0;
        claveCargada = '';
        visor.classList.remove('geo-lluvia-activa');
        mapa?.getSource('precipitacion-pronostico').setData(vacio);
        alternarPronostico(false);
        for (const id of capasSatelite) {
            mapa.removeLayer(id);
            mapa.removeSource(id);
        }
        capasSatelite = [];
        reproducir.disabled = hora.disabled = true;
        el('hora-actual').textContent = '';
        actualizarApariencia();
    }

    async function cargar(conservar = false) {
        pausar();
        solicitud?.abort();
        const control = new AbortController();
        solicitud = control;
        const clave = modo === 'satelite' ? modo : modo + '/' + region;
        conservar = conservar && claveCargada === clave && pasos.length > 0;
        if (!conservar) restablecer();
        reintentar.hidden = true;
        estado.textContent = modo === 'satelite' ? 'Buscando observaciones disponibles de NASA…' : 'Actualizando el pronóstico…';
        try {
            const datos = modo === 'satelite'
                ? await observaciones(control.signal)
                : await pedir(visor.dataset.lluvia.replace('__producto__', 'mapa').replace('__codigo__', encodeURIComponent(region)), { signal: control.signal });
            if (control.signal.aborted) return;
            if (!datos.pasos?.length) throw new Error('sin datos');
            restablecer();
            pasos = datos.pasos;
            claveCargada = clave;
            if (modo === 'satelite') prepararSatelite();
            else alternarPronostico(true);
            visor.classList.add('geo-lluvia-activa');
            estado.textContent = modo === 'satelite'
                ? 'NASA IMERG · última imagen ' + horaPeru.format(new Date(pasos.at(-1).fecha)) + ' (Perú). La publicación tiene retraso.'
                : (datos.desactualizado ? 'Última serie disponible · ' : 'Pronóstico Open-Meteo · ') + datos.puntos +
                    ' puntos. Actualizado ' + horaPeru.format(new Date(datos.consultado_en)) + ' (Perú).';
            reintentar.hidden = !datos.desactualizado;
            hora.max = pasos.length - 1;
            hora.disabled = reproducir.disabled = pasos.length < 2;
            dibujar(modo === 'satelite' ? pasos.length - 1 : 0);
            actualizarApariencia();
        } catch (error) {
            if (control.signal.aborted) return;
            estado.textContent = conservar
                ? 'La actualización no respondió. Puedes seguir reproduciendo la serie anterior.'
                : 'La fuente no respondió o aún no tiene datos. Prueba de nuevo o cambia de fuente.';
            reintentar.hidden = false;
        }
    }

    function prepararSatelite() {
        capasSatelite = pasos.map(({fecha}, indice) => {
            const id = 'imerg-hora-' + indice;
            mapa.addSource(id, { type: 'raster',
                tiles: [gibs + '/' + imerg + '/default/' + fecha + '/' + matriz + '/{z}/{y}/{x}.png'],
                tileSize: 256, maxzoom: 6, bounds: [-84, -20, -67, 2],
                attribution: '<a href="https://worldview.earthdata.nasa.gov/">NASA GPM IMERG · GIBS</a>',
            });
            mapa.addLayer({ id, type: 'raster', source: id,
                paint: { 'raster-opacity': 0, 'raster-fade-duration': 0,
                    'raster-opacity-transition': { duration: reducir.matches ? 0 : 230 } } }, 'puntos-lugares');
            return id;
        });
    }

    function alternarPronostico(visible) {
        for (const id of [capaCalor, capaMuestras]) {
            mapa?.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none');
        }
    }

    function actualizarApariencia() {
        const alpha = Number(opacidad.value) / 100;
        mapa?.setPaintProperty(capaCalor, 'heatmap-opacity', alpha);
        for (const [i, id] of capasSatelite.entries()) {
            mapa.setPaintProperty(id, 'raster-opacity', i === fotograma ? alpha : 0);
        }
        visor.dispatchEvent(new Event('lluvia:visualizacion'));
    }

    function dibujar(indice, fraccion = 0) {
        const paso = pasos[indice];
        if (!paso) return;
        if (modo === 'satelite') {
            fotograma = indice;
            actualizarApariencia();
        } else {
            const siguiente = new Map(pasos[indice + 1]?.muestras.map(p => [p.codigo, p.mm]));
            mapa.getSource('precipitacion-pronostico').setData({
                type: 'FeatureCollection',
                features: paso.muestras.map(p => ({
                    type: 'Feature', geometry: { type: 'Point', coordinates: [p.lon, p.lat] },
                    properties: { nombre: p.nombre, mm: p.mm,
                        peso: p.mm + ((siguiente.get(p.codigo) ?? p.mm) - p.mm) * fraccion,
                        fecha: paso.fecha },
                })),
            });
        }
        if (fraccion === 0) {
            hora.value = indice;
            const texto = modo === 'satelite' ? horaPeru.format(new Date(paso.fecha))
                : horaPeru.format(new Date(paso.fecha + '-05:00'));
            el('hora-actual').textContent = texto + ' · Perú';
            hora.setAttribute('aria-valuetext', texto + ' · Perú');
        }
    }

    function pausar() {
        clearInterval(reloj);
        reloj = null;
        reproducir.textContent = 'Reproducir';
    }

    function inspeccionar(punto) {
        if (lineaTiempo.hidden || modo !== 'pronostico' || !pasos.length) return null;
        const muestra = mapa.queryRenderedFeatures([[punto.x - 8, punto.y - 8], [punto.x + 8, punto.y + 8]], { layers: [capaMuestras] })[0];
        if (!muestra) return null;
        const datos = muestra.properties;
        return { titulo: datos.nombre, detalle: numero.format(datos.mm) + ' litros de agua por m² en esa hora', fuente: 'Open-Meteo · estimación del modelo' };
    }

    casilla.addEventListener('change', () => casilla.checked ? iniciar() : detener());
    abrir.addEventListener('click', () => lineaTiempo.hidden ? iniciar() : detener());
    cerrar.addEventListener('click', () => { detener(); abrir.focus(); });
    reintentar.addEventListener('click', () => cargar(true));
    opacidad.addEventListener('input', actualizarApariencia);
    for (const boton of modos) {
        boton.addEventListener('click', () => {
            if (boton.dataset.lluviaModo === modo) return;
            modo = boton.dataset.lluviaModo;
            lineaTiempo.dataset.fuente = modo;
            for (const item of modos) item.setAttribute('aria-pressed', String(item === boton));
            cargar();
        });
    }
    reproducir.addEventListener('click', () => {
        if (reloj) { pausar(); return; }
        reproducir.textContent = 'Pausar';
        const partes = modo === 'satelite' || reducir.matches ? 1 : 5;
        let frame = Number(hora.value) * partes;
        reloj = setInterval(() => {
            frame = (frame + 1) % ((pasos.length - 1) * partes + 1);
            dibujar(Math.floor(frame / partes), (frame % partes) / partes);
        }, (modo === 'satelite' ? 1050 : 730) / partes);
    });
    hora.addEventListener('input', () => { pausar(); dibujar(Number(hora.value)); });

    return { conectar, cambiarRegion, inspeccionar };
}

async function observaciones(signal) {
    const parametros = new URLSearchParams({ SERVICE: 'WMTS', REQUEST: 'DescribeDomains', VERSION: '1.0.0',
        LAYER: imerg, TILEMATRIXSET: matriz });
    const respuesta = await fetch(gibs + '/wmts.cgi?' + parametros, { signal, credentials: 'omit' });
    if (!respuesta.ok) throw new Error('NASA');
    const xml = new DOMParser().parseFromString(await respuesta.text(), 'application/xml');
    const dominio = [...xml.getElementsByTagNameNS('*', 'Domain')].map(nodo => nodo.textContent).join(',');
    const fechas = new Set();
    for (const tramo of dominio.split(',').slice(-12)) {
        const [desde, hasta = desde, intervalo] = tramo.trim().split('/');
        const minutos = Number(intervalo?.match(/^PT(\d+)M$/)?.[1] ?? 30);
        const tope = Date.parse(hasta);
        const piso = Date.parse(desde);
        if (minutos < 1 || !Number.isFinite(tope) || !Number.isFinite(piso)) continue;
        for (let i = 0; i < 6 && tope - i * minutos * 60_000 >= piso; i++) {
            const fecha = tope - i * minutos * 60_000;
            if (fecha <= Date.now()) fechas.add(fecha);
        }
    }
    return { pasos: [...fechas].sort((a, b) => a - b).slice(-6).map(fecha => ({
        fecha: new Date(fecha).toISOString().replace('.000Z', 'Z'),
    })) };
}
