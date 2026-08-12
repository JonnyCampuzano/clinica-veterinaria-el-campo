<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN, ROLES Y PERMISOS
|--------------------------------------------------------------------------
| Clínica Veterinaria El Campo
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| INICIAR SESIÓN
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

    function auth_redirect(string $ruta): void
    {
        /*
        |------------------------------------------------------------------
        | Si existe la función url() del archivo config/app.php
        | la utilizamos.
        |------------------------------------------------------------------
        */

        if (function_exists('url')) {

            header('Location: ' . url($ruta));
            exit;
        }

        /*
        |------------------------------------------------------------------
        | Ruta alternativa
        |------------------------------------------------------------------
        */

        $baseUrl = '/clinica_veterinaria_el_campo';

        header(
            'Location: ' .
            $baseUrl . '/' .
            ltrim($ruta, '/')
        );

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
                    ?? $_SESSION['usuario_nombre']
                    ?? $_SESSION['name']
                    ?? ''
                )
            ),

            /*
            |--------------------------------------------------------------
            | Tu base de datos utiliza EMAIL
            |--------------------------------------------------------------
            */

            'email' => trim(
                (string) (
                    $_SESSION['email']
                    ?? $_SESSION['correo']
                    ?? ''
                )
            ),

            'rol' => trim(
                (string) (
                    $_SESSION['rol']
                    ?? $_SESSION['role']
                    ?? ''
                )
            ),

            'estado' => trim(
                (string) (
                    $_SESSION['estado']
                    ?? 'Activo'
                )
            )
        ];
    }
}


/*
|--------------------------------------------------------------------------
| COMPROBAR SI HAY SESIÓN
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {

    function is_logged_in(): bool
    {
        $usuario = current_user();

        return $usuario['id'] > 0;
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR INICIO DE SESIÓN
|--------------------------------------------------------------------------
*/

if (!function_exists('require_login')) {

    function require_login(): void
    {
        if (!is_logged_in()) {

            $_SESSION['error_login'] =
                'Debes iniciar sesión para continuar.';

            auth_redirect('login.php');
        }
    }
}


/*
|--------------------------------------------------------------------------
| NORMALIZAR TEXTO
|--------------------------------------------------------------------------
*/

if (!function_exists('normalize_role')) {

    function normalize_role(string $rol): string
    {
        $rol = trim($rol);

        /*
        |------------------------------------------------------------------
        | Pasar a minúsculas
        |------------------------------------------------------------------
        */

        if (function_exists('mb_strtolower')) {

            $rol = mb_strtolower(
                $rol,
                'UTF-8'
            );

        } else {

            $rol = strtolower($rol);
        }


        /*
        |------------------------------------------------------------------
        | Eliminar tildes
        |------------------------------------------------------------------
        */

        $rol = strtr(
            $rol,
            [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n'
            ]
        );


        /*
        |------------------------------------------------------------------
        | Eliminar espacios repetidos
        |------------------------------------------------------------------
        */

        $rol = preg_replace(
            '/\s+/',
            ' ',
            $rol
        ) ?? $rol;


        return trim($rol);
    }
}


/*
|--------------------------------------------------------------------------
| ROL ACTUAL
|--------------------------------------------------------------------------
*/

if (!function_exists('current_role')) {

    function current_role(): string
    {
        $usuario = current_user();

        $rol = normalize_role(
            (string) $usuario['rol']
        );


        return match ($rol) {

            /*
            |--------------------------------------------------------------
            | ADMINISTRADOR
            |--------------------------------------------------------------
            */

            'administrador',
            'admin' => 'administrador',


            /*
            |--------------------------------------------------------------
            | RECEPCIONISTA
            |--------------------------------------------------------------
            */

            'recepcionista',
            'recepcion' => 'recepcionista',


            /*
            |--------------------------------------------------------------
            | MÉDICO VETERINARIO
            |--------------------------------------------------------------
            */

            'medico',
            'medico veterinario',
            'veterinario',
            'doctor',
            'doctora' => 'medico',


            /*
            |--------------------------------------------------------------
            | CLIENTE
            |--------------------------------------------------------------
            */

            'cliente',
            'usuario',
            'usuario publico' => 'cliente',


            /*
            |--------------------------------------------------------------
            | ROL NO RECONOCIDO
            |--------------------------------------------------------------
            */

            default => 'sin_rol'
        };
    }
}


