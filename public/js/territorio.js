(() => {
    const raiz = document.documentElement;
    const preferencia = window.matchMedia('(prefers-color-scheme: dark)');
    let tema = raiz.dataset.temaCuenta || leer('urbantwin:tema') || 'sistema';

    function leer(clave) {
        try { return window.localStorage.getItem(clave); } catch { return null; }
    }

    function recordar(clave, valor) {
        try { window.localStorage.setItem(clave, valor); } catch {}
    }

    function aplicar() {
        raiz.dataset.tema = tema === 'sistema' ? (preferencia.matches ? 'oscuro' : 'claro') : tema;
        const rapido = document.querySelector('[data-tema-rapido]');
        if (rapido) {
            const oscuro = raiz.dataset.tema === 'oscuro';
            rapido.setAttribute('aria-pressed', String(oscuro));
            rapido.textContent = oscuro ? 'Modo claro' : 'Modo oscuro';
        }
    }

    if (raiz.dataset.temaCuenta) recordar('urbantwin:tema', tema);
    aplicar();
    preferencia.addEventListener('change', aplicar);

    document.addEventListener('DOMContentLoaded', () => {
        aplicar();
        document.querySelector('[data-tema-rapido]')?.addEventListener('click', () => {
            tema = raiz.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';
            recordar('urbantwin:tema', tema);
            aplicar();
        });
        document.querySelector('[data-previsualizar-tema]')?.addEventListener('change', evento => {
            tema = evento.target.value;
            aplicar();
        });
        document.querySelectorAll('[data-abrir-dialogo]').forEach(boton => {
            boton.addEventListener('click', () => document.getElementById(boton.dataset.abrirDialogo)?.showModal());
        });
        document.querySelectorAll('form[data-confirmar]').forEach(formulario => {
            formulario.addEventListener('submit', evento => {
                if (!window.confirm(formulario.dataset.confirmar)) evento.preventDefault();
            });
        });
        document.querySelectorAll('[data-carga-geojson]').forEach(iniciarCargaGeoJson);

        const menu = document.getElementById('alternar-menu');
        if (menu) {
            const movil = matchMedia('(max-width: 880px)');
            const lateral = document.getElementById('menu-principal');
            const fondo = document.getElementById('menu-fondo');
            let contraido = movil.matches || leer('urbantwin:menu') === 'cerrado';
            const actualizarMenu = () => {
                raiz.classList.toggle('menu-contraido', contraido);
                menu.setAttribute('aria-expanded', String(!contraido));
                lateral.inert = contraido;
                fondo.hidden = contraido || !movil.matches;
            };
            const cerrarMenu = () => {
                contraido = true;
                actualizarMenu();
                menu.focus();
            };
            actualizarMenu();
            menu.addEventListener('click', () => {
                contraido = !contraido;
                if (!movil.matches) recordar('urbantwin:menu', contraido ? 'cerrado' : 'abierto');
                actualizarMenu();
                if (movil.matches && !contraido) lateral.querySelector('a, button')?.focus();
                window.dispatchEvent(new Event('resize'));
            });
            fondo.addEventListener('click', cerrarMenu);
            document.getElementById('menu-cerrar').addEventListener('click', cerrarMenu);
            document.addEventListener('keydown', evento => {
                if (evento.key === 'Escape' && movil.matches && !contraido) cerrarMenu();
            });
            movil.addEventListener('change', () => {
                contraido = movil.matches || leer('urbantwin:menu') === 'cerrado';
                actualizarMenu();
            });
        }

        iniciarGrafico();
        document.querySelectorAll('[data-pronostico-widget]').forEach(iniciarPronostico);
    });

    function iniciarCargaGeoJson(formulario) {
        const selector = formulario.elements.capa_id;
        const entrada = formulario.elements.archivo;
        const resumen = formulario.querySelector('[data-resumen-geojson]');
        const formatos = {
            peligro: 'Área de peligro · Polygon o MultiPolygon.',
            equipamiento: 'Ubicación de equipamiento · Point.',
            vias: 'Trazado vial · LineString o MultiLineString.',
            curvas: 'Curvas de nivel · LineString o MultiLineString con la propiedad cota_m en metros.',
        };
        const mostrarTipo = () => {
            formulario.querySelector('[data-tipo-geojson]').textContent = formatos[selector.selectedOptions[0]?.dataset.tipo] || '';
        };
        selector.addEventListener('change', mostrarTipo);
        mostrarTipo();

        entrada.addEventListener('change', async () => {
            const archivo = entrada.files[0];
            resumen.textContent = '';
            if (!archivo) return;
            if (archivo.size > Number(formulario.dataset.cargaGeojson)) {
                resumen.textContent = 'El archivo excede el límite indicado. Reduce el tamaño de la exportación.';
                return;
            }
            let descripcion;
            try {
                const datos = JSON.parse(await archivo.text());
                if (datos?.type !== 'FeatureCollection' || !Array.isArray(datos.features)) throw new Error();
                const tipos = [...new Set(datos.features.map(entidad => entidad?.geometry?.type || 'sin geometría'))];
                const cantidad = new Intl.NumberFormat('es-PE').format(datos.features.length);
                descripcion = `${cantidad} entidades${tipos.length ? ' · ' + tipos.slice(0, 5).join(', ') : ''}. La validación espacial se ejecuta al publicar.`;
            } catch {
                descripcion = 'No se pudo reconocer una colección GeoJSON. El catálogo puede mostrar el detalle del error al validar.';
            }
            if (entrada.files[0] === archivo) resumen.textContent = `${archivo.name} · ${descripcion}`;
        });
    }

    function iniciarGrafico() {
        const tarjeta = document.querySelector('[data-atlas-chart]');
        if (!tarjeta) return;
        const series = JSON.parse(document.getElementById('series-atlas').textContent);
        const selector = tarjeta.querySelector('select');
        const mapa = tarjeta.querySelector('[data-grafico-mapa]');
        const numero = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 0 });
        const dibujar = () => {
            const elegido = series[selector.value];
            const lista = tarjeta.querySelector('[data-grafico-leyenda]');
            const dona = tarjeta.querySelector('[data-grafico]');
            const segmentos = [];
            let inicio = 0;
            lista.replaceChildren();
            for (const serie of elegido.series) {
                const fin = inicio + serie.porcentaje;
                segmentos.push(serie.color + ' ' + inicio + '% ' + fin + '%');
                inicio = fin;
                const fila = document.createElement('li');
                const muestra = document.createElement('i');
                muestra.style.backgroundColor = serie.color;
                const nombre = document.createElement('span');
                nombre.textContent = serie.nombre;
                const valor = document.createElement('strong');
                valor.textContent = numero.format(serie.valor) + (elegido.unidad === 'km²' ? ' km²' : '');
                fila.append(muestra, nombre, valor);
                lista.append(fila);
            }
            dona.style.background = elegido.total ? 'conic-gradient(' + segmentos.join(',') + ')' : '';
            dona.setAttribute('aria-label', elegido.titulo + ' · ' + elegido.periodo + ' · ' + numero.format(elegido.total) + ' ' + elegido.unidad);
            tarjeta.querySelector('[data-grafico-total]').textContent = numero.format(elegido.total);
            tarjeta.querySelector('[data-grafico-unidad]').textContent = elegido.unidad;
            tarjeta.querySelector('[data-grafico-periodo]').textContent = elegido.periodo;
            tarjeta.querySelector('[data-grafico-fuente]').href = elegido.ficha_url;
            const url = new URL(mapa.href);
            url.searchParams.set('vista', selector.value);
            mapa.href = url.toString();
        };
        selector.addEventListener('change', dibujar);
        dibujar();
    }

    function iniciarPronostico(widget) {
        const formulario = widget.querySelector('form');
        const resultados = widget.querySelector('[data-pronostico-resultados]');
        formulario.addEventListener('submit', async evento => {
            evento.preventDefault();
            resultados.textContent = 'Consultando pronóstico…';
            evento.submitter.disabled = true;
            try {
                const codigo = formulario.elements.departamento.value;
                const respuesta = await fetch(widget.dataset.origen + '/' + encodeURIComponent(codigo), { headers: { Accept: 'application/json' } });
                if (!respuesta.ok) throw new Error('Pronóstico no disponible');
                const pronostico = await respuesta.json();
                if (!pronostico.disponible) {
                    resultados.textContent = 'El proveedor no tiene un pronóstico disponible para esta consulta.';
                    return;
                }
                resultados.replaceChildren();
                for (const dato of pronostico.dias) {
                    const dia = document.createElement('div');
                    const fecha = document.createElement('small');
                    const lluvia = document.createElement('strong');
                    const prob = document.createElement('small');
                    fecha.textContent = dato.fecha;
                    lluvia.textContent = dato.mm.toLocaleString('es-PE') + ' mm';
                    if (dato.probabilidad !== null) prob.textContent = dato.probabilidad + ' % prob. máx.';
                    dia.append(fecha, lluvia, prob);
                    resultados.append(dia);
                }
                const corte = document.createElement('small');
                corte.className = 'atlas-pronostico-corte';
                corte.textContent = 'Consultado ' + new Date(pronostico.consultado_en).toLocaleString('es-PE', { timeZone: 'America/Lima' }) + ' · hora de Perú';
                resultados.append(corte);
            } catch {
                resultados.textContent = 'No se pudo consultar el pronóstico. Vuelve a intentarlo más tarde.';
            } finally {
                evento.submitter.disabled = false;
            }
        });
        if (widget.closest('.atlas-dashboard-grid')) formulario.requestSubmit(formulario.querySelector('button[type="submit"]'));
    }
})();
