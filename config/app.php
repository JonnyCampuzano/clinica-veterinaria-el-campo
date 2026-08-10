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
| CONFIGURACIÓN GENERAL
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| El nombre de BASE_URL debe coincidir exactamente con el nombre
| de la carpeta de tu proyecto dentro de:
|
| C:\xampp\htdocs\
|
| Ejemplo:
| C:\xampp\htdocs\clinica_veterinaria_el_campo\
|
*/

if (!defined('APP_NAME')) {
    define(
        'APP_NAME',
        'Clínica Veterinaria El Campo'
    );
}

if (!defined('BASE_URL')) {
    define(
        'BASE_URL',
        '/clinica_veterinaria_el_campo'
    );
}

if (!defined('APP_URL')) {
    define(
        'APP_URL',
        BASE_URL
    );
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN url()
|--------------------------------------------------------------------------
|
| Genera URLs absolutas dentro del proyecto.
|
| Ejemplos:
|
| url('panel.php')
|
| Resultado:
| /clinica_veterinaria_el_campo/panel.php
|
| url('reportes/index.php')
|
| Resultado:
| /clinica_veterinaria_el_campo/reportes/index.php
|
*/

if (!function_exists('url')) {

    function url(string $ruta = ''): string
    {
        $base = rtrim(APP_URL, '/');
        $ruta = ltrim($ruta, '/');

        if ($ruta === '') {
            return $base . '/';
        }

        return $base . '/' . $ruta;
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN redirect()
|--------------------------------------------------------------------------
|
| Permite redireccionar correctamente dentro del proyecto.
|
| Ejemplo:
|
| redirect('panel.php');
|
*/

if (!function_exists('redirect')) {

    function redirect(string $ruta): never
    {
        header(
            'Location: ' . url($ruta)
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN e()
|--------------------------------------------------------------------------
|
| Escapa texto antes de mostrarlo en HTML.
| Ayuda a evitar problemas de XSS.
|
| Ejemplo:
|
| <?= e($nombreUsuario) ?>
|
*/

if (!function_exists('e')) {

    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) ($valor ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}