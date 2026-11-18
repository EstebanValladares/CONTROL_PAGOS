<?php

namespace App\Http\Controllers;

use App\Services\MetricasService;
use Exception;
use Illuminate\Http\JsonResponse;

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
}
