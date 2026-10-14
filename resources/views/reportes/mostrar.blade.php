@extends('layouts.app')
@section('titulo', 'Reporte ' . $r->id)

@section('contenido')
    <h1>Reporte de riesgo N.º {{ $r->id }}</h1>
    <p>
        <strong>Escenario:</strong> {{ $r->escenario }} ({{ ucfirst($r->nivel) }})
        @if($r->origen === 'simulado') <span class="tag">Simulado</span> @endif<br>
        <strong>Generado por:</strong> {{ $r->usuario }}<br>
        <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }}<br>
        <strong>Infraestructuras expuestas:</strong> {{ $r->total_expuestas }}
    </p>

    <h3>Resumen por tipo</h3>
    <table>
        <tr><th>Tipo</th><th>Cantidad</th></tr>
        @foreach($porTipo as $tipo => $n)
            <tr><td>{{ $tipo }}</td><td>{{ $n }}</td></tr>
        @endforeach
    </table>

    <h3>Detalle</h3>
    <table>
        <tr><th>Infraestructura</th><th>Tipo</th></tr>
        @foreach($detalles as $d)
            <tr><td>{{ $d->nombre_infra }}</td><td>{{ $d->tipo_infra }}</td></tr>
        @endforeach
    </table>

    @if($r->origen === 'simulado')
        <p><em>Este reporte se basa en un escenario simulado de demostración; no debe usarse para decisiones reales.</em></p>
    @endif

    <p class="no-imprimir">
        <button class="btn" onclick="window.print()">Imprimir / guardar como PDF</button>
        <a href="/reportes">Volver al historial</a>
    </p>
@endsection