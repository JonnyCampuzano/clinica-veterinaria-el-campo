<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Mascota no válida.');
    redirect('mascotas/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM mascotas WHERE id = ?');
$stmt->execute([$id]);
$mascota = $stmt->fetch();

if (!$mascota) {
    flash('error', 'La mascota no existe.');
    redirect('mascotas/index.php');
}

$clientes = $pdo->query('SELECT id, nombres, apellidos FROM clientes ORDER BY nombres, apellidos')->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $nombre = trim($_POST['nombre'] ?? '');
    $especie = trim($_POST['especie'] ?? '');
    $raza = trim($_POST['raza'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    $fechaNacimiento = $_POST['fecha_nacimiento'] ?? '';
    $peso = $_POST['peso'] ?? '';
    $color = trim($_POST['color'] ?? '');
    $alergias = trim($_POST['alergias'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (!$clienteId || $nombre === '' || $especie === '' || !in_array($sexo, ['Macho', 'Hembra'], true)) {
        $error = 'Propietario, nombre, especie y sexo son obligatorios.';
    } else {
        $update = $pdo->prepare(
            'UPDATE mascotas
             SET cliente_id = ?, nombre = ?, especie = ?, raza = NULLIF(?, ""), sexo = ?,
                 fecha_nacimiento = NULLIF(?, ""), peso = NULLIF(?, ""), color = NULLIF(?, ""),
                 alergias = NULLIF(?, ""), observaciones = NULLIF(?, "")
             WHERE id = ?'
        );
        $update->execute([
            $clienteId, $nombre, $especie, $raza, $sexo, $fechaNacimiento,
            $peso, $color, $alergias, $observaciones, $id
        ]);

        flash('success', 'Mascota actualizada correctamente.');
        redirect('mascotas/index.php');
    }
}

$values = array_merge($mascota, $_POST);
$pageTitle = 'Editar mascota';
$activePage = 'mascotas';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group full">
            <label for="cliente_id">Propietario *</label>
            <select id="cliente_id" name="cliente_id" required>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= e($cliente['id']) ?>" <?= (string) $cliente['id'] === (string) $values['cliente_id'] ? 'selected' : '' ?>>
                        <?= e($cliente['nombres'] . ' ' . $cliente['apellidos']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" value="<?= e($values['nombre']) ?>" required>
        </div>
        <div class="form-group">
            <label for="especie">Especie *</label>
            <select id="especie" name="especie" required>
                <?php foreach (['Perro', 'Gato', 'Ave', 'Conejo', 'Otro'] as $opcion): ?>
                    <option <?= $values['especie'] === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="raza">Raza</label>
            <input id="raza" name="raza" value="<?= e($values['raza']) ?>">
        </div>
        <div class="form-group">
            <label for="sexo">Sexo *</label>
            <select id="sexo" name="sexo" required>
                <option <?= $values['sexo'] === 'Macho' ? 'selected' : '' ?>>Macho</option>
                <option <?= $values['sexo'] === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
            </select>
        </div>
        <div class="form-group">
            <label for="fecha_nacimiento">Fecha de nacimiento</label>
            <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="<?= e($values['fecha_nacimiento']) ?>">
        </div>
        <div class="form-group">
            <label for="peso">Peso (kg)</label>
            <input id="peso" name="peso" type="number" min="0" step="0.01" value="<?= e($values['peso']) ?>">
        </div>
        <div class="form-group">
            <label for="color">Color</label>
            <input id="color" name="color" value="<?= e($values['color']) ?>">
        </div>
        <div class="form-group">
            <label for="alergias">Alergias</label>
            <input id="alergias" name="alergias" value="<?= e($values['alergias']) ?>">
        </div>
        <div class="form-group full">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones"><?= e($values['observaciones']) ?></textarea>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('mascotas/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Actualizar mascota</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
