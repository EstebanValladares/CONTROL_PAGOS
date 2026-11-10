<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class FTPService
{
    protected static function obtenerConexion()
    {
        $host = env('FTP_HOST', '10.10.202.244');
        $port = (int)env('FTP_PORT', 21);
        $user = env('FTP_USER', 'anonymous');
        $pass = env('FTP_PASS', '');
        $timeout = (int)env('FTP_TIMEOUT', 10);

        if (!function_exists('ftp_connect')) {
            throw new Exception("La extensión FTP de PHP no está habilitada.");
        }

        $conn = @ftp_connect($host, $port, $timeout);
        if (!$conn) {
            throw new Exception("No se pudo conectar al servidor FTP {$host}:{$port}");
        }

        if (!@ftp_login($conn, $user, $pass)) {
            @ftp_close($conn);
            throw new Exception("Autenticación fallida en el servidor FTP para el usuario {$user}");
        }

        ftp_pasv($conn, true);
        return $conn;
    }

    public static function probarConexion(): array
    {
        try {
            $conn = self::obtenerConexion();
            ftp_close($conn);
            return ['ok' => true, 'mensaje' => 'Conexión FTP exitosa'];
        } catch (Exception $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public static function subirArchivos(array $rutas): array
    {
        if (!filter_var(env('FTP_HABILITADO', false), FILTER_VALIDATE_BOOLEAN)) {
            $res = [];
            foreach ($rutas as $r) {
                $res[] = ['ruta' => $r, 'subido' => false, 'motivo' => 'FTP_HABILITADO es false'];
            }
            return $res;
        }

        $remoteDir = trim(env('FTP_DIR', '/WebEk10'));
        $resultados = [];

        try {
            $conn = self::obtenerConexion();

            if (!empty($remoteDir) && $remoteDir !== '/' && $remoteDir !== '.') {
                $partes = array_filter(explode('/', str_replace('\\', '/', $remoteDir)));
                foreach ($partes as $p) {
                    if (!@ftp_chdir($conn, $p)) {
                        @ftp_mkdir($conn, $p);
                        @ftp_chdir($conn, $p);
                    }
                }
            }

            foreach ($rutas as $ruta) {
                if (!file_exists($ruta)) {
                    $resultados[] = ['ruta' => $ruta, 'subido' => false, 'motivo' => 'Archivo local no encontrado'];
                    continue;
                }

                $nombre = basename($ruta);
                if (@ftp_put($conn, $nombre, $ruta, FTP_BINARY)) {
                    $resultados[] = [
                        'ruta' => $ruta,
                        'archivo' => $nombre,
                        'subido' => true,
                        'directorio_remoto' => $remoteDir
                    ];
                    Log::info("[FTP] Archivo {$nombre} subido a {$remoteDir}");
                } else {
                    $resultados[] = [
                        'ruta' => $ruta,
                        'archivo' => $nombre,
                        'subido' => false,
                        'motivo' => 'Error al transferir via ftp_put'
                    ];
                }
            }

            @ftp_close($conn);
        } catch (Exception $e) {
            Log::error("[FTP GENERAL] " . $e->getMessage());
            foreach ($rutas as $ruta) {
                $found = false;
                foreach ($resultados as $r) {
                    if ($r['ruta'] === $ruta) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $resultados[] = ['ruta' => $ruta, 'subido' => false, 'motivo' => $e->getMessage()];
                }
            }
        }

        return $resultados;
    }
}
