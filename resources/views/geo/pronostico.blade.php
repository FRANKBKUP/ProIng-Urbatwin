<section class="atlas-pronostico {{ $compacto ? 'atlas-pronostico-compacto' : 'panel territorio-panel' }}" data-pronostico-widget data-origen="{{ url('/api/geo/pronostico') }}">
    <h2>Pronóstico de precipitación</h2>
    <p>Tres días para un punto representativo del departamento.</p>
    <form class="territorio-inline">
        <label>Departamento
            <select name="departamento" required>
                @foreach($departamentos as $departamento)
                    <option value="{{ $departamento->codigo }}" @selected($departamento->codigo === 'PE-PIU')>{{ $departamento->nombre }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-sec" type="submit">Consultar pronóstico</button>
    </form>
    <div data-pronostico-resultados class="atlas-pronostico-dias" role="status"></div>
    <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo · salida de modelo</a>
</section>
