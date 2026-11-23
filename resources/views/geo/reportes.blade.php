@extends('layouts.app')
@section('titulo', 'Análisis y reportes')

@section('contenido')
    <header class="cabecera reportes-cabecera"><div><p>INFORMACIÓN TERRITORIAL</p><h1>Análisis y reportes</h1><span>Consulta y analiza información del territorio.</span></div></header>
    <nav class="reportes-accesos" aria-label="Secciones de reportes">
        <a href="#lluvia-titulo"><x-icono n="escenarios"/> Lluvias históricas</a>
        @if(auth()->user()->tieneRol('Analista'))<a href="#exposicion"><x-icono n="mapa"/> Análisis de exposición</a>@endif
        <a href="#reportes-guardados"><x-icono n="reportes"/> Reportes guardados</a>
    </nav>
    <section class="panel lluvia-reporte" aria-labelledby="lluvia-titulo">
        <div class="lluvia-cabecera">
            <div class="lluvia-titulo"><span class="lluvia-titulo-icono" aria-hidden="true"><x-icono n="escenarios"/></span><div><p class="lluvia-etiqueta">DATOS HISTÓRICOS</p><h2 id="lluvia-titulo">Lluvias históricas</h2></div></div>
        </div>
        <p class="lluvia-documento-fecha">UrbanTwin Perú · Reporte generado el {{ now()->format('d/m/Y') }}</p>
            @if($seriesLluvia->isNotEmpty())
                <form method="get" action="{{ route('geo.reportes.index') }}" class="lluvia-filtro" data-lluvia-rango>
                    <label class="lluvia-zona" for="serie-lluvia">Zona y disponibilidad
                    <select id="serie-lluvia" name="serie">
                        @foreach($seriesLluvia as $serie)
                            <option value="{{ $serie->id }}" data-inicio="{{ $serie->inicio_disponible }}" data-fin="{{ $serie->fin_disponible }}" @selected($lluvia['serie']->id === $serie->id)>{{ $serie->zona }} · {{ \Carbon\CarbonImmutable::parse($serie->inicio_disponible)->format('d/m/Y') }}–{{ \Carbon\CarbonImmutable::parse($serie->fin_disponible)->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
                    </label>
                    <div class="lluvia-fechas">
                        <label>Desde <input type="date" name="desde" value="{{ $lluvia['desde'] }}" min="{{ $lluvia['serie']->inicio_disponible }}" max="{{ $lluvia['serie']->fin_disponible }}" required></label>
                        <label>Hasta <input type="date" name="hasta" value="{{ $lluvia['hasta'] }}" min="{{ $lluvia['serie']->inicio_disponible }}" max="{{ $lluvia['serie']->fin_disponible }}" required></label>
                    </div>
                    <details class="lluvia-calendario">
                        <summary><span class="lluvia-calendario-rotulo"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg><span data-lluvia-rango-texto>Elegir fechas</span></span><span aria-hidden="true">⌄</span></summary>
                        <div data-calendario-lluvia></div>
                    </details>
                    <label class="lluvia-agrupar">Agrupar por
                        <select name="agrupar">
                            @foreach(['automatico' => 'Automático', 'diario' => 'Por día', 'mensual' => 'Por mes', 'anual' => 'Por año'] as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected($lluvia['agrupacion_solicitada'] === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="lluvia-acciones">
                    <a class="btn-sec" href="{{ route('geo.reportes.index') }}#lluvia-titulo">Limpiar</a>
                    <button class="btn" type="submit">Consultar</button>
                    </div>
                    <p class="lluvia-notificacion" data-lluvia-notificacion role="alert" hidden>Para más de 366 días, elige la agrupación mensual o anual.</p>
                </form>
            @endif
        @if(! $lluvia)
            <p class="lluvia-vacio">Todavía no hay series de lluvia importadas. Cuando estén disponibles, aparecerán aquí.</p>
        @else
            <div class="lluvia-contexto"><div><span>PERÍODO CONSULTADO</span><p><strong>{{ $lluvia['serie']->zona }}</strong> · {{ $lluvia['inicio'] }} al {{ $lluvia['fin'] }}</p><small>Promedio espacial estimado</small></div><button type="button" class="btn-sec no-imprimir" onclick="window.print()">Vista previa / guardar PDF</button></div>
            <div class="lluvia-resumen">
                <div><span>{{ $lluvia['faltantes'] ? 'Acumulado de días disponibles' : 'Lluvia acumulada' }}</span><strong>{{ $lluvia['total'] === null ? '—' : number_format($lluvia['total'], 2, ',', ' ') }} <small>mm</small></strong></div>
                <div><span>Día con más lluvia</span><strong>{{ $lluvia['mayor'] ? \Carbon\CarbonImmutable::parse($lluvia['mayor']['fecha'])->format('d/m/Y') : '—' }}</strong><small>{{ $lluvia['mayor'] ? number_format($lluvia['mayor']['valor'], 2, ',', ' ').' mm' : 'Sin datos' }}</small></div>
                <div><span>Promedio diario{{ $lluvia['faltantes'] ? ' disponible' : '' }}</span><strong>{{ $lluvia['disponibles'] ? number_format($lluvia['total'] / $lluvia['disponibles'], 2, ',', ' ') : '—' }} <small>mm/día</small></strong></div>
                <div><span>Días con datos</span><strong>{{ $lluvia['disponibles'] }} <small>de {{ $lluvia['dias']->count() }}</small></strong></div>
            </div>
            @if($lluvia['faltantes'])
                <p class="lluvia-aviso" role="status">Faltan datos de {{ $lluvia['faltantes'] }} día(s). El acumulado mostrado es parcial; los días sin datos no son cero.</p>
            @endif
            @if($lluvia['disponibles'])
                <div class="lluvia-grafico-titulo"><div><h3>Precipitación {{ ['diario' => 'diaria', 'mensual' => 'mensual', 'anual' => 'anual'][$lluvia['agrupacion']] }}</h3><p class="lluvia-ayuda">Selecciona una barra para ver su valor.</p></div><span>mm / {{ ['diario' => 'día', 'mensual' => 'mes', 'anual' => 'año'][$lluvia['agrupacion']] }}</span></div>
                <p class="lluvia-lectura" data-lluvia-lectura aria-live="polite">Selecciona una barra de la gráfica.</p>
                <div class="lluvia-grafico-marco">
                <div class="lluvia-grafico-escala" aria-hidden="true"><span>{{ number_format($lluvia['maximo_grafico'], 1, ',', ' ') }}</span><span>0</span></div>
                <div class="lluvia-grafico-desplazar" tabindex="0" role="region" aria-label="Gráfica de precipitación; desplázate horizontalmente para ver los períodos">
                    <div class="lluvia-grafico" aria-label="Selecciona un período para ver su precipitación">
                        @php($intervaloEtiquetas = max(1, (int) ceil($lluvia['grafico']->count() / 9)))
                        @foreach($lluvia['grafico'] as $punto)
                            <div class="lluvia-columna">
                                <div class="lluvia-barra-espacio">@if($punto['valor'] !== null)<button type="button" class="lluvia-barra" data-lluvia-dia="{{ $punto['etiqueta'] }}" data-lluvia-valor="{{ number_format($punto['valor'], 2, ',', ' ') }}" data-lluvia-parcial="{{ $punto['faltantes'] }}" aria-label="{{ $punto['etiqueta'] }}: {{ number_format($punto['valor'], 2, ',', ' ') }} milímetros{{ $punto['faltantes'] ? ', acumulado parcial' : '' }}" style="height: {{ $lluvia['maximo_grafico'] > 0 ? number_format($punto['valor'] / $lluvia['maximo_grafico'] * 100, 2, '.', '') : 0 }}%"></button>@else<span class="lluvia-sin-dato" aria-label="{{ $punto['etiqueta'] }}: sin datos">—</span>@endif</div>
                                <div class="lluvia-etiqueta-eje" aria-hidden="true">@if($loop->index % $intervaloEtiquetas === 0 || ($loop->last && $loop->index % $intervaloEtiquetas >= ceil($intervaloEtiquetas / 2)))<small>{{ $lluvia['agrupacion'] === 'diario' ? substr($punto['etiqueta'], 0, 5) : $punto['etiqueta'] }}</small>@endif</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                </div>
            @endif
            <section class="lluvia-provincias" aria-labelledby="lluvia-provincias-titulo">
                <h3 id="lluvia-provincias-titulo">Lluvia estimada por provincia</h3>
                <p class="lluvia-ayuda">Promedio espacial acumulado por provincia. La lluvia no confirma inundaciones ni daños.</p>
                @if($lluvia['provincias']->isNotEmpty())
                    <div class="tabla-wrap"><table><thead><tr><th scope="col">Provincia</th><th scope="col">Precipitación estimada</th><th scope="col">Días procesados</th></tr></thead><tbody>
                        @foreach($lluvia['provincias'] as $provincia)
                            <tr><td>{{ $provincia['nombre'] }}</td><td>{{ number_format($provincia['total'], 2, ',', ' ') }} mm{{ $provincia['dias'] < $lluvia['dias']->count() ? ' (parcial)' : '' }}</td><td>{{ $provincia['dias'] }} de {{ $lluvia['dias']->count() }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                    <p class="lluvia-ayuda">Los acumulados parciales solo incluyen los días provinciales procesados. Límites provinciales: geoBoundaries ADM2, fuente IGN/OCHA 2020; CC BY 3.0 IGO.</p>
                @else
                    <p class="lluvia-ayuda">No hay datos provinciales procesados para este período.</p>
                @endif
            </section>
            <details class="reportes-desplegable lluvia-datos-diarios" data-lluvia-datos-diarios>
            <summary><span class="reportes-resumen"><span class="reportes-icono"><x-icono n="reportes"/></span><span><strong>Datos diarios</strong><small>Fecha y precipitación estimada</small></span></span><svg class="reportes-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
            <h3 class="lluvia-titulo-impresion">Datos diarios de precipitación</h3>
            <div class="tabla-wrap" tabindex="0" role="region" aria-label="Tabla de precipitación diaria">
                <table><thead><tr><th scope="col">Fecha</th><th scope="col">Precipitación estimada</th></tr></thead><tbody>
                    @foreach($lluvia['dias'] as $dia)
                        <tr><td>{{ \Carbon\CarbonImmutable::parse($dia['fecha'])->format('d/m/Y') }}</td><td>{{ $dia['valor'] === null ? 'Sin datos' : number_format($dia['valor'], 2, ',', ' ').' mm' }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
            </details>
            <details class="lluvia-fuente"><summary><span class="reportes-resumen"><span class="reportes-icono"><x-icono n="mapa"/></span><span><strong>Fuente y metodología</strong><small>Origen y alcance de los datos</small></span></span><svg class="reportes-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><p>{{ $lluvia['serie']->producto }} · <a href="https://www.chc.ucsb.edu/data/chirps3" target="_blank" rel="noopener noreferrer">Climate Hazards Center</a></p><p>{{ $lluvia['serie']->metodo }} Los valores diarios son estimaciones derivadas, no mediciones exactas de cada localidad.</p></details>
            <div class="lluvia-fuente-impresion"><strong>Fuente, metodología y alcance</strong><p>{{ $lluvia['serie']->producto }} · Climate Hazards Center · https://www.chc.ucsb.edu/data/chirps3</p><p>{{ $lluvia['serie']->metodo }} Los valores diarios son estimaciones para el departamento, no mediciones exactas de cada localidad. Este reporte no identifica zonas inundadas ni daños confirmados.</p></div>
        @endif
    </section>
    @if(auth()->user()->tieneRol('Analista'))
        <section class="panel reportes-seccion" id="exposicion">
            <details @if($errors->hasAny(['peligro_id', 'vias_id', 'equipamiento_id'])) open @endif>
            <summary><span><strong>Análisis de exposición</strong><small>Cruza una zona de peligro con vías o equipamiento.</small></span><span aria-hidden="true">⌄</span></summary>
            @if($capas->has('peligro') && ($capas->has('vias') || $capas->has('equipamiento')))
                <p>Elige un peligro y al menos una capa de infraestructura de la misma cobertura.</p>
                <form class="formulario" method="post" action="{{ route('geo.reportes.generar') }}">
                    @csrf
                    @foreach(['peligro' => 'Peligro', 'vias' => 'Vías', 'equipamiento' => 'Equipamiento'] as $tipo => $titulo)
                        @if($capas->has($tipo))
                            <label>{{ $titulo }}
                                <select name="{{ $tipo }}_id" @required($tipo === 'peligro')>
                                    @if($tipo !== 'peligro')<option value="">Omitir capa</option>@endif
                                    @foreach($capas->get($tipo) as $capa)
                                        <option value="{{ $capa->version_id }}" @selected(old($tipo.'_id') == $capa->version_id)>
                                            {{ $capa->nombre }} · v{{ $capa->numero }} · {{ $capa->cobertura }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    @endforeach
                    <button class="btn" type="submit">Generar reporte</button>
                </form>
            @else
                <p>Se necesita un peligro publicado y al menos una capa de vías o equipamiento publicada.</p>
            @endif
            </details>
        </section>
    @endif

    <section class="panel reportes-seccion" id="reportes-guardados">
        <h2>Reportes guardados</h2>
        @if($reportes->isEmpty())
            <p class="reportes-vacio">Aún no hay reportes para mostrar.</p>
        @else
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Escenario</th><th>Exposiciones</th><th>Generado</th></tr></thead>
                <tbody>
                @foreach($reportes as $reporte)
                    <tr>
                        <td><a href="{{ route('geo.reportes.mostrar', $reporte->id) }}">{{ $reporte->peligro }} · v{{ $reporte->numero }}</a></td>
                        <td>{{ $reporte->total }}</td><td>{{ $reporte->creado_en }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $reportes->links() }}
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/lluvia-rango.js') }}?v={{ filemtime(public_path('js/lluvia-rango.js')) }}" defer></script>
@endpush
