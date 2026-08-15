<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirmarPassword = $_POST['confirmar_password'] ?? '';


// Validaciones
if (
    $token === '' ||
    $password === '' ||
    $confirmarPassword === ''
) {
    exit('Faltan datos obligatorios.');
}


if (strlen($password) < 8) {
    exit('La contraseña debe tener mínimo 8 caracteres.');
}


if ($password !== $confirmarPassword) {
    exit('Las contraseñas no coinciden.');
}


// Convertir token recibido a hash
$tokenHash = hash('sha256', $token);


try {

    $pdo->beginTransaction();


    // Buscar recuperación
    $stmt = $pdo->prepare("
        SELECT
            id,
            usuario_id,
            expira,
            usado
        FROM recuperacion_password
        WHERE token = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$tokenHash]);

    $recuperacion = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$recuperacion) {

        $pdo->rollBack();

        exit('El enlace de recuperación no es válido.');
    }


    if ((int)$recuperacion['usado'] === 1) {

        $pdo->rollBack();

        exit('Este enlace ya fue utilizado.');
    }


    if (strtotime($recuperacion['expira']) < time()) {

        $pdo->rollBack();

        exit('El enlace de recuperación ha expirado.');
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR HASH DE LA NUEVA CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET password = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $passwordHash,
        $recuperacion['usuario_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | MARCAR TOKEN COMO UTILIZADO
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE recuperacion_password
        SET usado = 1
        WHERE id = ?
    ");

    $stmt->execute([
        $recuperacion['id']
    ]);


    $pdo->commit();


    $_SESSION['mensaje_login'] =
        'Contraseña actualizada correctamente. '
        . 'Ya puedes iniciar sesión.';


    header('Location: login.php');
    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit(
        'Ocurrió un error al cambiar la contraseña.'
    );
}