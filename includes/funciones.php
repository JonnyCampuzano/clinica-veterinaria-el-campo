<?php
declare(strict_types=1);

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
| ESCAPAR TEXTO HTML
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| CONSTRUIR URL DEL PROYECTO
|--------------------------------------------------------------------------
*/

if (!function_exists('url')) {
    function url(string $ruta = ''): string
    {
        if (defined('APP_URL')) {
            $base = rtrim((string) APP_URL, '/');
            return $base . ($ruta !== '' ? '/' . ltrim($ruta, '/') : '');
        }

        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $projectRoot = realpath(dirname(__DIR__));
        $base = '';

        if (
            $documentRoot !== false &&
            $projectRoot !== false &&
            str_starts_with(
                str_replace('\\', '/', $projectRoot),
                rtrim(str_replace('\\', '/', $documentRoot), '/')
            )
        ) {
            $relative = substr(
                str_replace('\\', '/', $projectRoot),
                strlen(rtrim(str_replace('\\', '/', $documentRoot), '/'))
            );

            $base = '/' . trim((string) $relative, '/');
        }

        return rtrim($base, '/')
            . ($ruta !== '' ? '/' . ltrim($ruta, '/') : '');
    }
}

/*
|--------------------------------------------------------------------------
| REDIRECCIONAR
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect')) {
    function redirect(string $ruta): never
    {
        $destino = preg_match('#^https?://#i', $ruta) || str_starts_with($ruta, '/')
            ? $ruta
            : url($ruta);

        header('Location: ' . $destino);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| GENERAR TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (
            empty($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token'])
        ) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

/*
|--------------------------------------------------------------------------
| CAMPO OCULTO CSRF
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . e(csrf_token())
            . '">';
    }
}

/*
|--------------------------------------------------------------------------
| VERIFICAR TOKEN CSRF
|--------------------------------------------------------------------------
| La función acepta un token enviado como argumento. Si se llama sin
| argumento, también puede leer csrf_token o csrf directamente desde POST.
*/

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token = null): void
    {
        $tokenRecibido = $token;

        if ($tokenRecibido === null) {
            $tokenRecibido = (string) (
                $_POST['csrf_token']
                ?? $_POST['csrf']
                ?? ''
            );
        }

        $tokenGuardado = (string) (
            $_SESSION['csrf_token']
            ?? ''
        );

        if (
            $tokenRecibido === '' ||
            $tokenGuardado === '' ||
            !hash_equals($tokenGuardado, $tokenRecibido)
        ) {
            http_response_code(419);

            exit(
                'La solicitud no es válida o ha expirado. '
                . 'Regresa al formulario, actualiza la página e inténtalo nuevamente.'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| RENOVAR TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (!function_exists('regenerate_csrf')) {
    function regenerate_csrf(): string
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

/*
|--------------------------------------------------------------------------
| RECUPERAR VALOR ANTERIOR DE FORMULARIO
|--------------------------------------------------------------------------
*/

if (!function_exists('old')) {
    function old(string $campo, string $predeterminado = ''): string
    {
        return trim((string) ($_POST[$campo] ?? $predeterminado));
    }
}