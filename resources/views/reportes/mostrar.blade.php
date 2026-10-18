@extends('layouts.app')
@section('titulo', 'Reporte ' . $r->id)

@section('contenido')
    <h1>Reporte de riesgo N.º {{ $r->id }}</h1>

    <div class="kpis">
        <div class="glass kpi"><span>Escenario</span>
            <b style="font-size:20px">{{ $r->escenario }} ({{ ucfirst($r->nivel) }})</b>
            @if($r->origen === 'simulado') <span class="tag" style="margin-top:8px">Simulado</span> @endif
        </div>
        <div class="glass kpi"><span>Infraestructuras expuestas</span><b class="alerta-num">{{ $r->total_expuestas }}</b></div>
        <div class="glass kpi"><span>Generado por</span>
            <b style="font-size:18px">{{ $r->usuario }}</b>
            <span>{{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <h2 style="margin-top:8px">Resumen por tipo</h2>
    <table>
        <tr><th>Tipo</th><th>Cantidad</th></tr>
        @foreach($porTipo as $tipo => $n)
            <tr><td>{{ $tipo }}</td><td>{{ $n }}</td></tr>
        @endforeach
    </table>

    <h2>Detalle</h2>
    <table>
        <tr><th>Infraestructura</th><th>Tipo</th></tr>
        @foreach($detalles as $d)
            <tr><td>{{ $d->nombre_infra }}</td><td>{{ $d->tipo_infra }}</td></tr>
        @endforeach
    </table>

    @if($r->origen === 'simulado')
        <p class="muted"><em>Este reporte se basa en un escenario simulado de demostración; no debe usarse para decisiones reales.</em></p>
    @endif

    <p class="no-imprimir">
        <button class="btn" onclick="window.print()">Imprimir / guardar como PDF</button>
        <a href="{{ route('reportes.index') }}" style="margin-left:12px">Volver al historial</a>
    </p>
@endsection