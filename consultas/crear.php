<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$mascotaPreseleccionada = filter_input(INPUT_GET, 'mascota_id', FILTER_VALIDATE_INT);

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
    $motivo = trim($_POST['motivo'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $tratamiento = trim($_POST['tratamiento'] ?? '');
    $peso = $_POST['peso'] ?? '';
    $temperatura = $_POST['temperatura'] ?? '';
    $proximaCita = $_POST['proxima_cita'] ?? '';

    if (!$mascotaId || $fecha === '' || $motivo === '' || $diagnostico === '' || $tratamiento === '') {
        $error = 'Mascota, fecha, motivo, diagnóstico y tratamiento son obligatorios.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO consultas
             (mascota_id, usuario_id, fecha, motivo, diagnostico, tratamiento, peso, temperatura, proxima_cita)
             VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""))'
        );
        $stmt->execute([
            $mascotaId,
            current_user()['id'],
            $fecha,
            $motivo,
            $diagnostico,
            $tratamiento,
            $peso,
            $temperatura,
            $proximaCita
        ]);

        if ($peso !== '') {
            $updatePet = $pdo->prepare('UPDATE mascotas SET peso = ? WHERE id = ?');
            $updatePet->execute([$peso, $mascotaId]);
        }

        flash('success', 'Consulta clínica registrada correctamente.');
        redirect('consultas/index.php');
    }
}

$selectedPet = $_POST['mascota_id'] ?? ($mascotaPreseleccionada ?: '');
$pageTitle = 'Nueva consulta clínica';
$activePage = 'consultas';
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
                        <option value="<?= e($mascota['id']) ?>" <?= (string) $mascota['id'] === (string) $selectedPet ? 'selected' : '' ?>>
                            <?= e($mascota['nombre'] . ' — ' . $mascota['cliente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha">Fecha *</label>
                <input id="fecha" name="fecha" type="date" value="<?= e($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label for="proxima_cita">Próxima cita</label>
                <input id="proxima_cita" name="proxima_cita" type="date" value="<?= e($_POST['proxima_cita'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="peso">Peso (kg)</label>
                <input id="peso" name="peso" type="number" min="0" step="0.01" value="<?= e($_POST['peso'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="temperatura">Temperatura (°C)</label>
                <input id="temperatura" name="temperatura" type="number" min="30" max="45" step="0.1" value="<?= e($_POST['temperatura'] ?? '') ?>">
            </div>
            <div class="form-group full">
                <label for="motivo">Motivo de consulta *</label>
                <textarea id="motivo" name="motivo" required><?= e($_POST['motivo'] ?? '') ?></textarea>
            </div>
            <div class="form-group full">
                <label for="diagnostico">Diagnóstico *</label>
                <textarea id="diagnostico" name="diagnostico" required><?= e($_POST['diagnostico'] ?? '') ?></textarea>
            </div>
            <div class="form-group full">
                <label for="tratamiento">Tratamiento e indicaciones *</label>
                <textarea id="tratamiento" name="tratamiento" required><?= e($_POST['tratamiento'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a class="btn btn-secondary" href="<?= e(url('consultas/index.php')) ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar consulta</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
