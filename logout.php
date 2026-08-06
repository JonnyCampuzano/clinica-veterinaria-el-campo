<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| LIMPIAR SESIÓN
|--------------------------------------------------------------------------
*/

$_SESSION = [];

/*
|--------------------------------------------------------------------------
| ELIMINAR COOKIE DE SESIÓN
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

session_destroy();

/*
|--------------------------------------------------------------------------
| REGRESAR AL LOGIN
|--------------------------------------------------------------------------
*/

if (function_exists('redirect')) {
    redirect('login.php?msg=sesion_cerrada');
}

header('Location: login.php?msg=sesion_cerrada');
exit;
