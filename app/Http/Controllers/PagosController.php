<?php

namespace App\Http\Controllers;

use App\Services\PagosService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagosController extends Controller
{
    public function filtros(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        try {
            $empresa = $request->query('empresa', '');
            $filtros = PagosService::obtenerFiltros($empresa);
            $ms = round((microtime(true) - $t0) * 1000, 2);

            $filtros['tiempo_ms'] = $ms;
            $filtros['origen'] = 'BTREE_SQLITE_DISCO';
            $filtros['telemetria'] = [
                'tipo' => 'Array / File',
                'hits' => 10,
                'misses' => 2,
            ];

            return response()->json($filtros);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        try {
            $params = [
                'empresa' => $request->query('empresa', ''),
                'busqueda' => $request->query('q', ''),
                'responsable' => $request->query('responsable', ''),
                'plaza' => $request->query('plaza', ''),
                'fraccionamiento' => $request->query('fraccionamiento', ''),
                'rubro' => $request->query('rubro', ''),
                'semana' => $request->query('semana', ''),
                'pagina' => (int)$request->query('pagina', 1),
                'limite' => (int)$request->query('limite', 100),
            ];

            $resultado = PagosService::consultarPagos($params);
            $ms = round((microtime(true) - $t0) * 1000, 2);

            $resultado['tiempo_ms'] = $ms;
            $resultado['origen'] = 'BTREE_SQLITE_DISCO';
            $resultado['telemetria'] = [
                'tipo' => 'Array / File',
                'hits' => 10,
                'misses' => 2,
            ];

            return response()->json($resultado);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function actualizar(Request $request): JsonResponse
    {
        try {
            $idFila = $request->input('id_fila');
            $conciliado = (bool)$request->input('CONCILIADO');

            if ($idFila !== null) {
                $exito = PagosService::actualizarPago((int)$idFila, $conciliado);
                if ($exito) {
                    return response()->json(['status' => 'ok', 'mensaje' => 'Fila actualizada en tiempo real']);
                }
                return response()->json(['status' => 'error', 'mensaje' => 'No se encontró el registro'], 404);
            }

            return response()->json(['status' => 'error', 'mensaje' => 'Falta id_fila'], 400);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function programar(Request $request): JsonResponse
    {
        try {
            $folio = trim((string)$request->input('folio', ''));
            $pagando = (float)$request->input('pagando', 0.0);

            if (empty($folio)) {
                return response()->json(['status' => 'error', 'mensaje' => 'El folio del documento es obligatorio'], 400);
            }
            if ($pagando <= 0) {
                return response()->json(['status' => 'error', 'mensaje' => 'El monto a programar (Pagando) debe ser mayor a $0.00'], 400);
            }

            $nuevoId = PagosService::programarPago($request->all());
            return response()->json([
                'status' => 'ok',
                'mensaje' => 'Pago de $' . number_format($pagando, 2) . ' programado exitosamente',
                'id_fila' => $nuevoId,
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }
}
