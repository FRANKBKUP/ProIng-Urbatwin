const informeRegional = 'https://portal.indeci.gob.pe/wp-content/uploads/2026/11/REPORTE-COMPLEMENTARIO-N.%C2%BA-16297-22NOV2026-LLUVIAS-INTENSAS-EN-EL-DEPARTAMENTO-DE-PIURA-17.pdf';
const informeTambo = 'https://portal.indeci.gob.pe/wp-content/uploads/2026/11/REPORTE-COMPLEMENTARIO-N.%C2%BA-16366-24NOV2026-LLUVIAS-INTENSAS-EN-EL-DISTRITO-DE-TAMBO-GRANDE-PIURA-4.pdf';

export const huellaPiura = {
    titulo: 'Inundación observada · Bajo Piura',
    detalle: '30 de marzo de 2017 · extensión cartografiada en la imagen de esa fecha',
    fuente: 'Copernicus EMS Rapid Mapping · EMSR199',
    url: 'https://mapping.emergency.copernicus.eu/activations/EMSR199/',
};

export const incidentes = [
    { ubigeo: '200114', distrito: 'Tambo Grande', provincia: 'Piura', coord: [-80.35808, -4.91580], fecha: '24/11/2026',
        personas: 50, viviendas: 10, titulo: 'Lluvias intensas y aniegos en Tambo Grande', url: informeTambo, fotos: informeTambo + '#page=4' },
    { ubigeo: '200101', distrito: 'Piura', provincia: 'Piura', coord: [-80.68132, -5.09965], fecha: '22/11/2026',
        personas: 84, viviendas: 36, titulo: 'Daños por lluvias en el distrito de Piura', url: informeRegional, fotos: informeRegional + '#page=28' },
    { ubigeo: '200104', distrito: 'Castilla', provincia: 'Piura', coord: [-80.53591, -5.11263], fecha: '22/11/2026',
        personas: 74, viviendas: 20, titulo: 'Daños por lluvias en Castilla', url: informeRegional, fotos: informeRegional + '#page=28' },
    { ubigeo: '200105', distrito: 'Catacaos', provincia: 'Piura', coord: [-80.35682, -5.46922], fecha: '22/11/2026',
        personas: 13, viviendas: 4, titulo: 'Daños por lluvias en Catacaos', url: informeRegional, fotos: informeRegional + '#page=29' },
    { ubigeo: '200401', distrito: 'Chulucanas', provincia: 'Morropon', coord: [-80.19756, -5.12150], fecha: '22/11/2026',
        personas: 7, damnificadas: 18, titulo: 'Daños por lluvias en Chulucanas', url: informeRegional },
];

export const imagenes = [
    { nombre: 'Costa de Piura tras las lluvias', fecha: '23/03/2017', fuente: 'NASA Earth Observatory · MODIS',
        archivo: 'piura-modis-2017.jpg', url: 'https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/' },
    { nombre: 'Inundación cerca de Parachique', fecha: '04/04/2017', fuente: 'NASA Earth Observatory · Landsat',
        archivo: 'parachique-landsat-2017.jpg', url: 'https://science.nasa.gov/earth/earth-observatory/flooding-in-northern-peru-89978/' },
];

export const noticias = [
    { titulo: 'Calles de Piura tras el desborde del río', fuente: 'Agencia Andina · 27/03/2017',
        url: 'https://andina.pe/agencia/noticia-imagenes-calles-piura-estan-inundadas-tras-desborde-rio-660107.aspx' },
    { titulo: 'Boletín de monitoreo y perspectivas', fuente: 'INDECI · 24/11/2026',
        url: 'https://portal.indeci.gob.pe/wp-content/uploads/2026/11/BOLET%C3%8DN-INFORMATIVO-MONITOREO-DE-PELIGROS-Y-PERSPECTIVAS-N%C2%B0328-del-24-11-2026.pdf' },
];
