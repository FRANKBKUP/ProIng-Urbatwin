<?php

return [
    'mostrar_simulaciones' => env('URBANTWIN_MOSTRAR_SIMULACIONES', false),
    'mostrar_perfiles_demo' => env('URBANTWIN_MOSTRAR_PERFILES_DEMO', false),
    'satelite_academico' => env('URBANTWIN_SATELITE_ACADEMICO', true),
    'pronostico_ca' => env('URBANTWIN_PRONOSTICO_CA', storage_path('app/private/certificados/autoridades.pem')),
];
