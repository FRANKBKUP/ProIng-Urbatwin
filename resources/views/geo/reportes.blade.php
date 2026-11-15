@extends('layouts.app')
@section('titulo', 'Análisis y reportes')

@section('contenido')
    <header class="cabecera"><h1>Análisis y reportes</h1></header>
    @if(auth()->user()->tieneRol('Analista'))
        <section class="panel">
            <h2>Ejecutar análisis de exposición</h2>
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
        </section>
    @endif

    <section class="panel">
        <h2>Reportes guardados</h2>
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Escenario</th><th>Exposiciones</th><th>Generado</th></tr></thead>
                <tbody>
                @forelse($reportes as $reporte)
                    <tr>
                        <td><a href="{{ route('geo.reportes.mostrar', $reporte->id) }}">{{ $reporte->peligro }} · v{{ $reporte->numero }}</a></td>
                        <td>{{ $reporte->total }}</td><td>{{ $reporte->creado_en }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Aún no hay reportes para mostrar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $reportes->links() }}
    </section>
@endsection
