<?php

use App\Http\Controllers\AdminEscenarioController;
use App\Http\Controllers\AdminInfraestructuraController;
use App\Http\Controllers\AdminUsuarioController;
use App\Http\Controllers\AdminZonaController;
use App\Http\Controllers\AnalisisGeoController;
use App\Http\Controllers\AtlasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\GestionCapasController;
use App\Http\Controllers\GruposController;
use App\Http\Controllers\LugaresController;
use App\Http\Controllers\MapaApiController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PortalGeoController;
use App\Http\Controllers\PronosticoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SuperUsuariosController;
use App\Http\Controllers\TerritorioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

if (config('database.default') === 'pgsql') {
    Route::view('/', 'bienvenida')->name('bienvenida');
    Route::middleware('guest')->group(function () {
        Route::get('/registro', [AuthController::class, 'formularioRegistro'])->name('registro');
        Route::post('/registro', [AuthController::class, 'registrar'])->middleware('throttle:6,1');
    });

    Route::middleware(['auth', 'rol:Consulta,Analista,Administrador'])->name('geo.')->group(function () {
        Route::get('/panel', TerritorioController::class)->name('dashboard');
        Route::get('/visor', [PortalGeoController::class, 'index'])->name('portal');
        Route::get('/api/geo/capas/{capa}', [PortalGeoController::class, 'capa'])
            ->whereNumber('capa')->name('capa');
        Route::post('/api/geo/capas/{capa}/consulta', [PortalGeoController::class, 'consultar'])
            ->whereNumber('capa')->middleware('throttle:60,1,geo-huella-')->name('consultar');
        Route::get('/api/geo/lugares', [LugaresController::class, 'mapa'])->name('lugares.mapa');
        Route::get('/api/geo/atlas/limites', [AtlasController::class, 'limites'])->name('atlas.limites');
        Route::get('/api/geo/atlas/provincias/{provincia}', [AtlasController::class, 'provincia'])
            ->where('provincia', '[A-Z0-9]{1,40}')->name('atlas.provincia');
        Route::get('/api/geo/atlas/equipo', [AtlasController::class, 'equipo'])->name('atlas.equipo');
        Route::post('/api/geo/atlas/ficha', [AtlasController::class, 'ficha'])
            ->middleware('throttle:45,1,geo-ficha-')->name('atlas.ficha');
        Route::post('/api/geo/atlas/{tema}/consulta', [AtlasController::class, 'consultar'])
            ->where('tema', '[a-z0-9-]{1,48}')->middleware('throttle:60,1,geo-tema-')->name('atlas.consultar');
        Route::get('/api/geo/atlas/{tema}/{z}/{x}/{y}.pbf', [AtlasController::class, 'tesela'])
            ->where(['tema' => '[a-z0-9-]{1,48}', 'z' => '[0-9]{1,2}', 'x' => '[0-9]{1,4}', 'y' => '[0-9]{1,4}'])
            ->middleware('throttle:240,1,geo-tesela-')->name('atlas.tesela');
        Route::get('/api/geo/pronostico/{departamento}', PronosticoController::class)
            ->where('departamento', '[A-Z0-9*-]{1,16}')->middleware('throttle:30,1,geo-pronostico-')->name('pronostico');
        Route::get('/api/geo/lluvia/{producto}/{codigo}', [PronosticoController::class, 'lluvia'])
            ->where(['producto' => 'pronostico|historico|mapa', 'codigo' => '[A-Z0-9*-]{1,40}'])
            ->middleware('throttle:20,1,geo-lluvia-')->name('lluvia');
        Route::get('/api/geo/perfiles', [PerfilController::class, 'mapa'])->name('perfil.mapa');
        Route::get('/perfil', [PerfilController::class, 'mostrar'])->name('perfil.mostrar');
        Route::patch('/perfil', [PerfilController::class, 'guardar'])->name('perfil.guardar');
        Route::patch('/perfil/contrasena', [PerfilController::class, 'cambiarContrasena'])
            ->middleware('throttle:8,1')->name('perfil.contrasena');
        Route::post('/perfil/credenciales', [SuperUsuariosController::class, 'activar'])
            ->middleware('throttle:8,1')->name('perfil.credenciales');

        Route::prefix('mis-lugares')->name('lugares.')->group(function () {
            Route::get('/', [LugaresController::class, 'index'])->name('index');
            Route::post('/', [LugaresController::class, 'guardar'])->middleware('throttle:24,1')->name('guardar');
            Route::patch('/{lugar}', [LugaresController::class, 'compartir'])->whereUuid('lugar')->name('compartir');
            Route::patch('/{lugar}/datos', [LugaresController::class, 'editar'])->whereUuid('lugar')->name('editar');
            Route::delete('/{lugar}', [LugaresController::class, 'eliminar'])->whereUuid('lugar')->name('eliminar');
        });
        Route::prefix('grupos')->name('grupos.')->middleware('throttle:24,1')->group(function () {
            Route::post('/', [GruposController::class, 'crear'])->name('crear');
            Route::post('/aceptar', [GruposController::class, 'aceptar'])->name('aceptar');
            Route::post('/{grupo}/invitaciones', [GruposController::class, 'invitar'])->whereUuid('grupo')->name('invitar');
            Route::patch('/{grupo}', [GruposController::class, 'editar'])->whereUuid('grupo')->name('editar');
            Route::delete('/{grupo}', [GruposController::class, 'salir'])->whereUuid('grupo')->name('salir');
        });

        Route::middleware('rol:Analista,Administrador')->prefix('analisis/reportes')->name('reportes.')->group(function () {
            Route::get('/', [AnalisisGeoController::class, 'index'])->name('index');
            Route::get('/{reporte}', [AnalisisGeoController::class, 'mostrar'])->whereUuid('reporte')->name('mostrar');
            Route::post('/', [AnalisisGeoController::class, 'generar'])
                ->middleware('rol:Analista')->name('generar');
        });

        Route::middleware('rol:Administrador')->prefix('catalogo')->name('catalogo.')->group(function () {
            Route::get('/', [CatalogoController::class, 'index'])->name('index');
            Route::post('/coberturas', [CatalogoController::class, 'cobertura'])->name('cobertura');
            Route::post('/capas', [CatalogoController::class, 'capa'])->name('capa');
            Route::patch('/gestion/{tipo}/{registro}', [GestionCapasController::class, 'cambiar'])
                ->where(['tipo' => 'capa|tema', 'registro' => '[a-z0-9-]{1,48}'])->name('gestion');
            Route::post('/importaciones', [CatalogoController::class, 'importar'])->name('importar');
            Route::get('/importaciones/{importacion}', [CatalogoController::class, 'importacion'])
                ->whereNumber('importacion')->name('importacion');
            Route::match(['patch', 'delete'], '/importaciones/{importacion}/entidades/{elemento}', [CatalogoController::class, 'corregir'])
                ->where(['importacion' => '[0-9]{1,18}', 'elemento' => '[1-9][0-9]{0,3}'])->name('corregir');
            Route::get('/importaciones/{importacion}/original', [CatalogoController::class, 'original'])
                ->whereNumber('importacion')->name('original');
            Route::get('/importaciones/{importacion}/paquete', [CatalogoController::class, 'paquete'])
                ->whereNumber('importacion')->name('paquete');
        });

        Route::middleware('rol:Administrador')->prefix('admin/superusuarios')->name('superusuarios.')->group(function () {
            Route::get('/', [SuperUsuariosController::class, 'index'])->name('index');
            Route::post('/{plaza}/invitacion', [SuperUsuariosController::class, 'invitar'])
                ->where('plaza', '[1-5]')->middleware('throttle:12,1')->name('invitar');
        });
    });
} else {
    Route::redirect('/', '/mapa');
}

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/mapa', 'mapa')->name('mapa');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/{id}', [ReporteController::class, 'mostrar'])
        ->whereNumber('id')->name('reportes.mostrar');

    Route::prefix('api')->group(function () {
        Route::get('zonas', [MapaApiController::class, 'zonas']);
        Route::get('zonas/{id}/infraestructuras', [MapaApiController::class, 'infraestructuras']);
        Route::get('zonas/{id}/escenarios', [MapaApiController::class, 'escenarios']);
        Route::get('escenarios/{id}', [MapaApiController::class, 'escenario']);

        // Solo Analista y Administrador pueden analizar (RN05)
        Route::get('escenarios/{id}/exposicion', [MapaApiController::class, 'exposicion'])
            ->middleware('rol:Analista,Administrador');
        Route::post('escenarios/{id}/reportes', [ReporteController::class, 'guardar'])
            ->middleware('rol:Analista,Administrador');
    });
});

