<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nombre = trim($_POST['nombre'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $stockMinimo = filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT);
    $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
    $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';

    if ($nombre === '' || $categoria === '' || $stock === false || $stockMinimo === false || $precio === false) {
        $error = 'Completa correctamente todos los campos obligatorios.';
    } elseif ($stock < 0 || $stockMinimo < 0 || $precio < 0) {
        $error = 'Stock y precio no pueden ser negativos.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO inventario (nombre, categoria, stock, stock_minimo, precio, fecha_vencimiento)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, ""))'
        );
        $stmt->execute([$nombre, $categoria, $stock, $stockMinimo, $precio, $fechaVencimiento]);

        flash('success', 'Producto registrado correctamente.');
        redirect('inventario/index.php');
    }
}

$pageTitle = 'Nuevo producto';
$activePage = 'inventario';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form-card">
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form class="form-grid" method="post">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="nombre">Nombre del producto *</label>
            <input id="nombre" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="categoria">Categoría *</label>
            <select id="categoria" name="categoria" required>
                <option value="">Seleccione...</option>
                <?php foreach (['Medicamento', 'Vacuna', 'Alimento', 'Higiene', 'Accesorio', 'Otro'] as $opcion): ?>
                    <option <?= ($_POST['categoria'] ?? '') === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="stock">Stock actual *</label>
            <input id="stock" name="stock" type="number" min="0" value="<?= e($_POST['stock'] ?? '0') ?>" required>
        </div>
        <div class="form-group">
            <label for="stock_minimo">Stock mínimo *</label>
            <input id="stock_minimo" name="stock_minimo" type="number" min="0" value="<?= e($_POST['stock_minimo'] ?? '5') ?>" required>
        </div>
        <div class="form-group">
            <label for="precio">Precio unitario *</label>
            <input id="precio" name="precio" type="number" min="0" step="0.01" value="<?= e($_POST['precio'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="fecha_vencimiento">Fecha de vencimiento</label>
            <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="<?= e($_POST['fecha_vencimiento'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e(url('inventario/index.php')) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar producto</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
