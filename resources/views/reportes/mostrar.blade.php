@extends('layouts.app')
@section('titulo', 'Reporte ' . $r->id)

@section('contenido')
    <div class="cabecera" style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
        <div>
            <h1>Reporte de riesgo N.º {{ $r->id }}</h1>
            <p class="muted">Generado el {{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }} por {{ $r->usuario }}</p>
        </div>
        <div class="no-imprimir">
            <button class="btn" onclick="window.print()">Imprimir / guardar como PDF</button>
        </div>
    </div>

    <div class="kpis">
        <div class="kpi">
            <span class="etiqueta">Escenario</span>
            <span class="valor pequeno">{{ $r->escenario }} ({{ ucfirst($r->nivel) }})</span>
            @if($r->origen === 'simulado') <span class="tag">Simulado</span> @endif
        </div>
        <div class="kpi">
            <span class="etiqueta">Infraestructuras expuestas</span>
            <span class="valor alerta-num">{{ $r->total_expuestas }}</span>
        </div>
    </div>

    <h2 style="margin-top:8px">Resumen por tipo</h2>
    <table>
        <tr><th>Tipo</th><th class="num">Cantidad</th></tr>
        @foreach($porTipo as $tipo => $n)
            <tr><td>{{ $tipo }}</td><td class="num">{{ $n }}</td></tr>
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

    <p class="no-imprimir"><a href="{{ route('reportes.index') }}">Volver al historial</a></p>
@endsection