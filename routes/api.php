<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CajaChicaController;
use App\Http\Controllers\Api\ConductorController;
use App\Http\Controllers\Api\GasolineraController;
use App\Http\Controllers\Api\OrdenController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\SolicitudController;
use App\Http\Controllers\Api\VehiculoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Pública: único endpoint que no requiere token
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('yo', [AuthController::class, 'yo']);

    // --- Solo conductor ---
    Route::middleware('can:conductor')->group(function () {
        Route::post('solicitudes', [SolicitudController::class, 'store']);
        Route::post('ordenes/escanear', [OrdenController::class, 'escanear']);
        Route::post('detalle-ordenes/{id}/evidencia', [OrdenController::class, 'subirEvidencia']);
    });

    // --- Todos los roles (el controlador filtra según quién consulta) ---
    Route::get('vehiculos', [VehiculoController::class, 'index']);
    Route::get('solicitudes', [SolicitudController::class, 'index']);
    Route::get('solicitudes/{id}', [SolicitudController::class, 'show']);
    Route::get('ordenes', [OrdenController::class, 'index']);
    Route::get('ordenes/{id}', [OrdenController::class, 'show']);

    // --- Admin y contabilidad: solo lectura ---
    Route::middleware('can:consulta')->group(function () {
        Route::apiResource('conductores', ConductorController::class)->only(['index', 'show']);
        Route::apiResource('vehiculos', VehiculoController::class)->only(['show']);
        Route::apiResource('gasolineras', GasolineraController::class)->only(['index', 'show']);
        Route::apiResource('caja-chica', CajaChicaController::class)->only(['index', 'show']);
        Route::get('reportes/por-conductor', [ReporteController::class, 'porConductor']);
        Route::get('reportes/por-gasolinera', [ReporteController::class, 'porGasolinera']);
    });

    // --- Solo administrador: crear, editar, desactivar y asignar montos ---
    Route::middleware('can:admin')->group(function () {
        Route::apiResource('conductores', ConductorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('vehiculos', VehiculoController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('gasolineras', GasolineraController::class)->only(['store', 'update', 'destroy']);
        Route::post('caja-chica', [CajaChicaController::class, 'store']);
        Route::post('ordenes', [OrdenController::class, 'store']); // asignar montos y generar la orden
    });
});