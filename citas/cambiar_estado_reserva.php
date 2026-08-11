<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CONEXIÓN
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__)
    . '/config/conexion.php';


/*
|--------------------------------------------------------------------------
| VALIDAR LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['rol'])
) {

    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDAR ROL
|--------------------------------------------------------------------------
*/

$rol = strtolower(
    trim(
        (string) $_SESSION['rol']
    )
);

$rol = strtr(
    $rol,
    [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u'
    ]
);


/*
|--------------------------------------------------------------------------
| SOLO ADMINISTRADOR O RECEPCIONISTA
|--------------------------------------------------------------------------
*/

$rolesPermitidos = [
    'administrador',
    'admin',
    'recepcionista'
];


if (
    !in_array(
        $rol,
        $rolesPermitidos,
        true
    )
) {

    header('Location: ../panel.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| SOLO POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| DATOS
|--------------------------------------------------------------------------
*/

$id = (int) (
    $_POST['id']
    ?? 0
);

$estado = trim(
    (string) (
        $_POST['estado']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| VALIDAR
|--------------------------------------------------------------------------
*/

$estadosPermitidos = [
    'Confirmada',
    'Cancelada'
];


if (
    $id <= 0 ||
    !in_array(
        $estado,
        $estadosPermitidos,
        true
    )
) {

    $_SESSION['mensaje_cita_error'] =
        'La solicitud no es válida.';

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR ESTADO
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        UPDATE reservas_citas

        SET estado = :estado

        WHERE id = :id
    ";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute([

        ':estado' =>
            $estado,

        ':id' =>
            $id

    ]);


    if ($stmt->rowCount() > 0) {

        $_SESSION['mensaje_cita_exito'] =
            'La cita fue actualizada correctamente.';

    } else {

        $_SESSION['mensaje_cita_error'] =
            'No se encontró la solicitud.';
    }


} catch (PDOException $e) {

    error_log(
        'Error actualizando reserva: ' .
        $e->getMessage()
    );


    $_SESSION['mensaje_cita_error'] =
        'No fue posible actualizar la cita.';
}


/*
|--------------------------------------------------------------------------
| REGRESAR
|--------------------------------------------------------------------------
*/

header('Location: index.php');
exit;