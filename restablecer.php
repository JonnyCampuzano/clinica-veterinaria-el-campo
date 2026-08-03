<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Error: config/conexion.php debe crear una conexión PDO llamada $pdo.');
}

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_restablecer'])) {
    $_SESSION['csrf_restablecer'] = bin2hex(random_bytes(32));
}

$error = '';
$tokenValido = false;
$correoUsuario = '';

$token = trim(
    $_POST['token']
    ?? $_GET['token']
    ?? ''
);

/*
|--------------------------------------------------------------------------
| Función para validar el formato del token
|--------------------------------------------------------------------------
*/

function tokenTieneFormatoValido(string $token): bool
{
    return strlen($token) === 64 && ctype_xdigit($token);
}

/*
|--------------------------------------------------------------------------
| Buscar el token
|--------------------------------------------------------------------------
*/

if (tokenTieneFormatoValido($token)) {
    try {
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare(
            'SELECT correo
             FROM usuarios
             WHERE reset_token_hash = ?
             AND reset_expira IS NOT NULL
             AND reset_expira > NOW()
             LIMIT 1'
        );

        $stmt->execute([$tokenHash]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $tokenValido = true;
            $correoUsuario = (string) $usuario['correo'];
        }
    } catch (PDOException $e) {
        $error = 'No se pudo comprobar el enlace de recuperación.';
    }
}

/*
|--------------------------------------------------------------------------
| Procesar nueva contraseña
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';

    $contrasena = $_POST['contrasena'] ?? '';
    $confirmarContrasena = $_POST['confirmar_contrasena'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_restablecer'], $csrf)
    ) {
        $error = 'La solicitud no es válida. Recarga la página.';
    } elseif (!$tokenValido) {
        $error = 'El enlace es inválido o ya ha caducado.';
    } elseif (strlen($contrasena) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($contrasena !== $confirmarContrasena) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            /*
            |--------------------------------------------------------------------------
            | Crear contraseña segura
            |--------------------------------------------------------------------------
            */

            $passwordHash = password_hash(
                $contrasena,
                PASSWORD_DEFAULT
            );

            $tokenHash = hash('sha256', $token);

            /*
            |--------------------------------------------------------------------------
            | Actualizar contraseña
            |--------------------------------------------------------------------------
            | También eliminamos el token para que no pueda volver a utilizarse.
            */

            $actualizar = $pdo->prepare(
                'UPDATE usuarios
                 SET contrasena = ?,
                     reset_token_hash = NULL,
                     reset_expira = NULL
                 WHERE reset_token_hash = ?
                 AND reset_expira > NOW()'
            );

            $actualizar->execute([
                $passwordHash,
                $tokenHash
            ]);

            if ($actualizar->rowCount() === 1) {
                unset($_SESSION['csrf_restablecer']);

                header(
                    'Location: login.php?contrasena_actualizada=1'
                );

                exit;
            }

            $error = 'El enlace ya no está disponible o ha caducado.';
            $tokenValido = false;
        } catch (PDOException $e) {
            $error = 'No se pudo actualizar la contraseña.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Restablecer contraseña</title>

    <link
        rel="stylesheet"
        href="assets/css/recuperacion.css"
    >
</head>

<body>

<div class="auth-page">

    <section class="auth-card">

        <div class="auth-icon">
            🔑
        </div>

        <h1>Nueva contraseña</h1>

        <?php if ($error !== ''): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($tokenValido): ?>

            <p class="auth-description">
                Escribe una nueva contraseña para la cuenta:
            </p>

            <div class="account-email">
                <?= htmlspecialchars(
                    $correoUsuario,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <form method="post" autocomplete="off">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_restablecer'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars(
                        $token,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="form-group">

                    <label for="contrasena">
                        Nueva contraseña
                    </label>

                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        placeholder="Mínimo 8 caracteres"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="confirmar_contrasena">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        id="confirmar_contrasena"
                        name="confirmar_contrasena"
                        placeholder="Repite la contraseña"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button type="submit" class="btn-primary">
                    Guardar nueva contraseña
                </button>

            </form>

        <?php else: ?>

            <?php if ($error === ''): ?>

                <div class="alert alert-error">
                    El enlace de recuperación es inválido o ha caducado.
                </div>

            <?php endif; ?>

            <a
                href="recuperar_contrasena.php"
                class="btn-reset"
            >
                Solicitar otro enlace
            </a>

        <?php endif; ?>

        <a href="login.php" class="back-link">
            ← Volver al inicio de sesión
        </a>

    </section>

</div>

</body>

</html>