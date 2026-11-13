<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PagosService
{
    public static function obtenerFiltros(string $empresa = ''): array
    {
        $empresa = strtoupper(trim($empresa));
        $cacheKey = "filtros_pagos_{$empresa}";

        return Cache::remember($cacheKey, 300, function () use ($empresa) {
            $query = DB::table('pagos');

            if ($empresa === 'VELAS') {
                $query->where('PROPIETARIO', 'GVDC');
            } elseif ($empresa === 'ARYVE') {
                $query->whereIn('PROPIETARIO', ['ARYVE', 'Aryve']);
            }

            $responsables = (clone $query)
                ->whereNotNull('ENVIADO POR')
                ->where('ENVIADO POR', '!=', '')
                ->distinct()
                ->orderBy('ENVIADO POR')
                ->pluck('ENVIADO POR')
                ->toArray();

            $plazas = (clone $query)
                ->whereNotNull('ZONA')
                ->where('ZONA', '!=', '')
                ->distinct()
                ->orderBy('ZONA')
                ->pluck('ZONA')
                ->toArray();

            $fraccionamientos = (clone $query)
                ->whereNotNull('FRACCIONAMIENTO')
                ->where('FRACCIONAMIENTO', '!=', '')
                ->distinct()
                ->orderBy('FRACCIONAMIENTO')
                ->pluck('FRACCIONAMIENTO')
                ->toArray();

            $rubros = (clone $query)
                ->whereNotNull('RUBRO')
                ->where('RUBRO', '!=', '')
                ->distinct()
                ->orderBy('RUBRO')
                ->pluck('RUBRO')
                ->toArray();

            $semanas = (clone $query)
                ->whereNotNull('SEMANA')
                ->where('SEMANA', '!=', '')
                ->distinct()
                ->orderBy('SEMANA', 'desc')
                ->pluck('SEMANA')
                ->toArray();

            $totales = (clone $query)->selectRaw('
                COUNT(*) as total_registros,
                SUM(IMPORTE) as total_importe,
                SUM("PAGO A") as total_pagando,
                SUM(DIFERENCIA) as total_diferencia
            ')->first();

            $semanaActual = null;
            try {
                $semRow = DB::table('cat_semanas_ek9')
                    ->whereRaw("fecha_inicio <= date('now', 'localtime')")
                    ->whereRaw("fecha_final >= date('now', 'localtime')")
                    ->first();

                if (!$semRow) {
                    $ahora = new \DateTime();
                    $diaSemana = (int)$ahora->format('N'); // 1 = lunes, 7 = domingo
                    $lunes = (clone $ahora)->modify('-' . ($diaSemana - 1) . ' days')->format('Y-m-d');
                    $domingo = (clone $ahora)->modify('+' . (7 - $diaSemana) . ' days')->format('Y-m-d');
                    $semNum = (int)$ahora->format('W');
                    $yearNum = (int)$ahora->format('Y');
                    $etiqueta = "SEM: {$semNum}-{$yearNum}";

                    DB::table('cat_semanas_ek9')->insertOrIgnore([
                        'etiqueta' => $etiqueta,
                        'sem' => $semNum,
                        'year' => $yearNum,
                        'fecha_inicio' => $lunes,
                        'fecha_final' => $domingo,
                    ]);

                    $semRow = (object)[
                        'etiqueta' => $etiqueta,
                        'sem' => $semNum,
                        'year' => $yearNum,
                        'fecha_inicio' => $lunes,
                        'fecha_final' => $domingo,
                    ];
                }

                if ($semRow) {
                    $semanaActual = [
                        'etiqueta' => $semRow->etiqueta,
                        'sem' => $semRow->sem,
                        'year' => $semRow->year,
                        'fecha_inicio' => $semRow->fecha_inicio,
                        'fecha_final' => $semRow->fecha_final,
                    ];
                }
            } catch (Exception $e) {}

            return [
                'status' => 'ok',
                'empresa' => $empresa,
                'responsables' => $responsables,
                'plazas' => $plazas,
                'fraccionamientos' => $fraccionamientos,
                'rubros' => $rubros,
                'semanas' => $semanas,
                'semana_actual' => $semanaActual,
                'kpis_globales' => [
                    'total_registros' => (int)($totales->total_registros ?? 0),
                    'total_importe' => round((float)($totales->total_importe ?? 0.0), 2),
                    'total_pagando' => round((float)($totales->total_pagando ?? 0.0), 2),
                    'total_diferencia' => round((float)($totales->total_diferencia ?? 0.0), 2),
                ],
            ];
        });
    }

    public static function consultarPagos(array $params): array
    {
        $empresa = strtoupper(trim($params['empresa'] ?? ''));
        $busqueda = trim($params['busqueda'] ?? '');
        $responsable = trim($params['responsable'] ?? '');
        $plaza = trim($params['plaza'] ?? '');
        $fraccionamiento = trim($params['fraccionamiento'] ?? '');
        $rubro = trim($params['rubro'] ?? '');
        $semana = trim($params['semana'] ?? '');
        $limite = max(1, (int)($params['limite'] ?? 100));
        $pagina = max(1, (int)($params['pagina'] ?? 1));
        $offset = ($pagina - 1) * $limite;

        $query = DB::table('pagos');

        if ($empresa === 'VELAS') {
            $query->where('PROPIETARIO', 'GVDC');
        } elseif ($empresa === 'ARYVE') {
            $query->whereIn('PROPIETARIO', ['ARYVE', 'Aryve']);
        }

        if (!empty($busqueda)) {
            $p = "%{$busqueda}%";
            $query->where(function ($q) use ($p) {
                $q->where('O.C.', 'LIKE', $p)
                  ->orWhere('CONTRATISTA', 'LIKE', $p)
                  ->orWhere('OBRA', 'LIKE', $p)
                  ->orWhere('CONCEPTO', 'LIKE', $p);
            });
        }

        if (!empty($responsable) && !in_array(strtoupper($responsable), ['ADMIN', 'TODOS'], true)) {
            $query->where(function ($q) use ($responsable) {
                $q->where('ENVIADO POR', $responsable)
                  ->orWhere('ENVIADO POR', 'LIKE', "%{$responsable}%");
            });
        }

        if (!empty($plaza)) {
            $query->where('ZONA', $plaza);
        }

        if (!empty($fraccionamiento)) {
            $query->where('FRACCIONAMIENTO', $fraccionamiento);
        }

        if (!empty($rubro)) {
            $query->where('RUBRO', $rubro);
        }

        if (!empty($semana)) {
            $query->where('SEMANA', $semana);
        }

        $totales = (clone $query)->selectRaw('
            COUNT(*) as total,
            SUM(IMPORTE) as suma_importe,
            SUM("PAGO A") as suma_pagando,
            SUM(DIFERENCIA) as suma_diferencia
        ')->first();

        $totalCoincidencias = (int)($totales->total ?? 0);
        $sumaImporte = round((float)($totales->suma_importe ?? 0.0), 2);
        $sumaPagando = round((float)($totales->suma_pagando ?? 0.0), 2);
        $sumaDiferencia = round((float)($totales->suma_diferencia ?? 0.0), 2);

        $filas = $query->select(
            'id_fila', 'OBRA', 'NOMBRE OBRA', 'O.C.', 'CONTRATISTA', 'RUBRO',
            'IMPORTE', 'PAGO A', 'DIFERENCIA', 'ENVIADO POR', 'ZONA', 'FRACCIONAMIENTO', 'SEMANA',
            'ESTATUS', 'CONCILIADO', 'CLAVE PROV', 'ID PROV'
        )
        ->orderBy('id_fila', 'desc')
        ->offset($offset)
        ->limit($limite)
        ->get()
        ->map(fn($item) => (array)$item)
        ->toArray();

        $totalPaginas = $totalCoincidencias > 0 ? (int)ceil($totalCoincidencias / $limite) : 1;

        return [
            'status' => 'ok',
            'pagina' => $pagina,
            'total_paginas' => $totalPaginas,
            'total_filtrado' => $totalCoincidencias,
            'suma_importe' => $sumaImporte,
            'suma_pagando' => $sumaPagando,
            'suma_diferencia' => $sumaDiferencia,
            'datos' => $filas,
        ];
    }

    public static function actualizarPago(int $idFila, bool $conciliado): bool
    {
        $estatus = $conciliado ? 'APROBADO' : 'PENDIENTE';
        $valConciliado = $conciliado ? 1 : 0;

        $afectados = DB::table('pagos')
            ->where('id_fila', $idFila)
            ->update([
                'CONCILIADO' => $valConciliado,
                'ESTATUS' => $estatus,
            ]);

        Cache::flush();
        return $afectados > 0;
    }

    public static function programarPago(array $datos): int
    {
        $maxId = (int)DB::table('pagos')->max('id_fila');
        $nextId = $maxId + 1;
        $fechaHoy = date('d/m/Y');
        $empresa = strtoupper(trim($datos['empresa'] ?? 'ARYVE'));
        $propietario = ($empresa === 'VELAS') ? 'GVDC' : 'ARYVE';

        DB::table('pagos')->insert([
            'id_fila' => $nextId,
            'OBRA' => $datos['obra'] ?? '',
            'EST' => $datos['tipo'] ?? 'OC',
            'O.C.' => $datos['folio'] ?? '',
            'CONTRATISTA' => $datos['proveedor'] ?? '',
            'IMPORTE' => (float)($datos['importe'] ?? 0.0),
            'PAGO A' => (float)($datos['pagando'] ?? 0.0),
            'RUBRO' => $datos['rubro'] ?? 'MATERIALES',
            'CONCEPTO' => $datos['concepto'] ?? '',
            'FECHA' => $fechaHoy,
            'ENVIADO POR' => $datos['enviado_por'] ?? '',
            'SEMANA' => $datos['semana'] ?? '',
            'CIA' => $empresa,
            'ZONA' => $datos['zona'] ?? '',
            'TIPO' => $datos['tipo'] ?? 'OC',
            'PROPIETARIO' => $propietario,
            'DIFERENCIA' => (float)($datos['diferencia'] ?? 0.0),
            'CONCILIADO' => 0,
            'ESTATUS' => 'PROGRAMADO',
        ]);

        Cache::flush();
        return $nextId;
    }

    public static function generarCsvConciliado(array $params): string
    {
        $empresa = strtoupper(trim($params['empresa'] ?? ''));
        $plaza = trim($params['plaza'] ?? '');
        $semana = trim($params['semana'] ?? '');
        $responsable = trim($params['responsable'] ?? '');

        $query = DB::table('pagos');

        if ($empresa === 'VELAS') {
            $query->where('PROPIETARIO', 'GVDC');
        } elseif ($empresa === 'ARYVE') {
            $query->whereIn('PROPIETARIO', ['ARYVE', 'Aryve']);
        }

        if (!empty($plaza)) {
            $query->where('ZONA', $plaza);
        }

        if (!empty($semana)) {
            $query->where('SEMANA', $semana);
        }

        if (!empty($responsable) && !in_array(strtoupper($responsable), ['ADMIN', 'TODOS'], true)) {
            $query->where(function ($q) use ($responsable) {
                $q->where('ENVIADO POR', $responsable)
                  ->orWhere('ENVIADO POR', 'LIKE', "%{$responsable}%");
            });
        }

        $registros = $query->get();

        $rutaExport = storage_path('app/concentrado_oc_conciliado.csv');
        $fh = fopen($rutaExport, 'w');
        // Escribir BOM UTF-8 para apertura directa en Excel con acentos correctos
        fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));

        if ($registros->isNotEmpty()) {
            $columnas = array_keys((array)$registros->first());
            fputcsv($fh, $columnas);

            foreach ($registros as $row) {
                fputcsv($fh, array_values((array)$row));
            }
        } else {
            fputcsv($fh, ['Sin registros']);
        }

        fclose($fh);
        return $rutaExport;
    }
}
