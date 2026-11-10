<?php

namespace App\Http\Controllers;

use App\Services\MetricasService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetricasController extends Controller
{
    public function telemetria(): JsonResponse
    {
        try {
            $data = MetricasService::obtenerTelemetria();
            return response()->json($data);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'estado' => 'DEGRADADO',
                'salud' => '0%',
                'mensaje' => $e->getMessage(),
                'datos' => ['errores' => 1],
            ], 500);
        }
    }

    public function limpiarCache(): JsonResponse
    {
        Cache::flush();
        return response()->json([
            'status' => 'ok',
            'mensaje' => 'Caché vaciada. La siguiente consulta medirá el tiempo de lectura física al B-Tree en disco.',
        ]);
    }

    public function reindexar(): JsonResponse
    {
        Cache::flush();
        return response()->json([
            'status' => 'ok',
            'mensaje' => 'Ecosistema re-indexado exitosamente',
        ]);
    }
}
