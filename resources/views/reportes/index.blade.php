@extends('layouts.app')
@section('titulo', 'Reportes')

@section('contenido')
    <h1>Reportes de riesgo</h1>
    <table>
        <tr><th>N.º</th><th>Escenario</th><th>Expuestas</th><th>Generado por</th><th>Fecha</th><th></th></tr>
        @forelse($reportes as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->escenario }}
                    @if($r->origen === 'simulado') <span class="tag">Simulado</span> @endif
                </td>
                <td>{{ $r->total_expuestas }}</td>
                <td>{{ $r->usuario }}</td>
                <td>{{ \Carbon\Carbon::parse($r->generado_en)->format('d/m/Y H:i') }}</td>
                <td><a href="/reportes/{{ $r->id }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6">Aún no hay reportes. Genera uno desde el mapa.</td></tr>
        @endforelse
    </table>
@endsection