/*
|--------------------------------------------------------------------------
| NOMBRE DEL ROL PARA MOSTRAR EN PANTALLA
|--------------------------------------------------------------------------
*/

if (!function_exists('role_label')) {

    function role_label(?string $rol = null): string
    {
        $rol = $rol ?? current_role();

        return match ($rol) {

            'administrador' =>
                'Administrador',

            'recepcionista' =>
                'Recepcionista',

            'medico' =>
                'Médico Veterinario',

            'cliente' =>
                'Cliente',

            default =>
                'Sin rol'
        };
    }
}


/*
|--------------------------------------------------------------------------
| PERMISOS POR ROL
|--------------------------------------------------------------------------
*/

if (!function_exists('role_permissions')) {

    function role_permissions(
        ?string $rol = null
    ): array {

        $rol = $rol ?? current_role();


        /*
        |------------------------------------------------------------------
        | ADMINISTRADOR
        | Tiene acceso completo
        |------------------------------------------------------------------
        */

        if ($rol === 'administrador') {

            return [

                /*
                | Dashboard
                */

                'dashboard.ver',


                /*
                | Clientes
                */

                'clientes.ver',
                'clientes.crear',
                'clientes.editar',
                'clientes.eliminar',


                /*
                | Mascotas
                */

                'mascotas.ver',
                'mascotas.crear',
                'mascotas.editar',
                'mascotas.eliminar',


                /*
                | Citas
                */

                'citas.ver',
                'citas.crear',
                'citas.editar',
                'citas.eliminar',


                /*
                | Reservas públicas
                */

                'reservas.ver',
                'reservas.crear',
                'reservas.editar',
                'reservas.confirmar',
                'reservas.cancelar',
                'reservas.eliminar',


                /*
                | Historias clínicas
                */

                'historias.ver',
                'historias.crear',
                'historias.editar',
                'historias.eliminar',


                /*
                | Inventario
                */

                'inventario.ver',
                'inventario.crear',
                'inventario.editar',
                'inventario.eliminar',


                /*
                | Reportes
                */

                'reportes.ver',
                'reportes.generar',


                /*
                | Usuarios
                */

                'usuarios.ver',
                'usuarios.crear',
                'usuarios.editar',
                'usuarios.eliminar',
                'usuarios.roles',


                /*
                | Configuración
                */

                'configuracion.ver',
                'configuracion.editar'
            ];
        }


        /*
        |------------------------------------------------------------------
        | RECEPCIONISTA
        |------------------------------------------------------------------
        */

        if ($rol === 'recepcionista') {

            return [

                'dashboard.ver',

                /*
                | Clientes
                */

                'clientes.ver',
                'clientes.crear',
                'clientes.editar',


                /*
                | Mascotas
                */

                'mascotas.ver',
                'mascotas.crear',
                'mascotas.editar',


                /*
                | Citas
                */

                'citas.ver',
                'citas.crear',
                'citas.editar',


                /*
                | Reservas
                */

                'reservas.ver',
                'reservas.crear',
                'reservas.editar',
                'reservas.confirmar',
                'reservas.cancelar',


                /*
                | Historia clínica
                | Solo consulta
                */

                'historias.ver',


                /*
                | Inventario
                | Solo consulta
                */

                'inventario.ver'
            ];
        }


        /*
        |------------------------------------------------------------------
        | MÉDICO VETERINARIO
        |------------------------------------------------------------------
        */

        if ($rol === 'medico') {

            return [

                'dashboard.ver',

                /*
                | Clientes
                */

                'clientes.ver',


                /*
                | Mascotas
                */

                'mascotas.ver',
                'mascotas.editar',


                /*
                | Citas
                */

                'citas.ver',
                'citas.editar',


                /*
                | Historias clínicas
                */

                'historias.ver',
                'historias.crear',
                'historias.editar',


                /*
                | Inventario
                */

                'inventario.ver'
            ];
        }


        /*
        |------------------------------------------------------------------
        | CLIENTE
        |
        | El cliente NO entra a administración.
        | Solo puede manejar sus reservas.
        |------------------------------------------------------------------
        */

        if ($rol === 'cliente') {

            return [

                'reservas.crear',
                'reservas.ver_propias',
                'reservas.editar_propias',
                'reservas.cancelar_propias'
            ];
        }


        /*
        |------------------------------------------------------------------
        | SIN ROL
        |------------------------------------------------------------------
        */

        return [];
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR PERMISO
|--------------------------------------------------------------------------
*/

if (!function_exists('can')) {

    function can(string $permiso): bool
    {
        if (!is_logged_in()) {
            return false;
        }

        $permisos = role_permissions();

        return in_array(
            $permiso,
            $permisos,
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR PERMISO
|--------------------------------------------------------------------------
*/

if (!function_exists('require_permission')) {

    function require_permission(
        string $permiso
    ): void {

        /*
        |------------------------------------------------------------------
        | Primero verificamos sesión
        |------------------------------------------------------------------
        */

        require_login();


        /*
        |------------------------------------------------------------------
        | Verificar rol
        |------------------------------------------------------------------
        */

        $rol = current_role();

        if ($rol === 'sin_rol') {

            http_response_code(403);

            exit(
                '<h2>Acceso denegado</h2>
                <p>Tu usuario no tiene un rol válido asignado.</p>
                <p>Contacta con el administrador del sistema.</p>'
            );
        }


        /*
        |------------------------------------------------------------------
        | Verificar permiso
        |------------------------------------------------------------------
        */

        if (!can($permiso)) {

            http_response_code(403);

            exit(
                '<h2>Acceso denegado</h2>
                <p>No tienes permisos para acceder a esta función.</p>
                <p><a href="javascript:history.back()">← Volver</a></p>'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR UN ROL ESPECÍFICO
|--------------------------------------------------------------------------
*/

if (!function_exists('has_role')) {

    function has_role(
        string|array $roles
    ): bool {

        $rolActual = current_role();

        if (is_string($roles)) {
            $roles = [$roles];
        }

        foreach ($roles as $rol) {

            if (
                $rolActual ===
                normalize_role((string) $rol)
            ) {
                return true;
            }
        }

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR UNO O VARIOS ROLES
|--------------------------------------------------------------------------
*/

if (!function_exists('require_role')) {

    function require_role(
        string|array $roles
    ): void {

        require_login();

        if (!has_role($roles)) {

            http_response_code(403);

            exit(
                '<h2>Acceso denegado</h2>
                <p>No tienes autorización para acceder a esta sección.</p>
                <p><a href="javascript:history.back()">← Volver</a></p>'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| SABER SI ES ADMINISTRADOR
|--------------------------------------------------------------------------
*/

if (!function_exists('is_admin')) {

    function is_admin(): bool
    {
        return current_role() ===
            'administrador';
    }
}


/*
|--------------------------------------------------------------------------
| SABER SI ES RECEPCIONISTA
|--------------------------------------------------------------------------
*/

if (!function_exists('is_receptionist')) {

    function is_receptionist(): bool
    {
        return current_role() ===
            'recepcionista';
    }
}


/*
|--------------------------------------------------------------------------
| SABER SI ES MÉDICO
|--------------------------------------------------------------------------
*/

if (!function_exists('is_veterinarian')) {

    function is_veterinarian(): bool
    {
        return current_role() ===
            'medico';
    }
}


/*
|--------------------------------------------------------------------------
| SABER SI ES CLIENTE
|--------------------------------------------------------------------------
*/

if (!function_exists('is_client')) {

    function is_client(): bool
    {
        return current_role() ===
            'cliente';
    }
}


/*
|--------------------------------------------------------------------------
| PÁGINA PRINCIPAL SEGÚN EL ROL
|--------------------------------------------------------------------------
*/

if (!function_exists('home_by_role')) {

    function home_by_role(): string
    {
        return match (current_role()) {

            /*
            |--------------------------------------------------------------
            | Personal de la clínica
            |--------------------------------------------------------------
            */

            'administrador',
            'recepcionista',
            'medico' =>
                'panel.php',


            /*
            |--------------------------------------------------------------
            | Cliente público
            |--------------------------------------------------------------
            */

            'cliente' =>
                'reservar.php',


            /*
            |--------------------------------------------------------------
            | Si no tiene rol
            |--------------------------------------------------------------
            */

            default =>
                'login.php'
        };
    }
}