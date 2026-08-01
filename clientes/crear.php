<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if ($nombres === '' || $apellidos === '' || $telefono === '') {
        $error = 'Nombres, apellidos y teléfono son obligatorios.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO clientes (nombres, apellidos, cedula, telefono, email, direccion)
                 VALUES (?, ?, NULLIF(?, ""), ?, NULLIF(?, ""), NULLIF(?, ""))'
            );
            $stmt->execute([$nombres, $apellidos, $cedula, $telefono, $email, $direccion]);

            flash('success', 'Cliente registrado correctamente.');
            redirect('clientes/index.php');
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'La cédula o el correo ya están registrados.'
                : 'No se pudo guardar el cliente.';
        }
    }
}

$pageTitle = 'Nuevo cliente';
$activePage = 'clientes';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombres">Nombres *</label>
            <input id="nombres" name="nombres" value="<?= e($_POST['nombres'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="apellidos">Apellidos *</label>
            <input id="apellidos" name="apellidos" value="<?= e($_POST['apellidos'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="cedula">Cédula</label>
            <input id="cedula" name="cedula" maxlength="13" value="<?= e($_POST['cedula'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="telefono">Teléfono *</label>
            <input id="telefono" name="telefono" value="<?= e($_POST['telefono'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" value="<?= e($_POST['direccion'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('clientes/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar cliente</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
