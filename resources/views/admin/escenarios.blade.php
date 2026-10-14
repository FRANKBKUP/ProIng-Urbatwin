@extends('layouts.app')
@section('titulo', 'Escenarios')

@section('contenido')
    <h1>Gestión de escenarios</h1>

    <table>
        <tr><th>Nombre</th><th>Zona</th><th>Nivel</th><th>Origen</th><th>Estado</th><th>Reportes</th><th>Acciones</th></tr>
        @foreach($escenarios as $e)
            <tr>
                <td>{{ $e->nombre }}</td>
                <td>{{ $e->zona }}</td>
                <td>{{ ucfirst($e->nivel) }}</td>
                <td>@if($e->origen === 'simulado') <span class="tag">Simulado</span> @else Importado @endif</td>
                <td>{{ $e->activo ? 'Activo' : 'Inactivo' }}</td>
                <td>{{ $e->reportes }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.escenarios.estado', $e->id) }}" style="display:inline">
                        @csrf
                        <button class="btn-sec">{{ $e->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.escenarios.destroy', $e->id) }}" style="display:inline"
                          onsubmit="return confirm('¿Eliminar este escenario?')">
                        @csrf @method('DELETE')
                        <button class="btn-sec peligro">Eliminar</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </table>

    <h2>Nuevo escenario</h2>
    <form method="POST" action="{{ route('admin.escenarios.store') }}" class="formulario">
        @csrf
        <label>Nombre
            <input name="nombre" value="{{ old('nombre') }}" maxlength="100" required>
        </label>
        <label>Zona
            <select name="zona_id">
                @foreach($zonas as $z)
                    <option value="{{ $z->id }}" @selected(old('zona_id') == $z->id)>{{ $z->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label>Nivel
            <select name="nivel">
                @foreach(['leve', 'moderado', 'extremo'] as $n)
                    <option value="{{ $n }}" @selected(old('nivel') === $n)>{{ ucfirst($n) }}</option>
                @endforeach
            </select>
        </label>
        <label>Origen
            <select name="origen">
                <option value="simulado" @selected(old('origen', 'simulado') === 'simulado')>Simulado</option>
                <option value="importado" @selected(old('origen') === 'importado')>Importado</option>
            </select>
        </label>
        <label>Área inundada (GeoJSON)
            <textarea name="geojson" rows="8" required>{{ old('geojson') }}</textarea>
        </label>
        <p style="font-size:13px">
            Cómo obtenerlo: entra a <strong>geojson.io</strong>, dibuja un polígono sobre la zona y copia el JSON del panel derecho.
        </p>
        <button class="btn">Guardar escenario</button>
    </form>
@endsection