import { LngLatBounds, Marker, Popup } from 'maplibre-gl';

export function botonPersona(etiqueta, cantidad = 1) {
    const boton = document.createElement('button');
    boton.className = 'avatar-persona atlas-avatar';
    boton.type = 'button';
    boton.setAttribute('aria-label', etiqueta);
    boton.title = etiqueta;
    boton.append(document.getElementById('icono-persona-mapa').content.cloneNode(true));
    if (cantidad > 1) {
        boton.classList.add('atlas-avatar-grupo');
        boton.append(String(cantidad));
    }
    return boton;
}

export async function iniciarPersonas(mapa, visor, alElegir, pedir) {
    const selector = document.getElementById('geo-persona');
    const visible = document.getElementById('geo-equipo-visible');
    const estado = document.getElementById('geo-persona-estado');
    const descripcion = estado.textContent;
    let personas;

    try {
        personas = (await pedir(visor.dataset.equipo)).personas;
    } catch {
        estado.textContent = 'No se pudo cargar el equipo del proyecto.';
        return;
    }
    if (!personas.length) {
        estado.textContent = 'El equipo aún no tiene ciudades de referencia.';
        return;
    }

    const encuadre = new LngLatBounds();
    for (const persona of personas) {
        selector.add(new Option(persona.nombre + ' · ' + persona.ciudad, String(persona.plaza)));
        encuadre.extend(coordenadas(persona));
    }
    selector.disabled = false;
    const ficha = new Popup({ className: 'atlas-popup', offset: 18, closeOnClick: true });
    let marcadores = [];
    let modo = '';

    selector.addEventListener('change', () => {
        const persona = personas.find(item => String(item.plaza) === selector.value);
        if (persona) elegir(persona);
        else {
            ficha.remove();
            estado.textContent = descripcion;
        }
    });
    visible.addEventListener('change', dibujar);
    mapa.on('zoomend', dibujar);
    dibujar();

    function elegir(persona) {
        selector.value = String(persona.plaza);
        visible.checked = true;
        const titulo = 'Referencia urbana · ' + persona.ciudad;
        estado.textContent = persona.nombre + ' · ' + titulo;
        const detalle = document.createElement('div');
        detalle.className = 'atlas-lectura-contenido';
        const nombre = document.createElement('strong');
        nombre.textContent = persona.nombre;
        detalle.append(nombre, titulo);
        const [lon, lat] = coordenadas(persona);
        alElegir(lon, lat);
        ficha.setDOMContent(detalle).setLngLat([lon, lat]).addTo(mapa);
        dibujar();
    }

    function dibujar() {
        const vista = !visible.checked ? 'oculto' : (mapa.getZoom() < 8 && personas.length > 1 ? 'grupo' : 'personas');
        if (vista === modo) return;
        modo = vista;
        for (const marcador of marcadores) marcador.remove();
        marcadores = [];
        if (modo === 'oculto') {
            ficha.remove();
            return;
        }
        if (modo === 'grupo') {
            agregar('Encuadrar al equipo de UrbanTwin', encuadre.getCenter(), personas.length,
                () => mapa.fitBounds(encuadre, { padding: 85, maxZoom: 11, duration: 460 }));
            return;
        }
        for (const persona of personas) {
            agregar(persona.nombre + ' · ' + persona.ciudad, coordenadas(persona), 1, () => elegir(persona));
        }
    }

    function agregar(etiqueta, posicion, cantidad, accion) {
        const boton = botonPersona(etiqueta, cantidad);
        boton.classList.add('atlas-equipo');
        boton.addEventListener('click', evento => {
            evento.stopPropagation();
            accion();
        });
        marcadores.push(new Marker({ element: boton }).setLngLat(posicion).addTo(mapa));
    }
}

function coordenadas(persona) {
    return [Number(persona.longitud), Number(persona.latitud)];
}
