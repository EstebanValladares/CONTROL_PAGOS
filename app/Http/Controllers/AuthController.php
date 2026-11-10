<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $usuario = (string)$request->input('usuario', '');
            $password = (string)$request->input('password', '');
            $empresa = (string)$request->input('empresa', 'ARYVE');

            $res = AuthService::login($usuario, $password, $empresa);
            $codigo = $res['codigo'] ?? 200;
            unset($res['codigo']);

            return response()->json($res, $codigo);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function logout(): JsonResponse
    {
        AuthService::logout();
        return response()->json(['status' => 'ok']);
    }
}
