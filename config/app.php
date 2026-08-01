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

define('BASE_URL', '/Clinica_Veterinaria_El_Campo');

function url(string $ruta = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $ruta = ltrim($ruta, '/');

    if ($ruta === '') {
        return $base . '/';
    }

    return $base . '/' . $ruta;
}

function redirect(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

function e(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}