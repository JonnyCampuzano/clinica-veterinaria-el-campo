<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

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
        $stmt = $pdo->prepare(
            'INSERT INTO mascotas
             (cliente_id, nombre, especie, raza, sexo, fecha_nacimiento, peso, color, alergias, observaciones)
             VALUES (?, ?, ?, NULLIF(?, ""), ?, NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""), NULLIF(?, ""))'
        );
        $stmt->execute([
            $clienteId, $nombre, $especie, $raza, $sexo, $fechaNacimiento,
            $peso, $color, $alergias, $observaciones
        ]);

        flash('success', 'Mascota registrada correctamente.');
        redirect('mascotas/index.php');
    }
}

$pageTitle = 'Nueva mascota';
$activePage = 'mascotas';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if (!$clientes): ?>
        <div class="alert alert-warning">Primero debes registrar al propietario.</div>
        <a class="btn btn-primary" href="<?= e(url('clientes/crear.php')) ?>">Registrar cliente</a>
    <?php else: ?>
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form class="form-grid" method="post">
            <?= csrf_field() ?>

            <div class="form-group full">
                <label for="cliente_id">Propietario *</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= e($cliente['id']) ?>" <?= (string) ($cliente['id']) === ($_POST['cliente_id'] ?? '') ? 'selected' : '' ?>>
                            <?= e($cliente['nombres'] . ' ' . $cliente['apellidos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nombre">Nombre *</label>
                <input id="nombre" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="especie">Especie *</label>
                <select id="especie" name="especie" required>
                    <option value="">Seleccione...</option>
                    <?php foreach (['Perro', 'Gato', 'Ave', 'Conejo', 'Otro'] as $opcion): ?>
                        <option <?= ($_POST['especie'] ?? '') === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="raza">Raza</label>
                <input id="raza" name="raza" value="<?= e($_POST['raza'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="sexo">Sexo *</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione...</option>
                    <option <?= ($_POST['sexo'] ?? '') === 'Macho' ? 'selected' : '' ?>>Macho</option>
                    <option <?= ($_POST['sexo'] ?? '') === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="<?= e($_POST['fecha_nacimiento'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="peso">Peso (kg)</label>
                <input id="peso" name="peso" type="number" min="0" step="0.01" value="<?= e($_POST['peso'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="color">Color</label>
                <input id="color" name="color" value="<?= e($_POST['color'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="alergias">Alergias</label>
                <input id="alergias" name="alergias" value="<?= e($_POST['alergias'] ?? '') ?>">
            </div>
            <div class="form-group full">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones"><?= e($_POST['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a class="btn btn-secondary" href="<?= e(url('mascotas/index.php')) ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar mascota</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