Route::middleware(['auth', 'rol:Administrador'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('escenarios', [AdminEscenarioController::class, 'index'])->name('escenarios.index');
    Route::post('escenarios', [AdminEscenarioController::class, 'store'])->name('escenarios.store');
    Route::post('escenarios/{id}/estado', [AdminEscenarioController::class, 'estado'])
        ->whereNumber('id')->name('escenarios.estado');
    Route::delete('escenarios/{id}', [AdminEscenarioController::class, 'destroy'])
        ->whereNumber('id')->name('escenarios.destroy');

    Route::get('usuarios', [AdminUsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('usuarios', [AdminUsuarioController::class, 'store'])->name('usuarios.store');
    Route::put('usuarios/{id}', [AdminUsuarioController::class, 'update'])
        ->whereNumber('id')->name('usuarios.update');

    Route::get('infraestructuras', [AdminInfraestructuraController::class, 'index'])->name('infraestructuras.index');
    Route::post('infraestructuras', [AdminInfraestructuraController::class, 'store'])->name('infraestructuras.store');
    Route::post('infraestructuras/importar', [AdminInfraestructuraController::class, 'importar'])->name('infraestructuras.importar');
    Route::post('infraestructuras/{id}/estado', [AdminInfraestructuraController::class, 'estado'])
        ->whereNumber('id')->name('infraestructuras.estado');
    Route::delete('infraestructuras/{id}', [AdminInfraestructuraController::class, 'destroy'])
        ->whereNumber('id')->name('infraestructuras.destroy');

    Route::get('zonas', [AdminZonaController::class, 'index'])->name('zonas.index');
    Route::post('zonas', [AdminZonaController::class, 'store'])->name('zonas.store');
    Route::get('zonas/{id}/editar', [AdminZonaController::class, 'editar'])
        ->whereNumber('id')->name('zonas.editar');
    Route::put('zonas/{id}', [AdminZonaController::class, 'update'])
        ->whereNumber('id')->name('zonas.update');
    Route::delete('zonas/{id}', [AdminZonaController::class, 'destroy'])
        ->whereNumber('id')->name('zonas.destroy');
});
