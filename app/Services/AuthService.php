<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AuthService
{
    protected static array $usuariosAutorizadosBorrar = [
        "CLAUDIA SARAI PADILLA RAMIREZ",
        "LUIS SOTERO CRUZ GOMEZ",
        "ARTURO VILLANUEVA GUERRERO",
        "PEDRO PEREZ GUZMAN",
        "PEDRO PEREZ",
        "YEUDIEL ESTEBAN RICHARD PEREZ",
        "SANTOS PEREZ TOLENTINO",
        "IRMA MIREYA MOLINA CAZARES",
        "LUIS ANGEL PERNAS SUAREZ",
    ];

    public static function normalizarTexto(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }
        $texto = mb_strtoupper(trim($texto), 'UTF-8');
        // Quitar acentos
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ü' => 'U', 'Ñ' => 'N'
        ]);
        return preg_replace('/\s+/', ' ', $texto);
    }

    public static function puedeBorrarPagosProgramados(?string $nombre): bool
    {
        $norm = self::normalizarTexto($nombre);
        return in_array($norm, self::$usuariosAutorizadosBorrar, true);
    }

    public static function esUsuarioAdministradorAutorizado(?string $nombre = '', ?string $email = '', $empId = null, ?string $rol = ''): bool
    {
        if (strtoupper(trim((string)$rol)) === 'ADMIN') {
            return true;
        }

        if ($empId !== null && in_array((int)$empId, [18, 120, 166, 3399, 9119], true)) {
            return true;
        }

        $nom = self::normalizarTexto($nombre);
        $em = strtolower(trim((string)$email));

        $emailsAutorizados = [
            'claudia.padilla@grupovelas.com',
            'enkontrol2@grupovelas.com',
            'enkontrol3@grupovelas.com',
            'avillanueva@grupovelas.com',
            'pedro.ek@grupovelas.com',
            '',
            'santos.perez@grupovelas.com',
            'supervision.auxiliares3@grupovelas.com',
            'supervision.auxiliares@grupovelas.com',
        ];

        if (in_array($em, $emailsAutorizados, true)) {
            return true;
        }

        if (str_contains($nom, 'PADILLA') && (str_contains($nom, 'CLAUDIA') || str_contains($nom, 'SARAI'))) {
            return true;
        }
        if (str_contains($nom, 'SOTERO') && str_contains($nom, 'CRUZ')) {
            return true;
        }
        if (str_contains($nom, 'VILLANUEVA') && str_contains($nom, 'ARTURO')) {
            return true;
        }
        if ((str_contains($nom, 'PEDRO') && str_contains($nom, 'PEREZ')) || str_contains($nom, 'PEREZ GUZMAN')) {
            return true;
        }
        if (str_contains($nom, 'YEUDIEL') || (str_contains($nom, 'RICHARD') && str_contains($nom, 'PEREZ'))) {
            return true;
        }
        if (str_contains($nom, 'SANTOS') && (str_contains($nom, 'PEREZ') || str_contains($nom, 'TOLENTINO'))) {
            return true;
        }
        if (str_contains($nom, 'MOLINA') && (str_contains($nom, 'IRMA') || str_contains($nom, 'MIREYA'))) {
            return true;
        }
        if (str_contains($nom, 'PERNAS')) {
            return true;
        }

        return false;
    }

    public static function login(string $usuario, string $password, string $empresa = 'ARYVE'): array
    {
        $usuario = trim($usuario);
        $password = trim($password);
        $empresa = strtoupper(trim($empresa));
        if (!in_array($empresa, ['ARYVE', 'VELAS'], true)) {
            $empresa = 'ARYVE';
        }

        if (empty($usuario) || empty($password)) {
            return [
                'status' => 'error',
                'codigo' => 400,
                'mensaje' => 'Ingresa tu usuario y contraseña'
            ];
        }

        // Acceso ADMIN
        if (strtoupper($usuario) === 'ADMIN') {
            if (in_array($password, ['admin', 'admin123', 'velas2026', 'admin2026', ''], true)) {
                Session::put('usuario_autenticado', true);
                Session::put('usuario_nombre', 'ADMINISTRADOR');
                Session::put('usuario_rol', 'ADMIN');
                Session::put('usuario_empresa', $empresa);

                return [
                    'status' => 'ok',
                    'mensaje' => 'Acceso como Administrador autorizado',
                    'usuario' => [
                        'id' => 0,
                        'nombre' => 'ADMINISTRADOR',
                        'puesto' => 'SUPERVISIÓN GENERAL',
                        'email' => 'admin@grupovelas.com',
                        'rol' => 'ADMIN',
                        'empresa' => $empresa,
                        'plaza' => 'TODAS',
                        'puede_ver_conciliacion' => true,
                        'es_admin_funcional' => true,
                        'puede_borrar_pagos' => false,
                        'permiso_borrar_pagos_version' => 2,
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'codigo' => 401,
                    'mensaje' => 'Contraseña de administrador incorrecta'
                ];
            }
        }

        // Consulta en SQLite usuarios_ek9
        $query = DB::table('usuarios_ek9')
            ->select('empleado', 'nombre', 'puesto', 'email', 'password', 'rol', 'empresa', 'plaza', 'estatus')
            ->where('empresa', $empresa);

        if (ctype_digit($usuario)) {
            $query->where('empleado', (int)$usuario);
        } else {
            $query->where(function ($q) use ($usuario) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($usuario)])
                  ->orWhereRaw('LOWER(nombre) = ?', [strtolower($usuario)]);
            });
        }

        $fila = $query->first();

        if (!$fila) {
            $nombreGrupo = ($empresa === 'VELAS') ? 'Grupo Velas' : 'Construcciones Aryve';
            return [
                'status' => 'error',
                'codigo' => 401,
                'mensaje' => "El usuario no se encuentra disponible en {$nombreGrupo}. Verifica la empresa seleccionada o tu número de empleado."
            ];
        }

        if (strtoupper(trim((string)($fila->estatus ?? 'A'))) === 'B') {
            return [
                'status' => 'error',
                'codigo' => 403,
                'mensaje' => 'Acceso restringido: Tu usuario se encuentra inactivo (Estatus B) en el sistema EnKontrol. Contacta a Sistemas o Administración.'
            ];
        }

        if (trim((string)($fila->password ?? '')) !== $password) {
            return [
                'status' => 'error',
                'codigo' => 401,
                'mensaje' => 'Contraseña incorrecta. El acceso no se encuentra disponible con estas credenciales.'
            ];
        }

        $esAdminFunc = self::esUsuarioAdministradorAutorizado(
            $fila->nombre,
            $fila->email,
            $fila->empleado,
            $fila->rol
        );
        $puedeBorrar = self::puedeBorrarPagosProgramados($fila->nombre);

        Session::put('usuario_autenticado', true);
        Session::put('usuario_nombre', $fila->nombre);
        Session::put('usuario_rol', $fila->rol);
        Session::put('usuario_empresa', $fila->empresa);

        return [
            'status' => 'ok',
            'mensaje' => "Bienvenido(a), {$fila->nombre}",
            'usuario' => [
                'id' => $fila->empleado,
                'nombre' => $fila->nombre,
                'puesto' => $fila->puesto,
                'email' => $fila->email,
                'rol' => $fila->rol,
                'empresa' => $fila->empresa,
                'plaza' => $fila->plaza ?: ($empresa === 'VELAS' ? 'Los Cabos' : 'Tampico'),
                'puede_ver_conciliacion' => $esAdminFunc,
                'es_admin_funcional' => $esAdminFunc,
                'puede_borrar_pagos' => $puedeBorrar,
                'permiso_borrar_pagos_version' => 2,
            ]
        ];
    }

    public static function logout(): void
    {
        Session::flush();
    }
}
