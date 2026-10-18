@extends('layouts.app')
@section('titulo', 'Reportes')

@section('contenido')
    @php $lista = collect($reportes); @endphp

    <div class="cabecera">
        <h1>Reportes de riesgo</h1>
        <p class="muted">Historial de análisis guardados. Los reportes no se modifican una vez generados.</p>
    </div>

    <div class="kpis">
        <div class="kpi"><span class="etiqueta">Reportes guardados</span><span class="valor">{{ $lista->count() }}</span></div>
        <div class="kpi"><span class="etiqueta">Mayor exposición registrada</span><span class="valor alerta-num">{{ $lista->max('total_expuestas') ?? 0 }}</span></div>
        <div class="kpi"><span class="etiqueta">Último reporte</span>
            <span class="valor pequeno">{{ $lista->isEmpty() ? 'Sin reportes' : \Carbon\Carbon::parse($lista->first()->generado_en)->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <table>
        <tr><th class="num">N.º</th><th>Escenario</th><th class="num">Expuestas</th><th>Generado por</th><th>Fecha</th><th></th></tr>
        @forelse($lista as $r)
            <tr>
                <td class="num">{{ $r->id }}</td>
                <td>{{ $r->escenario }} @if($r->origen === 'simulado') <span class="tag">Simulado</span> @endif</td>
                <td class="num">{{ $r->total_expuestas }}</td>
                <td>{{ $r->usuario }}</td>
                <td>{{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }}</td>
                <td><a class="btn-sec" href="{{ route('reportes.mostrar', $r->id) }}">Ver reporte</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Aún no hay reportes. Genera uno desde el mapa.</td></tr>
        @endforelse
    </table>
@endsection