@extends('layouts.app')
@section('titulo', 'Visor ciudadano')
@section('modo', 'modo-portal')

@section('barra-superior')
    <div class="geo-toolbar"><div><h1>Visor territorial</h1></div></div>
@endsection

@push('estilos')
    @vite('resources/js/geo.js')
@endpush

@section('contenido')
    <div class="geo-workspace" id="visor-geo" data-vista="{{ $vistaAtlas }}" data-modo="{{ $vistaAtlas === 'huella' ? 'huella' : 'atlas' }}"
        data-limites="{{ route('geo.atlas.limites') }}" data-teselas="{{ url('/api/geo/atlas') }}"
        data-ficha="{{ route('geo.atlas.ficha') }}" data-lluvia="{{ route('geo.lluvia', ['producto' => '__producto__', 'codigo' => '__codigo__']) }}"
        data-huella="{{ asset('piura-huella-2017.geojson') }}" data-titicaca="{{ asset('lago-titicaca.geojson') }}" data-img="{{ asset('img') }}"
        data-provincia="{{ route('geo.atlas.provincia', ['provincia' => '__codigo__']) }}" data-equipo="{{ route('geo.atlas.equipo') }}"
        data-perfiles="{{ route('geo.perfil.mapa') }}" data-pronostico="{{ url('/api/geo/pronostico') }}"
        data-lugares="{{ route('geo.lugares.mapa') }}" data-guardar="{{ route('geo.lugares.guardar') }}"
        data-lugar="{{ $lugar }}" data-grupo="{{ $grupo }}" data-satelite="{{ config('territorio.satelite_academico') ? '1' : '0' }}">
        <section class="geo-viewport" aria-label="Mapa interactivo">
            <div class="geo-map" id="mapa-geo"></div>
            <div class="geo-mapa-barra" aria-label="Herramientas principales">
                <button class="btn geo-accion-panel" id="geo-ampliar" type="button" aria-controls="geo-panel-lectura" aria-expanded="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></svg>
                    <span>Explorar</span>
                </button>
                <button class="btn-sec" id="geo-pantalla" type="button" aria-pressed="false" aria-label="Pantalla completa">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4H4v5m11-5h5v5M4 15v5h5m11-5v5h-5"/></svg>
                    <span>Pantalla completa</span>
                </button>
                <button class="btn-sec" id="geo-lluvia-abrir" type="button" aria-controls="geo-tiempo" aria-expanded="false" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 15a4 4 0 1 1 1-7 6 6 0 0 1 11 2 3 3 0 1 1 1 5M8 18l-1 2m5-2-1 2m5-2-1 2"/></svg>
                    Lluvia
                </button>
            </div>
            <details class="geo-map-tools geo-ajustes">
                <summary>Mapa base y medidas</summary>
                <div class="geo-ajustes-opciones">
                <label class="sr-only" for="geo-base">Mapa base</label>
                <select id="geo-base" disabled>
                    <option value="urbano">Cartografía</option>
                    @if(config('territorio.satelite_academico'))
                        <option value="satelite">Satélite Sentinel-2 · 2024</option>
                    @endif
                    <option value="ninguno">Solo capas</option>
                </select>
                <button class="btn-sec" id="geo-terreno" type="button" aria-pressed="false" disabled>Terreno 3D</button>
                <button class="btn-sec" id="geo-medir" type="button" aria-pressed="false" disabled>Medir</button>
                <button class="btn-sec" id="geo-centrar" type="button" disabled>Encuadrar</button>
                </div>
            </details>
            <p id="geo-mapa-estado" class="geo-mapa-estado" role="status">Cargando mapa…</p>
            <button type="button" class="btn geo-boton-punto" id="geo-ver-punto" aria-haspopup="dialog" hidden>Ver ficha del punto</button>
            <p id="geo-medida" class="geo-medida" role="status" hidden></p>
            <div class="geo-legend geo-legend-huella" aria-label="Leyenda de huellas">
                <span><i class="geo-muestra peligro"></i><span id="geo-leyenda-peligro">Huella de inundación</span></span>
                <span><i class="geo-muestra cobertura"></i>Área analizada</span>
            </div>
            <div id="geo-atlas-leyenda" class="geo-legend geo-legend-atlas" aria-label="Leyenda de la capa"></div>
            <div class="geo-tiempo" id="geo-tiempo" hidden>
                <div class="geo-tiempo-cabecera">
                    <strong>Lluvia en el mapa</strong>
                    <button type="button" class="btn-sec" id="geo-cerrar-tiempo" aria-label="Cerrar mapa de precipitación">×</button>
                </div>
                <div class="geo-tiempo-modos" role="group" aria-label="Fuente de la animación">
                    <button type="button" class="btn-sec" data-lluvia-modo="pronostico" aria-pressed="true">Pronóstico 24 h</button>
                    <button type="button" class="btn-sec" data-lluvia-modo="satelite" aria-pressed="false">Satélite reciente</button>
                </div>
                <p id="geo-tiempo-estado" role="status"></p>
                <button type="button" class="btn-sec" id="geo-intentar-tiempo" hidden>Volver a consultar</button>
                <div class="geo-tiempo-controles">
                    <button type="button" class="btn-sec" id="geo-reproducir">Reproducir</button>
                    <label for="geo-hora"><output id="geo-hora-actual" for="geo-hora"></output></label>
                    <input id="geo-hora" type="range" min="0" max="0" value="0" step="1">
                </div>
                <p class="geo-tiempo-escala"><i aria-hidden="true"></i><span>Lluvia más suave → más intensa</span></p>
                <p class="geo-tiempo-nasa"><a href="https://worldview.earthdata.nasa.gov/" target="_blank" rel="noopener noreferrer">Ver escala satelital en NASA Worldview</a></p>
                <label class="geo-tiempo-opacidad" for="geo-lluvia-opacidad">Intensidad del color <input type="range" id="geo-lluvia-opacidad" min="15" max="85" value="48"></label>
            </div>
        </section>

        <button class="geo-panel-fondo" id="geo-panel-fondo" type="button" aria-label="Cerrar explorador" tabindex="-1" hidden></button>
        <aside class="panel geo-panel" id="geo-panel-lectura" aria-label="Capas y lectura territorial">
            <div class="atlas-panel-cabecera">
                <div><h2>Explorar el territorio</h2></div>
                <div class="geo-panel-acciones">
                    <button type="button" class="btn-sec" data-abrir-dialogo="guia-visor">Cómo usar</button>
                    <button type="button" class="btn-sec" id="geo-cerrar-panel" aria-label="Ocultar explorador">×</button>
                </div>
            </div>
            <section class="geo-localizar">
                <label for="geo-buscar-ambito">Buscar región o provincia</label>
                <input type="search" id="geo-buscar-ambito" placeholder="Ej. Piura o Sullana" autocomplete="off" aria-controls="geo-sugerencias">
                <ul id="geo-sugerencias" class="geo-sugerencias" hidden></ul>
            </section>
            <details class="geo-detalle geo-capas" open>
            <summary>Capas del mapa</summary>
            <label for="geo-tema">Capa de análisis</label>
            <select id="geo-tema">
                @foreach($temasAtlas as $tema)
                    <option value="{{ $tema->codigo }}" @selected($vistaAtlas === $tema->codigo)>
                        {{ $tema->tipo === 'riesgo' ? $tema->titulo.' · CENEPRED' : $tema->periodo }}
                    </option>
                @endforeach
                <option value="huella" @selected($vistaAtlas === 'huella')>Huella observada · Piura 2017</option>
            </select>
            <div class="geo-modulos">
                <label class="geo-check"><input type="checkbox" id="geo-mostrar-capa" checked disabled> Mostrar capa de análisis</label>
                <label class="geo-tiempo-opacidad" for="geo-tema-opacidad">Relleno de la capa <input type="range" id="geo-tema-opacidad" min="0" max="80" value="22" disabled></label>
                <label class="geo-check"><input type="checkbox" id="geo-agua" checked disabled> Lagos y ríos</label>
                <label class="geo-check"><input type="checkbox" id="geo-incidentes" checked disabled> Inundaciones documentadas y reportes</label>
                <label class="geo-check"><input type="checkbox" id="geo-curvas" disabled> Curvas de nivel · 200 m</label>
                <label class="geo-check"><input type="checkbox" id="geo-lluvia-mapa" disabled> Animación de precipitación</label>
                <p id="geo-capas-estado" role="status"></p>
            </div>
            <div id="geo-atlas-detalle" class="atlas-detalle" @if($vistaAtlas === 'huella') hidden @endif>
                <p id="geo-atlas-periodo"></p>
                <div id="geo-atlas-lectura" class="atlas-lectura" role="status">
                    <strong>Lectura del mapa</strong>
                    <p>Pasa el cursor por un área. Haz clic o toca el mapa para fijar la lectura.</p>
                </div>
                <a id="geo-atlas-fuente" href="#" target="_blank" rel="noopener noreferrer" hidden>Consultar fuente</a>
            </div>
            </details>
            <section class="geo-detalle geo-navegacion" aria-labelledby="geo-navegacion-titulo">
                <h3 id="geo-navegacion-titulo">Ir a una ubicación</h3>
                <div class="geo-coords">
                    <label for="geo-region">Región o departamento
                        <select id="geo-region">
                            <option value="">Todo el Perú</option>
                            @foreach($departamentosAtlas as $departamento)
                                <option value="{{ $departamento->codigo }}">{{ $departamento->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label for="geo-provincia">Provincia
                        <select id="geo-provincia" disabled><option value="">Elige un departamento</option></select>
                    </label>
                </div>
                <p id="geo-navegacion-estado" role="status" hidden></p>
                <form id="geo-form" class="geo-form" autocomplete="off">
                    <p id="geo-instruccion">Usa un punto del mapa o ingresa coordenadas para centrar y consultar la capa activa.</p>
                    <div class="geo-coords">
                        <label>Longitud <input id="geo-lon" type="number" step="any" required min="-180" max="180" placeholder="-80.63"></label>
                        <label>Latitud <input id="geo-lat" type="number" step="any" required min="-90" max="90" placeholder="-5.19"></label>
                    </div>
                    <div class="geo-acciones">
                        <button type="submit" class="btn" id="geo-consultar">Centrar y consultar</button>
                        <button type="button" class="btn-sec" id="geo-ubicacion" hidden>Mi ubicación</button>
                    </div>
                </form>
                <div class="geo-result" id="geo-resultado" role="status" aria-live="polite" hidden></div>
            </section>
            <div class="geo-detalle">
                <button type="button" class="btn-sec" id="geo-abrir-ficha" aria-haspopup="dialog">Abrir ficha y gráficos</button>
            </div>
            <details class="geo-detalle geo-personas">
                <summary>Personas en el mapa</summary>
                <label for="geo-persona">Equipo del proyecto</label>
                <select id="geo-persona" disabled><option value="">Selecciona una persona</option></select>
                <label class="geo-check atlas-profiles-switch"><input type="checkbox" id="geo-equipo-visible" checked> Mostrar equipo del proyecto</label>
                <label class="geo-check atlas-profiles-switch"><input type="checkbox" id="geo-perfiles" checked> Mostrar perfiles que comparten su región</label>
                <p id="geo-persona-estado" role="status">Los iconos del equipo señalan ciudades de referencia.</p>
            </details>
            <div class="atlas-archivos">
              <h3 id="titulo-consulta">Huellas históricas de Piura</h3>
            <label for="geo-capa">Observación de inundación</label>
            <select id="geo-capa" @disabled($capas->isEmpty())>
                @forelse($capas as $capa)
                    <option value="{{ $capa->capa_id }}" @selected($seleccion === $capa->capa_id)
                        data-mapa="{{ route('geo.capa', $capa->capa_id) }}"
                        data-consulta="{{ route('geo.consultar', $capa->capa_id) }}">
                        {{ $capa->nombre }}
                    </option>
                @empty
                    <option value="">Sin capas publicadas</option>
                @endforelse
            </select>

            <p id="geo-contexto" class="geo-contexto" @if($capas->isNotEmpty()) hidden @endif>El catálogo aún no tiene observaciones para consultar.</p>

            <details class="geo-detalle" id="geo-detalle-datos" hidden>
                <summary>Fuente y visualización</summary>
                <dl class="geo-meta">
                    <div><dt>Cobertura</dt><dd id="geo-cobertura"></dd></div>
                    <div><dt>Fuente</dt><dd id="geo-fuente"></dd></div>
                    <div><dt>Versión</dt><dd id="geo-version"></dd></div>
                    <div><dt>Fecha del dato</dt><dd id="geo-fecha"></dd></div>
                    <div id="geo-escala-fila" hidden><dt>Escala fuente</dt><dd id="geo-escala"></dd></div>
                    <div><dt>Crédito</dt><dd id="geo-licencia"></dd></div>
                </dl>
                <a id="geo-ficha" href="#" target="_blank" rel="noopener noreferrer" hidden>Ficha del producto</a>
                <label for="geo-opacidad">Opacidad de la huella</label>
                <input id="geo-opacidad" type="range" min="15" max="90" value="48">
                <label class="geo-check"><input id="geo-cobertura-visible" type="checkbox"> Mostrar límite del área de estudio EMSR199</label>
            </details>
            </div>

            <details class="geo-detalle" @if($grupo || $lugar) open @endif>
                <summary>Lugares en el mapa</summary>
                <label for="geo-grupo">Mostrar ubicaciones</label>
                <select id="geo-grupo">
                    <option value="">Mis lugares</option>
                    @foreach($grupos as $equipo)
                        <option value="{{ $equipo->id }}" @selected($grupo === $equipo->id)>{{ $equipo->nombre }}</option>
                    @endforeach
                </select>
                <label for="geo-buscar-lugar">Buscar lugar o miembro</label>
                <input id="geo-buscar-lugar" type="search" autocomplete="off">
                <div id="geo-lista-lugares" class="geo-lista-lugares"></div>
                <a href="{{ route('geo.lugares.index') }}">Administrar lugares y grupos</a>
            </details>

            <details class="geo-detalle">
                <summary>Guardar punto</summary>
                <form id="geo-form-lugar" class="geo-form">
                    <p>El punto elegido se guarda como privado en tu cuenta.</p>
                    <label>Nombre del lugar <input id="geo-nombre-lugar" maxlength="80" required></label>
                    <button type="submit" class="btn-sec">Guardar en mis lugares</button>
                    <p id="geo-lugar-estado" role="status"></p>
                </form>
            </details>
        </aside>
    @include('geo.exploracion')
    <dialog id="guia-visor" class="atlas-dialogo">
        <form method="dialog"><button class="btn-sec" aria-label="Cerrar guía">Cerrar</button></form>
        <h2>Cómo explorar el visor</h2>
        <ol>
            <li><strong>Elige una capa.</strong> Consulta el escenario distrital de CENEPRED, la precipitación histórica o las huellas de Piura.</li>
            <li><strong>Encuentra un lugar.</strong> Escribe una región o provincia, ingresa coordenadas o usa «Mi ubicación».</li>
            <li><strong>Compara datos.</strong> Pasa el cursor para leer la capa activa. Haz clic o toca para fijar el punto y elige qué consultar en la ficha.</li>
            <li><strong>Explora la lluvia.</strong> Activa la animación y mueve el control de hora, o revisa el pronóstico y los años completos en la ficha.</li>
            <li><strong>Guarda un lugar.</strong> Selecciona un punto y abre «Guardar punto». Los avatares regionales indican zonas aproximadas elegidas por cada cuenta.</li>
        </ol>
    </dialog>
    </div>
    <script id="temas-atlas" type="application/json">@json($temasAtlas)</script>
    <script id="provincias-atlas" type="application/json">@json($provinciasAtlas)</script>
    <template id="icono-persona-mapa"><x-icono n="persona"/></template>
@endsection
