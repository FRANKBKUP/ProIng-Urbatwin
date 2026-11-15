@extends('layouts.app')
@section('titulo', 'Lugares y grupos')

@section('contenido')
    <header class="territorio-hero">
        <div><h1>Lugares y grupos</h1><p>Guarda un punto desde el visor y decide si lo compartes con un grupo.</p></div>
        <a class="btn" href="{{ route('geo.portal') }}"><x-icono n="mapa"/> Elegir punto</a>
    </header>

    @if(session('invitacion'))
        <div class="alerta ok" role="status">
            <label for="codigo-emitido">Código de un solo uso · vence en 24 horas</label>
            <input id="codigo-emitido" class="codigo-invitacion" value="{{ session('invitacion') }}" readonly>
        </div>
    @endif

    <section class="panel territorio-panel">
        <h2>Mis lugares</h2>
        <div class="territorio-tabla">
            <table>
                <thead><tr><th>Nombre</th><th>Visibilidad</th><th>Acciones</th></tr></thead>
                <tbody>
                    @forelse($lugares as $lugar)
                        <tr>
                            <th scope="row">{{ $lugar->nombre }}</th>
                            <td>
                                <form class="territorio-inline" method="post" action="{{ route('geo.lugares.compartir', $lugar->id) }}">
                                    @csrf @method('PATCH')
                                    <select name="grupo_id" aria-label="Visibilidad de {{ $lugar->nombre }}">
                                        <option value="">Privado</option>
                                        @foreach($grupos as $grupo)
                                            <option value="{{ $grupo->id }}" @selected($grupo->id === $lugar->grupo_id)>{{ $grupo->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-sec">Aplicar</button>
                                </form>
                            </td>
                            <td><div class="territorio-inline">
                                <a href="{{ route('geo.portal', ['lugar' => $lugar->id]) }}">Ver en mapa</a>
                                <button type="button" class="btn-sec" data-abrir-dialogo="editar-lugar-{{ $lugar->id }}">Editar</button>
                                <form method="post" action="{{ route('geo.lugares.eliminar', $lugar->id) }}" data-confirmar="¿Eliminar este lugar de tu cuenta?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-sec">Eliminar</button>
                                </form>
                                <dialog class="territorio-dialogo" id="editar-lugar-{{ $lugar->id }}" aria-labelledby="titulo-lugar-{{ $lugar->id }}">
                                    <h2 id="titulo-lugar-{{ $lugar->id }}">Editar lugar</h2>
                                    <form class="formulario" method="post" action="{{ route('geo.lugares.editar', $lugar->id) }}">
                                        @csrf @method('PATCH')
                                        <label>Nombre <input name="nombre" value="{{ $lugar->nombre }}" maxlength="80" required></label>
                                        <label>Longitud <input type="number" name="longitud" min="-180" max="180" step="any" value="{{ $lugar->longitud }}" required></label>
                                        <label>Latitud <input type="number" name="latitud" min="-90" max="90" step="any" value="{{ $lugar->latitud }}" required></label>
                                        <button class="btn" type="submit">Guardar cambios</button>
                                    </form>
                                    <form method="dialog"><button class="btn-sec">Cancelar</button></form>
                                </dialog>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Aún no tienes lugares. Marca un punto en el visor y usa «Guardar punto».</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $lugares->links() }}
    </section>

    <section id="grupos" class="panel territorio-panel">
        <h2>Mis grupos</h2>
        <p>Los miembros pueden ver los lugares que se hayan compartido con ese grupo.</p>
        @forelse($grupos as $grupo)
            <div class="territorio-grupo">
                <strong>{{ $grupo->nombre }}</strong>
                <div class="territorio-inline">
                    <a class="btn-sec" href="{{ route('geo.portal', ['grupo' => $grupo->id]) }}">Ver en mapa</a>
                    @if($grupo->propietario_id === auth()->id())
                        <form method="post" action="{{ route('geo.grupos.invitar', $grupo->id) }}">
                            @csrf
                            <button class="btn-sec" type="submit">Crear invitación</button>
                        </form>
                        <button class="btn-sec" type="button" data-abrir-dialogo="editar-grupo-{{ $grupo->id }}">Editar grupo</button>
                        <form method="post" action="{{ route('geo.grupos.salir', $grupo->id) }}" data-confirmar="¿Eliminar el grupo? Los lugares de sus miembros volverán a ser privados.">
                            @csrf @method('DELETE')
                            <button class="btn-sec territorial-peligro" type="submit">Eliminar grupo</button>
                        </form>
                        <dialog class="territorio-dialogo" id="editar-grupo-{{ $grupo->id }}" aria-labelledby="titulo-grupo-{{ $grupo->id }}">
                            <h2 id="titulo-grupo-{{ $grupo->id }}">Editar grupo</h2>
                            <form class="formulario" method="post" action="{{ route('geo.grupos.editar', $grupo->id) }}">
                                @csrf @method('PATCH')
                                <label>Nombre <input name="nombre" value="{{ $grupo->nombre }}" maxlength="80" required></label>
                                <button class="btn" type="submit">Guardar cambios</button>
                            </form>
                            <form method="dialog"><button class="btn-sec">Cancelar</button></form>
                        </dialog>
                    @else
                        <form method="post" action="{{ route('geo.grupos.salir', $grupo->id) }}">
                            @csrf @method('DELETE')
                            <button class="btn-sec" type="submit">Salir del grupo</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="group-roster" aria-label="Miembros de {{ $grupo->nombre }}">
                @foreach($miembros->get($grupo->id, collect()) as $miembro)
                    <div class="group-persona">
                        <span class="avatar-persona" data-avatar="{{ $miembro->avatar ?? 'azul' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($miembro->nombre, 0, 2)) }}</span>
                        <div>
                            <strong>{{ $miembro->nombre }}</strong>
                            <small>{{ $miembro->propietario ? 'Propietario' : 'Miembro' }}@if($miembro->departamento) · {{ $miembro->departamento }}@endif</small>
                            <small>{{ (int) $miembro->puntos === 1 ? '1 punto compartido' : $miembro->puntos.' puntos compartidos' }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p>Aún no perteneces a un grupo.</p>
        @endforelse
        <div class="territorio-grupos-formularios">
            <form class="formulario" method="post" action="{{ route('geo.grupos.crear') }}">
                @csrf
                <label>Nombre del nuevo grupo <input name="nombre" maxlength="80" required></label>
                <button class="btn" type="submit">Crear grupo</button>
            </form>
            <form class="formulario" method="post" action="{{ route('geo.grupos.aceptar') }}">
                @csrf
                <label>Código de invitación <input name="codigo" type="password" maxlength="40" minlength="40" autocomplete="one-time-code" required></label>
                <button class="btn-sec" type="submit">Unirme al grupo</button>
            </form>
        </div>
    </section>
@endsection
