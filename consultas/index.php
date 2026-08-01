<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$buscar = trim($_GET['buscar'] ?? '');
$sql = "SELECT co.*, m.nombre AS mascota, m.especie,
               CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
               u.nombre AS veterinario
        FROM consultas co
        INNER JOIN mascotas m ON m.id = co.mascota_id
        INNER JOIN clientes c ON c.id = m.cliente_id
        INNER JOIN usuarios u ON u.id = co.usuario_id";
$params = [];

if ($buscar !== '') {
    $sql .= ' WHERE m.nombre LIKE ? OR c.nombres LIKE ? OR c.apellidos LIKE ? OR co.diagnostico LIKE ?';
    $term = '%' . $buscar . '%';
    $params = [$term, $term, $term, $term];
}

$sql .= ' ORDER BY co.fecha DESC, co.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$consultas = $stmt->fetchAll();

$pageTitle = 'Historia clínica';
$activePage = 'consultas';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <form class="search-bar" method="get">
        <input name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar mascota o diagnóstico...">
        <button class="btn btn-secondary" type="submit">Buscar</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('consultas/crear.php')) ?>">➕ Nueva consulta</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Mascota</th>
            <th>Propietario</th>
            <th>Diagnóstico</th>
            <th>Veterinario</th>
            <th>Acción</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($consultas): ?>
            <?php foreach ($consultas as $consulta): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime($consulta['fecha']))) ?></td>
                    <td><strong><?= e($consulta['mascota']) ?></strong> · <?= e($consulta['especie']) ?></td>
                    <td><?= e($consulta['cliente']) ?></td>
                    <td><?= e(mb_strimwidth($consulta['diagnostico'], 0, 55, '...')) ?></td>
                    <td><?= e($consulta['veterinario']) ?></td>
                    <td><a class="btn btn-secondary btn-sm" href="<?= e(url('consultas/ver.php?id=' . $consulta['id'])) ?>">Ver detalle</a></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6"><div class="empty-state">No existen consultas registradas.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
