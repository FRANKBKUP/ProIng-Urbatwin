@extends('layouts.app')
@section('titulo', 'Detalle de importación')

@section('contenido')
    <header class="cabecera">
        <a href="{{ route('geo.catalogo.index') }}">Volver al catálogo</a>
        <h1>{{ $importacion->capa }}</h1>
    </header>
    <section class="panel">
        <h2>{{ $importacion->estado === 'publicada' ? 'Publicada · v'.$importacion->numero : 'Importación rechazada' }}</h2>
        <dl>
            <dt>Archivo recibido</dt><dd>{{ $importacion->archivo }}</dd>
            <dt>Fuente</dt><dd>{{ $importacion->fuente }}</dd>
            <dt>Condición de uso</dt><dd>{{ $importacion->licencia }}</dd>
            <dt>Fecha del dato</dt><dd>{{ $importacion->fecha_dato }}</dd>
            <dt>SHA-256</dt><dd><code style="overflow-wrap: anywhere">{{ $importacion->sha256 }}</code></dd>
        </dl>
        <a class="btn-sec" href="{{ route('geo.catalogo.original', $importacion->id) }}">{{ $paquete ? 'Descargar GeoJSON transformado' : 'Descargar original' }}</a>
        @if($correccionOrigen)
            <p>Corrección del elemento {{ $correccionOrigen['elemento'] }} de la <a href="{{ route('geo.catalogo.importacion', $correccionOrigen['importacion_origen']) }}">importación de origen</a>.</p>
        @endif
    </section>
    @if($paquete)
        <section class="panel">
            <h2>Paquete de origen</h2>
            <p><a href="{{ $paquete->ficha_url }}" rel="noopener noreferrer">{{ $paquete->producto }}</a></p>
            <p>Escala 1:{{ number_format($paquete->escala, 0, ',', ' ') }} · CRS de origen {{ $preparacion['crs_origen'] }}</p>
            <p>SHA-256 del ZIP <code style="overflow-wrap: anywhere">{{ $paquete->sha256 }}</code></p>
            <a class="btn-sec" href="{{ route('geo.catalogo.paquete', $importacion->id) }}">Descargar ZIP original</a>
            <details>
                <summary>Preparación del paquete</summary>
                <p>{{ count($preparacion['recortadas_en_aoi']) }} entidades recortadas en el límite oficial de cobertura.</p>
                <ul>
                    @foreach($preparacion['omitidas'] as $omision)
                        <li>{{ $omision['capa'] }} · entidad {{ $omision['indice_shapefile'] }} · {{ $omision['motivo'] }}</li>
                    @endforeach
                </ul>
            </details>
        </section>
    @endif
    @if($entidades)
        <section class="panel territorio-panel" id="entidades">
            <h2>Entidades de la versión vigente</h2>
            <p>Editar o eliminar publica una nueva versión con los demás elementos. Los reportes y el archivo de origen se conservan.</p>
            @if($entidad)
                <form method="post" class="formulario" action="{{ route('geo.catalogo.corregir', [$importacion->id, $elemento]) }}">
                    @csrf @method('PATCH')
                    <label>GeoJSON de la entidad {{ $elemento }}
                        <textarea name="entidad" rows="10" maxlength="262144" required>{{ old('entidad', json_encode($entidad, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
                    </label>
                    <p>Conserva la estructura Feature y escribe primero la longitud y luego la latitud.</p>
                    <button class="btn" type="submit">Validar y publicar corrección</button>
                </form>
            @endif
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Elemento</th><th>Nombre</th><th>Geometría</th><th>Acciones</th></tr></thead>
                    <tbody>
                    @foreach($entidades as $indice => $item)
                        <tr>
                            <th scope="row">{{ $indice + 1 }}</th>
                            <td>{{ is_string($item->properties->nombre ?? null) ? $item->properties->nombre : '—' }}</td>
                            <td>{{ $item->geometry->type }}</td>
                            <td><div class="territorio-inline">
                                <a class="btn-sec" href="{{ route('geo.catalogo.importacion', ['importacion' => $importacion->id, 'entidades' => $entidades->currentPage(), 'elemento' => $indice + 1]) }}#entidades">Editar</a>
                                <form method="post" action="{{ route('geo.catalogo.corregir', [$importacion->id, $indice + 1]) }}"
                                    data-confirmar="¿Eliminar esta entidad de la versión vigente publicando una nueva versión?">
                                    @csrf @method('DELETE')
                                    <button class="btn-sec" type="submit">Eliminar</button>
                                </form>
                            </div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $entidades->links() }}
        </section>
    @endif
    @if($importacion->estado === 'rechazada')
        <section class="panel">
            <h2>Errores de validación</h2>
            <p>La importación rechazada conserva el archivo y sus errores. Si ya había una versión publicada, sigue siendo la vigente.</p>
            <ul>
                @foreach($erroresImportacion as $error)
                    <li><strong>{{ $error->elemento ? 'Elemento '.$error->elemento : 'Archivo' }}.</strong> {{ $error->motivo }}</li>
                @endforeach
            </ul>
            {{ $erroresImportacion->links() }}
        </section>
    @endif
@endsection
