<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Cliente no válido.');
    redirect('clientes/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    flash('error', 'El cliente no existe.');
    redirect('clientes/index.php');
}

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
            $update = $pdo->prepare(
                'UPDATE clientes
                 SET nombres = ?, apellidos = ?, cedula = NULLIF(?, ""),
                     telefono = ?, email = NULLIF(?, ""), direccion = NULLIF(?, "")
                 WHERE id = ?'
            );
            $update->execute([$nombres, $apellidos, $cedula, $telefono, $email, $direccion, $id]);

            flash('success', 'Cliente actualizado correctamente.');
            redirect('clientes/index.php');
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000'
                ? 'La cédula o el correo pertenecen a otro cliente.'
                : 'No se pudo actualizar el cliente.';
        }
    }
}

$values = array_merge($cliente, $_POST);
$pageTitle = 'Editar cliente';
$activePage = 'clientes';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombres">Nombres *</label>
            <input id="nombres" name="nombres" value="<?= e($values['nombres']) ?>" required>
        </div>
        <div class="form-group">
            <label for="apellidos">Apellidos *</label>
            <input id="apellidos" name="apellidos" value="<?= e($values['apellidos']) ?>" required>
        </div>
        <div class="form-group">
            <label for="cedula">Cédula</label>
            <input id="cedula" name="cedula" maxlength="13" value="<?= e($values['cedula']) ?>">
        </div>
        <div class="form-group">
            <label for="telefono">Teléfono *</label>
            <input id="telefono" name="telefono" value="<?= e($values['telefono']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="<?= e($values['email']) ?>">
        </div>
        <div class="form-group">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" value="<?= e($values['direccion']) ?>">
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('clientes/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Actualizar cliente</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
