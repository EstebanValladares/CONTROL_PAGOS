<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\DB;

class MetricasService
{
    protected static array $diasEsp = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo',
    ];

    public static function obtenerTelemetria(): array
    {
        $t0 = microtime(true);

        $fechaHoy = date('Y-m-d');
        $diaIngles = date('l');
        $diaNombre = self::$diasEsp[$diaIngles] ?? $diaIngles;

        // 1. Totales de OCs registradas
        $totalAryve = (int)DB::table('ordenes_compra_ek9')->where('empresa', 'ARYVE')->count();
        $totalVelas = (int)DB::table('ordenes_compra_ek9')->where('empresa', 'VELAS')->count();
        $totalGlobal = $totalAryve + $totalVelas;

        // 2. OCs de HOY
        $hoyStats = DB::table('ordenes_compra_ek9')
            ->whereRaw("fecha = date('now', 'localtime')")
            ->selectRaw("
                SUM(CASE WHEN empresa = 'ARYVE' THEN 1 ELSE 0 END) as ocs_hoy_aryve,
                SUM(CASE WHEN empresa = 'VELAS' THEN 1 ELSE 0 END) as ocs_hoy_velas,
                ROUND(COALESCE(SUM(CASE WHEN empresa = 'ARYVE' THEN total ELSE 0 END), 0), 2) as monto_hoy_aryve,
                ROUND(COALESCE(SUM(CASE WHEN empresa = 'VELAS' THEN total ELSE 0 END), 0), 2) as monto_hoy_velas
            ")->first();

        $ocsHoyAryve = (int)($hoyStats->ocs_hoy_aryve ?? 0);
        $ocsHoyVelas = (int)($hoyStats->ocs_hoy_velas ?? 0);
        $montoHoyAryve = (float)($hoyStats->monto_hoy_aryve ?? 0.0);
        $montoHoyVelas = (float)($hoyStats->monto_hoy_velas ?? 0.0);

        // 3. Últimos 5 días
        $ultimosDias = DB::table('ordenes_compra_ek9')
            ->selectRaw("
                fecha,
                SUM(CASE WHEN empresa = 'ARYVE' THEN 1 ELSE 0 END) as cant_aryve,
                SUM(CASE WHEN empresa = 'VELAS' THEN 1 ELSE 0 END) as cant_velas,
                COUNT(*) as cant_total
            ")
            ->groupBy('fecha')
            ->orderBy('fecha', 'desc')
            ->limit(5)
            ->get();

        $historialDias = [];
        foreach ($ultimosDias as $d) {
            $dt = DateTime::createFromFormat('Y-m-d', $d->fecha);
            $nombreDia = $dt ? (self::$diasEsp[$dt->format('l')] ?? $dt->format('l')) : $d->fecha;

            $historialDias[] = [
                'fecha' => $d->fecha,
                'dia' => $nombreDia,
                'aryve' => (int)$d->cant_aryve,
                'velas' => (int)$d->cant_velas,
                'total' => (int)$d->cant_total,
            ];
        }

        // 4. Pagos programados / conciliados
        $pagosStats = DB::table('pagos')
            ->selectRaw('COUNT(*) as total_pagos, COALESCE(SUM("PAGO A"), 0) as monto_pagos')
            ->first();

        $pagosProg = (int)($pagosStats->total_pagos ?? 0);
        $montoPagos = round((float)($pagosStats->monto_pagos ?? 0.0), 2);

        $latenciaMs = round((microtime(true) - $t0) * 1000, 2);

        return [
            'status' => 'ok',
            'estado' => 'OPERATIVO',
            'salud' => '100%',
            'version' => '2.0.0 (Laravel)',
            'servidor' => 'Laragon / PHP 8.3 / Apache',
            'uptime_segundos' => 3600,
            'latencia_db_ms' => $latenciaMs,
            'datos' => [
                'fecha_hoy' => $fechaHoy,
                'dia_hoy' => $diaNombre,
                'ocs_hoy_aryve' => $ocsHoyAryve,
                'ocs_hoy_velas' => $ocsHoyVelas,
                'ocs_hoy_total' => $ocsHoyAryve + $ocsHoyVelas,
                'monto_hoy_aryve' => $montoHoyAryve,
                'monto_hoy_velas' => $montoHoyVelas,
                'total_aryve' => $totalAryve,
                'total_velas' => $totalVelas,
                'total_global' => $totalGlobal,
                'historial_dias' => $historialDias,
                'pagos_programados' => $pagosProg,
                'monto_programado' => $montoPagos,
                'errores' => 0,
            ],
            'cache' => [
                'tipo' => 'Array / File',
                'hits' => 10,
                'misses' => 2,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
