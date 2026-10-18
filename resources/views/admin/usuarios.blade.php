@extends('layouts.app')
@section('titulo', 'Usuarios')

@section('contenido')
    <h1>Gestión de usuarios</h1>

    <table>
        <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Activo</th><th></th></tr>
        @foreach($usuarios as $u)
            @php $esYo = $u->id === auth()->id(); @endphp
            <tr>
                <td>{{ $u->nombre }} @if($esYo) <em>(tú)</em> @endif</td>
                <td>{{ $u->email }}</td>
                <td>
                    <select name="rol_id" form="uf{{ $u->id }}" aria-label="Rol de {{ $u->nombre }}" @disabled($esYo)>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected($u->rol_id === $r->id)>{{ $r->nombre }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="hidden" name="activo" value="0" form="uf{{ $u->id }}">
                    <input type="checkbox" name="activo" value="1" form="uf{{ $u->id }}"
                           aria-label="Activo: {{ $u->nombre }}" @checked($u->activo) @disabled($esYo)>
                </td>
                <td>@unless($esYo) <button class="btn-sec" form="uf{{ $u->id }}">Guardar</button> @endunless</td>
            </tr>
        @endforeach
    </table>

    @foreach($usuarios as $u)
        <form id="uf{{ $u->id }}" method="POST" action="{{ route('admin.usuarios.update', $u->id) }}">
            @csrf @method('PUT')
        </form>
    @endforeach

    <h2>Nuevo usuario</h2>
    <form method="POST" action="{{ route('admin.usuarios.store') }}" class="formulario">
        @csrf
        <label>Nombre <input name="nombre" value="{{ old('nombre') }}" maxlength="100" required></label>
        <label>Correo <input type="email" name="email" value="{{ old('email') }}" maxlength="120" required></label>
        <label>Rol
            <select name="rol_id">
                @foreach($roles as $r)
                    <option value="{{ $r->id }}" @selected(old('rol_id') == $r->id)>{{ $r->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label>Contraseña (mínimo 8 caracteres) <input type="password" name="password" required></label>
        <button class="btn btn-bloque">Crear usuario</button>
    </form>
@endsection