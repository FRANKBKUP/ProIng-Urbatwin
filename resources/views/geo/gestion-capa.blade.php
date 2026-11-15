<div class="territorio-inline">
    <button type="button" class="btn-sec" data-abrir-dialogo="gestion-{{ $tipo }}-{{ $registro }}">Editar nombre</button>
    <form method="post" action="{{ route('geo.catalogo.gestion', [$tipo, $registro]) }}"
        @unless($retirada) data-confirmar="¿Retirar esta capa del visor y de las consultas nuevas? Los reportes previos conservarán sus datos." @endunless>
        @csrf @method('PATCH')
        <button class="btn-sec" type="submit" name="accion" value="{{ $retirada ? 'restaurar' : 'retirar' }}">{{ $retirada ? 'Restaurar' : 'Retirar' }}</button>
    </form>
</div>
<dialog class="territorio-dialogo" id="gestion-{{ $tipo }}-{{ $registro }}" aria-labelledby="rotulo-{{ $tipo }}-{{ $registro }}">
    <h2 id="rotulo-{{ $tipo }}-{{ $registro }}">Editar nombre en el visor</h2>
    <form class="formulario" method="post" action="{{ route('geo.catalogo.gestion', [$tipo, $registro]) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="accion" value="editar">
        <label>Nombre visible <input name="etiqueta" maxlength="120" value="{{ $etiqueta }}" placeholder="{{ $nombre }}"></label>
        <p>Deja el campo vacío para volver al nombre original.</p>
        <button class="btn" type="submit">Guardar nombre</button>
    </form>
    <form method="dialog"><button class="btn-sec">Cancelar</button></form>
</dialog>
