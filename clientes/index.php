<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$buscar = trim($_GET['buscar'] ?? '');
$sql = 'SELECT c.*,
               (SELECT COUNT(*) FROM mascotas m WHERE m.cliente_id = c.id) AS total_mascotas
        FROM clientes c';
$params = [];

if ($buscar !== '') {
    $sql .= ' WHERE c.nombres LIKE ? OR c.apellidos LIKE ? OR c.cedula LIKE ? OR c.telefono LIKE ?';
    $term = '%' . $buscar . '%';
    $params = [$term, $term, $term, $term];
}

$sql .= ' ORDER BY c.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$pageTitle = 'Clientes';
$activePage = 'clientes';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <form class="search-bar" method="get">
        <input name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar cliente...">
        <button class="btn btn-secondary" type="submit">Buscar</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('clientes/crear.php')) ?>">➕ Nuevo cliente</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Cliente</th>
            <th>Cédula</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Mascotas</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($clientes): ?>
            <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><strong><?= e($cliente['nombres'] . ' ' . $cliente['apellidos']) ?></strong></td>
                    <td><?= e($cliente['cedula'] ?: '—') ?></td>
                    <td><?= e($cliente['telefono']) ?></td>
                    <td><?= e($cliente['email'] ?: '—') ?></td>
                    <td><span class="badge badge-info"><?= e($cliente['total_mascotas']) ?></span></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-warning btn-sm" href="<?= e(url('clientes/editar.php?id=' . $cliente['id'])) ?>">Editar</a>
                            <form class="inline-form" method="post" action="<?= e(url('clientes/eliminar.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($cliente['id']) ?>">
                                <button class="btn btn-danger btn-sm" data-confirm="Se eliminará el cliente y todos sus datos relacionados. ¿Continuar?" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6"><div class="empty-state">No se encontraron clientes.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
