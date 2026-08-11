<?php
declare(strict_types=1);

$raiz = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

require_login();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'No existe una conexión válida.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Solicitud no permitida.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

$idCliente = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

$tokenFormulario = (string) (
    $_POST['csrf_token'] ?? ''
);

$tokenSesion = (string) (
    $_SESSION['csrf_clientes'] ?? ''
);

if (
    $tokenFormulario === '' ||
    $tokenSesion === '' ||
    !hash_equals(
        $tokenSesion,
        $tokenFormulario
    )
) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' =>
            'La sesión del formulario expiró. Recarga la página.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

if (!$idCliente || $idCliente <= 0) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'El cliente solicitado no existe.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

try {
    $consulta = $pdo->prepare(
        'SELECT id, nombres, apellidos
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $idCliente
    ]);

    $cliente = $consulta->fetch();

    if (!$cliente) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'El cliente solicitado no existe.'
        ];

        header(
            'Location: ' .
            url('clientes/index.php')
        );

        exit;
    }

    /*
     * No elimina clientes que todavía tengan mascotas,
     * para evitar borrar información relacionada.
     */
    $consultarMascotas = $pdo->prepare(
        'SELECT COUNT(*)
         FROM mascotas
         WHERE cliente_id = :cliente_id'
    );

    $consultarMascotas->execute([
        'cliente_id' => $idCliente
    ]);

    $totalMascotas = (int) $consultarMascotas->fetchColumn();

    if ($totalMascotas > 0) {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' =>
                'No puedes eliminar este cliente porque tiene ' .
                $totalMascotas .
                ($totalMascotas === 1
                    ? ' mascota registrada.'
                    : ' mascotas registradas.')
        ];

        header(
            'Location: ' .
            url('clientes/index.php')
        );

        exit;
    }

    $eliminar = $pdo->prepare(
        'DELETE FROM clientes
         WHERE id = :id'
    );

    $eliminar->execute([
        'id' => $idCliente
    ]);

    if ($eliminar->rowCount() === 0) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'No se pudo eliminar el cliente.'
        ];
    } else {
        $_SESSION['csrf_clientes'] =
            bin2hex(random_bytes(32));

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Cliente eliminado correctamente.'
        ];
    }
} catch (PDOException $error) {
    error_log(
        'Error al eliminar cliente: ' .
        $error->getMessage()
    );

    if ($error->getCode() === '23000') {
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' =>
                'No se puede eliminar el cliente porque tiene información relacionada.'
        ];
    } else {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'No se pudo eliminar el cliente.'
        ];
    }
}

header(
    'Location: ' .
    url('clientes/index.php')
);

exit;