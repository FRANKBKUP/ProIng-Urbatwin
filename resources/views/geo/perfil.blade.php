@extends('layouts.app')
@section('titulo', 'Mi perfil')

@section('contenido')
<div class="perfil-pagina">
    <div class="perfil-resumen panel">
        <div class="perfil-identidad">
            <form method="post" action="{{ route('geo.perfil.foto.guardar') }}" enctype="multipart/form-data" class="perfil-foto-form" id="formulario-foto">
                @csrf
                <span class="perfil-avatar-contenedor">
                    <span class="avatar-persona perfil-avatar" data-avatar="{{ $perfil->avatar ?? 'azul' }}" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 2)) }}
                        @if(!empty($perfil->foto)) <img src="{{ route('geo.perfil.foto') }}" alt=""> @endif
                    </span>
                    <button type="button" class="perfil-ver-foto" aria-label="Ver foto de perfil" title="Ver foto de perfil" @if(empty($perfil->foto)) hidden @endif>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <label for="foto-perfil" class="perfil-camara" title="Cambiar foto de perfil">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h-4L8 7H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg>
                        <span class="perfil-texto-oculto">Cambiar foto de perfil</span>
                    </label>
                    <input id="foto-perfil" class="perfil-archivo" type="file" name="foto" accept="image/jpeg,image/png,image/webp" aria-label="Seleccionar foto de perfil (JPG, PNG o WebP, máximo 2 MB)" required>
                </span>
            </form>
            <div><h2>{{ auth()->user()->nombre }}</h2><p>{{ auth()->user()->email }}</p><span class="perfil-rol">{{ auth()->user()->rol->nombre }}</span></div>
        </div>
        <button class="btn perfil-guardar-foto" type="submit" form="formulario-foto">Guardar foto</button>
    </div>
    <dialog class="territorio-dialogo perfil-foto-dialogo" aria-label="Foto de perfil ampliada">
        <form method="dialog"><button class="btn-sec" aria-label="Cerrar foto">Cerrar</button></form>
        <img alt="Foto de perfil ampliada">
    </dialog>

    <div class="perfil-contenido">
    <form method="post" action="{{ route('geo.perfil.guardar') }}" class="perfil-layout">
        @csrf @method('PATCH')
        <section class="panel perfil-tarjeta">
            <div class="perfil-seccion-titulo"><span aria-hidden="true"><x-icono n="persona"/></span><div><h2>Información personal</h2></div></div>
            <label>Nombre visible <input name="nombre" maxlength="100" value="{{ old('nombre', auth()->user()->nombre) }}" required></label>
            <label>Ocupación o interés <input name="ocupacion" maxlength="90" value="{{ old('ocupacion', $perfil->ocupacion ?? '') }}" placeholder="Ej. Planificación urbana"></label>
            <label>Presentación privada <textarea name="descripcion" maxlength="400" rows="3" placeholder="Escribe una breve presentación">{{ old('descripcion', $perfil->descripcion ?? '') }}</textarea></label>
        </section>
        <section class="panel perfil-tarjeta">
            <div class="perfil-seccion-titulo"><span aria-hidden="true"><x-icono n="mapa"/></span><div><h2>Mapa y apariencia</h2></div></div>
            <label>Departamento
                <select name="departamento"><option value="">Elegir departamento</option>
                    @foreach($departamentos as $departamento)
                        <option value="{{ $departamento->codigo }}" @selected(old('departamento', $perfil->departamento ?? null) === $departamento->codigo)>{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <div class="perfil-dos-campos">
                <label>Color del avatar en el mapa <select name="avatar">
                    @foreach(['azul' => 'Azul', 'verde' => 'Verde', 'uva' => 'Violeta', 'coral' => 'Coral', 'oro' => 'Oro'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('avatar', $perfil->avatar ?? 'azul') === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select></label>
                <label>Tema <select name="tema" data-previsualizar-tema>
                    @foreach(['sistema' => 'Según el dispositivo', 'claro' => 'Claro', 'oscuro' => 'Oscuro'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('tema', auth()->user()->tema) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select></label>
            </div>
            <input type="hidden" name="visible_en_mapa" value="0">
            <label class="perfil-check"><input type="checkbox" name="visible_en_mapa" value="1" @checked(old('visible_en_mapa', $perfil->visible_en_mapa ?? false))> Mostrar mi avatar en el atlas</label>
            <p class="perfil-ayuda">Comparte tu nombre, ocupación e iniciales en un punto aproximado del departamento. Tu foto no se publica.</p>
        </section>
        <div class="perfil-acciones"><button type="submit" class="btn">Guardar cambios</button></div>
    </form>
    <section class="panel perfil-seguridad">
        <div class="perfil-seccion-titulo"><span aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span><div><h2>Seguridad</h2><p>Credenciales de acceso a la plataforma.</p></div></div>
        <details @if($errors->hasAny(['current_password', 'password'])) open @endif>
            <summary><span class="perfil-abrir-clave">Cambiar contraseña</span><span class="perfil-cerrar-clave">Ocultar cambio de clave</span></summary>
            <form method="post" action="{{ route('geo.perfil.contrasena') }}" class="formulario perfil-clave" data-formulario-clave>
                @csrf @method('PATCH')
                <label for="clave-actual">Contraseña actual</label>
                <div class="perfil-campo-clave"><input id="clave-actual" name="current_password" type="password" autocomplete="current-password" required><button type="button" data-ver-clave="clave-actual" aria-label="Mostrar contraseña actual" aria-pressed="false"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/><path class="perfil-ojo-tachado" d="M4 4l16 16"/></svg></button></div>
                <label for="clave-nueva">Nueva contraseña</label>
                <div class="perfil-campo-clave"><input id="clave-nueva" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="requisitos-clave" required><button type="button" data-ver-clave="clave-nueva" aria-label="Mostrar nueva contraseña" aria-pressed="false"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/><path class="perfil-ojo-tachado" d="M4 4l16 16"/></svg></button></div>
                <label for="clave-confirmacion">Confirmar nueva contraseña</label>
                <div class="perfil-campo-clave"><input id="clave-confirmacion" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><button type="button" data-ver-clave="clave-confirmacion" aria-label="Mostrar confirmación de contraseña" aria-pressed="false"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/><path class="perfil-ojo-tachado" d="M4 4l16 16"/></svg></button></div>
                <ul id="requisitos-clave" class="perfil-requisitos">
                    <li data-requisito="longitud">Mínimo 12 caracteres</li>
                    <li data-requisito="mayuscula">Una letra mayúscula</li>
                    <li data-requisito="minuscula">Una letra minúscula</li>
                    <li data-requisito="numero">Un número o carácter especial</li>
                </ul>
                <button type="submit" class="btn">Actualizar contraseña</button>
            </form>
        </details>
        @unless(auth()->user()->tieneRol('Superusuario'))
            <details><summary>Activar credenciales de superusuario</summary>
                <form method="post" action="{{ route('geo.perfil.credenciales') }}" class="formulario">
                    @csrf
                    <p>Usa el código recibido en el correo de esta cuenta.</p>
                    <label>Código de invitación <input name="codigo_superusuario" type="password" minlength="48" maxlength="48" autocomplete="one-time-code" required></label>
                    <button class="btn" type="submit">Activar acceso</button>
                </form>
            </details>
        @endunless
    </section>
    </div>
</div>
@endsection
