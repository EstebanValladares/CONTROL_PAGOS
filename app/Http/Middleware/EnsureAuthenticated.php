<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Session::get('usuario_autenticado')) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Sesión expirada o no iniciada. Por favor ingresa con tus credenciales.',
                'codigo' => 401,
            ], 401);
        }

        return $next($request);
    }
}
