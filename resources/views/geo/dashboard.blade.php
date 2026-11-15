@extends('layouts.app')
@section('titulo', 'Panel territorial')

@section('contenido')
    <header class="territorio-hero atlas-hero">
        <img src="{{ asset('img/piura-modis-2017.jpg') }}" alt="Costa norte del Perú y superficie inundada captadas por MODIS Aqua en marzo de 2017">
        <div>
            <p class="territorio-localidad">PERÚ / ATLAS TERRITORIAL</p>
            <h1>Panel territorial</h1>
            <p>Explora el riesgo distrital, la lluvia de marzo de 2017 y la distribución geográfica de las inundaciones en Piura.</p>
            <a class="credito-imagen" href="https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/" target="_blank" rel="noopener noreferrer">NASA Earth Observatory · 23 mar 2017</a>
        </div>
        <a class="btn" href="{{ route('geo.portal') }}"><x-icono n="mapa"/> Abrir visor</a>
    </header>

    <section class="territorio-metricas" aria-label="Resumen territorial">
        <article><span>Capas nacionales</span><strong>{{ count($resumenAtlas) }}</strong><small>{{ $total }} huellas históricas adicionales en el catálogo</small></article>
        <a href="{{ route('geo.lugares.index') }}"><span>Mis lugares</span><strong>{{ $lugares }}</strong><small>Ubicaciones guardadas en tu cuenta</small></a>
        <a href="{{ route('geo.lugares.index') }}#grupos"><span>Mis grupos</span><strong>{{ $grupos }}</strong><small>Espacios compartidos por invitación</small></a>
    </section>

    @if(count($resumenAtlas) || $departamentosAtlas->isNotEmpty())
        <div class="atlas-dashboard-grid">
            @if(count($resumenAtlas))
            <section class="panel territorio-panel" data-atlas-chart>
                <h2>Distribución por categoría</h2>
                <label for="serie-atlas">Capa de análisis</label>
                <select id="serie-atlas">
                    @foreach($resumenAtlas as $codigo => $serie)
                        <option value="{{ $codigo }}">{{ $serie['unidad'] === 'distritos' ? $serie['titulo'] : $serie['periodo'] }}</option>
                    @endforeach
                </select>
                <p data-grafico-periodo></p>
                <div class="atlas-chart-layout">
                    <div class="atlas-donut" data-grafico role="img"><div><strong data-grafico-total></strong><small data-grafico-unidad></small></div></div>
                    <ul class="atlas-chart-leyenda" data-grafico-leyenda></ul>
                </div>
                <div class="territorio-inline">
                    <a data-grafico-fuente href="#" target="_blank" rel="noopener noreferrer">Ver fuente</a>
                    <a data-grafico-mapa href="{{ route('geo.portal') }}">Abrir capa en el visor</a>
                </div>
                <script id="series-atlas" type="application/json">@json($resumenAtlas)</script>
            </section>
            @endif
            @if($departamentosAtlas->isNotEmpty())
                @include('geo.pronostico', ['departamentos' => $departamentosAtlas, 'compacto' => false])
            @endif
        </div>
    @endif

    <section class="panel territorio-panel">
        <div class="territorio-panel-titulo">
            <div><h2>Inundaciones observadas</h2>
                <p>El área se calcula sobre la unión de las huellas de cada fecha. Consulta la cobertura y escala de su fuente.</p></div>
        </div>
        <div class="territorio-tabla">
            <table>
                <thead><tr><th>Evento y fuente</th><th>Fecha de observación</th><th>Huella delimitada</th><th>Cobertura analizada</th><th><span class="sr-only">Abrir mapa</span></th></tr></thead>
                <tbody>
                    @forelse($capas as $capa)
                        <tr>
                            <th scope="row"><strong>{{ $capa->nombre }}</strong><small>{{ $capa->fuente }}</small></th>
                            <td>{{ $capa->fecha_dato }}<small>Versión {{ $capa->numero }}</small></td>
                            <td>{{ $capa->km2 !== null ? number_format($capa->km2, 2, ',', ' ').' km²' : '—' }}</td>
                            <td>{{ number_format($capa->cobertura_km2, 2, ',', ' ') }} km²</td>
                            <td><a class="btn-sec" href="{{ route('geo.portal', ['capa' => $capa->capa_id]) }}">Ver en mapa</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">El catálogo aún no tiene capas de inundación observada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $capas->links() }}
    </section>

    <section class="panel territorio-panel">
        <h2>El Niño Costero de 2017 en imágenes</h2>
        <div class="atlas-galeria">
            <figure>
                <a href="{{ asset('img/piura-modis-2017.jpg') }}" target="_blank" rel="noopener noreferrer"><img loading="lazy" src="{{ asset('img/piura-modis-2017.jpg') }}" alt="Vista satelital de las inundaciones en la costa norte de Perú"></a>
                <figcaption><strong>Piura y lago La Niña</strong><span>23 de marzo de 2017 · MODIS Aqua</span><a href="https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/" target="_blank" rel="noopener noreferrer">NASA Earth Observatory</a></figcaption>
            </figure>
            <figure>
                <a href="{{ asset('img/parachique-landsat-2017.jpg') }}" target="_blank" rel="noopener noreferrer"><img loading="lazy" src="{{ asset('img/parachique-landsat-2017.jpg') }}" alt="Agua de inundación cerca de Parachique en el litoral de Piura"></a>
                <figcaption><strong>Parachique y el litoral sur de Piura</strong><span>4 de abril de 2017 · Landsat 8 OLI</span><a href="https://science.nasa.gov/earth/earth-observatory/flooding-in-northern-peru-89978/" target="_blank" rel="noopener noreferrer">NASA Earth Observatory</a></figcaption>
            </figure>
        </div>
    </section>
@endsection
