<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CAMBIA ESTO POR EL NOMBRE EXACTO DE TU CARPETA EN htdocs
|--------------------------------------------------------------------------
*/

define('BASE_URL', '/clinica_veterinaria_el_campo');
define('APP_URL', BASE_URL);

/*
|--------------------------------------------------------------------------
| FUNCIÓN url() — genera rutas absolutas dentro del proyecto
|--------------------------------------------------------------------------
*/

if (!function_exists('url')) {
    function url(string $ruta = ''): string
    {
        $base = rtrim(BASE_URL, '/');
        $ruta = ltrim($ruta, '/');

        if ($ruta === '') {
            return $base . '/';
        }

        return $base . '/' . $ruta;
    }
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN redirect() — redirige usando url()
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect')) {
    function redirect(string $ruta): never
    {
        header('Location: ' . url($ruta));
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN e() — escapa texto para HTML (evita XSS)
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) ($valor ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}