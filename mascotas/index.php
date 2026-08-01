<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$buscar = trim($_GET['buscar'] ?? '');
$sql = "SELECT m.*, CONCAT(c.nombres, ' ', c.apellidos) AS cliente
        FROM mascotas m
        INNER JOIN clientes c ON c.id = m.cliente_id";
$params = [];

if ($buscar !== '') {
    $sql .= ' WHERE m.nombre LIKE ? OR m.especie LIKE ? OR m.raza LIKE ? OR c.nombres LIKE ? OR c.apellidos LIKE ?';
    $term = '%' . $buscar . '%';
    $params = [$term, $term, $term, $term, $term];
}

$sql .= ' ORDER BY m.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mascotas = $stmt->fetchAll();

$pageTitle = 'Mascotas';
$activePage = 'mascotas';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <form class="search-bar" method="get">
        <input name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar mascota o propietario...">
        <button class="btn btn-secondary" type="submit">Buscar</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('mascotas/crear.php')) ?>">➕ Nueva mascota</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Mascota</th>
            <th>Propietario</th>
            <th>Especie</th>
            <th>Raza</th>
            <th>Sexo</th>
            <th>Peso</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($mascotas): ?>
            <?php foreach ($mascotas as $mascota): ?>
                <tr>
                    <td><strong><?= e($mascota['nombre']) ?></strong></td>
                    <td><?= e($mascota['cliente']) ?></td>
                    <td><?= e($mascota['especie']) ?></td>
                    <td><?= e($mascota['raza'] ?: '—') ?></td>
                    <td><?= e($mascota['sexo']) ?></td>
                    <td><?= $mascota['peso'] !== null ? e($mascota['peso']) . ' kg' : '—' ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-secondary btn-sm" href="<?= e(url('consultas/crear.php?mascota_id=' . $mascota['id'])) ?>">Consulta</a>
                            <a class="btn btn-warning btn-sm" href="<?= e(url('mascotas/editar.php?id=' . $mascota['id'])) ?>">Editar</a>
                            <form class="inline-form" method="post" action="<?= e(url('mascotas/eliminar.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($mascota['id']) ?>">
                                <button class="btn btn-danger btn-sm" data-confirm="Se eliminará la mascota, sus citas y consultas. ¿Continuar?" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7"><div class="empty-state">No se encontraron mascotas.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
