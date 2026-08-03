<?php
declare(strict_types=1);

$raiz = __DIR__;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

require_login();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No se encontró una conexión PDO válida.');
}

$idUsuario = (int) (
    $_SESSION['usuario_id']
    ?? $_SESSION['id_usuario']
    ?? 0
);

if ($idUsuario <= 0) {
    header('Location: ' . url('login.php'));
    exit;
}

if (empty($_SESSION['csrf_cambiar_password'])) {
    $_SESSION['csrf_cambiar_password'] = bin2hex(
        random_bytes(32)
    );
}

$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrasenaActual = (string) (
        $_POST['contrasena_actual'] ?? ''
    );

    $nuevaContrasena = (string) (
        $_POST['nueva_contrasena'] ?? ''
    );

    $confirmarContrasena = (string) (
        $_POST['confirmar_contrasena'] ?? ''
    );

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_cambiar_password'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $mensaje =
            'La sesión del formulario expiró.';

        $tipoMensaje = 'error';

    } elseif (strlen($nuevaContrasena) < 8) {
        $mensaje =
            'La nueva contraseña debe tener al menos 8 caracteres.';

        $tipoMensaje = 'error';

    } elseif ($nuevaContrasena !== $confirmarContrasena) {
        $mensaje =
            'Las contraseñas nuevas no coinciden.';

        $tipoMensaje = 'error';

    } else {
        try {
            $consulta = $pdo->prepare(
                'SELECT contrasena
                 FROM usuarios
                 WHERE id = :id
                 LIMIT 1'
            );

            $consulta->execute([
                'id' => $idUsuario
            ]);

            $usuario = $consulta->fetch(
                PDO::FETCH_ASSOC
            );

            if (
                !$usuario ||
                !password_verify(
                    $contrasenaActual,
                    (string) $usuario['contrasena']
                )
            ) {
                $mensaje =
                    'La contraseña actual es incorrecta.';

                $tipoMensaje = 'error';
            } else {
                $nuevoHash = password_hash(
                    $nuevaContrasena,
                    PASSWORD_DEFAULT
                );

                $actualizar = $pdo->prepare(
                    'UPDATE usuarios
                     SET contrasena = :contrasena
                     WHERE id = :id'
                );

                $actualizar->execute([
                    'contrasena' => $nuevoHash,
                    'id' => $idUsuario
                ]);

                $_SESSION['csrf_cambiar_password'] =
                    bin2hex(random_bytes(32));

                $mensaje =
                    'Contraseña actualizada correctamente.';

                $tipoMensaje = 'success';
            }
        } catch (Throwable $error) {
            error_log(
                'Error al cambiar contraseña: ' .
                $error->getMessage()
            );

            $mensaje =
                'No se pudo actualizar la contraseña.';

            $tipoMensaje = 'error';
        }
    }
}

$pageTitle = 'Cambiar contraseña';
$activePage = 'configuracion';

require_once $raiz . '/includes/header.php';
?>

<div class="card form-card">

    <div class="card-title">
        <h2>Seguridad de la cuenta</h2>
    </div>

    <?php if ($mensaje !== ''): ?>

        <div class="alert alert-<?= e($tipoMensaje) ?>">
            <?= e($mensaje) ?>
        </div>

    <?php endif; ?>

    <form method="POST" class="form-grid">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_cambiar_password']) ?>"
        >

        <div class="form-group full">

            <label for="contrasena_actual">
                Contraseña actual
            </label>

            <input
                type="password"
                id="contrasena_actual"
                name="contrasena_actual"
                autocomplete="current-password"
                required
            >

        </div>

        <div class="form-group">

            <label for="nueva_contrasena">
                Nueva contraseña
            </label>

            <input
                type="password"
                id="nueva_contrasena"
                name="nueva_contrasena"
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
                minlength="8"
                autocomplete="new-password"
                required
            >

        </div>

        <div class="form-actions">

            <a
                class="btn btn-secondary"
                href="<?= e(url('panel.php')) ?>"
            >
                Cancelar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
            >
                🔐 Cambiar contraseña
            </button>

        </div>

    </form>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>