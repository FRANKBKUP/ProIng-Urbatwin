import { imagenes, incidentes, noticias } from './geo-evidencia.js';

function crear(etiqueta, texto, clase) {
    const elemento = document.createElement(etiqueta);
    if (texto !== undefined) elemento.textContent = texto;
    if (clase) elemento.className = clase;
    return elemento;
}

const numero = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 });
const nombrar = texto => texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();

export function iniciarExploracion(visor, pedir, alElegirRegion) {
    const el = id => visor.querySelector('#geo-' + id);
    const region = el('region');
    const provincia = el('provincia');
    const ubicacion = el('ficha-ubicacion');
    const estado = el('ficha-estado');
    const filtros = [...visor.querySelectorAll('[data-consulta-geo]')];
    const buscar = el('buscar-ambito');
    const sugerencias = el('sugerencias');
    const regiones = [...region.options].filter(opcion => opcion.value);
    const provincias = JSON.parse(document.getElementById('provincias-atlas').textContent);
    const ambitos = [
        ...regiones.map(opcion => ({ codigo: opcion.value, nombre: opcion.textContent.trim(), region: opcion.value, detalle: 'Región' })),
        ...provincias.map(p => ({ ...p, region: p.departamento, detalle: 'Provincia · ' + regiones.find(r => r.value === p.departamento)?.textContent.trim() })),
    ];
    const pronosticos = new Map();
    const pendientes = new Map();
    let ficha, ambito, consulta, meteorologia, objeto;
    let conectado = false;
    let busquedaPendiente;

    const url = (producto, codigo) => visor.dataset.lluvia.replace('__producto__', producto)
        .replace('__codigo__', encodeURIComponent(codigo));

    function filtrar() {
        const texto = nombrar(buscar.value);
        sugerencias.replaceChildren();
        sugerencias.hidden = !texto;
        if (!texto) return;
        const opciones = ambitos.filter(item => nombrar(item.nombre).includes(texto)).slice(0, 8);
        for (const item of opciones) {
            const boton = crear('button');
            boton.type = 'button';
            boton.append(crear('strong', item.nombre), crear('small', item.detalle));
            boton.addEventListener('click', () => {
                buscar.value = item.nombre;
                if (conectado) {
                    aplicarBusqueda(item);
                } else {
                    busquedaPendiente = item;
                    seleccionarAmbito(item.codigo, item.region);
                }
                buscar.focus();
                sugerencias.hidden = true;
            });
            const li = crear('li');
            li.append(boton);
            sugerencias.append(li);
        }
        if (!opciones.length) sugerencias.append(crear('li', 'No encontramos una región o provincia.'));
    }
    buscar.addEventListener('input', filtrar);
    buscar.addEventListener('focus', filtrar);
    buscar.addEventListener('keydown', evento => {
        if (evento.key === 'ArrowDown') {
            const primero = sugerencias.querySelector('button');
            if (primero) {
                evento.preventDefault();
                primero.focus();
            }
        }
    });
    buscar.closest('section').addEventListener('focusout', evento => {
        if (!evento.currentTarget.contains(evento.relatedTarget)) sugerencias.hidden = true;
    });
    sugerencias.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') { buscar.focus(); sugerencias.hidden = true; }
    });

    region.addEventListener('change', () => seleccionarAmbito(region.value, region.value));
    provincia.addEventListener('change', () => seleccionarAmbito(provincia.value || region.value, region.value));
    for (const filtro of filtros) filtro.addEventListener('change', mostrarFicha);

    function aplicarBusqueda(item) {
        region.value = item.region;
        region.dispatchEvent(new Event('change'));
        if (item.codigo !== item.region) {
            provincia.value = item.codigo;
            provincia.dispatchEvent(new Event('change'));
        }
    }

    function conectar() {
        conectado = true;
        if (busquedaPendiente) {
            aplicarBusqueda(busquedaPendiente);
            busquedaPendiente = null;
        }
    }

    function seleccionarAmbito(codigo, departamento) {
        consulta?.abort();
        ficha = null;
        objeto = null;
        ambito = ambitos.find(item => item.codigo === codigo);
        const acceso = el('ver-punto');
        acceso.textContent = 'Ver ficha de la zona';
        acceso.hidden = !ambito;
        estado.textContent = '';
        alElegirRegion(departamento);
        mostrarFicha();
    }

    async function seleccionarPunto(lon, lat, rasgo) {
        consulta?.abort();
        const control = new AbortController();
        consulta = control;
        ficha = null;
        ambito = null;
        objeto = rasgo;
        el('ver-punto').textContent = 'Ver ficha del punto';
        meteorologia?.abort();
        ubicacion.textContent = numero.format(lat) + '°, ' + numero.format(lon) + '°';
        estado.textContent = 'Cruzando capas territoriales…';
        mostrarFicha();
        try {
            ficha = await pedir(visor.dataset.ficha, { method: 'POST', signal: control.signal,
                body: JSON.stringify({ longitud: lon, latitud: lat }) });
            if (control.signal.aborted) return;
            ambito = ambitos.find(item => item.codigo === (ficha.provincia?.codigo || ficha.departamento?.codigo));
            estado.textContent = '';
            mostrarFicha();
        } catch (error) {
            if (error.name !== 'AbortError') estado.textContent = 'No se pudo consultar el punto. Intenta seleccionarlo de nuevo.';
        }
    }

    function mostrarFicha() {
        meteorologia?.abort();
        const control = new AbortController();
        meteorologia = control;
        const distrito = ficha?.capas.find(capa => capa.distrito);
        const titulo = objeto?.titulo ?? ambito?.nombre ?? (ficha ? 'Punto fuera de los límites disponibles' : 'Elige una región, provincia o punto en el mapa.');
        ubicacion.textContent = [distrito?.distrito && distrito.distrito !== titulo ? distrito.distrito : null, titulo].filter(Boolean).join(' · ');

        for (const filtro of filtros) {
            const tipo = filtro.dataset.consultaGeo;
            const contenedor = el('ficha-' + tipo);
            contenedor.hidden = !filtro.checked;
            contenedor.replaceChildren();
            if (!filtro.checked) continue;

            if (tipo === 'territorio' && (ambito || ficha || objeto)) {
                const territorio = [distrito?.ubigeo ? 'UBIGEO ' + distrito.ubigeo : null, ambito?.detalle,
                    distrito?.km2 != null ? numero.format(distrito.km2) + ' km² en la capa distrital' : null].filter(Boolean);
                if (objeto) {
                    contenedor.append(crear('p', objeto.detalle + ' · ' + objeto.fuente, 'geo-ficha-contexto'));
                    if (objeto.url) contenedor.append(enlace('Ver fuente del elemento seleccionado', objeto.url));
                }
                contenedor.append(crear('p', territorio.join(' · ') || 'Sin identificación territorial para este punto.'));
            } else if (tipo === 'capas') {
                if (!ficha) {
                    contenedor.append(crear('p', 'Toca un punto del mapa para comparar las capas que lo cubren.'));
                } else {
                    contenedor.append(...ficha.capas.map(tarjetaCapa));
                    if (!ficha.capas.length) contenedor.append(crear('p', 'Aún no hay capas temáticas publicadas.'));
                }
            } else if (tipo === 'pronostico' || tipo === 'historico') {
                if (ambito) cargarClima(tipo, ambito, contenedor, control.signal);
                else contenedor.append(crear('p', 'Selecciona una región o provincia para consultar su punto de referencia.'));
            } else if (tipo === 'evidencia') {
                mostrarEvidencia(contenedor, objeto?.ubigeo || distrito?.ubigeo, ambito);
            }
        }
    }

    function tarjetaCapa(capa) {
        const tarjeta = crear('article', undefined, 'geo-ficha-tarjeta');
        const categoria = capa.categoria?.replace(/\bmm\b/g, 'milímetros');
        tarjeta.append(crear('strong', capa.titulo), crear('span', categoria || 'Sin cobertura en este punto'));
        const fuente = crear('a', capa.periodo + ' · Fuente');
        if (/^https:\/\//i.test(capa.ficha_url ?? '')) {
            fuente.href = capa.ficha_url;
            fuente.target = '_blank';
            fuente.rel = 'noopener noreferrer';
            tarjeta.append(fuente);
        } else {
            tarjeta.append(crear('small', capa.periodo));
        }
        return tarjeta;
    }

    async function cargarClima(tipo, lugar, contenedor, signal) {
        const clave = tipo + '/' + lugar.codigo;
        contenedor.append(crear('p', tipo === 'historico' ? 'Consultando años completos…' : 'Consultando el pronóstico…'));
        try {
            let copia = pronosticos.get(clave);
            if (!copia || copia.vence < Date.now()) {
                if (!pendientes.has(clave)) {
                    pendientes.set(clave, pedir(url(tipo, lugar.codigo)).finally(() => pendientes.delete(clave)));
                }
                const datos = await pendientes.get(clave);
                copia = { datos, vence: Date.now() + (datos.desactualizado ? 60_000 : 5 * 60_000) };
                if (datos.disponible) pronosticos.set(clave, copia);
            }
            if (signal.aborted) return;
            const { datos } = copia;
            contenedor.replaceChildren();
            if (!datos.disponible) {
                mostrarReintento(contenedor, 'La fuente aún no devolvió datos para esta consulta.');
                return;
            }
            if (tipo === 'historico') mostrarAnual(contenedor, datos);
            else mostrarPronostico(contenedor, datos);
        } catch (error) {
            if (!signal.aborted && error.name !== 'AbortError') {
                mostrarReintento(contenedor, 'La conexión con la fuente no respondió.');
            }
        }
    }

    function mostrarReintento(contenedor, texto) {
        const boton = crear('button', 'Volver a consultar', 'btn-sec');
        boton.type = 'button';
        boton.addEventListener('click', mostrarFicha);
        contenedor.replaceChildren(crear('p', texto), boton);
    }

    function mostrarPronostico(contenedor, datos) {
        contenedor.append(crear('h4', 'Pronóstico de 7 días'));
        const maximo = Math.max(0, ...datos.dias.map(dia => dia.mm));
        const total = datos.dias.reduce((suma, dia) => suma + dia.mm, 0);
        contenedor.append(tarjetas([
            [datos.dias.length + ' días', 'Con datos de pronóstico'],
            [litros(total), 'Precipitación total estimada'],
            [litros(maximo), 'Mayor cantidad en un día'],
        ]));
        const lista = crear('ul', undefined, 'geo-dias');
        for (const dia of datos.dias) {
            const fila = crear('li');
            const etiqueta = new Date(dia.fecha + 'T12:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' });
            const barra = crear('span', undefined, 'geo-anual-barra');
            barra.setAttribute('aria-hidden', 'true');
            barra.style.setProperty('--anual', dia.mm / Math.max(1, maximo) * 100 + '%');
            fila.append(crear('span', etiqueta), barra, crear('strong', litros(dia.mm)),
                crear('small', dia.probabilidad == null ? 'Probabilidad s/d' : dia.probabilidad + ' % máx.'));
            lista.append(fila);
        }
        const horario = new Date(datos.consultado_en).toLocaleString('es-PE', { timeZone: 'America/Lima' });
        contenedor.append(lista, crear('small', (datos.desactualizado ? 'Última respuesta guardada. ' : '') +
            'Open-Meteo · Estimación para un punto de ' + datos.lugar + ' · actualizada ' + horario + ' (Perú).'));
    }

    function mostrarAnual(contenedor, datos) {
        const grafico = crear('ul', undefined, 'geo-historico');
        const maximo = Math.max(1, ...datos.anios.map(anio => anio.mm ?? 0));
        for (const anio of datos.anios) {
            const fila = crear('li');
            const barra = crear('span', undefined, 'geo-anual-barra');
            barra.setAttribute('aria-hidden', 'true');
            barra.style.setProperty('--anual', (anio.mm ?? 0) / maximo * 100 + '%');
            fila.append(crear('strong', anio.anio), barra,
                crear('span', anio.mm == null ? anio.dias + '/' + anio.esperados + ' días' : litros(anio.mm)));
            grafico.append(fila);
        }
        const completos = datos.anios.filter(anio => anio.mm !== null);
        const media = completos.reduce((suma, anio) => suma + anio.mm, 0) / completos.length;
        contenedor.append(crear('h4', 'Precipitación por año'),
            tarjetas([[completos.length + ' años', 'Con cobertura completa'], [litros(media), 'Promedio anual']]), grafico,
            histograma(completos.map(anio => anio.mm)), crear('small',
            datos.fuente + ' · Acumulados en un punto de ' + datos.lugar + '. Los años incompletos se muestran sin total.'));
    }

    function litros(mm) {
        return numero.format(mm) + ' L/m²';
    }

    function tarjetas(metricas) {
        const caja = crear('div', undefined, 'geo-estadisticas');
        for (const [valor, leyenda] of metricas) {
            const tarjeta = crear('div');
            tarjeta.append(crear('strong', valor), crear('span', leyenda));
            caja.append(tarjeta);
        }
        return caja;
    }

    function histograma(valores) {
        const ancho = Math.max(10, Math.ceil(Math.max(...valores) / 50) * 10);
        const clases = Array.from({ length: 5 }, (_, i) => ({ desde: ancho * i, hasta: ancho * (i + 1), total: 0 }));
        for (const valor of valores) clases[Math.min(4, Math.floor(valor / ancho))].total++;
        const grupo = crear('div', undefined, 'geo-histograma');
        grupo.append(crear('h4', 'Distribución de años por cantidad de lluvia'));
        const lista = crear('ul');
        for (const clase of clases) {
            const fila = crear('li');
            const barra = crear('span', undefined, 'geo-anual-barra');
            barra.setAttribute('aria-hidden', 'true');
            barra.style.setProperty('--anual', clase.total / valores.length * 100 + '%');
            fila.append(crear('span', numero.format(clase.desde) + '–' + numero.format(clase.hasta) + ' L/m²'),
                barra, crear('strong', clase.total + (clase.total === 1 ? ' año' : ' años')));
            lista.append(fila);
        }
        grupo.append(lista);
        return grupo;
    }

    function enlace(texto, url) {
        const link = crear('a', texto);
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        return link;
    }

    function mostrarEvidencia(contenedor, ubigeo, zona) {
        contenedor.append(crear('h4', 'Reportes, fotos y noticias'));
        const seleccionado = incidentes.find(item => item.ubigeo === ubigeo);
        const piura = seleccionado || zona?.region === 'PE-PIU' || objeto?.fecha === '2017-03-30'
            || objeto?.fuente?.startsWith('Copernicus EMS');
        if (!piura) {
            contenedor.append(crear('p', 'El visor aún no tiene un reporte asociado a esta zona.'),
                enlace('Consultar el registro oficial de emergencias', 'https://portal.indeci.gob.pe/informe/informe-de-emergencia/'));
            return;
        }
        const filas = seleccionado ? [seleccionado] : incidentes.filter(item => zona?.codigo === 'PE-PIU'
            || !zona || nombrar(item.provincia) === nombrar(zona.nombre));
        if (!filas.length) contenedor.append(crear('p', 'Sin cifras verificadas para este ámbito en la selección de reportes.'));
        if (seleccionado) {
            const metricas = [[numero.format(seleccionado.personas), 'Personas afectadas']];
            if (seleccionado.viviendas != null) metricas.push([seleccionado.viviendas, 'Viviendas afectadas']);
            if (seleccionado.damnificadas != null) metricas.push([seleccionado.damnificadas, 'Personas damnificadas']);
            contenedor.append(tarjetas(metricas));
        }
        if (filas.length) contenedor.append(tablaIncidentes(filas));
        const galeria = crear('div', undefined, 'geo-evidencia-galeria');
        for (const foto of imagenes) {
            const link = enlace('', foto.url);
            const img = crear('img');
            img.src = visor.dataset.img + '/' + foto.archivo;
            img.alt = foto.nombre;
            img.loading = 'lazy';
            link.append(img, crear('strong', foto.nombre), crear('small', foto.fuente + ' · ' + foto.fecha));
            galeria.append(link);
        }
        contenedor.append(crear('h5', 'Archivo satelital de Piura · 2017'), galeria);
        const referencias = crear('ul', undefined, 'geo-evidencia-enlaces');
        for (const noticia of noticias) {
            const item = crear('li');
            item.append(enlace(noticia.titulo + ' · ' + noticia.fuente, noticia.url));
            referencias.append(item);
        }
        contenedor.append(referencias);
    }

    function tablaIncidentes(filas) {
        const caja = crear('div', undefined, 'geo-tabla-incidentes');
        caja.tabIndex = 0;
        caja.setAttribute('aria-label', 'Resultados de los informes de daños');
        const tabla = crear('table');
        tabla.append(crear('caption', 'Cifras por distrito y por informe · COEN–INDECI'));
        const cabecera = tabla.createTHead().insertRow();
        for (const nombre of ['Distrito', 'Corte', 'Afectadas', 'Fuente y fotos']) {
            const th = crear('th', nombre);
            th.scope = 'col';
            cabecera.append(th);
        }
        const cuerpo = tabla.createTBody();
        for (const dato of filas) {
            const fila = cuerpo.insertRow();
            fila.insertCell().textContent = dato.distrito;
            fila.insertCell().textContent = dato.fecha;
            fila.insertCell().textContent = numero.format(dato.personas) + ' personas';
            const fuente = fila.insertCell();
            fuente.append(enlace('Reporte', dato.url));
            if (dato.fotos) fuente.append(' · ', enlace('Fotos', dato.fotos));
        }
        caja.append(tabla);
        return caja;
    }

    mostrarFicha();

    return { conectar, seleccionarPunto };
}
