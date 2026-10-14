<?php
use App\Http\Controllers\MapaApiController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/mapa');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

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