<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Usuario no válido.');
    redirect('usuarios/index.php');
}

$stmt = $pdo->prepare('SELECT id, nombre, email, rol, estado FROM usuarios WHERE id = ?');
$stmt->execute([$id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    flash('error', 'El usuario no existe.');
    redirect('usuarios/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $estado = $_POST['estado'] ?? '';

    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un nombre y un correo válido.';
    } elseif (!in_array($rol, ['Administrador', 'Veterinario', 'Recepción'], true)) {
        $error = 'El rol seleccionado no es válido.';
    } elseif (!in_array($estado, ['Activo', 'Inactivo'], true)) {
        $error = 'El estado seleccionado no es válido.';
    } elseif ($id === current_user()['id'] && $estado === 'Inactivo') {
        $error = 'No puedes desactivar tu propia cuenta.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            if ($password !== '') {
                $update = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?, email = ?, password = ?, rol = ?, estado = ?
                     WHERE id = ?'
                );
                $update->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol, $estado, $id]);
            } else {
                $update = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?, email = ?, rol = ?, estado = ?
                     WHERE id = ?'
                );
                $update->execute([$nombre, $email, $rol, $estado, $id]);
            }

            if ($id === current_user()['id']) {
                $_SESSION['usuario_nombre'] = $nombre;
                $_SESSION['usuario_email'] = $email;
                $_SESSION['usuario_rol'] = $rol;
            }

            flash('success', 'Usuario actualizado correctamente.');
            redirect('usuarios/index.php');
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'El correo ya pertenece a otro usuario.'
                : 'No se pudo actualizar el usuario.';
        }
    }
}

$values = array_merge($usuario, $_POST);
$pageTitle = 'Editar usuario';
$activePage = 'usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombre">Nombre completo *</label>
            <input id="nombre" name="nombre" value="<?= e($values['nombre']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo *</label>
            <input id="email" name="email" type="email" value="<?= e($values['email']) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Nueva contraseña</label>
            <input id="password" name="password" type="password" minlength="8" placeholder="Déjala vacía para conservarla">
        </div>
        <div class="form-group">
            <label for="rol">Rol *</label>
            <select id="rol" name="rol" required>
                <?php foreach (['Administrador', 'Veterinario', 'Recepción'] as $opcion): ?>
                    <option <?= $values['rol'] === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="estado">Estado *</label>
            <select id="estado" name="estado" required>
                <option <?= $values['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                <option <?= $values['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('usuarios/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Actualizar usuario</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
