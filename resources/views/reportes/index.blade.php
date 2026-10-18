@extends('layouts.app')
@section('titulo', 'Reportes')

@section('contenido')
    @php $lista = collect($reportes); @endphp
    <h1>Reportes de riesgo</h1>

    <div class="kpis">
        <div class="glass kpi"><span>Reportes guardados</span><b>{{ $lista->count() }}</b></div>
        <div class="glass kpi"><span>Mayor exposición registrada</span><b class="alerta-num">{{ $lista->max('total_expuestas') ?? 0 }}</b></div>
        <div class="glass kpi"><span>Último reporte</span>
            <b style="font-size:20px">{{ $lista->isEmpty() ? '–' : \Carbon\Carbon::parse($lista->first()->generado_en)->format('d/m/Y H:i') }}</b>
        </div>
    </div>

    <table>
        <tr><th>N.º</th><th>Escenario</th><th>Expuestas</th><th>Generado por</th><th>Fecha</th><th></th></tr>
        @forelse($lista as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->escenario }} @if($r->origen === 'simulado') <span class="tag">Simulado</span> @endif</td>
                <td>{{ $r->total_expuestas }}</td>
                <td>{{ $r->usuario }}</td>
                <td>{{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }}</td>
                <td><a class="btn-sec" href="{{ route('reportes.mostrar', $r->id) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Aún no hay reportes. Genera uno desde el mapa.</td></tr>
        @endforelse
    </table>
@endsection