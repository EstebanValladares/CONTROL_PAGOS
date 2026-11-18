<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrdenSeguimientoService
{
    private const ESTADOS_CERRADOS = ['CERRADA', 'CANCELADA'];

    public function listar(string $empresa, ?int $empleadoId, bool $esAdministrador, string $filtro, int $pagina, int $limite): array
    {
        $this->inicializarOcsSinSeguimiento($empresa);

        $movimientos = DB::table('oc_movimientos')
            ->select('empresa', 'centro_costos', 'numero_oc')
            ->selectRaw('MAX(creado_en) as ultimo_movimiento')
            ->where('empresa', $empresa)
            ->where('tipo', '<>', 'REASIGNACION')
            ->groupBy('empresa', 'centro_costos', 'numero_oc');

        $query = DB::table('ordenes_compra_ek9 as oc')
            ->leftJoin('oc_seguimientos as seg', function ($join): void {
                $join->on('seg.empresa', '=', 'oc.empresa')
                    ->on('seg.centro_costos', '=', 'oc.centro_costos')
                    ->on('seg.numero_oc', '=', 'oc.numero_oc');
            })
            ->leftJoinSub($movimientos, 'mov', function ($join): void {
                $join->on('mov.empresa', '=', 'oc.empresa')
                    ->on('mov.centro_costos', '=', 'oc.centro_costos')
                    ->on('mov.numero_oc', '=', 'oc.numero_oc');
            })
            ->where('oc.empresa', $empresa)
            ->whereRaw("UPPER(TRIM(COALESCE(oc.estatus, ''))) <> 'C'")
            ->select([
                'oc.id',
                'oc.empresa',
                'oc.centro_costos',
                'oc.numero_oc',
                'oc.fecha',
                'oc.proveedor',
                'oc.total',
                'oc.estatus',
                'seg.responsable_id',
                'seg.responsable_nombre',
                'seg.estado_seguimiento',
                'seg.seguimiento_iniciado_en',
                'seg.ultimo_movimiento_tipo',
            ])
            ->selectRaw('COALESCE(mov.ultimo_movimiento, seg.ultimo_movimiento_en, seg.seguimiento_iniciado_en) as ultima_actividad')
            ->selectRaw("CAST(julianday('now') - julianday(COALESCE(mov.ultimo_movimiento, seg.ultimo_movimiento_en, seg.seguimiento_iniciado_en)) AS INTEGER) as dias_sin_movimiento")
            ->selectRaw("CASE WHEN julianday('now') - julianday(COALESCE(mov.ultimo_movimiento, seg.ultimo_movimiento_en, seg.seguimiento_iniciado_en)) > 60 THEN 1 ELSE 0 END as en_limbo");

        if (! $esAdministrador) {
            $query->where('seg.responsable_id', $empleadoId ?? -1);
        }

        if ($filtro === 'limbo') {
            $query->whereRaw("julianday('now') - julianday(COALESCE(mov.ultimo_movimiento, seg.ultimo_movimiento_en, seg.seguimiento_iniciado_en)) > 60")
                ->whereNotIn('seg.estado_seguimiento', self::ESTADOS_CERRADOS);
        } elseif ($filtro === 'sin_responsable' && $esAdministrador) {
            $query->whereNull('seg.responsable_id')
                ->whereNotIn('seg.estado_seguimiento', self::ESTADOS_CERRADOS);
        }

        $total = (clone $query)->count();
        $datos = $query
            ->orderByDesc('dias_sin_movimiento')
            ->orderBy('oc.centro_costos')
            ->orderBy('oc.numero_oc')
            ->forPage($pagina, $limite)
            ->get()
            ->map(static function (object $oc): array {
                $oc->dias_sin_movimiento = max(0, (int) $oc->dias_sin_movimiento);
                $oc->en_limbo = ! in_array(strtoupper((string) $oc->estado_seguimiento), self::ESTADOS_CERRADOS, true)
                    && (bool) $oc->en_limbo;

                return (array) $oc;
            })
            ->all();

        return [
            'status' => 'ok',
            'datos' => $datos,
            'pagina' => $pagina,
            'limite' => $limite,
            'total' => $total,
            'total_paginas' => max(1, (int) ceil($total / $limite)),
            'resumen' => $this->obtenerResumen($empresa, $empleadoId, $esAdministrador),
        ];
    }

    public function obtenerCompradoras(string $empresa): array
    {
        $compradoras = DB::table('usuarios_ek9')
            ->where('empresa', $empresa)
            ->whereRaw("UPPER(COALESCE(estatus, 'A')) <> 'B'")
            ->whereRaw("UPPER(COALESCE(puesto, '')) LIKE '%COMPRADOR%'")
            ->orderBy('nombre')
            ->get(['empleado as id', 'nombre'])
            ->map(static fn (object $usuario): array => (array) $usuario)
            ->all();

        if (empty($compradoras)) {
            $compradoras = DB::table('usuarios_ek9')
                ->where('empresa', $empresa)
                ->whereRaw("UPPER(COALESCE(estatus, 'A')) <> 'B'")
                ->orderBy('nombre')
                ->get(['empleado as id', 'nombre'])
                ->map(static fn (object $usuario): array => (array) $usuario)
                ->all();
        }

        return $compradoras;
    }

    public function obtenerHistorial(array $orden, int $usuarioId, bool $esAdministrador): array
    {
        $this->autorizarSeguimiento($this->obtenerSeguimiento($orden), $usuarioId, $esAdministrador);

        return DB::table('oc_movimientos')
            ->where($orden)
            ->orderByDesc('creado_en')
            ->limit(30)
            ->get(['tipo', 'descripcion', 'usuario_nombre', 'metadata', 'creado_en'])
            ->map(static function (object $movimiento): array {
                if ($movimiento->metadata) {
                    $movimiento->metadata = json_decode($movimiento->metadata, true);
                }

                return (array) $movimiento;
            })
            ->all();
    }

    public function registrarNota(array $orden, string $nota, int $usuarioId, string $usuarioNombre, bool $esAdministrador): void
    {
        $seguimiento = $this->obtenerSeguimiento($orden);
        $this->autorizarSeguimiento($seguimiento, $usuarioId, $esAdministrador);

        $this->registrarMovimiento($orden, 'NOTA', $nota, $usuarioId, $usuarioNombre);
    }

    public function actualizarEstado(
        array $orden,
        string $estado,
        string $motivo,
        int $usuarioId,
        string $usuarioNombre,
        bool $esAdministrador
    ): void {
        $estado = strtoupper(trim($estado));
        if (! in_array($estado, ['ABIERTA', 'EN_ESPERA_PROVEEDOR', 'CERRADA', 'CANCELADA'], true)) {
            throw new InvalidArgumentException('El estado de seguimiento no es válido.');
        }

        $seguimiento = $this->obtenerSeguimiento($orden);
        $this->autorizarSeguimiento($seguimiento, $usuarioId, $esAdministrador);

        $this->registrarMovimiento(
            $orden,
            'CAMBIO_ESTADO',
            $motivo,
            $usuarioId,
            $usuarioNombre,
            ['estado_anterior' => $seguimiento->estado_seguimiento, 'estado_nuevo' => $estado],
            ['estado_seguimiento' => $estado]
        );
    }

    public function reasignarCartera(
        string $empresa,
        array $ordenes,
        int $compradoraId,
        string $motivo,
        int $usuarioId,
        string $usuarioNombre
    ): int {
        $compradora = DB::table('usuarios_ek9')
            ->where('empresa', $empresa)
            ->where('empleado', $compradoraId)
            ->whereRaw("UPPER(COALESCE(estatus, 'A')) <> 'B'")
            ->first(['empleado', 'nombre']);

        if (! $compradora) {
            throw new InvalidArgumentException('El usuario seleccionado no está activo en esta empresa.');
        }

        return DB::transaction(function () use ($empresa, $ordenes, $compradora, $motivo, $usuarioId, $usuarioNombre): int {
            $ahora = now()->utc()->toDateTimeString();

            foreach ($ordenes as $orden) {
                $orden = [
                    'empresa' => $empresa,
                    'centro_costos' => trim((string) $orden['centro_costos']),
                    'numero_oc' => (int) $orden['numero_oc'],
                ];

                $ordenFuente = DB::table('ordenes_compra_ek9')
                    ->where($orden)
                    ->first(['estatus']);
                if (! $ordenFuente) {
                    throw new InvalidArgumentException('No se encontró una de las órdenes seleccionadas.');
                }
                if (strtoupper(trim((string) $ordenFuente->estatus)) === 'C') {
                    throw new InvalidArgumentException('La OC está cancelada en Enkontrol y no se puede reasignar.');
                }

                $this->inicializarUnaOc($orden, $ahora);
                $seguimiento = $this->obtenerSeguimiento($orden);
                if (! $seguimiento) {
                    throw new InvalidArgumentException('No se encontró una de las órdenes seleccionadas.');
                }

                DB::table('oc_seguimientos')
                    ->where($orden)
                    ->update([
                        'responsable_id' => $compradora->empleado,
                        'responsable_nombre' => $compradora->nombre,
                        'updated_at' => $ahora,
                    ]);

                DB::table('oc_movimientos')->insert([
                    ...$orden,
                    'tipo' => 'REASIGNACION',
                    'descripcion' => $motivo,
                    'usuario_id' => $usuarioId,
                    'usuario_nombre' => $usuarioNombre,
                    'metadata' => json_encode([
                        'responsable_anterior_id' => $seguimiento->responsable_id,
                        'responsable_anterior_nombre' => $seguimiento->responsable_nombre,
                        'responsable_nuevo_id' => $compradora->empleado,
                        'responsable_nuevo_nombre' => $compradora->nombre,
                    ], JSON_THROW_ON_ERROR),
                    'creado_en' => $ahora,
                ]);
            }

            return count($ordenes);
        });
    }

    public function registrarActividadSistema(
        string $empresa,
        string $centroCostos,
        int $numeroOc,
        string $tipo,
        string $descripcion,
        ?int $usuarioId,
        ?string $usuarioNombre
    ): void {
        $this->registrarMovimiento(
            ['empresa' => $empresa, 'centro_costos' => $centroCostos, 'numero_oc' => $numeroOc],
            $tipo,
            $descripcion,
            $usuarioId,
            $usuarioNombre
        );
    }

    private function obtenerResumen(string $empresa, ?int $empleadoId, bool $esAdministrador): array
    {
        $movimientos = DB::table('oc_movimientos')
            ->select('empresa', 'centro_costos', 'numero_oc')
            ->selectRaw('MAX(creado_en) as ultimo_movimiento')
            ->where('empresa', $empresa)
            ->where('tipo', '<>', 'REASIGNACION')
            ->groupBy('empresa', 'centro_costos', 'numero_oc');

        $query = DB::table('ordenes_compra_ek9 as oc')
            ->leftJoin('oc_seguimientos as seg', function ($join): void {
                $join->on('seg.empresa', '=', 'oc.empresa')
                    ->on('seg.centro_costos', '=', 'oc.centro_costos')
                    ->on('seg.numero_oc', '=', 'oc.numero_oc');
            })
            ->leftJoinSub($movimientos, 'mov', function ($join): void {
                $join->on('mov.empresa', '=', 'oc.empresa')
                    ->on('mov.centro_costos', '=', 'oc.centro_costos')
                    ->on('mov.numero_oc', '=', 'oc.numero_oc');
            })
            ->where('oc.empresa', $empresa)
            ->whereRaw("UPPER(TRIM(COALESCE(oc.estatus, ''))) <> 'C'");

        if (! $esAdministrador) {
            $query->where('seg.responsable_id', $empleadoId ?? -1);
        }

        $abiertas = (clone $query)->whereNotIn('seg.estado_seguimiento', self::ESTADOS_CERRADOS);
        $diasInactivo = "julianday('now') - julianday(COALESCE(mov.ultimo_movimiento, seg.ultimo_movimiento_en, seg.seguimiento_iniciado_en))";

        return [
            'limbo' => (clone $abiertas)->whereRaw("{$diasInactivo} > 60")->count(),
            'sin_responsable' => $esAdministrador
                ? (clone $abiertas)->whereNull('seg.responsable_id')->count()
                : 0,
            'total' => (clone $query)->count(),
        ];
    }

    private function registrarMovimiento(
        array $orden,
        string $tipo,
        string $descripcion,
        ?int $usuarioId,
        ?string $usuarioNombre,
        array $metadata = [],
        array $cambiosSeguimiento = []
    ): void {
        DB::transaction(function () use ($orden, $tipo, $descripcion, $usuarioId, $usuarioNombre, $metadata, $cambiosSeguimiento): void {
            $ahora = now()->utc()->toDateTimeString();
            $this->inicializarUnaOc($orden, $ahora);

            DB::table('oc_seguimientos')
                ->where($orden)
                ->update(array_merge($cambiosSeguimiento, [
                    'ultimo_movimiento_en' => $ahora,
                    'ultimo_movimiento_tipo' => $tipo,
                    'updated_at' => $ahora,
                ]));

            DB::table('oc_movimientos')->insert([
                ...$orden,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
                'creado_en' => $ahora,
            ]);
        });
    }

    private function inicializarOcsSinSeguimiento(string $empresa): void
    {
        $ahora = now()->utc()->toDateTimeString();
        DB::statement(
            "INSERT OR IGNORE INTO oc_seguimientos (empresa, centro_costos, numero_oc, estado_seguimiento, seguimiento_iniciado_en, created_at, updated_at)
             SELECT empresa, centro_costos, numero_oc, 'ABIERTA', ?, ?, ?
             FROM ordenes_compra_ek9
             WHERE empresa = ?
               AND UPPER(TRIM(COALESCE(estatus, ''))) <> 'C'",
            [$ahora, $ahora, $ahora, $empresa]
        );
    }

    private function inicializarUnaOc(array $orden, string $ahora): void
    {
        DB::table('oc_seguimientos')->insertOrIgnore([
            ...$orden,
            'estado_seguimiento' => 'ABIERTA',
            'seguimiento_iniciado_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    private function obtenerSeguimiento(array $orden): ?object
    {
        $ordenFuente = DB::table('ordenes_compra_ek9')->where($orden)->first(['estatus']);
        if ($ordenFuente && strtoupper(trim((string) $ordenFuente->estatus)) === 'C') {
            throw new InvalidArgumentException('La OC está cancelada en Enkontrol y no admite seguimiento.');
        }

        if (! $ordenFuente) {
            return null;
        }

        return DB::table('oc_seguimientos')->where($orden)->first();
    }

    private function autorizarSeguimiento(?object $seguimiento, int $usuarioId, bool $esAdministrador): void
    {
        if (! $seguimiento) {
            throw new InvalidArgumentException('No se encontró el seguimiento de esta orden.');
        }

        if (! $esAdministrador && (int) $seguimiento->responsable_id !== $usuarioId) {
            throw new InvalidArgumentException('Solo la compradora responsable puede registrar actividad en esta OC.');
        }
    }
}
