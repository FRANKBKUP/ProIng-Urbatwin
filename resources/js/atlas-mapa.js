import { LngLatBounds, Marker, Popup } from 'maplibre-gl';
import { botonPersona } from './personas-mapa.js';
import { incidentes } from './geo-evidencia.js';

const peru = [[-81.35, -18.4], [-68.57, -0.02]];
const vacio = { type: 'FeatureCollection', features: [] };

export function iniciarAtlas(mapa, visor, temas, alCambiar, pedir, inspeccionarObjeto) {
    const el = id => document.getElementById('geo-' + id);
    const selector = el('tema');
    const region = el('region');
    const provincia = el('provincia');
    const provincias = JSON.parse(document.getElementById('provincias-atlas').textContent);
    const estado = el('navegacion-estado');
    const perfiles = el('perfiles');
    const leyenda = el('atlas-leyenda');
    const lectura = el('atlas-lectura');
    const reducir = matchMedia('(prefers-reduced-motion: reduce)');
    const emergente = new Popup({ closeButton: false, closeOnClick: false, className: 'atlas-popup', offset: 12 });
    let limites = [];
    let abierto = null;
    let avatares = [];
    let solicitud = null;
    let solicitudProvincia = null;
    let ambitoProvincia = null;
    let claveAnterior = '';
    let zonaResaltada;

    mapa.addSource('atlas-limites', { type: 'geojson', data: vacio });
    mapa.addLayer({
        id: 'atlas-region-sensor', type: 'fill', source: 'atlas-limites',
        paint: { 'fill-color': '#45637b', 'fill-opacity': 0.001 },
    }, 'cobertura-borde');
    mapa.addLayer({
        id: 'atlas-region-borde', type: 'line', source: 'atlas-limites',
        paint: { 'line-color': '#173c58', 'line-width': 0.85, 'line-opacity': 0.64 },
    }, 'cobertura-borde');
    mapa.addSource('atlas-ambito', {
        type: 'geojson', data: vacio,
        attribution: '<a href="https://www.geoboundaries.org/api/current/gbOpen/PER/ADM2/">Límites · geoBoundaries (IGN/OCHA)</a>',
    });
    mapa.addLayer({
        id: 'atlas-ambito-borde', type: 'line', source: 'atlas-ambito',
        paint: { 'line-color': '#075673', 'line-width': 3, 'line-dasharray': [3, 1.4] },
    }, 'medicion-linea');

    pedir(visor.dataset.limites).then(datos => {
        limites = datos.features;
        mapa.getSource('atlas-limites').setData(datos);
        if (region.value && !provincia.value) encuadrar();
    }).catch(() => {
        if (region.value) avisar('No se pudieron cargar los límites departamentales.');
    });

    selector.addEventListener('change', () => actualizar());
    region.addEventListener('change', () => {
        cargarProvincias();
        encuadrar();
        cargarPerfiles();
    });
    provincia.addEventListener('change', elegirProvincia);
    perfiles.addEventListener('change', cargarPerfiles);

    mapa.on('mousemove', evento => {
        if (el('medir').getAttribute('aria-pressed') === 'true') {
            emergente.remove();
            claveAnterior = '';
            resaltarZona();
            return;
        }
        const objeto = inspeccionarObjeto(evento.point);
        const zona = abierto ? leer(evento.point) : null;
        resaltarZona(zona?.id);
        const detalle = objeto ? {
            clave: [objeto.titulo, objeto.detalle, zona?.clave].join('|'), titulo: objeto.titulo,
            categoria: objeto.detalle, periodo: [objeto.fuente, zona?.categoria].filter(Boolean).join(' · '),
        } : zona;
        if (!detalle) {
            emergente.remove();
            claveAnterior = '';
            return;
        }
        if (detalle.clave !== claveAnterior) {
            emergente.setDOMContent(dibujarLectura(detalle));
            claveAnterior = detalle.clave;
        }
        emergente.setLngLat(evento.lngLat);
        if (!emergente.isOpen()) emergente.addTo(mapa);
    });
    mapa.getCanvas().addEventListener('mouseleave', () => {
        emergente.remove();
        claveAnterior = '';
        resaltarZona();
    });
    mapa.on('click', evento => {
        if (!abierto || el('medir').getAttribute('aria-pressed') === 'true') return;
        const detalle = leer(evento.point);
        if (!detalle) return;
        lectura.replaceChildren(dibujarLectura(detalle));
    });

    cargarProvincias();
    actualizar(true);
    return { encuadrar, fijar };

    function actualizar(inicial = false) {
        emergente.remove();
        resaltarZona();
        claveAnterior = '';
        abierto = temas[selector.value] ?? null;
        if (mapa.getLayer('atlas-tema-renglones')) mapa.removeLayer('atlas-tema-renglones');
        if (mapa.getLayer('atlas-tema-color')) mapa.removeLayer('atlas-tema-color');
        if (mapa.getSource('atlas-tema')) mapa.removeSource('atlas-tema');
        for (const id of ['atlas-region-sensor', 'atlas-region-borde']) {
            mapa.setLayoutProperty(id, 'visibility', abierto ? 'visible' : 'none');
        }
        el('atlas-detalle').hidden = !abierto;
        leyenda.replaceChildren();
        alCambiar(abierto ? 'atlas' : 'huella', inicial);
        if (!abierto) {
            cargarPerfiles();
            return;
        }
        const colores = ['match', ['get', 'clase']];
        for (const clase of abierto.leyenda) {
            colores.push(clase.clase, clase.color);
            const ficha = document.createElement('span');
            const marcador = document.createElement('i');
            marcador.style.backgroundColor = clase.color;
            ficha.append(marcador, clase.nombre);
            leyenda.append(ficha);
        }
        colores.push('#829eae');
        mapa.addSource('atlas-tema', {
            type: 'vector', tiles: [visor.dataset.teselas + '/' + abierto.codigo + '/{z}/{x}/{y}.pbf'],
            bounds: [-82, -19, -68, 0], minzoom: 3, maxzoom: 11, promoteId: 'id',
        });
        mapa.addLayer({
            id: 'atlas-tema-color', type: 'fill', source: 'atlas-tema', 'source-layer': 'zonas',
            layout: { visibility: el('mostrar-capa').checked ? 'visible' : 'none' },
            paint: { 'fill-color': colores, 'fill-opacity': Number(el('tema-opacidad').value) / 100 *
                    (visor.classList.contains('geo-lluvia-activa') ? .46 : 1) },
        }, 'atlas-region-borde');
        mapa.addLayer({
            id: 'atlas-tema-renglones', type: 'line', source: 'atlas-tema', 'source-layer': 'zonas',
            layout: { visibility: el('mostrar-capa').checked ? 'visible' : 'none' },
            paint: { 'line-color': colores, 'line-opacity': .82,
                'line-width': ['case', ['boolean', ['feature-state', 'resaltada'], false], 3.2, abierto.tipo === 'riesgo' ? 1.25 : .65] },
        }, 'atlas-region-borde');
        el('atlas-periodo').textContent = abierto.periodo;
        const enlace = el('atlas-fuente');
        enlace.href = abierto.ficha_url;
        enlace.textContent = abierto.tipo === 'riesgo' ? 'Fuente oficial · CENEPRED / SIGRID' : 'Fuente · CHIRPS / NOAA';
        enlace.hidden = false;
        const guia = document.createElement('p');
        guia.textContent = 'Pasa el cursor sobre una zona. Haz clic o toca para mantener la lectura.';
        lectura.replaceChildren(guia);
        if (inicial) encuadrar();
        cargarPerfiles();
    }

    function leer(punto) {
        const orden = mapa.queryRenderedFeatures(punto, { layers: ['atlas-tema-color', 'atlas-region-sensor'] });
        const distrito = orden.find(item => item.layer.id === 'atlas-tema-color');
        const departamento = orden.find(item => item.layer.id === 'atlas-region-sensor')?.properties;
        if (!distrito && !departamento) return null;
        return { ...detalleLectura(distrito?.properties, departamento), id: distrito?.id };
    }

    function resaltarZona(id) {
        if (id === zonaResaltada || !mapa.getSource('atlas-tema')) return;
        const referencia = valor => ({ source: 'atlas-tema', sourceLayer: 'zonas', id: valor });
        if (zonaResaltada != null) mapa.removeFeatureState(referencia(zonaResaltada), 'resaltada');
        zonaResaltada = id;
        if (id != null) mapa.setFeatureState(referencia(id), { resaltada: true });
    }

    function detalleLectura(distrito, departamento) {
        const titulo = [distrito?.distrito, distrito?.departamento || departamento?.nombre].filter(Boolean).join(' · ') || 'Perú';
        const nivel = abierto.leyenda.find(item => item.clase === Number(distrito?.clase));
        return {
            clave: titulo + '/' + nivel?.clase, titulo, color: nivel?.color,
            categoria: nivel ? (abierto.tipo === 'riesgo' ? 'Riesgo · ' + nivel.nombre : 'Precipitación · ' + nivel.nombre.replace(/\bmm\b/g, 'milímetros')) : 'Sin dato de esta capa',
            periodo: abierto.periodo, ubigeo: distrito?.ubigeo,
        };
    }

    function dibujarLectura(detalle) {
        const cuerpo = document.createElement('div');
        cuerpo.className = 'atlas-lectura-contenido';
        const zona = document.createElement('strong');
        zona.textContent = detalle.titulo;
        const nivel = document.createElement('span');
        nivel.className = 'atlas-nivel';
        if (detalle.color) {
            const muestra = document.createElement('i');
            muestra.style.backgroundColor = detalle.color;
            nivel.append(muestra);
        }
        nivel.append(detalle.categoria);
        const periodo = document.createElement('small');
        periodo.textContent = detalle.periodo + (detalle.ubigeo ? ' · UBIGEO ' + detalle.ubigeo : '');
        cuerpo.append(zona, nivel, periodo, document.createTextNode('Toca o haz clic para ver la ficha.'));
        const informe = incidentes.find(incidente => incidente.ubigeo === detalle.ubigeo);
        if (informe) {
            const afectacion = document.createElement('small');
            afectacion.textContent = informe.personas + ' personas afectadas · COEN–INDECI, corte ' + informe.fecha;
            cuerpo.insertBefore(afectacion, cuerpo.lastChild);
        }
        return cuerpo;
    }

    function fijar(zona) {
        if (abierto) lectura.replaceChildren(dibujarLectura(detalleLectura(zona)));
    }

    function avisar(mensaje) {
        estado.textContent = mensaje;
        estado.hidden = !mensaje;
    }

    function cargarProvincias() {
        solicitudProvincia?.abort();
        ambitoProvincia = null;
        const disponibles = provincias.filter(item => item.departamento === region.value);
        provincia.replaceChildren(new Option(region.value ? 'Todas las provincias' : 'Elige un departamento', ''));
        for (const item of disponibles) provincia.add(new Option(item.nombre, item.codigo));
        provincia.disabled = !disponibles.length;
    }

    async function elegirProvincia() {
        solicitudProvincia?.abort();
        ambitoProvincia = null;
        const codigo = provincia.value;
        if (!codigo) {
            encuadrar();
            return;
        }
        avisar('Cargando límite provincial…');
        const control = new AbortController();
        solicitudProvincia = control;
        try {
            const datos = await pedir(visor.dataset.provincia.replace('__codigo__', encodeURIComponent(codigo)), { signal: control.signal });
            if (control.signal.aborted) return;
            ambitoProvincia = datos;
            encuadrar();
        } catch (error) {
            if (error.name !== 'AbortError') avisar('No se pudo cargar el límite provincial.');
        }
    }

    function encuadrar() {
        if (ambitoProvincia) {
            resaltar(ambitoProvincia.geometria);
            mapa.fitBounds(ambitoProvincia.limites, { padding: 48, duration: reducir.matches ? 0 : 450, maxZoom: 11 });
            avisar('Provincia de ' + ambitoProvincia.nombre + ' · límites de referencia');
            return;
        }
        const distrito = limites.find(item => item.properties.codigo === region.value);
        resaltar(distrito?.geometry);
        if (!distrito) {
            avisar(region.value ? 'Cargando límite departamental…' : '');
            mapa.fitBounds(peru, { padding: 65, duration: 0, maxZoom: 7 });
            return;
        }
        const zona = distrito.geometry;
        const bounds = new LngLatBounds();
        const puntos = zona.coordinates.flat(zona.type === 'MultiPolygon' ? 2 : 1);
        for (const punto of puntos) bounds.extend(punto);
        mapa.fitBounds(bounds, { padding: 48, duration: reducir.matches ? 0 : 450, maxZoom: 11 });
        avisar(distrito.properties.nombre);
    }

    function resaltar(geometria) {
        mapa.getSource('atlas-ambito').setData(geometria ? { type: 'Feature', geometry: geometria, properties: {} } : vacio);
    }

    async function cargarPerfiles() {
        solicitud?.abort();
        for (const avatar of avatares) avatar.remove();
        avatares = [];
        if (!perfiles.checked) return;
        const control = new AbortController();
        solicitud = control;
        const url = new URL(visor.dataset.perfiles);
        if (region.value) url.searchParams.set('departamento', region.value);
        try {
            const respuesta = await fetch(url, { signal: control.signal, headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!respuesta.ok) return;
            const datos = (await respuesta.json()).perfiles;
            if (control.signal.aborted) return;
            const porRegion = new globalThis.Map();
            for (const persona of datos) {
                const lista = porRegion.get(persona.codigo) ?? [];
                lista.push(persona);
                porRegion.set(persona.codigo, lista);
            }
            for (const personas of porRegion.values()) {
                const persona = personas[0];
                const boton = botonPersona('Perfiles visibles en ' + persona.departamento, personas.length);
                boton.dataset.avatar = persona.avatar;
                const panel = document.createElement('div');
                panel.className = 'atlas-lectura-contenido';
                const titulo = document.createElement('strong');
                titulo.textContent = persona.departamento;
                panel.append(titulo);
                for (const visible of personas) {
                    const fila = document.createElement('span');
                    fila.textContent = visible.nombre + (visible.demostracion ? ' · Demostración' : '')
                        + (visible.ocupacion ? ' · ' + visible.ocupacion : '');
                    panel.append(fila);
                }
                avatares.push(new Marker({ element: boton, anchor: 'center' })
                    .setLngLat([Number(persona.longitud), Number(persona.latitud)])
                    .setPopup(new Popup({ className: 'atlas-popup', offset: 18 }).setDOMContent(panel)).addTo(mapa));
            }
        } catch {
            // El atlas temático sigue activo cuando la consulta de perfiles se interrumpe.
        }
    }
}
