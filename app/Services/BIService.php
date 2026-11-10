<?php

namespace App\Services;

use PDO;
use Exception;
use Illuminate\Support\Facades\Log;

class BIService
{
    protected static function getPdo(): ?PDO
    {
        $host = env('BI_HOST', '10.30.0.2');
        $port = env('BI_PORT', '1433');
        $db = env('BI_DB', 'Aryve2');
        $user = env('BI_USER', 'sa');
        $pass = env('BI_PASS', 'Aryve2025*');

        try {
            $dsn = "odbc:Driver={SQL Server};Server={$host};Database={$db};Uid={$user};Pwd={$pass};";
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return $pdo;
        } catch (Exception $e) {
            Log::error("[BI SQL Server] Error de conexión: " . $e->getMessage());
            return null;
        }
    }

    public static function insertarPago(array $pago): bool
    {
        return self::insertarLote([$pago]) > 0;
    }

    public static function insertarLote(array $pagos): int
    {
        if (empty($pagos)) {
            return 0;
        }

        $pdo = self::getPdo();
        if (!$pdo) {
            return 0;
        }

        $sql = "INSERT INTO dbo._pagos_programados (
            empresa, centro_costos, numero_oc, id_ek10, proveedor,
            total_oc, monto_programado, semana, rubro, tipo, concepto,
            creado_por, creado_en
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $insertados = 0;
        $ahora = date('Y-m-d H:i:s');

        foreach ($pagos as $p) {
            try {
                $stmt->execute([
                    trim(strtoupper($p['empresa'] ?? 'ARYVE')),
                    trim($p['centro_costos'] ?? ''),
                    (int)($p['numero_oc'] ?? 0),
                    !empty($p['id_ek10']) ? trim($p['id_ek10']) : null,
                    !empty($p['proveedor']) ? trim($p['proveedor']) : null,
                    (float)($p['total_oc'] ?? 0.0),
                    (float)($p['monto_programado'] ?? 0.0),
                    trim($p['semana'] ?? ''),
                    !empty($p['rubro']) ? trim($p['rubro']) : null,
                    !empty($p['tipo']) ? trim($p['tipo']) : null,
                    !empty($p['concepto']) ? trim($p['concepto']) : null,
                    trim($p['creado_por'] ?? 'USUARIO'),
                    $p['creado_en'] ?? $ahora,
                ]);
                $insertados++;
            } catch (Exception $e) {
                Log::error("[BI SQL Server] Error insertando fila: " . $e->getMessage());
            }
        }

        return $insertados;
    }

    public static function eliminarAbono(string $empresa, string $centroCostos, int $numeroOc): bool
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM dbo._pagos_programados WHERE empresa = ? AND centro_costos = ? AND numero_oc = ?");
            $stmt->execute([strtoupper(trim($empresa)), trim($centroCostos), $numeroOc]);
            return true;
        } catch (Exception $e) {
            Log::error("[BI SQL Server] Error eliminando abono: " . $e->getMessage());
            return false;
        }
    }
}
