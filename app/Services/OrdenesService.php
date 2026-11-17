<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

class OrdenesService
{
    public static function asegurarColumnaEstadoEnvio(): void
    {
        try {
            $cols = DB::select('PRAGMA table_info(programaciones_oc);');
            $nombres = array_map(fn ($c) => $c->name, $cols);
            if (! in_array('estado_envio', $nombres, true)) {
                DB::statement("ALTER TABLE programaciones_oc ADD COLUMN estado_envio TEXT NOT NULL DEFAULT 'ENVIADO';");
            }
        } catch (Exception $e) {
            Log::warning('[DB] Error verificando estado_envio: '.$e->getMessage());
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
        if (! in_array($empresa, ['ARYVE', 'VELAS'], true)) {
            $empresa = 'ARYVE';
        }

        $empleadoId = trim((string) ($params['empleado_id'] ?? ''));
        $filtroTemporal = strtoupper(trim((string) ($params['filtro_temporal'] ?? 'HOY')));
        $filtroSemana = trim((string) ($params['semana'] ?? ''));
        $busqueda = trim((string) ($params['busqueda'] ?? ''));
        $obra = trim((string) ($params['obra'] ?? ''));
        $filtroSemaforo = strtoupper(trim((string) ($params['semaforo'] ?? '')));
        $misOrdenes = filter_var($params['mis_ordenes'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $limite = max(1, (int) ($params['limite'] ?? 50));
        $pagina = max(1, (int) ($params['pagina'] ?? 1));
        $offset = ($pagina - 1) * $limite;

        $maxFecha = self::obtenerMaxFecha($empresa);

        // Construir query base
        $query = DB::table('ordenes_compra_ek9')->where('empresa', $empresa);

        // Filtro por usuario / mis_ordenes
        if ($misOrdenes && ! empty($empleadoId) && $empleadoId !== '0' && strtoupper($empleadoId) !== 'ADMIN') {
            if (ctype_digit($empleadoId)) {
                $query->where('id_empleado', (int) $empleadoId);
            }
        }

        // Filtro por temporal o semana (el filtro por semáforo de pasivos antiguos tiene prioridad sobre el filtro de hoy)
        if (! empty($filtroSemaforo)) {
            // El usuario está filtrando específicamente por antigüedad de adeudos; no restringir a sólo hoy
        } elseif ($filtroTemporal === 'HOY') {
            $query->whereRaw("fecha = date('now', 'localtime')");
        } elseif (! empty($filtroSemana)) {
            $catSemana = DB::table('cat_semanas_ek9')
                ->where('etiqueta', $filtroSemana)
                ->orWhere('sem', $filtroSemana)
                ->first();
            if ($catSemana && ! empty($catSemana->fecha_inicio) && ! empty($catSemana->fecha_final)) {
                $query->whereBetween('fecha', [$catSemana->fecha_inicio, $catSemana->fecha_final]);
            }
        } elseif ($filtroTemporal === 'SEMANA') {
            $query->whereRaw("fecha >= date('now', '-7 days')");
        } elseif ($filtroTemporal === 'MES') {
            $query->whereRaw("fecha >= date('now', 'start of month')");
        }

        if (! empty($obra)) {
            $query->where('centro_costos', $obra);
        }

        // Filtro por semáforo de antigüedad de adeudo
        if ($filtroSemaforo === 'VERDE') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) < 30");
        } elseif ($filtroSemaforo === 'AMARILLO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) >= 30 AND (julianday('now', 'localtime') - julianday(fecha)) <= 90");
        } elseif ($filtroSemaforo === 'NARANJA') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) > 90 AND (julianday('now', 'localtime') - julianday(fecha)) <= 180");
        } elseif ($filtroSemaforo === 'ROJO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) > 180");
        } elseif ($filtroSemaforo === 'MAS_UN_ANO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) >= 365");
        }

