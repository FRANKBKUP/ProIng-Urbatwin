@extends('layouts.app')
@section('titulo', 'Superusuarios')

@section('contenido')
    <header class="territorio-hero">
        <div><h1>Superusuarios</h1><p>Las cinco plazas se activan con una invitación de 24 horas asociada a un correo personal.</p></div>
        <a class="btn-sec" href="{{ route('admin.usuarios.index') }}">Ver usuarios</a>
    </header>
    <section class="panel territorio-panel">
        <h2>Plazas del equipo</h2>
        @if($inicioDisponible)
            <p>Activa primero la plaza de Frank Suarez. Desde esa cuenta se podrán invitar a las otras cuatro personas.</p>
        @endif
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Persona</th><th>Estado</th><th>Invitación</th></tr></thead>
                <tbody>
                @foreach($plazas as $plaza)
                    @php $vigente = $plaza->vence_en && \Illuminate\Support\Carbon::parse($plaza->vence_en)->isFuture(); @endphp
                    <tr>
                        <th scope="row">{{ $plaza->nombre }}</th>
                        <td>{{ $plaza->activado_en ? 'Activada' : ($vigente ? 'Invitación vigente' : 'Pendiente') }}</td>
                        <td>
                            @if(!$plaza->activado_en && ($puedeInvitar || ($inicioDisponible && $plaza->plaza === 1)))
                                <form class="territorio-inline" method="post" action="{{ route('geo.superusuarios.invitar', $plaza->plaza) }}"
                                    @if($vigente) data-confirmar="Si generas otro código, la invitación anterior dejará de funcionar." @endif>
                                    @csrf
                                    <input aria-label="Correo personal de {{ $plaza->nombre }}" type="email" name="correo" placeholder="Correo personal" maxlength="120" required>
                                    <button class="btn-sec" type="submit">{{ $vigente ? 'Reemplazar código' : 'Generar código' }}</button>
                                </form>
                            @else
                                <span aria-hidden="true">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
