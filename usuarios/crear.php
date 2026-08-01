<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? '';

    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Ingresa nombre, correo válido y una contraseña de al menos 8 caracteres.';
    } elseif (!in_array($rol, ['Administrador', 'Veterinario', 'Recepción'], true)) {
        $error = 'El rol seleccionado no es válido.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, email, password, rol, estado)
                 VALUES (?, ?, ?, ?, "Activo")'
            );
            $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol]);

            flash('success', 'Usuario registrado correctamente.');
            redirect('usuarios/index.php');
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'El correo ya pertenece a otro usuario.'
                : 'No se pudo crear el usuario.';
        }
    }
}

$pageTitle = 'Nuevo usuario';
$activePage = 'usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombre">Nombre completo *</label>
            <input id="nombre" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo *</label>
            <input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Contraseña *</label>
            <input id="password" name="password" type="password" minlength="8" required>
        </div>
        <div class="form-group">
            <label for="rol">Rol *</label>
            <select id="rol" name="rol" required>
                <option value="">Seleccione...</option>
                <?php foreach (['Administrador', 'Veterinario', 'Recepción'] as $opcion): ?>
                    <option <?= ($_POST['rol'] ?? '') === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('usuarios/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar usuario</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
