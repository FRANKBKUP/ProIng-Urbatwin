(() => {
    const formulario = document.querySelector('[data-lluvia-rango]');
    if (!formulario) return;
    const selector = formulario.querySelector('[name="serie"]');
    const desde = formulario.querySelector('[name="desde"]');
    const hasta = formulario.querySelector('[name="hasta"]');
    const notificacion = formulario.querySelector('[data-lluvia-notificacion]');
    const agrupacion = formulario.querySelector('[name="agrupar"]');
    const calendario = formulario.querySelector('[data-calendario-lluvia]');
    const desplegable = calendario.closest('details');
    const lectura = document.querySelector('[data-lluvia-lectura]');
    const datosDiarios = document.querySelector('[data-lluvia-datos-diarios]');
    let datosAbiertosAntesDeImprimir = false;
    window.addEventListener('beforeprint', () => {
        if (!datosDiarios) return;
        datosAbiertosAntesDeImprimir = datosDiarios.open;
        datosDiarios.open = true;
    });
    window.addEventListener('afterprint', () => {
        if (datosDiarios) datosDiarios.open = datosAbiertosAntesDeImprimir;
    });
    formulario.addEventListener('submit', evento => {
        const dias = (Date.parse(`${hasta.value}T00:00:00Z`) - Date.parse(`${desde.value}T00:00:00Z`)) / 86400000 + 1;
        notificacion.hidden = !(agrupacion.value === 'diario' && dias > 366);
        if (!notificacion.hidden) {
            evento.preventDefault();
            agrupacion.focus();
        }
    });
    agrupacion.addEventListener('change', () => { notificacion.hidden = true; });
    document.querySelectorAll('[data-lluvia-dia]').forEach(barra => {
        const mostrar = () => {
            const parcial = Number(barra.dataset.lluviaParcial) > 0 ? ' · acumulado parcial' : '';
            lectura.textContent = `${barra.dataset.lluviaDia} · ${barra.dataset.lluviaValor} mm de precipitación estimada${parcial}`;
        };
        barra.addEventListener('click', mostrar);
        barra.addEventListener('focus', mostrar);
        barra.addEventListener('mouseenter', mostrar);
    });
    document.addEventListener('click', evento => {
        if (desplegable.open && !evento.composedPath().includes(desplegable)) desplegable.open = false;
    });
    document.addEventListener('keydown', evento => {
        if (evento.key === 'Escape' && desplegable.open) {
            desplegable.open = false;
            desplegable.querySelector('summary').focus();
        }
    });
    let mes = desde.value.slice(0, 7);
    let esperaFin = false;
    let arrastre = null;
    let ignorarClick = false;
    const fechaTexto = fecha => new Intl.DateTimeFormat('es-PE', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(fecha + 'T12:00:00Z'));
    const previsualizar = (primero, ultimo) => {
        const [inicio, fin] = primero <= ultimo ? [primero, ultimo] : [ultimo, primero];
        calendario.querySelectorAll('[data-fecha]').forEach(boton => {
            const fecha = boton.dataset.fecha;
            const enRango = fecha >= inicio && fecha <= fin;
            boton.classList.toggle('en-rango', enRango);
            boton.classList.toggle('extremo', fecha === inicio || fecha === fin);
            boton.setAttribute('aria-pressed', String(enRango));
        });
    };
    const seleccionar = (primero, ultimo) => {
        [desde.value, hasta.value] = primero <= ultimo ? [primero, ultimo] : [ultimo, primero];
        notificacion.hidden = true;
        dibujar();
    };
    desde.closest('.lluvia-fechas').hidden = true;
    const mesRelativo = (valor, diferencia) => {
        const [ano, numero] = valor.split('-').map(Number);
        const fecha = new Date(Date.UTC(ano, numero - 1 + diferencia, 1));
        return fecha.toISOString().slice(0, 7);
    };
    function dibujar() {
        const formatoCorto = fecha => fecha.split('-').reverse().join('/');
        formulario.querySelector('[data-lluvia-rango-texto]').textContent = `${formatoCorto(desde.value)} – ${formatoCorto(hasta.value)}`;
        calendario.replaceChildren();
        const cabecera = document.createElement('div');
        cabecera.className = 'lluvia-calendario-cabecera';
        const anterior = document.createElement('button');
        anterior.type = 'button'; anterior.textContent = '‹'; anterior.setAttribute('aria-label', 'Mes anterior');
        anterior.disabled = mes <= desde.min.slice(0, 7);
        anterior.addEventListener('click', () => { mes = mesRelativo(mes, -1); dibujar(); });
        const controles = document.createElement('div');
        controles.className = 'lluvia-calendario-selectores';
        const elegirMes = document.createElement('select');
        elegirMes.setAttribute('aria-label', 'Mes del calendario');
        for (let i = 1; i <= 12; i++) {
            const numero = String(i).padStart(2, '0');
            const valor = `${mes.slice(0, 4)}-${numero}`;
            const nombre = new Intl.DateTimeFormat('es-PE', { month: 'long', timeZone: 'UTC' }).format(new Date(`2020-${numero}-01T12:00:00Z`));
            const opcion = new Option(nombre, valor, false, valor === mes);
            opcion.disabled = valor < desde.min.slice(0, 7) || valor > hasta.max.slice(0, 7);
            elegirMes.add(opcion);
        }
        elegirMes.addEventListener('change', () => { mes = elegirMes.value; dibujar(); });
        const elegirAno = document.createElement('select');
        elegirAno.setAttribute('aria-label', 'Año del calendario');
        for (let ano = Number(desde.min.slice(0, 4)); ano <= Number(hasta.max.slice(0, 4)); ano++) {
            elegirAno.add(new Option(String(ano), String(ano), false, String(ano) === mes.slice(0, 4)));
        }
        elegirAno.addEventListener('change', () => {
            const candidato = `${elegirAno.value}-${mes.slice(5)}`;
            mes = candidato < desde.min.slice(0, 7) ? desde.min.slice(0, 7) : candidato > hasta.max.slice(0, 7) ? hasta.max.slice(0, 7) : candidato;
            dibujar();
        });
        controles.append(elegirMes, elegirAno);
        const siguiente = document.createElement('button');
        siguiente.type = 'button'; siguiente.textContent = '›'; siguiente.setAttribute('aria-label', 'Mes siguiente');
        siguiente.disabled = mes >= hasta.max.slice(0, 7);
        siguiente.addEventListener('click', () => { mes = mesRelativo(mes, 1); dibujar(); });
        cabecera.append(anterior, controles, siguiente);
        const accesos = document.createElement('div');
        accesos.className = 'lluvia-calendario-accesos';
        for (const [etiqueta, inicio, fin] of [
            ['Todo el mes', `${mes}-01`, `${mes}-${String(new Date(Date.UTC(Number(mes.slice(0, 4)), Number(mes.slice(5)), 0)).getUTCDate()).padStart(2, '0')}`],
            ['Todo el año', `${mes.slice(0, 4)}-01-01`, `${mes.slice(0, 4)}-12-31`],
        ]) {
            const boton = document.createElement('button');
            boton.type = 'button'; boton.textContent = etiqueta;
            boton.addEventListener('click', () => {
                seleccionar(inicio < desde.min ? desde.min : inicio, fin > hasta.max ? hasta.max : fin);
                esperaFin = false; desplegable.open = false; desplegable.querySelector('summary').focus();
            });
            accesos.append(boton);
        }
        const ayuda = document.createElement('p');
        ayuda.className = 'lluvia-calendario-ayuda';
        ayuda.textContent = 'Elige dos fechas o arrastra para seleccionar un rango.';
        const rejilla = document.createElement('div');
        rejilla.className = 'lluvia-calendario-rejilla';
        for (const dia of ['L', 'M', 'M', 'J', 'V', 'S', 'D']) {
            const etiqueta = document.createElement('span'); etiqueta.textContent = dia; etiqueta.setAttribute('aria-hidden', 'true'); rejilla.append(etiqueta);
        }
        const [ano, numero] = mes.split('-').map(Number);
        const inicioSemana = (new Date(Date.UTC(ano, numero - 1, 1)).getUTCDay() + 6) % 7;
        for (let i = 0; i < inicioSemana; i++) rejilla.append(document.createElement('span'));
        const dias = new Date(Date.UTC(ano, numero, 0)).getUTCDate();
        for (let dia = 1; dia <= dias; dia++) {
            const valor = `${mes}-${String(dia).padStart(2, '0')}`;
            const boton = document.createElement('button');
            boton.type = 'button'; boton.textContent = dia; boton.dataset.fecha = valor;
            boton.disabled = valor < desde.min || valor > hasta.max;
            boton.setAttribute('aria-label', fechaTexto(valor));
            const enRango = valor >= desde.value && valor <= hasta.value;
            boton.setAttribute('aria-pressed', String(enRango));
            boton.classList.toggle('en-rango', enRango);
            boton.classList.toggle('extremo', valor === desde.value || valor === hasta.value);
            boton.addEventListener('click', () => {
                if (ignorarClick) { ignorarClick = false; return; }
                if (!esperaFin) {
                    seleccionar(valor, valor);
                    esperaFin = true;
                    calendario.querySelector(`[data-fecha="${valor}"]`)?.focus();
                } else {
                    seleccionar(desde.value, valor);
                    esperaFin = false;
                    desplegable.open = false;
                    desplegable.querySelector('summary').focus();
                }
            });
            boton.addEventListener('pointerdown', evento => {
                if (evento.button !== 0) return;
                arrastre = { inicio: valor, fin: valor, pointerId: evento.pointerId };
                boton.setPointerCapture(evento.pointerId);
            });
            rejilla.append(boton);
        }
        calendario.append(cabecera, accesos, ayuda, rejilla);
    }
    document.addEventListener('pointermove', evento => {
        if (!arrastre || evento.pointerId !== arrastre.pointerId) return;
        const boton = document.elementFromPoint(evento.clientX, evento.clientY)?.closest('[data-fecha]');
        if (boton && calendario.contains(boton) && !boton.disabled && boton.dataset.fecha !== arrastre.fin) {
            arrastre.fin = boton.dataset.fecha;
            previsualizar(arrastre.inicio, arrastre.fin);
        }
    });
    document.addEventListener('pointerup', evento => {
        if (!arrastre || evento.pointerId !== arrastre.pointerId) return;
        if (arrastre.fin !== arrastre.inicio) {
            ignorarClick = true;
            seleccionar(arrastre.inicio, arrastre.fin);
            esperaFin = false;
            desplegable.open = false;
            setTimeout(() => { ignorarClick = false; }, 0);
        }
        arrastre = null;
    });
    document.addEventListener('pointercancel', evento => {
        if (arrastre && evento.pointerId === arrastre.pointerId) {
            arrastre = null;
            previsualizar(desde.value, hasta.value);
        }
    });
    selector.addEventListener('change', () => {
        notificacion.hidden = true;
        const opcion = selector.selectedOptions[0];
        desde.min = hasta.min = opcion.dataset.inicio;
        desde.max = hasta.max = opcion.dataset.fin;
        desde.value = opcion.dataset.inicio; hasta.value = opcion.dataset.fin;
        mes = desde.value.slice(0, 7); esperaFin = false; dibujar();
    });
    for (const entrada of [desde, hasta]) entrada.addEventListener('change', () => {
        notificacion.hidden = true;
        if (entrada.value) { mes = entrada.value.slice(0, 7); esperaFin = false; dibujar(); }
    });
    dibujar();
})();
