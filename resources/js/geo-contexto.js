import { huellaPiura, incidentes } from './geo-evidencia.js';

const capasAgua = ['agua-interior', 'cauce-linea', 'agua-nombre', 'agua-nombre-linea', 'lago-titicaca'];
const capasIncidente = ['eventos-indeci', 'huella-oficial-linea', 'huella-oficial-relleno'];
const tipoAgua = { lake: 'Lago o laguna', river: 'Río', canal: 'Canal', stream: 'Quebrada o arroyo', pond: 'Estanque' };

export function iniciarContextoMapa(mapa, visor) {
    const el = id => visor.querySelector('#geo-' + id);
    const agua = el('agua');
    const reportes = el('incidentes');
    const nombre = ['coalesce', ['get', 'name:es'], ['get', 'name'], ''];

    mapa.addSource('hidrografia', { type: 'vector', url: 'https://tiles.openfreemap.org/planet' });
    mapa.addLayer({
        id: 'agua-interior', type: 'fill', source: 'hidrografia', 'source-layer': 'water', minzoom: 6,
        filter: ['match', ['get', 'class'], ['ocean', 'swimming_pool', 'dock'], false, true],
        paint: { 'fill-color': '#36a7c1', 'fill-opacity': .2, 'fill-outline-color': '#37849c' },
    }, 'puntos-lugares');
    mapa.addLayer({
        id: 'cauce-linea', type: 'line', source: 'hidrografia', 'source-layer': 'waterway', minzoom: 7,
        paint: { 'line-color': '#1986ad', 'line-opacity': .69, 'line-width': ['interpolate', ['linear'], ['zoom'], 7, .65, 13, 1.8] },
    }, 'puntos-lugares');
    mapa.addLayer({
        id: 'agua-nombre', type: 'symbol', source: 'hidrografia', 'source-layer': 'water_name', minzoom: 7,
        filter: ['==', ['geometry-type'], 'Point'],
        layout: { 'text-field': nombre, 'text-font': ['Noto Sans Regular'], 'text-size': 12 },
        paint: { 'text-color': '#0c5579', 'text-halo-color': '#ecfbff', 'text-halo-width': 1.3 },
    }, 'puntos-lugares');
    mapa.addLayer({
        id: 'agua-nombre-linea', type: 'symbol', source: 'hidrografia', 'source-layer': 'water_name', minzoom: 7,
        filter: ['==', ['geometry-type'], 'LineString'],
        layout: { 'symbol-placement': 'line', 'text-field': nombre, 'text-font': ['Noto Sans Regular'], 'text-size': 12 },
        paint: { 'text-color': '#0c5579', 'text-halo-color': '#ecfbff', 'text-halo-width': 1.3 },
    }, 'puntos-lugares');
    mapa.addSource('lago-titicaca', { type: 'geojson', data: visor.dataset.titicaca,
        attribution: '<a href="https://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-lakes/">Natural Earth</a>',
    });
    mapa.addLayer({ id: 'lago-titicaca', type: 'fill', source: 'lago-titicaca', minzoom: 6,
        paint: { 'fill-color': '#16758c', 'fill-opacity': .001 } }, 'puntos-lugares');
    mapa.addSource('huella-oficial', { type: 'geojson', data: visor.dataset.huella,
        attribution: '<a href="https://mapping.emergency.copernicus.eu/activations/EMSR199/">Copernicus EMS · EMSR199 (2017)</a>',
    });
    mapa.addLayer({ id: 'huella-oficial-relleno', type: 'fill', source: 'huella-oficial', minzoom: 8,
        paint: { 'fill-color': '#60d9ea', 'fill-opacity': .23 } }, 'puntos-lugares');
    mapa.addLayer({ id: 'huella-oficial-linea', type: 'line', source: 'huella-oficial', minzoom: 8,
        paint: { 'line-color': '#066582', 'line-width': 1.5, 'line-dasharray': [2, 1] } }, 'puntos-lugares');

    mapa.addSource('eventos-indeci', {
        type: 'geojson',
        data: {
            type: 'FeatureCollection',
            features: incidentes.map(incidente => ({
                type: 'Feature', geometry: { type: 'Point', coordinates: incidente.coord },
                properties: { ubigeo: incidente.ubigeo },
            })),
        },
        attribution: '<a href="https://portal.indeci.gob.pe/informe/informe-de-emergencia/">COEN–INDECI · reportes seleccionados</a>',
    });
    mapa.addLayer({
        id: 'eventos-indeci', type: 'circle', source: 'eventos-indeci', minzoom: 6,
        paint: { 'circle-radius': ['interpolate', ['linear'], ['zoom'], 6, 5, 12, 8], 'circle-color': '#b8334d',
            'circle-stroke-width': 2, 'circle-stroke-color': '#fff' },
    }, 'puntos-lugares');
    agua.disabled = reportes.disabled = false;
    const controlar = (casilla, capas) => {
        const actualizar = () => {
            for (const id of capas) mapa.setLayoutProperty(id, 'visibility', casilla.checked ? 'visible' : 'none');
        };
        casilla.addEventListener('change', actualizar);
        actualizar();
    };
    controlar(agua, capasAgua);
    controlar(reportes, capasIncidente);

    return function inspeccionar(punto) {
        const caja = [[punto.x - 5, punto.y - 5], [punto.x + 5, punto.y + 5]];
        const hit = reportes.checked && mapa.queryRenderedFeatures(caja, { layers: ['eventos-indeci'] })[0];
        if (hit) {
            const incidente = incidentes.find(dato => dato.ubigeo === hit.properties.ubigeo);
            if (incidente) return { ...incidente, detalle: incidente.personas + ' personas afectadas · distrito de ' + incidente.distrito,
                fuente: 'COEN–INDECI · corte ' + incidente.fecha };
        }
        if (reportes.checked && mapa.queryRenderedFeatures(punto, { layers: ['huella-oficial-relleno'] }).length) {
            return huellaPiura;
        }
        if (!agua.checked) return null;
        const lago = mapa.queryRenderedFeatures(punto, { layers: ['lago-titicaca'] })[0];
        if (lago) return { titulo: lago.properties.nombre, detalle: 'Lago · Perú y Bolivia', fuente: 'Natural Earth' };
        const rasgo = mapa.queryRenderedFeatures(caja, { layers: ['agua-nombre', 'agua-nombre-linea', 'cauce-linea', 'agua-interior'] })[0];
        if (!rasgo) return null;
        const prop = rasgo.properties;
        const tipo = tipoAgua[prop.class] ?? 'Cuerpo o curso de agua';
        const etiqueta = prop['name:es'] || prop.name;
        return { titulo: etiqueta || tipo, detalle: etiqueta ? tipo : 'Nombre sin registrar en la capa', fuente: 'OpenStreetMap · OpenFreeMap' };
    };
}
