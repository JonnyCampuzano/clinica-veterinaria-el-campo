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
         * Si existe la función url() del proyecto,
         * se utiliza automáticamente.
         */
        if (function_exists('url')) {

            header('Location: ' . url($ruta));

        } else {

            /*
             * Ruta base del proyecto en XAMPP.
             *
             * Si tu carpeta tiene otro nombre,
             * cambia clinica_veterinaria_el_campo.
             */
            $baseUrl = '/clinica_veterinaria_el_campo/';

            header(
                'Location: '
                . $baseUrl
                . ltrim($ruta, '/')
            );
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SI EXISTE SESIÓN
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {

    function is_logged_in(): bool
    {
        $id = (int) (
            $_SESSION['usuario_id']
            ?? $_SESSION['id_usuario']
            ?? $_SESSION['user_id']
            ?? $_SESSION['id']
            ?? 0
        );

        return $id > 0;
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

            /*
             * El usuario no tiene una sesión válida.
             */
            auth_redirect('login.php');
        }
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

            'rol' => current_role(),

            'estado' => trim(
                (string) (
                    $_SESSION['estado']
                    ?? ''
                )
            )
        ];
    }
}


/*
|--------------------------------------------------------------------------
| NORMALIZAR TEXTO
|--------------------------------------------------------------------------
*/

