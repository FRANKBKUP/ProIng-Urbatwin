@extends('layouts.app')
@section('titulo', 'Editar zona')

@section('contenido')
    <h1>Editar zona</h1>

    <form method="POST" action="{{ route('admin.zonas.update', $zona->id) }}" class="formulario">
        @csrf @method('PUT')
        <label>Nombre <input name="nombre" value="{{ old('nombre', $zona->nombre) }}" maxlength="100" required></label>
        <label>Descripción <input name="descripcion" value="{{ old('descripcion', $zona->descripcion) }}" maxlength="255"></label>
        <label>Nuevo límite (GeoJSON) — déjalo vacío para conservar el actual
            <textarea name="geojson" rows="7">{{ old('geojson') }}</textarea>
        </label>
        <button class="btn">Guardar cambios</button>
        <a href="{{ route('admin.zonas.index') }}">Cancelar</a>
    </form>
@endsection