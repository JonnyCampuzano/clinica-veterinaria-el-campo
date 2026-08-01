<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$mascotas = $pdo->query(
    "SELECT m.id, m.nombre, CONCAT(c.nombres, ' ', c.apellidos) AS cliente
     FROM mascotas m
     INNER JOIN clientes c ON c.id = m.cliente_id
     ORDER BY m.nombre"
)->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $mascotaId = filter_input(INPUT_POST, 'mascota_id', FILTER_VALIDATE_INT);
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $estado = $_POST['estado'] ?? 'Pendiente';

    if (!$mascotaId || $fecha === '' || $hora === '' || $motivo === '') {
        $error = 'Mascota, fecha, hora y motivo son obligatorios.';
    } elseif (!in_array($estado, ['Pendiente', 'Confirmada', 'Atendida', 'Cancelada'], true)) {
        $error = 'El estado seleccionado no es válido.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO citas (mascota_id, fecha, hora, motivo, estado)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$mascotaId, $fecha, $hora, $motivo, $estado]);

        flash('success', 'Cita registrada correctamente.');
        redirect('citas/index.php');
    }
}

$pageTitle = 'Nueva cita';
$activePage = 'citas';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if (!$mascotas): ?>
        <div class="alert alert-warning">Primero debes registrar una mascota.</div>
        <a class="btn btn-primary" href="<?= e(url('mascotas/crear.php')) ?>">Registrar mascota</a>
    <?php else: ?>
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form class="form-grid" method="post">
            <?= csrf_field() ?>

            <div class="form-group full">
                <label for="mascota_id">Mascota y propietario *</label>
                <select id="mascota_id" name="mascota_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($mascotas as $mascota): ?>
                        <option value="<?= e($mascota['id']) ?>" <?= (string) $mascota['id'] === ($_POST['mascota_id'] ?? '') ? 'selected' : '' ?>>
                            <?= e($mascota['nombre'] . ' — ' . $mascota['cliente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha">Fecha *</label>
                <input id="fecha" name="fecha" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label for="hora">Hora *</label>
                <input id="hora" name="hora" type="time" value="<?= e($_POST['hora'] ?? '09:00') ?>" required>
            </div>
            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <?php foreach (['Pendiente', 'Confirmada', 'Atendida', 'Cancelada'] as $opcion): ?>
                        <option <?= ($_POST['estado'] ?? 'Pendiente') === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label for="motivo">Motivo de la cita *</label>
                <textarea id="motivo" name="motivo" required><?= e($_POST['motivo'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a class="btn btn-secondary" href="<?= e(url('citas/index.php')) ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar cita</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
