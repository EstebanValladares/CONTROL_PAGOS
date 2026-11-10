<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller
{
    public function index(): Response
    {
        $documentosHabilitados = true;
        $usuarioNombre = Session::get('usuario_nombre', '');
        $puedeBorrar = Session::get('usuario_autenticado', false) && AuthService::puedeBorrarPagosProgramados($usuarioNombre);

        return response()
            ->view('index', [
                'documentos_habilitados' => $documentosHabilitados,
                'puede_borrar_pagos_sesion' => $puedeBorrar,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