        if (! empty($busqueda)) {
            $p = "%{$busqueda}%";
            $query->where(function ($q) use ($p) {
                $q->whereRaw('CAST(numero_oc AS TEXT) LIKE ?', [$p])
                    ->orWhere('proveedor', 'LIKE', $p)
                    ->orWhere('centro_costos', 'LIKE', $p)
                    ->orWhere('id_ek10', 'LIKE', $p)
                    ->orWhere('solicito_empleado', 'LIKE', $p)
                    ->orWhereRaw('CAST(id_empleado AS TEXT) LIKE ?', [$p])
                    ->orWhere('comentarios', 'LIKE', $p);
            });
        }

        // Totales de la consulta filtrada
        $conteoQuery = clone $query;
        $totalCoincidencias = $conteoQuery->count();
        $sumaTotal = round((float) ($conteoQuery->sum('total') ?? 0.0), 2);

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
            ->map(fn ($item) => (array) $item)
            ->toArray();

        // Enlazar programaciones y documentos para cada OC
        if (! empty($filas)) {
            foreach ($filas as &$f) {
                $progs = DB::table('programaciones_oc')
                    ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en', 'estado_envio')
                    ->where('empresa', $f['empresa'])
                    ->where('centro_costos', $f['centro_costos'])
                    ->where('numero_oc', $f['numero_oc'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(fn ($p) => (array) $p)
                    ->toArray();

                $f['programaciones'] = $progs;
                $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
                $f['total_programado'] = round((float) $pagadoAcum, 2);
                $saldoDisp = max(0.0, round(((float) ($f['total'] ?? 0.0)) - $pagadoAcum, 2));
                $f['saldo_disponible'] = $saldoDisp;

                // Cálculo de antigüedad y semáforo de adeudo
                $diasAntiguedad = ! empty($f['fecha']) ? max(0, (int) floor((time() - strtotime($f['fecha'])) / 86400)) : 0;
                $f['dias_antiguedad'] = $diasAntiguedad;
                if ($saldoDisp <= 0.009) {
                    $f['semaforo_nivel'] = 'LIQUIDADO';
                    $f['semaforo_color'] = 'slate';
                    $f['semaforo_label'] = 'PAGADA';
                } elseif ($diasAntiguedad < 30) {
                    $f['semaforo_nivel'] = 'VERDE';
                    $f['semaforo_color'] = 'emerald';
                    $f['semaforo_label'] = "30d ({$diasAntiguedad}d)";
                } elseif ($diasAntiguedad <= 90) {
                    $f['semaforo_nivel'] = 'AMARILLO';
                    $f['semaforo_color'] = 'amber';
                    $f['semaforo_label'] = "30-90d ({$diasAntiguedad}d)";
                } elseif ($diasAntiguedad <= 180) {
                    $f['semaforo_nivel'] = 'NARANJA';
                    $f['semaforo_color'] = 'orange';
                    $f['semaforo_label'] = "90-180d ({$diasAntiguedad}d)";
                } else {
                    $f['semaforo_nivel'] = 'ROJO';
                    $f['semaforo_color'] = 'rose';
                    $f['semaforo_label'] = ($diasAntiguedad >= 365) ? "1 año ({$diasAntiguedad}d)" : "180d ({$diasAntiguedad}d)";
                }

                $docs = DB::table('documentos_oc')
                    ->select('id', 'nombre_original', 'nombre_guardado', 'tamano_bytes', 'creado_en')
                    ->where('empresa', $f['empresa'])
                    ->where('centro_costos', $f['centro_costos'])
                    ->where('numero_oc', $f['numero_oc'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(fn ($d) => (array) $d)
                    ->toArray();

                $f['documentos'] = $docs;
                $f['total_documentos'] = count($docs);
            }
            unset($f);
        }

        // KPIs rápidos (Hoy vs Acumulado)
        $kpiQuery = DB::table('ordenes_compra_ek9')->where('empresa', $empresa);
        if ($misOrdenes && ! empty($empleadoId) && $empleadoId !== '0' && strtoupper($empleadoId) !== 'ADMIN') {
            if (ctype_digit($empleadoId)) {
                $kpiQuery->where('id_empleado', (int) $empleadoId);
            }
        }

        $kpisRaw = $kpiQuery->selectRaw("
            SUM(CASE WHEN fecha = date('now', 'localtime') THEN 1 ELSE 0 END) as ordenes_hoy,
            SUM(CASE WHEN fecha = date('now', 'localtime') THEN total ELSE 0 END) as monto_hoy,
            COUNT(*) as total_acumulado,
            SUM(total) as monto_acumulado,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) < 30 THEN 1 ELSE 0 END) as count_verde,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) < 30 THEN total ELSE 0 END) as monto_verde,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) >= 30 AND (julianday('now', 'localtime') - julianday(fecha)) <= 90 THEN 1 ELSE 0 END) as count_amarillo,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) >= 30 AND (julianday('now', 'localtime') - julianday(fecha)) <= 90 THEN total ELSE 0 END) as monto_amarillo,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) > 90 AND (julianday('now', 'localtime') - julianday(fecha)) <= 180 THEN 1 ELSE 0 END) as count_naranja,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) > 90 AND (julianday('now', 'localtime') - julianday(fecha)) <= 180 THEN total ELSE 0 END) as monto_naranja,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) > 180 THEN 1 ELSE 0 END) as count_rojo,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) > 180 THEN total ELSE 0 END) as monto_rojo,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) >= 365 THEN 1 ELSE 0 END) as count_mas_un_ano,
            SUM(CASE WHEN (julianday('now', 'localtime') - julianday(fecha)) >= 365 THEN total ELSE 0 END) as monto_mas_un_ano
        ")->first();

        $kpis = [
            'ordenes_hoy' => (int) ($kpisRaw->ordenes_hoy ?? 0),
            'monto_hoy' => round((float) ($kpisRaw->monto_hoy ?? 0.0), 2),
            'total_acumulado' => (int) ($kpisRaw->total_acumulado ?? 0),
            'monto_acumulado' => round((float) ($kpisRaw->monto_acumulado ?? 0.0), 2),
            'semaforo' => [
                'verde' => [
                    'cantidad' => (int) ($kpisRaw->count_verde ?? 0),
                    'monto' => round((float) ($kpisRaw->monto_verde ?? 0.0), 2),
                ],
                'amarillo' => [
                    'cantidad' => (int) ($kpisRaw->count_amarillo ?? 0),
                    'monto' => round((float) ($kpisRaw->monto_amarillo ?? 0.0), 2),
                ],
                'naranja' => [
                    'cantidad' => (int) ($kpisRaw->count_naranja ?? 0),
                    'monto' => round((float) ($kpisRaw->monto_naranja ?? 0.0), 2),
                ],
                'rojo' => [
                    'cantidad' => (int) ($kpisRaw->count_rojo ?? 0),
                    'monto' => round((float) ($kpisRaw->monto_rojo ?? 0.0), 2),
                    'mas_un_ano' => (int) ($kpisRaw->count_mas_un_ano ?? 0),
                    'monto_mas_un_ano' => round((float) ($kpisRaw->monto_mas_un_ano ?? 0.0), 2),
                ],
            ],
        ];

        $totalPaginas = $totalCoincidencias > 0 ? (int) ceil($totalCoincidencias / $limite) : 1;

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

        if (! empty($semana)) {
            $semanaLimpia = trim(str_replace(['SEM:', 'SEM.'], '', $semana));
            $query->where(function ($q) use ($semana, $semanaLimpia) {
                $q->where('semana', $semana)
                    ->orWhere('semana', "SEM: {$semanaLimpia}")
                    ->orWhere('semana', 'LIKE', "%{$semanaLimpia}%");
            });
        }

        if (! empty($obra)) {
            $query->where('centro_costos', $obra);
        }

        // Filtro por semáforo de antigüedad de adeudo
        if ($filtroSemaforo === 'VERDE') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) < 30");
        } elseif ($filtroSemaforo === 'AMARILLO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) >= 30 AND (julianday('now', 'localtime') - julianday(fecha)) <= 90");
        } elseif ($filtroSemaforo === 'NARANJA') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) > 90 AND (julianday('now', 'localtime') - julianday(fecha)) <= 180");
        } elseif ($filtroSemaforo === 'ROJO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) > 180");
        } elseif ($filtroSemaforo === 'MAS_UN_ANO') {
            $query->whereRaw("(julianday('now', 'localtime') - julianday(fecha)) >= 365");
        }

        $totales = (clone $query)->selectRaw('COUNT(*) as conteo, SUM(monto_programado) as monto')->first();
        $totalItems = (int) ($totales->conteo ?? 0);
        $totalMonto = round((float) ($totales->monto ?? 0.0), 2);

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
                $arr = (array) $row;
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

    /**
     * @return array{total_oc: float, saldo_disponible: float}
     */
    private static function obtenerTotalYSaldoDisponible(string $empresa, string $centroCostos, int $numeroOc): array
    {
        $orden = DB::table('ordenes_compra_ek9')
            ->where('empresa', $empresa)
            ->where('centro_costos', $centroCostos)
            ->where('numero_oc', $numeroOc)
            ->lockForUpdate()
            ->first(['total']);

        if (! $orden) {
            throw new InvalidArgumentException("No se encontró la OC #{$numeroOc} para validar su saldo real");
        }

        $totalOc = round((float) ($orden->total ?? 0.0), 2);
        $totalProgramado = round((float) DB::table('programaciones_oc')
            ->where('empresa', $empresa)
            ->where('centro_costos', $centroCostos)
            ->where('numero_oc', $numeroOc)
            ->sum('monto_programado'), 2);

        return [
            'total_oc' => $totalOc,
            'saldo_disponible' => max(0.0, round($totalOc - $totalProgramado, 2)),
        ];
    }

    private static function validarMontoContraSaldo(float $monto, float $saldoDisponible, int $numeroOc): void
    {
        if (! is_finite($monto) || $monto <= 0) {
            throw new InvalidArgumentException('El monto a programar debe ser mayor a $0.00');
        }

        $montoCentavos = $monto * 100;
        if (abs($montoCentavos - round($montoCentavos)) > 0.000001) {
            throw new InvalidArgumentException('El monto a programar solo puede tener hasta dos decimales');
        }

        if ((int) round($montoCentavos) > (int) round($saldoDisponible * 100)) {
            throw new InvalidArgumentException(
                "La OC #{$numeroOc} solo tiene $".number_format($saldoDisponible, 2).
                ' disponibles; no se puede programar $'.number_format($monto, 2).'.'
            );
        }
    }

    public static function programarAbono(array $datos): array
    {
        $empresa = strtoupper(trim($datos['empresa'] ?? 'ARYVE'));
        $centroCostos = trim($datos['centro_costos'] ?? '');
        $numeroOc = (int) ($datos['numero_oc'] ?? 0);
        $idEk10 = trim($datos['id_ek10'] ?? '');
        $proveedor = trim($datos['proveedor'] ?? '');
        $montoProgramado = (float) ($datos['monto_programado'] ?? 0.0);
        $semana = trim($datos['semana'] ?? '');
        $rubro = trim($datos['rubro'] ?? 'MATERIALES');
        $tipo = trim($datos['tipo'] ?? '');
        $concepto = trim($datos['concepto'] ?? '');
        $creadoPor = trim($datos['creado_por'] ?? 'USUARIO');

        if ($numeroOc <= 0) {
            throw new InvalidArgumentException('Número de OC inválido');
        }

        $ahora = date('Y-m-d H:i:s');

        [$nuevoId, $totalOc] = DB::transaction(function () use (
            $empresa,
            $centroCostos,
            $numeroOc,
            $idEk10,
            $proveedor,
            $montoProgramado,
            $semana,
            $rubro,
            $tipo,
            $concepto,
            $creadoPor,
            $ahora
        ): array {
            $saldo = self::obtenerTotalYSaldoDisponible($empresa, $centroCostos, $numeroOc);
            self::validarMontoContraSaldo($montoProgramado, $saldo['saldo_disponible'], $numeroOc);

            $id = DB::table('programaciones_oc')->insertGetId([
                'empresa' => $empresa,
                'centro_costos' => $centroCostos,
                'numero_oc' => $numeroOc,
                'id_ek10' => $idEk10,
                'proveedor' => $proveedor,
                'total_oc' => $saldo['total_oc'],
                'monto_programado' => $montoProgramado,
                'semana' => $semana,
                'rubro' => $rubro,
                'tipo' => $tipo,
                'concepto' => $concepto,
                'creado_por' => $creadoPor,
                'creado_en' => $ahora,
                'estado_envio' => 'ENVIADO',
            ]);

            app(OrdenSeguimientoService::class)->registrarActividadSistema(
                $empresa,
                $centroCostos,
                $numeroOc,
                'PAGO_PROGRAMADO',
                'Pago programado por $'.number_format($montoProgramado, 2),
                Session::has('usuario_id') ? (int) Session::get('usuario_id') : null,
                (string) Session::get('usuario_nombre', $creadoPor)
            );

            return [$id, $saldo['total_oc']];
        });

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
            Log::error('[BI ERROR] Error replicando a SQL Server: '.$e->getMessage());
        }

        $progs = DB::table('programaciones_oc')
            ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en')
            ->where('empresa', $empresa)
            ->where('centro_costos', $centroCostos)
            ->where('numero_oc', $numeroOc)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($p) => (array) $p)
            ->toArray();

        $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
        $saldoRestante = max(0.0, round($totalOc - $pagadoAcum, 2));

        return [
            'status' => 'ok',
            'mensaje' => 'Pago de $'.number_format($montoProgramado, 2).' registrado con éxito',
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
            throw new InvalidArgumentException('No se enviaron órdenes para programar');
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
                $monto = (float) ($it['monto_programado'] ?? 0.0);

                if ($monto <= 0) {
                    if (! empty($archivos)) {
                        throw new InvalidArgumentException('No se pueden guardar documentos sin un monto programado');
                    }

                    continue;
                }

                $empresa = strtoupper(trim($it['empresa'] ?? 'ARYVE'));
                $centroCostos = trim($it['centro_costos'] ?? '');
                $numeroOc = (int) ($it['numero_oc'] ?? 0);
                $idEk10 = trim($it['id_ek10'] ?? '');
                $proveedor = trim($it['proveedor'] ?? '');
                $semana = trim($it['semana'] ?? '');
                $rubro = trim($it['rubro'] ?? 'MATERIALES');
                $tipo = trim($it['tipo'] ?? '');
                $concepto = trim($it['concepto'] ?? '');
                $creadoPor = trim($it['creado_por'] ?? 'USUARIO');

                $saldo = self::obtenerTotalYSaldoDisponible($empresa, $centroCostos, $numeroOc);
                $totalOc = $saldo['total_oc'];
                $saldoDisp = $saldo['saldo_disponible'];
                self::validarMontoContraSaldo($monto, $saldoDisp, $numeroOc);

                if (! empty($archivos)) {
                    $docsExistentes = DB::table('documentos_oc')
                        ->where('empresa', $empresa)
                        ->where('centro_costos', $centroCostos)
                        ->where('numero_oc', $numeroOc)
                        ->count();
                    if ($docsExistentes > 0) {
                        throw new InvalidArgumentException("La OC #{$numeroOc} ya tiene documentos adjuntos");
                    }
                }

                // Validar XML si hay documentos
                $totalXml = null;
                $xmls = array_filter($archivos, fn ($f) => str_ends_with(strtolower($f->getClientOriginalName()), '.xml'));
                if (! empty($xmls)) {
                    $totalXml = 0.0;
                    foreach ($xmls as $xml) {
                        $totalXml += DocumentService::extraerTotalFacturaXml($xml);
                    }
                } elseif (isset($it['total_facturas_xml']) && $it['total_facturas_xml'] !== null && $it['total_facturas_xml'] !== '') {
                    $totalXml = (float) $it['total_facturas_xml'];
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

                if (! empty($archivos)) {
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

                app(OrdenSeguimientoService::class)->registrarActividadSistema(
                    $empresa,
                    $centroCostos,
                    $numeroOc,
                    'PAGO_PROGRAMADO',
                    'Pago programado por $'.number_format($monto, 2),
                    Session::has('usuario_id') ? (int) Session::get('usuario_id') : null,
                    (string) Session::get('usuario_nombre', $creadoPor)
                );

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
            'mensaje' => "Se guardaron {$guardados} pagos pendientes por un total de $".number_format($totalMonto, 2).'. Aún no se enviaron a BI.',
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
        if (! $fila) {
            throw new InvalidArgumentException('No se encontró el pago guardado');
        }
        if ($fila->estado_envio !== 'PENDIENTE') {
            throw new InvalidArgumentException('Este pago ya fue enviado a BI');
        }

        $insertados = BIService::insertarLote([(array) $fila]);
        if ($insertados !== 1) {
            throw new Exception('No se pudo enviar el pago a BI. El pago permanece pendiente para reintentar.');
        }

        DB::table('programaciones_oc')
            ->where('id', $idProgramacion)
            ->where('estado_envio', 'PENDIENTE')
            ->update(['estado_envio' => 'ENVIADO']);

        app(OrdenSeguimientoService::class)->registrarActividadSistema(
            (string) $fila->empresa,
            (string) $fila->centro_costos,
            (int) $fila->numero_oc,
            'PAGO_ENVIADO_BI',
            'Pago enviado a BI por $'.number_format((float) $fila->monto_programado, 2),
            Session::has('usuario_id') ? (int) Session::get('usuario_id') : null,
            (string) Session::get('usuario_nombre', $fila->creado_por ?? 'SISTEMA')
        );

        return [
            'status' => 'ok',
            'mensaje' => 'Pago de $'.number_format((float) $fila->monto_programado, 2).' enviado a BI correctamente',
            'id' => $idProgramacion,
        ];
    }

    public static function eliminarAbono(int $idAbono, string $empresa, string $centroCostos, int $numeroOc, float $totalOc): array
    {
        self::asegurarColumnaEstadoEnvio();

        $programacion = DB::table('programaciones_oc')->where('id', $idAbono)->first();
        if (! $programacion) {
            throw new InvalidArgumentException('No se encontró el pago programado');
        }

        $replicarEliminacionBi = ($programacion->estado_envio === 'ENVIADO');
        $archivosPendientesEliminados = [];

        DB::beginTransaction();
        try {
            if (! $replicarEliminacionBi) {
                $docs = DB::table('documentos_oc')
                    ->where('id_abono', $idAbono)
                    ->pluck('ruta_archivo')
                    ->toArray();
                $archivosPendientesEliminados = $docs;

                DB::table('documentos_oc')->where('id_abono', $idAbono)->delete();
            }

            DB::table('programaciones_oc')->where('id', $idAbono)->delete();

            app(OrdenSeguimientoService::class)->registrarActividadSistema(
                $empresa,
                $centroCostos,
                $numeroOc,
                'PAGO_ELIMINADO',
                'Pago programado eliminado por $'.number_format((float) $programacion->monto_programado, 2),
                Session::has('usuario_id') ? (int) Session::get('usuario_id') : null,
                (string) Session::get('usuario_nombre', $programacion->creado_por ?? 'SISTEMA')
            );

            $progs = DB::table('programaciones_oc')
                ->select('id', 'monto_programado', 'semana', 'rubro', 'concepto', 'creado_por', 'creado_en')
                ->where('empresa', $empresa)
                ->where('centro_costos', $centroCostos)
                ->where('numero_oc', $numeroOc)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn ($p) => (array) $p)
                ->toArray();

            $pagadoAcum = array_sum(array_column($progs, 'monto_programado'));
            $saldoRestante = max(0.0, round($totalOc - $pagadoAcum, 2));

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        if (! empty($archivosPendientesEliminados)) {
            DocumentService::limpiarArchivos($archivosPendientesEliminados);
        }

        if ($replicarEliminacionBi) {
            try {
                BIService::eliminarAbono($empresa, $centroCostos, $numeroOc);
            } catch (Exception $e) {
                Log::error('[BI ERROR] Error al eliminar de SQL Server: '.$e->getMessage());
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
