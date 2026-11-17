<?php

namespace App\Http\Controllers;

use App\Services\OrdenSeguimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

class OrdenSeguimientoController extends Controller
{
    public function __construct(private readonly OrdenSeguimientoService $seguimiento) {}

    public function index(Request $request): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        $filtro = (string) $request->query('filtro', 'todas');
        if (! in_array($filtro, ['todas', 'limbo', 'sin_responsable'], true)) {
            $filtro = 'todas';
        }

        return response()->json($this->seguimiento->listar(
            $contexto['empresa'],
            $contexto['usuario_id'],
            $contexto['es_administrador'],
            $filtro,
            max(1, (int) $request->query('pagina', 1)),
            min(100, max(1, (int) $request->query('limite', 25)))
        ));
    }

    public function compradoras(): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        return response()->json([
            'status' => 'ok',
            'datos' => $this->seguimiento->obtenerCompradoras($contexto['empresa']),
        ]);
    }

    public function historial(Request $request): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        $datos = $request->validate([
            'centro_costos' => ['required', 'string', 'max:100'],
            'numero_oc' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $historial = $this->seguimiento->obtenerHistorial(
                $this->ordenDeSesion($datos, $contexto),
                $contexto['usuario_id'],
                $contexto['es_administrador']
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'error', 'mensaje' => $exception->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'datos' => $historial]);
    }

    public function nota(Request $request): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        $datos = $request->validate([
            'centro_costos' => ['required', 'string', 'max:100'],
            'numero_oc' => ['required', 'integer', 'min:1'],
            'nota' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $this->seguimiento->registrarNota(
                $this->ordenDeSesion($datos, $contexto),
                trim($datos['nota']),
                $contexto['usuario_id'],
                $contexto['usuario_nombre'],
                $contexto['es_administrador']
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'error', 'mensaje' => $exception->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'mensaje' => 'Seguimiento registrado.']);
    }

    public function estado(Request $request): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        $datos = $request->validate([
            'centro_costos' => ['required', 'string', 'max:100'],
            'numero_oc' => ['required', 'integer', 'min:1'],
            'estado' => ['required', 'in:ABIERTA,EN_ESPERA_PROVEEDOR,CERRADA,CANCELADA'],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $this->seguimiento->actualizarEstado(
                $this->ordenDeSesion($datos, $contexto),
                $datos['estado'],
                trim($datos['motivo']),
                $contexto['usuario_id'],
                $contexto['usuario_nombre'],
                $contexto['es_administrador']
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'error', 'mensaje' => $exception->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'mensaje' => 'Estado de seguimiento actualizado.']);
    }

    public function reasignarCartera(Request $request): JsonResponse
    {
        $contexto = $this->contexto(true);
        if ($contexto instanceof JsonResponse) {
            return $contexto;
        }

        $datos = $request->validate([
            'ordenes' => ['required', 'array', 'min:1', 'max:100'],
            'ordenes.*.centro_costos' => ['required', 'string', 'max:100'],
            'ordenes.*.numero_oc' => ['required', 'integer', 'min:1'],
            'responsable_id' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $cantidad = $this->seguimiento->reasignarCartera(
                $contexto['empresa'],
                $datos['ordenes'],
                (int) $datos['responsable_id'],
                trim($datos['motivo']),
                $contexto['usuario_id'],
                $contexto['usuario_nombre']
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'error', 'mensaje' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => 'ok',
            'mensaje' => "{$cantidad} OC(s) reasignada(s).",
            'reasignadas' => $cantidad,
        ]);
    }

    private function contexto(bool $requiereAdministrador = false): array|JsonResponse
    {
        if (! Session::get('usuario_autenticado')) {
            return response()->json(['status' => 'error', 'mensaje' => 'Inicia sesión para continuar.'], 401);
        }

        $esAdminDeRol = strtoupper(trim((string) Session::get('usuario_rol'))) === 'ADMIN';
        $esAdminAutorizado = $esAdminDeRol
            || (
                (int) Session::get('usuario_id', 0) === 3399
                && (bool) Session::get('usuario_admin_funcional', false)
            );
        $contexto = [
            'empresa' => strtoupper(trim((string) Session::get('usuario_empresa', ''))),
            'usuario_id' => (int) Session::get('usuario_id', 0),
            'usuario_nombre' => (string) Session::get('usuario_nombre', ''),
            'es_admin_rol' => $esAdminAutorizado,
            'es_administrador' => $esAdminAutorizado,
        ];

        if ($contexto['empresa'] === '') {
            return response()->json(['status' => 'error', 'mensaje' => 'La sesión no tiene una empresa válida.'], 403);
        }

        if ($requiereAdministrador && ! $esAdminAutorizado) {
            return response()->json(['status' => 'error', 'mensaje' => 'Solo los administradores autorizados pueden acceder al seguimiento de OCs.'], 403);
        }

        return $contexto;
    }

    private function ordenDeSesion(array $datos, array $contexto): array
    {
        return [
            'empresa' => $contexto['empresa'],
            'centro_costos' => trim($datos['centro_costos']),
            'numero_oc' => (int) $datos['numero_oc'],
        ];
    }
}
