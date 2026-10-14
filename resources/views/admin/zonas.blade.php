@extends('layouts.app')
@section('titulo', 'Zonas')

@section('contenido')
    <h1>Gestión de zonas</h1>

    <table>
        <tr><th>Nombre</th><th>Descripción</th><th>Infraestructuras</th><th>Escenarios</th><th>Acciones</th></tr>
        @foreach($zonas as $z)
            <tr>
                <td>{{ $z->nombre }}</td>
                <td>{{ $z->descripcion }}</td>
                <td>{{ $z->infra }}</td>
                <td>{{ $z->escenarios }}</td>
                <td>
                    <a class="btn-sec" href="{{ route('admin.zonas.editar', $z->id) }}">Editar</a>
                    <form method="POST" action="{{ route('admin.zonas.destroy', $z->id) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar esta zona?')">
                        @csrf @method('DELETE')
                        <button class="btn-sec peligro">Eliminar</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>

    <h2>Nueva zona</h2>
    <form method="POST" action="{{ route('admin.zonas.store') }}" class="formulario">
        @csrf
        <label>Nombre <input name="nombre" value="{{ old('nombre') }}" maxlength="100" required></label>
        <label>Descripción (opcional) <input name="descripcion" value="{{ old('descripcion') }}" maxlength="255"></label>
        <label>Límite de la zona (GeoJSON de un polígono)
            <textarea name="geojson" rows="7" required>{{ old('geojson') }}</textarea>
        </label>
        <p style="font-size:13px">
            Dibuja el contorno del distrito o sector en <strong>geojson.io</strong> y copia el JSON.
        </p>
        <button class="btn">Guardar zona</button>
    </form>
@endsection