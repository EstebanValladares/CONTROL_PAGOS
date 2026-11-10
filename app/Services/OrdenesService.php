<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrdenesService
{
    public static function asegurarColumnaEstadoEnvio(): void
    {
        try {
            $cols = DB::select("PRAGMA table_info(programaciones_oc);");
            $nombres = array_map(fn($c) => $c->name, $cols);
            if (!in_array('estado_envio', $nombres, true)) {
                DB::statement("ALTER TABLE programaciones_oc ADD COLUMN estado_envio TEXT NOT NULL DEFAULT 'ENVIADO';");
            }
        } catch (Exception $e) {
            Log::warning("[DB] Error verificando estado_envio: " . $e->getMessage());
        }
    }

    public static function obtenerMaxFecha(string $empresa): ?string
    {
        $row = DB::table('ordenes_compra_ek9')
            ->where('empresa', $empresa)
            ->max('fecha');
        return $row ?: null;
    }

    public static function obtenerCatalogoObras(string $empresa = 'ARYVE'): array
    {
        return DB::table('ordenes_compra_ek9')
            ->where('empresa', $empresa)
            ->whereNotNull('centro_costos')
            ->where('centro_costos', '!=', '')
            ->distinct()
            ->orderBy('centro_costos', 'asc')
            ->pluck('centro_costos')
            ->toArray();
    }

    public static function consultarOrdenesRecientes(array $params): array
    {
        $empresa = strtoupper(trim($params['empresa'] ?? 'ARYVE'));
        if (!in_array($empresa, ['ARYVE', 'VELAS'], true)) {
            $empresa = 'ARYVE';
        }

        $empleadoId = trim((string)($params['empleado_id'] ?? ''));
        $filtroTemporal = strtoupper(trim((string)($params['filtro_temporal'] ?? 'HOY')));
        $filtroSemana = trim((string)($params['semana'] ?? ''));
        $busqueda = trim((string)($params['busqueda'] ?? ''));
        $obra = trim((string)($params['obra'] ?? ''));
        $misOrdenes = filter_var($params['mis_ordenes'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $limite = max(1, (int)($params['limite'] ?? 50));
        $pagina = max(1, (int)($params['pagina'] ?? 1));
        $offset = ($pagina - 1) * $limite;

        $maxFecha = self::obtenerMaxFecha($empresa);

        // Construir query base
        $query = DB::table('ordenes_compra_ek9')->where('empresa', $empresa);

        // Filtro por usuario / mis_ordenes
        if ($misOrdenes && !empty($empleadoId) && $empleadoId !== '0' && strtoupper($empleadoId) !== 'ADMIN') {
            if (ctype_digit($empleadoId)) {
                $query->where('id_empleado', (int)$empleadoId);
            }
        }

        // Filtro por semana o temporal
        if (!empty($filtroSemana)) {
            $catSemana = DB::table('cat_semanas_ek9')
                ->where('etiqueta', $filtroSemana)
                ->orWhere('sem', $filtroSemana)
                ->first();
            if ($catSemana && !empty($catSemana->fecha_inicio) && !empty($catSemana->fecha_final)) {
                $query->whereBetween('fecha', [$catSemana->fecha_inicio, $catSemana->fecha_final]);
            }
        } elseif ($filtroTemporal === 'HOY') {
            $query->whereRaw("fecha = date('now', 'localtime')");
        } elseif ($filtroTemporal === 'SEMANA') {
            $query->whereRaw("fecha >= date('now', '-7 days')");
        } elseif ($filtroTemporal === 'MES') {
            $query->whereRaw("fecha >= date('now', 'start of month')");
        }

        if (!empty($obra)) {
            $query->where('centro_costos', $obra);
        }

        if (!empty($busqueda)) {
            $p = "%{$busqueda}%";
            $query->where(function ($q) use ($p) {
                $q->whereRaw("CAST(numero_oc AS TEXT) LIKE ?", [$p])
                  ->orWhere('proveedor', 'LIKE', $p)
                  ->orWhere('centro_costos', 'LIKE', $p)
                  ->orWhere('id_ek10', 'LIKE', $p)
                  ->orWhere('comentarios', 'LIKE', $p);
            });
        }

        // Totales de la consulta filtrada
        $conteoQuery = clone $query;
        $totalCoincidencias = $conteoQuery->count();
        $sumaTotal = round((float)($conteoQuery->sum('total') ?? 0.0), 2);

        // Obtener filas paginadas
        $filas = $query->select(
            'id', 'empresa', 'centro_costos', 'numero_oc', 'fecha', 'proveedor',
            'total', 'total_rec', 'total_fac', 'estatus', 'solicito_empleado', 'id_empleado',
            'id_ek10', 'comentarios', 'actualizado_en', 'tipo_sugerido', 'rubro_sugerido'
        )
        ->orderBy('fecha', 'desc')
        ->orderBy('numero_oc', 'desc')
        ->offset($offset)
        ->limit($limite)
        ->get()
        ->map(fn($item) => (array)$item)
        ->toArray();

        // Enlazar programaciones y documentos para cada OC
        if (!empty($filas)) {
            foreach ($filas as &$f) {
                $progs = DB::table('programaciones_oc')
                    ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en', 'estado_envio')
                    ->where('empresa', $f['empresa'])
                    ->where('centro_costos', $f['centro_costos'])
                    ->where('numero_oc', $f['numero_oc'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(fn($p) => (array)$p)
                    ->toArray();

                $f['programaciones'] = $progs;
                $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
                $f['total_programado'] = round((float)$pagadoAcum, 2);
                $f['saldo_disponible'] = max(0.0, round(((float)($f['total'] ?? 0.0)) - $pagadoAcum, 2));

                $docs = DB::table('documentos_oc')
                    ->select('id', 'nombre_original', 'nombre_guardado', 'tamano_bytes', 'creado_en')
                    ->where('empresa', $f['empresa'])
                    ->where('centro_costos', $f['centro_costos'])
                    ->where('numero_oc', $f['numero_oc'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(fn($d) => (array)$d)
                    ->toArray();

                $f['documentos'] = $docs;
                $f['total_documentos'] = count($docs);
            }
            unset($f);
        }

        // KPIs rápidos (Hoy vs Acumulado)
        $kpiQuery = DB::table('ordenes_compra_ek9')->where('empresa', $empresa);
        if ($misOrdenes && !empty($empleadoId) && $empleadoId !== '0' && strtoupper($empleadoId) !== 'ADMIN') {
            if (ctype_digit($empleadoId)) {
                $kpiQuery->where('id_empleado', (int)$empleadoId);
            }
        }

        $kpisRaw = $kpiQuery->selectRaw("
            SUM(CASE WHEN fecha = date('now', 'localtime') THEN 1 ELSE 0 END) as ordenes_hoy,
            SUM(CASE WHEN fecha = date('now', 'localtime') THEN total ELSE 0 END) as monto_hoy,
            COUNT(*) as total_acumulado,
            SUM(total) as monto_acumulado
        ")->first();

        $kpis = [
            'ordenes_hoy' => (int)($kpisRaw->ordenes_hoy ?? 0),
            'monto_hoy' => round((float)($kpisRaw->monto_hoy ?? 0.0), 2),
            'total_acumulado' => (int)($kpisRaw->total_acumulado ?? 0),
            'monto_acumulado' => round((float)($kpisRaw->monto_acumulado ?? 0.0), 2),
        ];

        $totalPaginas = $totalCoincidencias > 0 ? (int)ceil($totalCoincidencias / $limite) : 1;

        return [
            'status' => 'ok',
            'empresa' => $empresa,
            'max_fecha' => $maxFecha,
            'kpis' => $kpis,
            'total_filtrado' => $totalCoincidencias,
            'suma_total_filtrado' => $sumaTotal,
            'total_paginas' => $totalPaginas,
            'pagina' => $pagina,
            'datos' => $filas,
        ];
    }

    public static function obtenerProgramacionesSemana(string $empresa, string $semana = '', string $obra = ''): array
    {
        self::asegurarColumnaEstadoEnvio();

        $empresa = strtoupper(trim($empresa ?: 'ARYVE'));
        $query = DB::table('programaciones_oc')->where('empresa', $empresa);

        if (!empty($semana)) {
            $semanaLimpia = trim(str_replace(['SEM:', 'SEM.'], '', $semana));
            $query->where(function ($q) use ($semana, $semanaLimpia) {
                $q->where('semana', $semana)
                  ->orWhere('semana', "SEM: {$semanaLimpia}")
                  ->orWhere('semana', 'LIKE', "%{$semanaLimpia}%");
            });
        }

        if (!empty($obra)) {
            $query->where('centro_costos', $obra);
        }

        $totales = (clone $query)->selectRaw('COUNT(*) as conteo, SUM(monto_programado) as monto')->first();
        $totalItems = (int)($totales->conteo ?? 0);
        $totalMonto = round((float)($totales->monto ?? 0.0), 2);

        $filas = $query->select(
            'id', 'empresa', 'centro_costos', 'numero_oc', 'id_ek10', 'proveedor',
            'total_oc', 'monto_programado', 'semana', 'rubro', 'tipo', 'concepto',
            'creado_por', 'creado_en', 'estado_envio'
        )
        ->selectSub(function ($sub) {
            $sub->from('documentos_oc')
                ->selectRaw('COUNT(*)')
                ->whereColumn('documentos_oc.empresa', 'programaciones_oc.empresa')
                ->whereColumn('documentos_oc.centro_costos', 'programaciones_oc.centro_costos')
                ->whereColumn('documentos_oc.numero_oc', 'programaciones_oc.numero_oc');
        }, 'total_documentos')
        ->orderBy('id', 'desc')
        ->get()
        ->map(function ($row) {
            $arr = (array)$row;
            $arr['es_pendiente'] = ($arr['estado_envio'] ?? '') === 'PENDIENTE';
            return $arr;
        })
        ->toArray();

        return [
            'status' => 'ok',
            'semana' => $semana,
            'obra' => $obra,
            'total_items' => $totalItems,
            'total_monto' => $totalMonto,
            'datos' => $filas,
        ];
    }

    public static function programarAbono(array $datos): array
    {
        $empresa = strtoupper(trim($datos['empresa'] ?? 'ARYVE'));
        $centroCostos = trim($datos['centro_costos'] ?? '');
        $numeroOc = (int)($datos['numero_oc'] ?? 0);
        $idEk10 = trim($datos['id_ek10'] ?? '');
        $proveedor = trim($datos['proveedor'] ?? '');
        $totalOc = (float)($datos['total_oc'] ?? 0.0);
        $montoProgramado = (float)($datos['monto_programado'] ?? 0.0);
        $semana = trim($datos['semana'] ?? '');
        $rubro = trim($datos['rubro'] ?? 'MATERIALES');
        $tipo = trim($datos['tipo'] ?? '');
        $concepto = trim($datos['concepto'] ?? '');
        $creadoPor = trim($datos['creado_por'] ?? 'USUARIO');

        if ($numeroOc <= 0) {
            throw new InvalidArgumentException("Número de OC inválido");
        }
        if ($montoProgramado <= 0) {
            throw new InvalidArgumentException("El monto a programar debe ser mayor a $0.00");
        }

        $ahora = date('Y-m-d H:i:s');

        $nuevoId = DB::table('programaciones_oc')->insertGetId([
            'empresa' => $empresa,
            'centro_costos' => $centroCostos,
            'numero_oc' => $numeroOc,
            'id_ek10' => $idEk10,
            'proveedor' => $proveedor,
            'total_oc' => $totalOc,
            'monto_programado' => $montoProgramado,
            'semana' => $semana,
            'rubro' => $rubro,
            'tipo' => $tipo,
            'concepto' => $concepto,
            'creado_por' => $creadoPor,
            'creado_en' => $ahora,
            'estado_envio' => 'ENVIADO',
        ]);

        // Replicar a base de datos de BI (SQL Server)
        try {
            BIService::insertarPago([
                'empresa' => $empresa,
                'centro_costos' => $centroCostos,
                'numero_oc' => $numeroOc,
                'id_ek10' => $idEk10,
                'proveedor' => $proveedor,
                'total_oc' => $totalOc,
                'monto_programado' => $montoProgramado,
                'semana' => $semana,
                'rubro' => $rubro,
                'tipo' => $tipo,
                'concepto' => $concepto,
                'creado_por' => $creadoPor,
                'creado_en' => $ahora,
            ]);
        } catch (Exception $e) {
            Log::error("[BI ERROR] Error replicando a SQL Server: " . $e->getMessage());
        }

        $progs = DB::table('programaciones_oc')
            ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en')
            ->where('empresa', $empresa)
            ->where('centro_costos', $centroCostos)
            ->where('numero_oc', $numeroOc)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn($p) => (array)$p)
            ->toArray();

        $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
        $saldoRestante = max(0.0, round($totalOc - $pagadoAcum, 2));

        return [
            'status' => 'ok',
            'mensaje' => "Pago de $" . number_format($montoProgramado, 2) . " registrado con éxito",
            'id' => $nuevoId,
            'total_programado' => round($pagadoAcum, 2),
            'saldo_disponible' => $saldoRestante,
            'programaciones' => $progs,
        ];
    }

    public static function programarLote(array $items, array $archivosPorItem = []): array
    {
        self::asegurarColumnaEstadoEnvio();

        if (empty($items)) {
            throw new InvalidArgumentException("No se enviaron órdenes para programar");
        }

        $archivosCreados = [];
        $ahora = date('Y-m-d H:i:s');
        $guardados = 0;
        $totalMonto = 0.0;
        $totalDocumentos = 0;
        $itemsGuardados = [];
        $programacionesGuardadas = [];

        DB::beginTransaction();
        try {
            foreach ($items as $indice => $it) {
                $archivos = $archivosPorItem[$indice] ?? [];
                $monto = (float)($it['monto_programado'] ?? 0.0);

                if ($monto <= 0) {
                    if (!empty($archivos)) {
                        throw new InvalidArgumentException("No se pueden guardar documentos sin un monto programado");
                    }
                    continue;
                }

                $empresa = strtoupper(trim($it['empresa'] ?? 'ARYVE'));
                $centroCostos = trim($it['centro_costos'] ?? '');
                $numeroOc = (int)($it['numero_oc'] ?? 0);
                $idEk10 = trim($it['id_ek10'] ?? '');
                $proveedor = trim($it['proveedor'] ?? '');
                $totalOc = (float)($it['total_oc'] ?? 0.0);
                $semana = trim($it['semana'] ?? '');
                $rubro = trim($it['rubro'] ?? 'MATERIALES');
                $tipo = trim($it['tipo'] ?? '');
                $concepto = trim($it['concepto'] ?? '');
                $creadoPor = trim($it['creado_por'] ?? 'USUARIO');

                // Validar saldo disponible
                $yaProg = (float)DB::table('programaciones_oc')
                    ->where('empresa', $empresa)
                    ->where('centro_costos', $centroCostos)
                    ->where('numero_oc', $numeroOc)
                    ->sum('monto_programado');

                $saldoDisp = max(0.0, $totalOc - $yaProg);

                if ($saldoDisp <= 0.009) {
                    if (!empty($archivos)) {
                        throw new InvalidArgumentException("La OC #{$numeroOc} ya no tiene saldo disponible para guardar el pago y sus documentos");
                    }
                    continue;
                }

                if (!empty($archivos)) {
                    $docsExistentes = DB::table('documentos_oc')
                        ->where('empresa', $empresa)
                        ->where('centro_costos', $centroCostos)
                        ->where('numero_oc', $numeroOc)
                        ->count();
                    if ($docsExistentes > 0) {
                        throw new InvalidArgumentException("La OC #{$numeroOc} ya tiene documentos adjuntos");
                    }
                }

                $monto = min($monto, $saldoDisp);

                // Validar XML si hay documentos
                $totalXml = null;
                $xmls = array_filter($archivos, fn($f) => str_ends_with(strtolower($f->getClientOriginalName()), '.xml'));
                if (!empty($xmls)) {
                    $totalXml = 0.0;
                    foreach ($xmls as $xml) {
                        $totalXml += DocumentService::extraerTotalFacturaXml($xml);
                    }
                } elseif (isset($it['total_facturas_xml']) && $it['total_facturas_xml'] !== null && $it['total_facturas_xml'] !== '') {
                    $totalXml = (float)$it['total_facturas_xml'];
                }

                DocumentService::validarTotalXmlMenorPago($totalXml, $monto, $numeroOc);

                $idAbono = DB::table('programaciones_oc')->insertGetId([
                    'empresa' => $empresa,
                    'centro_costos' => $centroCostos,
                    'numero_oc' => $numeroOc,
                    'id_ek10' => $idEk10,
                    'proveedor' => $proveedor,
                    'total_oc' => $totalOc,
                    'monto_programado' => $monto,
                    'semana' => $semana,
                    'rubro' => $rubro,
                    'tipo' => $tipo,
                    'concepto' => $concepto,
                    'creado_por' => $creadoPor,
                    'creado_en' => $ahora,
                    'estado_envio' => 'PENDIENTE',
                ]);

                if (!empty($archivos)) {
                    $guardadosDocs = DocumentService::guardarDocumentos(
                        $archivos,
                        $empresa,
                        $centroCostos,
                        $numeroOc,
                        $idAbono,
                        $creadoPor,
                        $archivosCreados
                    );
                    $totalDocumentos += count($guardadosDocs);
                }

                $guardados++;
                $totalMonto += $monto;
                $itemsGuardados[] = $indice;
                $programacionesGuardadas[] = [
                    'indice' => $indice,
                    'id' => $idAbono,
                ];
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            DocumentService::limpiarArchivos($archivosCreados);
            throw $e;
        }

        DocumentService::replicarDocumentos($archivosCreados);

        return [
            'status' => 'ok',
            'mensaje' => "Se guardaron {$guardados} pagos pendientes por un total de $" . number_format($totalMonto, 2) . ". Aún no se enviaron a BI.",
            'guardados' => $guardados,
            'total_monto' => round($totalMonto, 2),
            'documentos_guardados' => $totalDocumentos,
            'items_guardados' => $itemsGuardados,
            'programaciones_guardadas' => $programacionesGuardadas,
        ];
    }

    public static function enviarProgramacionBi(int $idProgramacion): array
    {
        self::asegurarColumnaEstadoEnvio();

        $fila = DB::table('programaciones_oc')->where('id', $idProgramacion)->first();
        if (!$fila) {
            throw new InvalidArgumentException("No se encontró el pago guardado");
        }
        if ($fila->estado_envio !== 'PENDIENTE') {
            throw new InvalidArgumentException("Este pago ya fue enviado a BI");
        }

        $insertados = BIService::insertarLote([(array)$fila]);
        if ($insertados !== 1) {
            throw new Exception("No se pudo enviar el pago a BI. El pago permanece pendiente para reintentar.");
        }

        DB::table('programaciones_oc')
            ->where('id', $idProgramacion)
            ->where('estado_envio', 'PENDIENTE')
            ->update(['estado_envio' => 'ENVIADO']);

        return [
            'status' => 'ok',
            'mensaje' => "Pago de $" . number_format((float)$fila->monto_programado, 2) . " enviado a BI correctamente",
            'id' => $idProgramacion,
        ];
    }

    public static function eliminarAbono(int $idAbono, string $empresa, string $centroCostos, int $numeroOc, float $totalOc): array
    {
        self::asegurarColumnaEstadoEnvio();

        $programacion = DB::table('programaciones_oc')->where('id', $idAbono)->first();
        if (!$programacion) {
            throw new InvalidArgumentException("No se encontró el pago programado");
        }

        $replicarEliminacionBi = ($programacion->estado_envio === 'ENVIADO');
        $archivosPendientesEliminados = [];

        DB::beginTransaction();
        try {
            if (!$replicarEliminacionBi) {
                $docs = DB::table('documentos_oc')
                    ->where('id_abono', $idAbono)
                    ->pluck('ruta_archivo')
                    ->toArray();
                $archivosPendientesEliminados = $docs;

                DB::table('documentos_oc')->where('id_abono', $idAbono)->delete();
            }

            DB::table('programaciones_oc')->where('id', $idAbono)->delete();

            $progs = DB::table('programaciones_oc')
                ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en')
                ->where('empresa', $empresa)
                ->where('centro_costos', $centroCostos)
                ->where('numero_oc', $numeroOc)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn($p) => (array)$p)
                ->toArray();

            $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
            $saldoRestante = max(0.0, round($totalOc - $pagadoAcum, 2));

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if (!empty($archivosPendientesEliminados)) {
            DocumentService::limpiarArchivos($archivosPendientesEliminados);
        }

        if ($replicarEliminacionBi) {
            try {
                BIService::eliminarAbono($empresa, $centroCostos, $numeroOc);
            } catch (Exception $e) {
                Log::error("[BI ERROR] Error al eliminar de SQL Server: " . $e->getMessage());
            }
        }

        return [
            'status' => 'ok',
            'mensaje' => 'Abono eliminado correctamente',
            'total_programado' => round($pagadoAcum, 2),
            'saldo_disponible' => $saldoRestante,
            'programaciones' => $progs,
        ];
    }
}
