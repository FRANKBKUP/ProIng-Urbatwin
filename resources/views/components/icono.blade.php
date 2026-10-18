@props(['n'])
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($n)
        @case('mapa')
            <path d="M12 21s-6-5.2-6-10a6 6 0 1 1 12 0c0 4.8-6 10-6 10z"/><circle cx="12" cy="11" r="2"/>
            @break
        @case('reportes')
            <path d="M7 3h7l4 4v14H7z"/><path d="M14 3v4h4"/><path d="M10 12h5M10 16h5"/>
            @break
        @case('zonas')
            <path d="M4 8l8-4 8 5-2 9-9 3z"/>
            @break
        @case('escenarios')
            <path d="M3 9c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M3 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/>
            @break
        @case('infraestructura')
            <path d="M5 21V4h9v17"/><path d="M14 9h5v12"/><path d="M8 8h3M8 12h3M8 16h3"/>
            @break
        @case('usuarios')
            <circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5a3 3 0 0 1 0 6M18 14c1.8.7 3 2.5 3 4.5"/>
            @break
        @case('salir')
            <path d="M10 4H5v16h5"/><path d="M14 8l4 4-4 4M18 12H9"/>
            @break
    @endswitch
</svg>