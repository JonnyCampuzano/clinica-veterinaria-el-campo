<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| VERIFICAR SESIÓN
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return !empty(
        $_SESSION['usuario_id']
        ?? $_SESSION['id_usuario']
        ?? null
    );
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php?error=sesion_requerida');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

function current_user(): array
{
    return [
        'id' => (int) (
            $_SESSION['usuario_id']
            ?? $_SESSION['id_usuario']
            ?? 0
        ),

        'nombre' => trim(
            (string) (
                $_SESSION['nombre']
                ?? $_SESSION['usuario']
                ?? 'Usuario'
            )
        ),

        'correo' => trim(
            (string) (
                $_SESSION['correo']
                ?? ''
            )
        ),

        'rol' => current_role()
    ];
}

/*
|--------------------------------------------------------------------------
| NORMALIZAR ROL
|--------------------------------------------------------------------------
*/

function current_role(): string
{
    $rol = trim(
        (string) (
            $_SESSION['rol']
            ?? ''
        )
    );

    if (function_exists('mb_strtolower')) {
        $rol = mb_strtolower($rol, 'UTF-8');
    } else {
        $rol = strtolower($rol);
    }

    $rol = strtr($rol, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u'
    ]);

    return match ($rol) {
        'administrador',
        'admin' => 'administrador',

        'medico',
        'veterinario' => 'medico',

        'recepcionista',
        'recepcion' => 'recepcionista',

        default => 'sin_rol'
    };
}

/*
|--------------------------------------------------------------------------
| NOMBRE VISIBLE DEL ROL
|--------------------------------------------------------------------------
*/

function role_label(string $rol): string
{
    return match ($rol) {
        'administrador' => 'Administrador',
        'medico' => 'Médico',
        'recepcionista' => 'Recepcionista',
        default => 'Usuario'
    };
}

/*
|--------------------------------------------------------------------------
| PERMISOS POR ROL
|--------------------------------------------------------------------------
*/

function role_permissions(): array
{
    return [
        'administrador' => [
            'dashboard.ver',

            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
            'clientes.eliminar',

            'mascotas.ver',
            'mascotas.crear',
            'mascotas.editar',
            'mascotas.eliminar',

            'citas.ver',
            'citas.crear',
            'citas.editar',
            'citas.eliminar',

            'historias.ver',
            'historias.crear',
            'historias.editar',
            'historias.eliminar',

            'inventario.ver',
            'inventario.crear',
            'inventario.editar',
            'inventario.eliminar',

            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar'
        ],

        'medico' => [
            'dashboard.ver',

            'clientes.ver',

            'mascotas.ver',

            'citas.ver',
            'citas.editar',

            'historias.ver',
            'historias.crear',
            'historias.editar',

            'inventario.ver'
        ],

        'recepcionista' => [
            'dashboard.ver',

            'clientes.ver',
            'clientes.crear',
            'clientes.editar',

            'mascotas.ver',
            'mascotas.crear',
            'mascotas.editar',

            'citas.ver',
            'citas.crear',
            'citas.editar'
        ]
    ];
}

/*
|--------------------------------------------------------------------------
| COMPROBAR PERMISO
|--------------------------------------------------------------------------
*/

function can(string $permiso): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $rol = current_role();
    $permisos = role_permissions();

    if (!isset($permisos[$rol])) {
        return false;
    }

    return in_array(
        $permiso,
        $permisos[$rol],
        true
    );
}

/*
|--------------------------------------------------------------------------
| EXIGIR PERMISO
|--------------------------------------------------------------------------
*/

function require_permission(string $permiso): void
{
    require_login();

    if (!can($permiso)) {
        http_response_code(403);

        $rol = role_label(current_role());

        exit(
            '<!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport"
                      content="width=device-width, initial-scale=1.0">

                <title>Acceso denegado</title>

                <style>
                    body {
                        min-height: 100vh;
                        margin: 0;
                        display: grid;
                        place-items: center;
                        background: #f1f5f9;
                        font-family: Arial, sans-serif;
                    }

                    .mensaje {
                        width: min(420px, 90%);
                        padding: 32px;
                        text-align: center;
                        background: #ffffff;
                        border-radius: 18px;
                        box-shadow: 0 15px 40px
                            rgba(15, 23, 42, 0.10);
                    }

                    h1 {
                        color: #dc2626;
                    }

                    p {
                        color: #475569;
                        line-height: 1.6;
                    }

                    a {
                        display: inline-block;
                        margin-top: 15px;
                        padding: 12px 20px;
                        color: #ffffff;
                        background: #2563eb;
                        border-radius: 10px;
                        text-decoration: none;
                        font-weight: bold;
                    }
                </style>
            </head>

            <body>
                <div class="mensaje">
                    <h1>Acceso denegado</h1>

                    <p>
                        El rol <strong>'
                        . htmlspecialchars(
                            $rol,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</strong> no posee el permiso requerido.
                    </p>

                    <a href="panel.php">
                        Regresar al panel
                    </a>
                </div>
            </body>
            </html>'
        );
    }
}


/*
|--------------------------------------------------------------------------
| REDIRECCIÓN DEL SISTEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('auth_redirect')) {
    function auth_redirect(string $ruta): void
    {
        $ruta = trim($ruta);

        if ($ruta === '') {
            $ruta = 'login.php';
        }

        if (headers_sent($archivo, $linea)) {
            exit(
                'No se pudo redirigir porque ya se envió contenido desde '
                . htmlspecialchars($archivo)
                . ' en la línea '
                . $linea
            );
        }

        header('Location: ' . $ruta);
        exit;
    }
}