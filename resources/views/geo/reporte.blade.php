@extends('layouts.app')
@section('titulo', 'Reporte de exposición')

@section('contenido')
    <header class="cabecera">
        <a href="{{ route('geo.reportes.index') }}">Volver a reportes</a>
        <h1>Exposición por inundación</h1>
        <p>{{ $reporte->peligro }} · v{{ $reporte->numero }} · {{ $reporte->creado_en }}</p>
    </header>
    <section class="panel">
        <h2>Resultados · {{ $reporte->total }} exposiciones</h2>
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Elemento</th><th>Tipo</th><th>Tramo expuesto</th></tr></thead>
                <tbody>
                @forelse($resultados as $resultado)
                    <tr>
                        <td>{{ $resultado['nombre'] }}</td><td>{{ $resultado['tipo'] }}</td>
                        <td>{{ $resultado['longitud_m'] === null ? '—' : number_format($resultado['longitud_m'], 2, ',', ' ').' m' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sin exposiciones para las capas analizadas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <h2>Datos que sustentan el reporte</h2>
        <ul>
            @foreach($versiones as $version)
                <li>{{ $version->nombre }} · v{{ $version->numero }} (ID {{ $version->version_id }}) · {{ $version->origen }}
                    · {{ $version->fecha_dato }} · {{ $version->fuente }} · {{ $version->licencia }}</li>
            @endforeach
        </ul>
        <p>{{ $parametros['algoritmo'] }} · EPSG:{{ $parametros['srid_metrico'] }} · {{ $parametros['via'] }}.</p>
    </section>
@endsection
