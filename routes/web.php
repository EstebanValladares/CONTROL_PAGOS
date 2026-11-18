<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MetricasController;
use App\Http\Controllers\OrdenesController;
use App\Http\Controllers\OrdenSeguimientoController;
use App\Http\Controllers\PagosController;
use Illuminate\Support\Facades\Route;

// Vista Principal SPA
Route::get('/', [HomeController::class, 'index']);

// Rutas de API
Route::prefix('api')->group(function () {
    // 1. Endpoints Públicos (Autenticación, Telemetría y Filtros Base)
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/telemetria', [MetricasController::class, 'telemetria']);
    Route::get('/filtros', [PagosController::class, 'filtros']);

    // 2. Endpoints Protegidos (Requieren Sesión Activa de Usuario)
    Route::middleware('auth.session')->group(function () {
        // Órdenes de Compra
        Route::get('/ordenes-recientes', [OrdenesController::class, 'recientes']);
        Route::get('/ordenes/catalogo-obras', [OrdenesController::class, 'catalogoObras']);
        Route::get('/descargar-excel', [OrdenesController::class, 'descargarExcel']);
        Route::post('/subir-documentos', [OrdenesController::class, 'subirDocumentos']);
        Route::get('/documentos-oc', [OrdenesController::class, 'documentosOc']);
        Route::get('/descargar-archivo-oc/{id}', [OrdenesController::class, 'descargarArchivoOc']);
        Route::post('/ordenes/programar-abono', [OrdenesController::class, 'programarAbono']);
        Route::post('/ordenes/eliminar-abono', [OrdenesController::class, 'eliminarAbono']);
        Route::get('/ordenes/programaciones-semana', [OrdenesController::class, 'programacionesSemana']);
        Route::post('/ordenes/programar-lote', [OrdenesController::class, 'programarLote']);
        Route::post('/ordenes/programaciones/{id}/enviar', [OrdenesController::class, 'enviarProgramacionBi']);
        Route::post('/ordenes/sincronizar', [OrdenesController::class, 'sincronizarEnkontrol']);

        // Seguimiento y reasignación de OCs
        Route::get('/ordenes/seguimiento', [OrdenSeguimientoController::class, 'index']);
        Route::get('/ordenes/seguimiento/compradoras', [OrdenSeguimientoController::class, 'compradoras']);
        Route::get('/ordenes/seguimiento/historial', [OrdenSeguimientoController::class, 'historial']);
        Route::post('/ordenes/seguimiento/nota', [OrdenSeguimientoController::class, 'nota']);
        Route::post('/ordenes/seguimiento/estado', [OrdenSeguimientoController::class, 'estado']);
        Route::post('/ordenes/seguimiento/reasignar-cartera', [OrdenSeguimientoController::class, 'reasignarCartera']);

        // Pagos y Conciliación
        Route::get('/pagos', [PagosController::class, 'index']);
        Route::post('/pagos/actualizar', [PagosController::class, 'actualizar']);
    });
});
