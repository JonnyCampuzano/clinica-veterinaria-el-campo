<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

/*
|--------------------------------------------------------------------------
| Comprobar conexión
|--------------------------------------------------------------------------
| El archivo config/conexion.php debe crear una variable llamada $pdo.
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Error: config/conexion.php debe crear una conexión PDO llamada $pdo.');
}

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_recuperacion'])) {
    $_SESSION['csrf_recuperacion'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$error = '';
$enlaceRecuperacion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $correo = trim($_POST['correo'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validar seguridad del formulario
    |--------------------------------------------------------------------------
    */

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['csrf_recuperacion'], $csrf)
    ) {
        $error = 'La solicitud no es válida. Recarga la página e inténtalo nuevamente.';
    } elseif ($correo === '') {
        $error = 'Ingresa tu correo electrónico.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } else {
        try {
            /*
            |--------------------------------------------------------------------------
            | Buscar usuario
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT correo
                 FROM usuarios
                 WHERE correo = ?
                 LIMIT 1'
            );

            $stmt->execute([$correo]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | Crear token si el usuario existe
            |--------------------------------------------------------------------------
            */

            if ($usuario) {
                // Token que recibirá el usuario.
                $token = bin2hex(random_bytes(32));

                // En la base de datos se almacena solamente el hash.
                $tokenHash = hash('sha256', $token);

                /*
                |--------------------------------------------------------------------------
                | Guardar token y fecha de expiración
                |--------------------------------------------------------------------------
                | El token tendrá una duración de una hora.
                */

                $actualizar = $pdo->prepare(
                    'UPDATE usuarios
                     SET reset_token_hash = ?,
                         reset_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                     WHERE correo = ?'
                );

                $actualizar->execute([
                    $tokenHash,
                    $correo
                ]);

                /*
                |--------------------------------------------------------------------------
                | Construir URL de recuperación automáticamente
                |--------------------------------------------------------------------------
                */

                $esHttps = isset($_SERVER['HTTPS'])
                    && $_SERVER['HTTPS'] !== ''
                    && $_SERVER['HTTPS'] !== 'off';

                $protocolo = $esHttps ? 'https' : 'http';

                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

                $directorio = str_replace(
                    '\\',
                    '/',
                    dirname($_SERVER['SCRIPT_NAME'] ?? '')
                );

                if ($directorio === '/' || $directorio === '.') {
                    $directorio = '';
                }

                $directorio = rtrim($directorio, '/');

                $enlaceRecuperacion =
                    $protocolo
                    . '://'
                    . $host
                    . $directorio
                    . '/restablecer.php?token='
                    . urlencode($token);
            }

            /*
            |--------------------------------------------------------------------------
            | Mensaje general
            |--------------------------------------------------------------------------
            | No indicamos directamente si el correo existe para evitar que otras
            | personas puedan descubrir qué correos están registrados.
            */

            $mensaje = 'Solicitud procesada correctamente.';

            // Generar un token CSRF nuevo.
            $_SESSION['csrf_recuperacion'] = bin2hex(random_bytes(32));
        } catch (PDOException $e) {
            $error = 'No se pudo procesar la recuperación de contraseña.';
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

    <title>Recuperar contraseña</title>

    <link
        rel="stylesheet"
        href="assets/css/recuperacion.css"
    >
</head>

<body>

<div class="auth-page">

    <section class="auth-card">

        <div class="auth-icon">
            🔐
        </div>

        <h1>Recuperar contraseña</h1>

        <p class="auth-description">
            Ingresa el correo registrado en el sistema para generar
            un enlace de recuperación.
        </p>

        <?php if ($error !== ''): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <?php if ($mensaje !== ''): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="post" autocomplete="off">

            <input
                type="hidden"
                name="csrf"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_recuperacion'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="form-group">

                <label for="correo">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="correo"
                    name="correo"
                    placeholder="ejemplo@correo.com"
                    value="<?= htmlspecialchars(
                        $_POST['correo'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <button type="submit" class="btn-primary">
                Generar enlace de recuperación
            </button>

        </form>

        <?php if ($enlaceRecuperacion !== ''): ?>

            <div class="development-box">

                <strong>
                    Enlace de recuperación para XAMPP
                </strong>

                <p>
                    Pulsa el siguiente botón para cambiar la contraseña.
                    El enlace caduca en una hora.
                </p>

                <a
                    href="<?= htmlspecialchars(
                        $enlaceRecuperacion,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="btn-reset"
                >
                    Restablecer contraseña
                </a>

                <small>
                    Este enlace se muestra directamente porque el proyecto
                    está ejecutándose localmente en XAMPP.
                </small>

            </div>

        <?php endif; ?>

        <a href="login.php" class="back-link">
            ← Volver al inicio de sesión
        </a>

    </section>

</div>

</body>

</html>