if (!function_exists('normalize_role_text')) {

    function normalize_role_text(string $texto): string
    {
        $texto = trim($texto);

        /*
         * Convertir a minúsculas.
         */
        if (function_exists('mb_strtolower')) {

            $texto = mb_strtolower(
                $texto,
                'UTF-8'
            );

        } else {

            $texto = strtolower($texto);
        }

        /*
         * Eliminar tildes.
         */
        $texto = str_replace(
            [
                'á',
                'é',
                'í',
                'ó',
                'ú',
                'ü',
                'ñ',
                'Á',
                'É',
                'Í',
                'Ó',
                'Ú',
                'Ü',
                'Ñ'
            ],
            [
                'a',
                'e',
                'i',
                'o',
                'u',
                'u',
                'n',
                'a',
                'e',
                'i',
                'o',
                'u',
                'u',
                'n'
            ],
            $texto
        );

        /*
         * Quitar espacios dobles.
         */
        $texto = preg_replace(
            '/\s+/',
            ' ',
            $texto
        ) ?? $texto;

        return trim($texto);
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
        $rol = (string) (
            $_SESSION['rol']
            ?? $_SESSION['role']
            ?? ''
        );

        $rol = normalize_role_text($rol);

        return match ($rol) {

            /*
             * ADMINISTRADOR
             */
            'administrador',
            'admin',
            'administrator'
                => 'administrador',


            /*
             * MÉDICO VETERINARIO
             */
            'medico',
            'medico veterinario',
            'veterinario',
            'doctor',
            'doctor veterinario'
                => 'medico',


            /*
             * RECEPCIONISTA
             */
            'recepcionista',
            'recepcion'
                => 'recepcionista',


            /*
             * CUALQUIER OTRO
             */
            default
                => 'usuario'
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
        if ($rol === null || trim($rol) === '') {
            $rol = current_role();
        }

        $rol = normalize_role_text($rol);

        return match ($rol) {

            'administrador'
                => 'Administrador',

            'medico'
                => 'Médico Veterinario',

            'recepcionista'
                => 'Recepcionista',

            default
                => 'Usuario'
        };
    }
}


/*
|--------------------------------------------------------------------------
| PERMISOS POR ROL
|--------------------------------------------------------------------------
|
| ADMINISTRADOR
| - Acceso completo.
|
| MÉDICO VETERINARIO
| - Clientes.
| - Mascotas.
| - Citas.
| - Historias clínicas.
| - Inventario en consulta.
| - Reportes relacionados.
|
| RECEPCIONISTA
| - Clientes.
| - Mascotas.
| - Citas.
| - Inventario básico.
|
|--------------------------------------------------------------------------
*/

if (!function_exists('role_permissions')) {

    function role_permissions(
        ?string $rol = null
    ): array {

        if ($rol === null || trim($rol) === '') {
            $rol = current_role();
        }

        $rol = normalize_role_text($rol);


        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR
        |--------------------------------------------------------------------------
        */

        $administrador = [

            /*
             * Dashboard
             */
            'dashboard.ver',

            /*
             * Clientes
             */
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
            'clientes.eliminar',

            /*
             * Mascotas
             */
            'mascotas.ver',
            'mascotas.crear',
            'mascotas.editar',
            'mascotas.eliminar',

            /*
             * Citas
             */
            'citas.ver',
            'citas.crear',
            'citas.editar',
            'citas.eliminar',

            /*
             * Historias clínicas
             */
            'historias.ver',
            'historias.crear',
            'historias.editar',
            'historias.eliminar',

            /*
             * Inventario
             */
            'inventario.ver',
            'inventario.crear',
            'inventario.editar',
            'inventario.eliminar',

            /*
             * Reportes
             */
            'reportes.ver',
            'reportes.crear',
            'reportes.exportar',

            /*
             * Usuarios
             */
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',

            /*
             * Configuración
             */
            'configuracion.ver',
            'configuracion.editar'
        ];


        /*
        |--------------------------------------------------------------------------
        | MÉDICO VETERINARIO
        |--------------------------------------------------------------------------
        */

        $medico = [

            /*
             * Dashboard
             */
            'dashboard.ver',

            /*
             * Clientes
             */
            'clientes.ver',

            /*
             * Mascotas
             */
            'mascotas.ver',
            'mascotas.crear',
            'mascotas.editar',

            /*
             * Citas
             */
            'citas.ver',
            'citas.editar',

            /*
             * Historias clínicas
             */
            'historias.ver',
            'historias.crear',
            'historias.editar',

            /*
             * Inventario
             */
            'inventario.ver',

            /*
             * Reportes
             */
            'reportes.ver'
        ];


        /*
        |--------------------------------------------------------------------------
        | RECEPCIONISTA
        |--------------------------------------------------------------------------
        */

        $recepcionista = [

            /*
             * Dashboard
             */
            'dashboard.ver',

            /*
             * Clientes
             */
            'clientes.ver',
            'clientes.crear',
            'clientes.editar',

            /*
             * Mascotas
             */
            'mascotas.ver',
            'mascotas.crear',
            'mascotas.editar',

            /*
             * Citas
             */
            'citas.ver',
            'citas.crear',
            'citas.editar',
            'citas.eliminar',

            /*
             * Inventario
             */
            'inventario.ver'
        ];


        /*
        |--------------------------------------------------------------------------
        | DEVOLVER PERMISOS
        |--------------------------------------------------------------------------
        */

        return match ($rol) {

            'administrador'
                => $administrador,

            'medico'
                => $medico,

            'recepcionista'
                => $recepcionista,

            default
                => []
        };
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR PERMISO
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| if (can('clientes.ver')) {
|     echo 'Puede ver clientes';
| }
|
|--------------------------------------------------------------------------
*/

if (!function_exists('can')) {

    function can(string $permiso): bool
    {
        /*
         * Debe existir sesión.
         */
        if (!is_logged_in()) {
            return false;
        }

        $permiso = trim($permiso);

        if ($permiso === '') {
            return false;
        }

        $rol = current_role();

        /*
         * Administrador siempre tiene acceso completo.
         */
        if ($rol === 'administrador') {
            return true;
        }

        $permisos = role_permissions($rol);

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
|
| Ejemplo:
|
| require_permission('clientes.ver');
|
|--------------------------------------------------------------------------
*/

if (!function_exists('require_permission')) {

    function require_permission(
        string $permiso
    ): void {

        /*
         * Primero verificar sesión.
         */
        require_login();

        /*
         * Después verificar permiso.
         */
        if (!can($permiso)) {

            /*
             * Guardamos mensaje opcional.
             */
            $_SESSION['mensaje_error'] =
                'No tienes permiso para acceder a esta sección.';

            /*
             * Enviar al dashboard.
             */
            auth_redirect('panel.php');
        }
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SI TIENE UNO DE VARIOS PERMISOS
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| can_any([
|     'clientes.ver',
|     'mascotas.ver'
| ]);
|
|--------------------------------------------------------------------------
*/

if (!function_exists('can_any')) {

    function can_any(array $permisos): bool
    {
        foreach ($permisos as $permiso) {

            if (can((string) $permiso)) {
                return true;
            }
        }

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SI TIENE TODOS LOS PERMISOS
|--------------------------------------------------------------------------
*/

if (!function_exists('can_all')) {

    function can_all(array $permisos): bool
    {
        if (empty($permisos)) {
            return false;
        }

        foreach ($permisos as $permiso) {

            if (!can((string) $permiso)) {
                return false;
            }
        }

        return true;
    }
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ROL
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| if (has_role('administrador')) {
|     ...
| }
|
|--------------------------------------------------------------------------
*/

if (!function_exists('has_role')) {

    function has_role(string $rol): bool
    {
        $rol = normalize_role_text($rol);

        /*
         * Normalizar nombres alternativos.
         */
        $rolNormalizado = match ($rol) {

            'administrador',
            'admin'
                => 'administrador',

            'medico',
            'medico veterinario',
            'veterinario'
                => 'medico',

            'recepcionista',
            'recepcion'
                => 'recepcionista',

            default
                => $rol
        };

        return current_role() === $rolNormalizado;
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR ROL
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| require_role('administrador');
|
|--------------------------------------------------------------------------
*/

if (!function_exists('require_role')) {

    function require_role(string $rol): void
    {
        require_login();

        if (!has_role($rol)) {

            $_SESSION['mensaje_error'] =
                'No tienes autorización para acceder a esta sección.';

            auth_redirect('panel.php');
        }
    }
}


/*
|--------------------------------------------------------------------------
| EXIGIR UNO DE VARIOS ROLES
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| require_any_role([
|     'administrador',
|     'medico'
| ]);
|
|--------------------------------------------------------------------------
*/

if (!function_exists('require_any_role')) {

    function require_any_role(
        array $roles
    ): void {

        require_login();

        foreach ($roles as $rol) {

            if (has_role((string) $rol)) {
                return;
            }
        }

        $_SESSION['mensaje_error'] =
            'No tienes autorización para acceder a esta sección.';

        auth_redirect('panel.php');
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER NOMBRE DEL USUARIO
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_name')) {

    function current_user_name(): string
    {
        $usuario = current_user();

        $nombre = trim(
            (string) ($usuario['nombre'] ?? '')
        );

        return $nombre !== ''
            ? $nombre
            : 'Usuario';
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER ID DEL USUARIO
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_id')) {

    function current_user_id(): int
    {
        $usuario = current_user();

        return (int) (
            $usuario['id']
            ?? 0
        );
    }
}


/*
|--------------------------------------------------------------------------
| OBTENER CORREO DEL USUARIO
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_email')) {

    function current_user_email(): string
    {
        $usuario = current_user();

        return trim(
            (string) (
                $usuario['correo']
                ?? ''
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| CERRAR SESIÓN
|--------------------------------------------------------------------------
*/

if (!function_exists('logout_user')) {

    function logout_user(): void
    {
        /*
         * Vaciar variables de sesión.
         */
        $_SESSION = [];

        /*
         * Eliminar cookie de sesión.
         */
        if (
            ini_get('session.use_cookies')
        ) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        /*
         * Destruir sesión.
         */
        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {
            session_destroy();
        }
    }
}