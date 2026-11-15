@extends('layouts.app')
@section('titulo', 'Invitación de superusuario')

@section('contenido')
    <section class="panel territorio-panel">
        <h1>Invitación para {{ $nombre }}</h1>
        <p>Comparte el código en privado con la persona titular de {{ $correo }}. Podrá activarlo en «Perfil y preferencias» una vez iniciada la sesión.</p>
        <p>El código aparece en esta pantalla, dura 24 horas y se puede usar una sola vez.</p>
        <label>Código de invitación <input class="codigo-invitacion" value="{{ $codigo }}" readonly></label>
        <p><a class="btn-sec" href="{{ route('geo.superusuarios.index') }}">Volver a superusuarios</a></p>
    </section>
@endsection
