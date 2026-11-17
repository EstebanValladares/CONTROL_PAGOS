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

// Rutas de API (sesión activa, exentas de CSRF para Vue 3)
Route::prefix('api')->group(function () {
    // Autenticación
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);

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

    // Seguimiento local y reasignación de OCs
    Route::get('/ordenes/seguimiento', [OrdenSeguimientoController::class, 'index']);
    Route::get('/ordenes/seguimiento/compradoras', [OrdenSeguimientoController::class, 'compradoras']);
    Route::get('/ordenes/seguimiento/historial', [OrdenSeguimientoController::class, 'historial']);
    Route::post('/ordenes/seguimiento/nota', [OrdenSeguimientoController::class, 'nota']);
    Route::post('/ordenes/seguimiento/estado', [OrdenSeguimientoController::class, 'estado']);
    Route::post('/ordenes/seguimiento/reasignar-cartera', [OrdenSeguimientoController::class, 'reasignarCartera']);

    // Pagos y Conciliación
    Route::get('/filtros', [PagosController::class, 'filtros']);
    Route::get('/pagos', [PagosController::class, 'index']);
    Route::post('/pagos/actualizar', [PagosController::class, 'actualizar']);
    Route::post('/pagos/programar', [PagosController::class, 'programar']);

    // Métricas y Telemetría
    Route::get('/telemetria', [MetricasController::class, 'telemetria']);
    Route::post('/limpiar-cache', [MetricasController::class, 'limpiarCache']);
    Route::post('/reindexar', [MetricasController::class, 'reindexar']);
});
