<?php
use App\Http\Controllers\MapaApiController;
use App\Http\Controllers\AdminInfraestructuraController;
use App\Http\Controllers\AdminZonaController;
use App\Http\Controllers\AdminEscenarioController;
use App\Http\Controllers\AdminUsuarioController;
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