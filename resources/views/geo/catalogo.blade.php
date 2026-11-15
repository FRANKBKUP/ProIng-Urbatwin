@extends('layouts.app')
@section('titulo', 'Catálogo geoespacial')

@section('contenido')
    <header class="territorio-hero">
        <div><h1>Catálogo geoespacial</h1><p>Publica, corrige o retira datos del visor. El historial conserva los archivos y las versiones utilizadas en los reportes.</p></div>
        @if($capas->isNotEmpty())<a class="btn" href="#cargar-geojson">Cargar GeoJSON</a>@endif
    </header>

    <section class="panel">
        <h2>Capas</h2>
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Nombre</th><th>Cobertura</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($capas as $capa)
                    <tr>
                        <td>{{ $capa->etiqueta ?? $capa->nombre }}</td><td>{{ $capa->cobertura }}</td><td>{{ $capa->tipo }}</td>
                        <td>{{ $capa->retirada_en ? 'Retirada' : ($capa->numero ? 'Publicada · v'.$capa->numero : 'Sin publicar') }}</td>
                        <td>@include('geo.gestion-capa', ['tipo' => 'capa', 'registro' => $capa->id, 'nombre' => $capa->nombre, 'etiqueta' => $capa->etiqueta, 'retirada' => $capa->retirada_en])</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aún no hay capas. Crea una cobertura y luego una capa para cargar el primer archivo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($temas->isNotEmpty())
        <section class="panel">
            <h2>Capas nacionales del atlas</h2>
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Nombre</th><th>Periodo</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    @foreach($temas as $tema)
                        <tr>
                            <td>{{ $tema->etiqueta ?? $tema->titulo }}</td><td>{{ $tema->periodo }}</td>
                            <td>{{ $tema->retirada_en ? 'Retirada' : 'Publicada' }}</td>
                            <td>@include('geo.gestion-capa', ['tipo' => 'tema', 'registro' => $tema->codigo, 'nombre' => $tema->titulo, 'etiqueta' => $tema->etiqueta, 'retirada' => $tema->retirada_en])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($capas->isNotEmpty())
        <section id="cargar-geojson" class="panel territorio-panel">
            <h2>Cargar un GeoJSON</h2>
            <p>Agrega una versión nueva o corrige una anterior usando un archivo actualizado. Si la capa está retirada, vuelve a activarla desde el catálogo cuando hayas comprobado la corrección.</p>
            <form class="formulario catalogo-carga" data-carga-geojson="{{ $maximoImportacion }}" method="post" enctype="multipart/form-data" action="{{ route('geo.catalogo.importar') }}">
                @csrf
                <fieldset>
                <legend>1. Elige la capa</legend>
                <label>Capa
                    <select name="capa_id" required>
                        @foreach($capas as $capa)
                            <option value="{{ $capa->id }}" data-tipo="{{ $capa->tipo }}" @selected(old('capa_id') == $capa->id)>
                                {{ $capa->etiqueta ?? $capa->nombre }} · {{ $capa->cobertura }} · {{ $capa->tipo }}{{ $capa->retirada_en ? ' · Retirada' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <p data-tipo-geojson></p>
                </fieldset>
                <fieldset>
                <legend>2. Selecciona el archivo</legend>
                <label>Archivo .geojson o .json <input type="file" name="archivo" accept=".geojson,.json,application/geo+json,application/json" aria-describedby="guia-geojson" required></label>
                <p id="guia-geojson">Exporta una colección de entidades (FeatureCollection) en WGS 84, en orden longitud y latitud. Admite hasta {{ str_replace('.', ',', (string) round($maximoImportacion / 1048576, 2)) }} MiB y 5.000 entidades dentro de la cobertura.</p>
                <output data-resumen-geojson aria-live="polite"></output>
                </fieldset>
                <fieldset>
                <legend>3. Registra la procedencia</legend>
                <label>Fuente <input name="fuente" value="{{ old('fuente') }}" maxlength="160" required></label>
                <label>Licencia o condición de uso <input name="licencia" value="{{ old('licencia') }}" maxlength="200" required></label>
                <label>Origen
                    <select name="origen" required>
                        <option value="" disabled @selected(old('origen') === null)>Selecciona el origen</option>
                        <option value="simulado" @selected(old('origen') === 'simulado')>Simulado</option>
                        <option value="observado" @selected(old('origen') === 'observado')>Observado</option>
                    </select>
                </label>
                <label>Fecha del dato <input type="date" name="fecha_dato" value="{{ old('fecha_dato') }}" required></label>
                </fieldset>
                <button class="btn" type="submit">Validar y publicar</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <h2>Historial de importaciones</h2>
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Capa</th><th>Archivo</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($importaciones as $carga)
                    <tr>
                        <td>{{ $carga->capa }}</td>
                        <td><a href="{{ route('geo.catalogo.importacion', $carga->id) }}">{{ $carga->archivo }}</a></td>
                        <td>{{ ucfirst($carga->estado) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Aún no hay importaciones.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $importaciones->links() }}
    </section>

    @if($coberturas->isNotEmpty())
        <section class="panel">
            <h2>Crear capa</h2>
            <form class="formulario" method="post" action="{{ route('geo.catalogo.capa') }}">
                @csrf
                <label>Nombre <input name="nombre" maxlength="120" required></label>
                <label>Cobertura
                    <select name="cobertura_id" required>
                        @foreach($coberturas as $cobertura)
                            <option value="{{ $cobertura->id }}">{{ $cobertura->nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Tipo
                    <select name="tipo" required>
                        <option value="peligro">Peligro · Polygon o MultiPolygon</option>
                        <option value="equipamiento">Equipamiento · Point</option>
                        <option value="vias">Vías · LineString o MultiLineString</option>
                        <option value="curvas">Curvas de nivel · líneas con cota_m</option>
                    </select>
                </label>
                <button class="btn" type="submit">Registrar capa</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <h2>Crear cobertura</h2>
        <form class="formulario" method="post" action="{{ route('geo.catalogo.cobertura') }}">
            @csrf
            <label>Nombre <input name="nombre" maxlength="120" required></label>
            <label>Geometría de cobertura (Polygon o MultiPolygon, CRS84)
                <textarea name="geojson" rows="4" maxlength="100000" required>{{ old('geojson') }}</textarea>
            </label>
            <button class="btn" type="submit">Registrar cobertura</button>
        </form>
    </section>
@endsection
