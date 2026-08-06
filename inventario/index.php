<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/includes/auth.php';

require_permission('inventario.ver');

$buscar = trim($_GET['buscar'] ?? '');
$sql = 'SELECT * FROM inventario';
$params = [];

if ($buscar !== '') {
    $sql .= ' WHERE nombre LIKE ? OR categoria LIKE ?';
    $term = '%' . $buscar . '%';
    $params = [$term, $term];
}

$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Inventario';
$activePage = 'inventario';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <form class="search-bar" method="get">
        <input name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar producto...">
        <button class="btn btn-secondary" type="submit">Buscar</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('inventario/crear.php')) ?>">➕ Nuevo producto</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Producto</th>
            <th>Categoría</th>
            <th>Stock</th>
            <th>Mínimo</th>
            <th>Precio</th>
            <th>Vencimiento</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($productos): ?>
            <?php foreach ($productos as $producto): ?>
                <tr>
                    <td><strong><?= e($producto['nombre']) ?></strong></td>
                    <td><?= e($producto['categoria']) ?></td>
                    <td class="<?= $producto['stock'] <= $producto['stock_minimo'] ? 'low-stock' : '' ?>"><?= e($producto['stock']) ?></td>
                    <td><?= e($producto['stock_minimo']) ?></td>
                    <td>$<?= e(number_format((float) $producto['precio'], 2)) ?></td>
                    <td><?= $producto['fecha_vencimiento'] ? e(date('d/m/Y', strtotime($producto['fecha_vencimiento']))) : '—' ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-warning btn-sm" href="<?= e(url('inventario/editar.php?id=' . $producto['id'])) ?>">Editar</a>
                            <form class="inline-form" method="post" action="<?= e(url('inventario/eliminar.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($producto['id']) ?>">
                                <button class="btn btn-danger btn-sm" data-confirm="¿Eliminar este producto?" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7"><div class="empty-state">No se encontraron productos.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
