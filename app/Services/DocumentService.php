<?php

namespace App\Services;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentService
{
    public static function getDocsDir(): string
    {
        $dir = base_path('documentos_ejemplo');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function getEnkontrolWebDir(): string
    {
        return 'C:\\EnkontrolV9\\WebEk10';
    }

    public static function guardarDocumentos(
        array $archivos,
        string $empresa,
        string $centroCostos,
        int $numeroOc,
        ?int $idAbono,
        string $creadoPor,
        array &$archivosCreados,
        bool $persistirDb = true
    ): array {
        $guardados = [];
        $docsDir = self::getDocsDir();
        $ahora = date('Y-m-d H:i:s');

        foreach ($archivos as $archivo) {
            if (!$archivo || !$archivo->isValid()) {
                continue;
            }

            $nombreOriginal = $archivo->getClientOriginalName();
            $nombreBase = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nombreOriginal);
            $ccSanitizado = preg_replace('/[^a-zA-Z0-9_-]/', '_', $centroCostos) ?: 'obra';
            $uuid = Str::random(16);
            $nombreGuardado = "{$ccSanitizado}_OC{$numeroOc}_{$uuid}_{$nombreBase}";

            $rutaCompleta = $docsDir . DIRECTORY_SEPARATOR . $nombreGuardado;
            $archivo->move($docsDir, $nombreGuardado);
            $archivosCreados[] = $rutaCompleta;
            $tamano = file_exists($rutaCompleta) ? filesize($rutaCompleta) : 0;

            if ($persistirDb) {
                DB::table('documentos_oc')->insert([
                    'empresa' => $empresa,
                    'centro_costos' => $centroCostos,
                    'numero_oc' => $numeroOc,
                    'id_abono' => $idAbono,
                    'nombre_original' => $nombreOriginal,
                    'nombre_guardado' => $nombreGuardado,
                    'ruta_archivo' => $rutaCompleta,
                    'tamano_bytes' => $tamano,
                    'creado_por' => $creadoPor,
                    'creado_en' => $ahora,
                ]);
            }

            $guardados[] = [
                'nombre_original' => $nombreOriginal,
                'nombre_guardado' => $nombreGuardado,
                'tamano' => $tamano,
            ];
        }

        return $guardados;
    }

    public static function limpiarArchivos(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            try {
                if (file_exists($ruta)) {
                    @unlink($ruta);
                }
            } catch (Exception $e) {
                Log::warning("[DOCS] Error eliminando archivo temporal {$ruta}: " . $e->getMessage());
            }
        }
    }

    public static function extraerTotalFacturaXml($archivo): float
    {
        $contenido = file_get_contents(is_string($archivo) ? $archivo : $archivo->getRealPath());
        $nombreOriginal = is_string($archivo) ? basename($archivo) : $archivo->getClientOriginalName();

        if (empty($contenido)) {
            throw new InvalidArgumentException("El XML de factura '{$nombreOriginal}' está vacío");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenido);
        if ($xml === false) {
            libxml_clear_errors();
            throw new InvalidArgumentException("El XML de factura '{$nombreOriginal}' no es válido");
        }

        $tag = $xml->getName();
        if ($tag !== 'Comprobante') {
            throw new InvalidArgumentException("El XML '{$nombreOriginal}' no es un CFDI Comprobante");
        }

        $atributos = $xml->attributes();
        $totalRaw = null;
        foreach ($atributos as $k => $v) {
            if (strtolower($k) === 'total') {
                $totalRaw = (string)$v;
                break;
            }
        }

        if ($totalRaw === null || !preg_match('/^\d{1,18}(\.\d{1,6})?$/', $totalRaw)) {
            throw new InvalidArgumentException("El XML '{$nombreOriginal}' no contiene el total de la factura");
        }

        $total = (float)$totalRaw;
        if (!is_finite($total) || $total < 0) {
            throw new InvalidArgumentException("El XML '{$nombreOriginal}' contiene un total inválido");
        }

        return $total;
    }

    public static function validarTotalXmlMenorPago(?float $totalXml, float $montoPago, int $numeroOc): void
    {
        if ($totalXml !== null && $totalXml >= $montoPago) {
            $totalFormato = number_format($totalXml, 2);
            $pagoFormato = number_format($montoPago, 2);
            throw new InvalidArgumentException(
                "La suma de las facturas XML de la OC #{$numeroOc} (\${$totalFormato}) debe ser menor al importe del pago (\${$pagoFormato})"
            );
        }
    }

    public static function replicarDocumentos(array $rutas): void
    {
        $ekDir = self::getEnkontrolWebDir();
        foreach ($rutas as $ruta) {
            try {
                if (is_dir($ekDir) && file_exists($ruta)) {
                    @copy($ruta, $ekDir . DIRECTORY_SEPARATOR . basename($ruta));
                }
            } catch (Exception $e) {
                Log::warning("[DOCS] Error replicando a WebEk10: " . $e->getMessage());
            }
        }

        try {
            FTPService::subirArchivos($rutas);
        } catch (Exception $e) {
            Log::warning("[DOCS] Error replicando a FTP: " . $e->getMessage());
        }
    }
}
