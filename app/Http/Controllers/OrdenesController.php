<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\DocumentService;
use App\Services\OrdenesService;
use App\Services\PagosService;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrdenesController extends Controller
{
    public function recientes(Request $request): JsonResponse
    {
        try {
            $params = [
                'empresa' => $request->query('empresa', 'ARYVE'),
                'empleado_id' => $request->query('empleado_id', ''),
                'filtro_temporal' => $request->query('temporal', 'HOY'),
                'semana' => $request->query('semana', ''),
                'busqueda' => $request->query('busqueda', $request->query('q', '')),
                'obra' => $request->query('obra', ''),
                'mis_ordenes' => $request->query('mis_ordenes', '1'),
                'pagina' => (int)$request->query('pagina', 1),
                'limite' => (int)$request->query('limite', 50),
            ];

            $resultado = OrdenesService::consultarOrdenesRecientes($params);
            return response()->json($resultado);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'mensaje' => $e->getMessage(),
                'traceback' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function catalogoObras(Request $request): JsonResponse
    {
        try {
            $empresa = $request->query('empresa', 'ARYVE');
            $obras = OrdenesService::obtenerCatalogoObras($empresa);
            return response()->json(['status' => 'ok', 'obras' => $obras]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function descargarExcel(Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $params = [
                'empresa' => $request->query('empresa', ''),
                'plaza' => $request->query('plaza', ''),
                'semana' => $request->query('semana', ''),
                'responsable' => $request->query('responsable', ''),
            ];

            $ruta = PagosService::generarCsvConciliado($params);
            return response()->download($ruta, 'concentrado_oc_conciliado.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function subirDocumentos(Request $request): JsonResponse
    {
        $archivosCreados = [];
        try {
            $archivos = $request->file('archivos');
            if (empty($archivos)) {
                $archivos = $request->allFiles();
            }
            if (empty($archivos)) {
                return response()->json(['status' => 'error', 'mensaje' => 'No se seleccionaron archivos'], 400);
            }
            if (!is_array($archivos)) {
                $archivos = [$archivos];
            }

            $empresa = strtoupper(trim($request->input('empresa', 'ARYVE')));
            $centroCostos = trim($request->input('centro_costos', ''));
            $numeroOc = (int)$request->input('numero_oc', 0);
            $creadoPor = trim($request->input('creado_por', 'USUARIO'));

            // Validar que la OC no tenga ya documentos para evitar duplicados
            $totalExistentes = DB::table('documentos_oc')
                ->where('empresa', $empresa)
                ->where('centro_costos', $centroCostos)
                ->where('numero_oc', $numeroOc)
                ->count();

            if ($totalExistentes > 0) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => "La OC #{$numeroOc} de {$centroCostos} ya cuenta con {$totalExistentes} documento(s) adjunto(s). No se permite duplicar.",
                ], 400);
            }

            $guardados = DocumentService::guardarDocumentos(
                $archivos,
                $empresa,
                $centroCostos,
                $numeroOc,
                null,
                $creadoPor,
                $archivosCreados,
                !empty($centroCostos) && $numeroOc > 0
            );

            DocumentService::replicarDocumentos($archivosCreados);

            return response()->json([
                'status' => 'ok',
                'mensaje' => count($guardados) . " documento(s) adjuntado(s) exitosamente",
                'guardados' => $guardados,
                'total_documentos' => count($guardados),
            ]);
        } catch (Exception $e) {
            DocumentService::limpiarArchivos($archivosCreados);
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function documentosOc(Request $request): JsonResponse
    {
        try {
            $empresa = strtoupper(trim($request->query('empresa', 'ARYVE')));
            $centroCostos = trim($request->query('centro_costos', ''));
            $numeroOc = (int)$request->query('numero_oc', 0);

            $docs = DB::table('documentos_oc')
                ->select('id', 'nombre_original', 'nombre_guardado', 'tamano_bytes', 'creado_por', 'creado_en')
                ->where('empresa', $empresa)
                ->where('centro_costos', $centroCostos)
                ->where('numero_oc', $numeroOc)
                ->orderBy('id', 'asc')
                ->get();

            return response()->json(['status' => 'ok', 'documentos' => $docs]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function descargarArchivoOc(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $doc = DB::table('documentos_oc')->where('id', $id)->first();
            if (!$doc) {
                return response()->json(['status' => 'error', 'mensaje' => 'Documento no encontrado'], 404);
            }

            $ruta = $doc->ruta_archivo;
            if (!file_exists($ruta)) {
                $rutaLocal = DocumentService::getDocsDir() . DIRECTORY_SEPARATOR . $doc->nombre_guardado;
                $rutaEk = DocumentService::getEnkontrolWebDir() . DIRECTORY_SEPARATOR . $doc->nombre_guardado;
                if (file_exists($rutaLocal)) {
                    $ruta = $rutaLocal;
                } elseif (file_exists($rutaEk)) {
                    $ruta = $rutaEk;
                } else {
                    return response()->json(['status' => 'error', 'mensaje' => 'El archivo físico no se encuentra en el servidor'], 404);
                }
            }

            return response()->download($ruta, $doc->nombre_original);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function programarAbono(Request $request): JsonResponse
    {
        try {
            $datos = $request->all();
            $res = OrdenesService::programarAbono($datos);
            return response()->json($res);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminarAbono(Request $request): JsonResponse
    {
        try {
            if (!Session::get('usuario_autenticado')) {
                return response()->json(['status' => 'error', 'mensaje' => 'No hay sesión de usuario activa'], 401);
            }

            $usuarioNombre = Session::get('usuario_nombre', '');
            if (!AuthService::puedeBorrarPagosProgramados($usuarioNombre)) {
                return response()->json(['status' => 'error', 'mensaje' => 'No tienes permiso para borrar pagos programados'], 403);
            }

            $idAbono = (int)$request->input('id', 0);
            $empresa = strtoupper(trim($request->input('empresa', 'ARYVE')));
            $centroCostos = trim($request->input('centro_costos', ''));
            $numeroOc = (int)$request->input('numero_oc', 0);
            $totalOc = (float)$request->input('total_oc', 0.0);

            if ($idAbono <= 0) {
                return response()->json(['status' => 'error', 'mensaje' => 'ID de abono no válido'], 400);
            }

            $res = OrdenesService::eliminarAbono($idAbono, $empresa, $centroCostos, $numeroOc, $totalOc);
            return response()->json($res);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function programacionesSemana(Request $request): JsonResponse
    {
        try {
            $empresa = $request->query('empresa', 'ARYVE');
            $semana = $request->query('semana', '');
            $obra = $request->query('obra', '');

            $res = OrdenesService::obtenerProgramacionesSemana($empresa, $semana, $obra);
            return response()->json($res);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function programarLote(Request $request): JsonResponse
    {
        try {
            $itemsRaw = $request->input('items', []);
            if (is_string($itemsRaw)) {
                $items = json_decode($itemsRaw, true) ?: [];
            } else {
                $items = $itemsRaw;
            }

            if (!is_array($items) || empty($items)) {
                return response()->json(['status' => 'error', 'mensaje' => 'No se enviaron órdenes para programar'], 400);
            }

            $archivosPorItem = [];
            foreach ($items as $indice => $it) {
                $files = $request->file("archivos_{$indice}", []);
                if (!is_array($files) && $files !== null) {
                    $files = [$files];
                }
                if (!empty($files) && (count($files) < 2 || count($files) > 4)) {
                    $numOc = $it['numero_oc'] ?? '';
                    return response()->json([
                        'status' => 'error',
                        'mensaje' => "Adjunta entre 2 y 4 documentos para la orden #{$numOc}",
                    ], 400);
                }
                $archivosPorItem[$indice] = $files ?: [];
            }

            $res = OrdenesService::programarLote($items, $archivosPorItem);
            return response()->json($res);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function enviarProgramacionBi(int $id): JsonResponse
    {
        try {
            $res = OrdenesService::enviarProgramacionBi($id);
            return response()->json($res);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function sincronizarEnkontrol(Request $request): JsonResponse
    {
        try {
            $script = base_path('sincronizar_ordenes_ek9.py');
            if (!file_exists($script)) {
                return response()->json(['status' => 'error', 'mensaje' => 'Script de sincronización no encontrado'], 404);
            }

            $pythonExec = 'python';
            $posibles = [
                'sys.executable',
                'python3',
                'python'
            ];
            foreach ($posibles as $p) {
                if (file_exists($p)) {
                    $pythonExec = $p;
                    break;
                }
            }

            $process = new Process([$pythonExec, $script]);
            $process->setTimeout(30);
            $process->run();

            return response()->json([
                'status' => 'ok',
                'salida' => $process->getOutput(),
                'exitoso' => $process->isSuccessful()
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }
}
