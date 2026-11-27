<?php

use App\Http\Controllers\ConductorWebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GasolineraWebController;
use App\Http\Controllers\OrdenWebController;
use App\Http\Controllers\ReporteWebController;
use App\Http\Controllers\SolicitudWebController;
use App\Http\Controllers\VehiculoWebController;
use App\Http\Controllers\Web\AuthWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect('/dashboard') : redirect('/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login']);
});

Route::middleware(['auth', 'can:web_consulta'])->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/solicitudes', [SolicitudWebController::class, 'index'])->name('web.solicitudes.index');
    Route::get('/ordenes', [OrdenWebController::class, 'index'])->name('web.ordenes.index');
    Route::get('/ordenes/{id}', [OrdenWebController::class, 'show'])->name('web.ordenes.show');
    Route::get('/evidencias/{id}', [OrdenWebController::class, 'verEvidencia'])->name('web.evidencias.ver');
    Route::get('/conductores', [ConductorWebController::class, 'index'])->name('web.conductores.index');
    Route::get('/vehiculos', [VehiculoWebController::class, 'index'])->name('web.vehiculos.index');
    Route::get('/gasolineras', [GasolineraWebController::class, 'index'])->name('web.gasolineras.index');
    Route::get('/reportes', [ReporteWebController::class, 'index'])->name('web.reportes.index');
    Route::get('/reportes/exportar-pdf', [ReporteWebController::class, 'exportarPdf'])
        ->name('web.reportes.pdf');

    Route::middleware('can:web_operativo')->group(function () {
        Route::post('/solicitudes/{id}/rechazar', [SolicitudWebController::class, 'rechazar'])
            ->name('web.solicitudes.rechazar');
        Route::post('/ordenes', [OrdenWebController::class, 'store'])->name('web.ordenes.store');
        Route::post('/ordenes/estimar', [OrdenWebController::class, 'estimar'])->name('web.ordenes.estimar');
        Route::post('/ordenes/{id}/rehabilitar', [OrdenWebController::class, 'rehabilitar'])
            ->name('web.ordenes.rehabilitar');

        Route::post('/conductores', [ConductorWebController::class, 'store'])->name('web.conductores.store');
        Route::put('/conductores/{id}', [ConductorWebController::class, 'update'])->name('web.conductores.update');
        Route::delete('/conductores/{id}', [ConductorWebController::class, 'destroy'])->name('web.conductores.destroy');

        Route::post('/vehiculos', [VehiculoWebController::class, 'store'])->name('web.vehiculos.store');
        Route::put('/vehiculos/{id}', [VehiculoWebController::class, 'update'])->name('web.vehiculos.update');
        Route::delete('/vehiculos/{id}', [VehiculoWebController::class, 'destroy'])->name('web.vehiculos.destroy');

        Route::post('/gasolineras', [GasolineraWebController::class, 'store'])->name('web.gasolineras.store');
        Route::put('/gasolineras/{id}', [GasolineraWebController::class, 'update'])->name('web.gasolineras.update');
        Route::delete('/gasolineras/{id}', [GasolineraWebController::class, 'destroy'])->name('web.gasolineras.destroy');

    });
});
