function crear(etiqueta, texto, clase) {
    const elemento = document.createElement(etiqueta);
    if (texto !== undefined) elemento.textContent = texto;
    if (clase) elemento.className = clase;
    return elemento;
}

const numero = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 });
const nombrar = texto => texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();

export function iniciarExploracion(visor, pedir) {
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
    const vacio = { type: 'FeatureCollection', features: [] };
    const movimientoReducido = matchMedia('(prefers-reduced-motion: reduce)');
    const casillaMapa = el('lluvia-mapa');
    const lineaTiempo = el('tiempo');
    const hora = el('hora');
    const reproducir = el('reproducir');
    const pronosticos = new Map();
    const pais = 'PE';
    let mapa, ficha, ambito, consulta, lluvia, meteorologia, temporizador;
    let pasos = [];
    let regionMapa = pais;

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
                region.value = item.region;
                region.dispatchEvent(new Event('change'));
                if (item.codigo !== item.region) {
                    provincia.value = item.codigo;
                    provincia.dispatchEvent(new Event('change'));
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

    function seleccionarAmbito(codigo, departamento) {
        consulta?.abort();
        ficha = null;
        ambito = ambitos.find(item => item.codigo === codigo);
        const acceso = el('ver-punto');
        acceso.textContent = 'Ver ficha de la zona';
        acceso.hidden = !ambito;
        estado.textContent = '';
        if (regionMapa !== (departamento || pais)) {
            regionMapa = departamento || pais;
            if (casillaMapa.checked) cargarMapa();
        }
        mostrarFicha();
    }

    async function seleccionarPunto(lon, lat) {
        consulta?.abort();
        const control = new AbortController();
        consulta = control;
        ficha = null;
        ambito = null;
        el('ver-punto').textContent = 'Ver ficha del punto';
        meteorologia?.abort();
        ubicacion.textContent = numero.format(lat) + '°, ' + numero.format(lon) + '°';
        estado.textContent = 'Cruzando capas territoriales…';
        for (const id of ['territorio', 'capas', 'pronostico', 'historico']) el('ficha-' + id).replaceChildren();
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
        const titulo = ambito?.nombre ?? (ficha ? 'Punto fuera de los límites disponibles' : 'Elige una región, provincia o punto en el mapa.');
        ubicacion.textContent = [distrito?.distrito && distrito.distrito !== titulo ? distrito.distrito : null, titulo].filter(Boolean).join(' · ');

        for (const filtro of filtros) {
            const tipo = filtro.dataset.consultaGeo;
            const contenedor = el('ficha-' + tipo);
            contenedor.hidden = !filtro.checked;
            contenedor.replaceChildren();
            if (!filtro.checked) continue;

            if (tipo === 'territorio' && (ambito || ficha)) {
                const territorio = [distrito?.ubigeo ? 'UBIGEO ' + distrito.ubigeo : null, ambito?.detalle,
                    distrito?.km2 != null ? numero.format(distrito.km2) + ' km² en la capa distrital' : null].filter(Boolean);
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
            }
        }
    }

    function tarjetaCapa(capa) {
        const tarjeta = crear('article', undefined, 'geo-ficha-tarjeta');
        tarjeta.append(crear('strong', capa.titulo), crear('span', capa.categoria || 'Sin cobertura en este punto'));
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
            const datos = pronosticos.get(clave) ?? await pedir(url(tipo, lugar.codigo), { signal });
            if (signal.aborted) return;
            if (datos.disponible) pronosticos.set(clave, datos);
            contenedor.replaceChildren();
            if (!datos.disponible) {
                contenedor.append(crear('p', 'El proveedor no devolvió datos suficientes. Vuelve a intentar más tarde.'));
                return;
            }
            if (tipo === 'historico') mostrarAnual(contenedor, datos);
            else mostrarPronostico(contenedor, datos);
        } catch (error) {
            if (!signal.aborted && error.name !== 'AbortError') {
                contenedor.replaceChildren(crear('p', 'La consulta no está disponible por el momento.'));
            }
        }
    }

    function mostrarPronostico(contenedor, datos) {
        contenedor.append(crear('h4', 'Pronóstico de 7 días'));
        const lista = crear('ul', undefined, 'geo-dias');
        for (const dia of datos.dias) {
            const fila = crear('li');
            const etiqueta = new Date(dia.fecha + 'T12:00:00').toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric', month: 'short' });
            fila.append(crear('span', etiqueta), crear('strong', numero.format(dia.mm) + ' mm'),
                crear('small', dia.probabilidad == null ? 'Probabilidad s/d' : dia.probabilidad + ' % máx.'));
            lista.append(fila);
        }
        contenedor.append(lista, crear('small', 'Open-Meteo · Estimación para un punto de ' + datos.lugar + '. Hora del Perú. Consulta: ' + formatoHora(datos.consultado_en.slice(0, 16)) + '.'));
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
                crear('span', anio.mm == null ? anio.dias + '/' + anio.esperados + ' días' : numero.format(anio.mm) + ' mm'));
            grafico.append(fila);
        }
        contenedor.append(crear('h4', 'Precipitación por año'), grafico, crear('small',
            datos.fuente + ' · Acumulados en un punto de ' + datos.lugar + '. Los años incompletos se muestran sin total.'));
    }

    function conectarMapa(instancia) {
        mapa = instancia;
        mapa.addSource('precipitacion-pronostico', {
            type: 'geojson', data: vacio, attribution: '<a href="https://open-meteo.com/">Open-Meteo · pronóstico</a>',
        });
        mapa.addLayer({
            id: 'precipitacion-calor', type: 'heatmap', source: 'precipitacion-pronostico',
            layout: { visibility: 'none' },
            paint: {
                'heatmap-weight': ['interpolate', ['linear'], ['get', 'mm'], 0, 0, 2, 0.35, 10, 1],
                'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 4, 25, 8, 52, 12, 85],
                'heatmap-intensity': 1.25,
                'heatmap-opacity': 0.83,
                'heatmap-color': ['interpolate', ['linear'], ['heatmap-density'], 0, 'rgba(18,60,194,0)',
                    0.2, '#246bf4', 0.43, '#23c6d9', 0.63, '#e7de5b', 0.82, '#fb893f', 1, '#c93768'],
            },
        }, 'puntos-lugares');
        mapa.addLayer({
            id: 'precipitacion-muestras', type: 'circle', source: 'precipitacion-pronostico',
            layout: { visibility: 'none' },
            paint: { 'circle-radius': 2.6, 'circle-color': '#f6fcff', 'circle-stroke-width': 1, 'circle-stroke-color': '#23597c' },
        }, 'puntos-lugares');
        casillaMapa.disabled = false;
    }

    async function cargarMapa() {
        lluvia?.abort();
        pausar();
        pasos = [];
        lineaTiempo.hidden = true;
        visor.classList.remove('geo-lluvia-activa');
        mapa?.getSource('precipitacion-pronostico').setData(vacio);
        mostrarLluvia(false);
        const aviso = el('capas-estado');
        aviso.textContent = '';
        if (!casillaMapa.checked || !mapa) return;

        const control = new AbortController();
        lluvia = control;
        aviso.textContent = 'Cargando las horas del pronóstico…';
        try {
            const datos = await pedir(url('mapa', regionMapa), { signal: control.signal });
            if (control.signal.aborted) return;
            pasos = datos.pasos;
            if (!datos.disponible || !pasos.length) {
                aviso.textContent = 'Sin pronóstico horario disponible para este ámbito.';
                return;
            }
            hora.max = pasos.length - 1;
            hora.value = 0;
            reproducir.disabled = pasos.length < 2;
            lineaTiempo.hidden = false;
            mostrarLluvia(true);
            visor.classList.add('geo-lluvia-activa');
            aviso.textContent = datos.puntos + ' puntos del modelo · campo orientativo, sin cobertura continua.';
            dibujarHora(0);
        } catch (error) {
            if (error.name !== 'AbortError') aviso.textContent = 'No se pudo cargar el mapa de precipitación.';
        }
    }

    function mostrarLluvia(visible) {
        for (const id of ['precipitacion-calor', 'precipitacion-muestras']) {
            mapa?.setLayoutProperty(id, 'visibility', visible ? 'visible' : 'none');
        }
    }

    function formatoHora(fecha) {
        const [dia, tiempo] = fecha.split('T');
        return dia.split('-').reverse().slice(0, 2).join('/') + ' · ' + tiempo?.slice(0, 5) + ' PET';
    }

    function dibujarHora(indice, fraccion = 0) {
        const paso = pasos[indice];
        if (!paso) return;
        const siguiente = pasos[indice + 1];
        const valores = new Map(siguiente?.muestras.map(punto => [punto.codigo, punto.mm]));
        mapa.getSource('precipitacion-pronostico').setData({
            type: 'FeatureCollection',
            features: paso.muestras.map(punto => ({
                type: 'Feature', geometry: { type: 'Point', coordinates: [punto.lon, punto.lat] },
                properties: { mm: punto.mm + ((valores.get(punto.codigo) ?? punto.mm) - punto.mm) * fraccion },
            })),
        });
        if (fraccion === 0) {
            hora.value = indice;
            el('hora-actual').textContent = formatoHora(paso.fecha);
            hora.setAttribute('aria-valuetext', formatoHora(paso.fecha));
        }
    }

    function pausar() {
        clearInterval(temporizador);
        temporizador = null;
        reproducir.textContent = 'Reproducir';
    }

    reproducir.addEventListener('click', () => {
        if (temporizador) { pausar(); return; }
        reproducir.textContent = 'Pausar';
        const subdivisiones = movimientoReducido.matches ? 1 : 5;
        let frame = Number(hora.value) * subdivisiones;
        temporizador = setInterval(() => {
            frame = (frame + 1) % ((pasos.length - 1) * subdivisiones + 1);
            dibujarHora(Math.floor(frame / subdivisiones), (frame % subdivisiones) / subdivisiones);
        }, 1000 / subdivisiones);
    });
    hora.addEventListener('input', () => { pausar(); dibujarHora(Number(hora.value)); });
    el('cerrar-tiempo').addEventListener('click', () => {
        casillaMapa.checked = false;
        cargarMapa();
    });
    casillaMapa.addEventListener('change', cargarMapa);
    mostrarFicha();

    return { conectarMapa, seleccionarPunto };
}
