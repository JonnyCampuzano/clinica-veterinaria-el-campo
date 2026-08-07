<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN, ROLES Y PERMISOS
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| REDIRECCIÓN
|--------------------------------------------------------------------------
*/

if (!function_exists('auth_redirect')) {
    function auth_redirect(string $ruta): never
    {
        if (function_exists('url')) {
            header('Location: ' . url($ruta));
        } else {
            header(
                'Location: /clinica_veterinaria_el_campo/' .
                ltrim($ruta, '/')
            );
        }

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user')) {
    function current_user(): array
    {
        return [
            'id' => (int) (
                $_SESSION['usuario_id']
                ?? $_SESSION['id_usuario']
                ?? $_SESSION['user_id']
                ?? $_SESSION['id']
                ?? 0
            ),

            'nombre' => trim(
                (string) (
                    $_SESSION['nombre']
                    ?? $_SESSION['usuario']
                    ?? $_SESSION['nombre_usuario']
                    ?? ''
                )
            ),

            'correo' => trim(
                (string) (
                    $_SESSION['correo']
                    ?? $_SESSION['email']
                    ?? ''
                )
            ),

            'rol' => trim(
                (string) (
                    $_SESSION['rol']
                    ?? $_SESSION['nombre_rol']
                    ?? ''
                )
            ),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| SESIÓN ACTIVA
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        $usuario = current_user();

        return (int) ($usuario['id'] ?? 0) > 0;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            auth_redirect('login.php?error=sesion');
        }
    }
}

/*
|--------------------------------------------------------------------------
| NORMALIZAR ROL
|--------------------------------------------------------------------------
*/

if (!function_exists('normalize_role')) {
    function normalize_role(string $rol): string
    {
        $rol = strtolower(trim($rol));

        $rol = strtr(
            $rol,
            [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
            ]
        );

        $rol = preg_replace('/\s+/', ' ', $rol) ?? $rol;

        return match ($rol) {
            'admin',
            'administrador',
            'administrator' => 'administrador',

            'medico',
            'medico veterinario',
            'veterinario' => 'veterinario',

            'recepcion',
            'recepcionista' => 'recepcionista',

            default => $rol,
        };
    }
}

if (!function_exists('current_role')) {
    function current_role(): string
    {
        $usuario = current_user();

        return normalize_role(
            (string) ($usuario['rol'] ?? '')
        );
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $rol = null): string
    {
        $rolNormalizado = normalize_role(
            $rol ?? current_role()
        );

        return match ($rolNormalizado) {
            'administrador' => 'Administrador',
            'veterinario' => 'Veterinario',
            'recepcionista' => 'Recepcionista',
            default => 'Usuario',
        };
    }
}

/*
|--------------------------------------------------------------------------
| MATRIZ DE PERMISOS
|--------------------------------------------------------------------------
*/

if (!function_exists('role_permissions')) {
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
                'usuarios.eliminar',

                'reportes.ver',

                'configuracion.ver',
                'configuracion.editar',
            ],

            'veterinario' => [
                'dashboard.ver',

                'clientes.ver',

                'mascotas.ver',
                'mascotas.crear',
                'mascotas.editar',

                'citas.ver',
                'citas.editar',

                'historias.ver',
                'historias.crear',
                'historias.editar',

                'inventario.ver',

                'reportes.ver',
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
                'citas.editar',
                'citas.eliminar',

                'historias.ver',

                'inventario.ver',
            ],
        ];
    }
}

if (!function_exists('current_permissions')) {
    function current_permissions(): array
    {
        $matriz = role_permissions();
        $rol = current_role();

        return $matriz[$rol] ?? [];
    }
}

if (!function_exists('can')) {
    function can(string $permiso): bool
    {
        if (!is_logged_in()) {
            return false;
        }

        return in_array(
            $permiso,
            current_permissions(),
            true
        );
    }
}

if (!function_exists('require_permission')) {
    function require_permission(string $permiso): void
    {
        require_login();

        if (!can($permiso)) {
            auth_redirect('panel.php?error=sin_permiso');
        }
    }
}

/*
|--------------------------------------------------------------------------
| COMPATIBILIDAD CON ARCHIVOS ANTIGUOS: require_role()
|--------------------------------------------------------------------------
|
| Esto evita el error:
| Call to undefined function require_role()
|
*/

if (!function_exists('has_role')) {
    function has_role(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $rolActual = current_role();

        foreach ($roles as $rol) {
            if ($rolActual === normalize_role((string) $rol)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('require_role')) {
    function require_role(string|array $roles): void
    {
        require_login();

        if (!has_role($roles)) {
            auth_redirect('panel.php?error=sin_permiso');
        }
    }
}