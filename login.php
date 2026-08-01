<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['usuario_id'])) {
    redirect('panel.php');
}

$error = '';
$flashMessage = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Completa el correo y la contraseña.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, nombre, email, password, rol, estado
             FROM usuarios
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $error = 'El correo o la contraseña son incorrectos.';
        } elseif ($usuario['estado'] !== 'Activo') {
            $error = 'Este usuario se encuentra inactivo.';
        } else {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['rol'];

            redirect('panel.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <span>🐾</span>
            <h1>Clínica Veterinaria El Campo</h1>
            <p>Ingresa al sistema de gestión</p>
        </div>

        <?php if ($flashMessage): ?>
            <div class="alert alert-<?= e($flashMessage['type']) ?>">
                <?= e($flashMessage['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button class="btn btn-primary" type="submit">🔐 Iniciar sesión</button>
        </form>

        <div class="login-help">
            <strong>Usuario de prueba</strong><br>
            Correo: admin@elcampo.ec<br>
            Contraseña: Admin123*
        </div>
    </div>
</body>
</html>
