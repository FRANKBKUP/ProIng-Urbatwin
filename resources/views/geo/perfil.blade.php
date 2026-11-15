@extends('layouts.app')
@section('titulo', 'Perfil y preferencias')

@section('contenido')
    <header class="territorio-hero perfil-hero">
        <div>
            <p class="territorio-localidad">Cuenta ciudadana</p>
            <h1>Perfil y preferencias</h1>
            <p>Elige tu aspecto y qué datos quieres mostrar en el atlas.</p>
        </div>
        <span class="avatar-persona avatar-grande" data-avatar="{{ $perfil->avatar ?? 'azul' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->nombre, 0, 2)) }}</span>
    </header>

    <form method="post" action="{{ route('geo.perfil.guardar') }}" class="perfil-layout">
        @csrf @method('PATCH')
        <section class="panel territorio-panel">
            <h2>Datos de la cuenta</h2>
            <p>{{ auth()->user()->email }}</p>
            <label>Nombre visible <input name="nombre" maxlength="100" value="{{ old('nombre', auth()->user()->nombre) }}" required></label>
            <label>Ocupación o interés <input name="ocupacion" maxlength="90" value="{{ old('ocupacion', $perfil->ocupacion ?? '') }}"></label>
            <label>Presentación privada <textarea name="descripcion" maxlength="400" rows="4">{{ old('descripcion', $perfil->descripcion ?? '') }}</textarea></label>
        </section>
        <section class="panel territorio-panel">
            <h2>Mapa y apariencia</h2>
            <label>Departamento
                <select name="departamento">
                    <option value="">Elegir departamento</option>
                    @foreach($departamentos as $departamento)
                        <option value="{{ $departamento->codigo }}" @selected(old('departamento', $perfil->departamento ?? null) === $departamento->codigo)>{{ $departamento->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label>Color del avatar
                <select name="avatar">
                    @foreach(['azul' => 'Azul', 'verde' => 'Verde', 'uva' => 'Violeta', 'coral' => 'Coral', 'oro' => 'Oro'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('avatar', $perfil->avatar ?? 'azul') === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
            <label>Tema de la interfaz
                <select name="tema" data-previsualizar-tema>
                    @foreach(['sistema' => 'Según el dispositivo', 'claro' => 'Claro', 'oscuro' => 'Oscuro'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('tema', auth()->user()->tema) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="visible_en_mapa" value="0">
            <label class="perfil-check"><input type="checkbox" name="visible_en_mapa" value="1" @checked(old('visible_en_mapa', $perfil->visible_en_mapa ?? false))> Mostrar mi avatar en el atlas</label>
            <p>Al activarlo, otros usuarios podrán ver tu nombre, ocupación y avatar en un punto representativo del departamento.</p>
        </section>
        <button type="submit" class="btn">Guardar preferencias</button>
    </form>
    <section class="panel territorio-panel">
        <h2>Contraseña de acceso</h2>
        <details @if($errors->hasAny(['current_password', 'password'])) open @endif>
            <summary>Cambiar mi contraseña</summary>
            <form method="post" action="{{ route('geo.perfil.contrasena') }}" class="formulario">
                @csrf @method('PATCH')
                <label>Contraseña actual <input name="current_password" type="password" autocomplete="current-password" required></label>
                <label>Contraseña nueva <input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
                <label>Confirma la contraseña nueva <input name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
                <button type="submit" class="btn">Actualizar contraseña</button>
            </form>
        </details>
    </section>
    @unless(auth()->user()->tieneRol('Superusuario'))
        <section class="panel territorio-panel">
            <h2>Activar credenciales</h2>
            <p>Si recibiste una invitación de superusuario, actívala desde tu cuenta con el mismo correo al que fue destinada.</p>
            <details>
                <summary>Tengo un código</summary>
                <form method="post" action="{{ route('geo.perfil.credenciales') }}" class="formulario">
                    @csrf
                    <label>Código de invitación <input name="codigo_superusuario" type="password" minlength="48" maxlength="48" autocomplete="one-time-code" required></label>
                    <button class="btn" type="submit">Activar acceso</button>
                </form>
            </details>
        </section>
    @endunless
@endsection
