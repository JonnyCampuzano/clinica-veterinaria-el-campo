<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RUTA PRINCIPAL DEL PROYECTO
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__);

/*
|--------------------------------------------------------------------------
| CARGAR ARCHIVOS NECESARIOS
|--------------------------------------------------------------------------
*/

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

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
| PROTEGER LA PÁGINA
|--------------------------------------------------------------------------
*/

if (function_exists('require_login')) {
    require_login();
} elseif (
    empty($_SESSION['usuario_id']) &&
    empty($_SESSION['id_usuario']) &&
    empty($_SESSION['usuario'])
) {
    redirect('login.php');
}

/*
|--------------------------------------------------------------------------
| SOLO PERMITIR SOLICITUD POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('mascotas/index.php?msg=metodo_invalido');
}

/*
|--------------------------------------------------------------------------
| VERIFICAR TOKEN DE SEGURIDAD CSRF
|--------------------------------------------------------------------------
*/

verify_csrf(
    (string) ($_POST['csrf_token'] ?? '')
);

/*
|--------------------------------------------------------------------------
| OBTENER Y VALIDAR EL ID
|--------------------------------------------------------------------------
| Se aceptan los nombres "id" y "mascota_id" para evitar incompatibilidades.
*/

$idRecibido = $_POST['id']
    ?? $_POST['mascota_id']
    ?? null;

$idMascota = filter_var(
    $idRecibido,
    FILTER_VALIDATE_INT
);

if ($idMascota === false || $idMascota === null || $idMascota <= 0) {
    redirect('mascotas/index.php?msg=id_invalido');
}

/*
|--------------------------------------------------------------------------
| VERIFICAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        'Error: config/conexion.php debe crear una conexión PDO llamada $pdo.'
    );
}

/*
|--------------------------------------------------------------------------
| ELIMINAR MASCOTA
|--------------------------------------------------------------------------
*/

try {
    /*
    | Comprobar que la mascota exista.
    */

    $verificar = $pdo->prepare(
        'SELECT id
         FROM mascotas
         WHERE id = :id
         LIMIT 1'
    );

    $verificar->execute([
        ':id' => $idMascota,
    ]);

    $mascotaExiste = $verificar->fetchColumn();

    if (!$mascotaExiste) {
        regenerate_csrf();

        redirect('mascotas/index.php?msg=no_encontrada');
    }

    /*
    | Eliminar la mascota.
    */

    $eliminar = $pdo->prepare(
        'DELETE FROM mascotas
         WHERE id = :id'
    );

    $eliminar->execute([
        ':id' => $idMascota,
    ]);

    /*
    | Renovar el token después de realizar la operación.
    */

    regenerate_csrf();

    redirect('mascotas/index.php?msg=eliminada');

} catch (PDOException $e) {
    error_log(
        'Error eliminando mascota con ID '
        . $idMascota
        . ': '
        . $e->getMessage()
    );

    regenerate_csrf();

    redirect('mascotas/index.php?msg=error_eliminar');
}
