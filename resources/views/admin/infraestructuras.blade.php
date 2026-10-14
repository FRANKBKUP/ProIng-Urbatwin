@extends('layouts.app')
@section('titulo', 'Infraestructura')

@section('contenido')
    <h1>Gestión de infraestructura</h1>

    @if(session('motivos'))
        <div class="alerta err">
            <strong>Elementos omitidos:</strong>
            <ul>@foreach(session('motivos') as $m)<li>{{ $m }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="GET" class="formulario" style="display:flex;gap:12px;align-items:flex-end">
        <label>Zona
            <select name="zona_id">
                <option value="">Todas</option>
                @foreach($zonas as $z)
                    <option value="{{ $z->id }}" @selected($zonaId === $z->id)>{{ $z->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label>Tipo
            <select name="tipo_id">
                <option value="">Todos</option>
                @foreach($tipos as $t)
                    <option value="{{ $t->id }}" @selected($tipoId === $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn">Filtrar</button>
    </form>

    <table>
        <tr><th>Nombre</th><th>Tipo</th><th>Zona</th><th>Geometría</th><th>Estado</th><th>En reportes</th><th>Acciones</th></tr>
        @forelse($infra as $i)
            <tr>
                <td>{{ $i->nombre }}</td>
                <td>{{ $i->tipo }}</td>
                <td>{{ $i->zona }}</td>
                <td>{{ ucfirst(strtolower($i->geom)) }}</td>
                <td>{{ $i->activo ? 'Activo' : 'Inactivo' }}</td>
                <td>{{ $i->en_reportes }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.infraestructuras.estado', $i->id) }}" style="display:inline">
                        @csrf
                        <button class="btn-sec">{{ $i->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.infraestructuras.destroy', $i->id) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar esta infraestructura?')">
                        @csrf @method('DELETE')
                        <button class="btn-sec peligro">Eliminar</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No hay infraestructura con esos filtros.</td></tr>
        @endforelse
    </table>

    <p>
        @if($infra->previousPageUrl()) <a href="{{ $infra->previousPageUrl() }}">« Anterior</a> @endif
        &nbsp;Página {{ $infra->currentPage() }}&nbsp;
        @if($infra->hasMorePages()) <a href="{{ $infra->nextPageUrl() }}">Siguiente »</a> @endif
    </p>

    <h2>Nueva infraestructura</h2>
    <form method="POST" action="{{ route('admin.infraestructuras.store') }}" class="formulario">
        @csrf
        <label>Nombre <input name="nombre" value="{{ old('nombre') }}" maxlength="120" required></label>
        <label>Zona
            <select name="zona_id">
                @foreach($zonas as $z)
                    <option value="{{ $z->id }}" @selected(old('zona_id') == $z->id)>{{ $z->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label>Tipo
            <select name="tipo_id">
                @foreach($tipos as $t)
                    <option value="{{ $t->id }}" @selected(old('tipo_id') == $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label>Ubicación (GeoJSON: punto, línea o polígono)
            <textarea name="geojson" rows="5" required>{{ old('geojson') }}</textarea>
        </label>
        <button class="btn">Guardar</button>
    </form>

    <h2>Importar desde archivo</h2>
    <form method="POST" action="{{ route('admin.infraestructuras.importar') }}" enctype="multipart/form-data" class="formulario">
        @csrf
        <label>Archivo .geojson (FeatureCollection, máximo 5 MB)
            <input type="file" name="archivo" required>
        </label>
        <label>Zona
            <select name="zona_id">
                @foreach($zonas as $z)<option value="{{ $z->id }}">{{ $z->nombre }}</option>@endforeach
            </select>
        </label>
        <label>Tipo (todos los elementos del archivo se guardan con este tipo)
            <select name="tipo_id">
                @foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nombre }}</option>@endforeach
            </select>
        </label>
        <p style="font-size:13px">El nombre de cada elemento se toma de la propiedad <code>nombre</code> o <code>name</code>.</p>
        <button class="btn">Importar</button>
    </form>
@endsection