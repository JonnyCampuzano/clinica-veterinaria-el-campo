<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$estado = trim($_GET['estado'] ?? '');
$sql = "SELECT c.*, m.nombre AS mascota,
               CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente
        FROM citas c
        INNER JOIN mascotas m ON m.id = c.mascota_id
        INNER JOIN clientes cl ON cl.id = m.cliente_id";
$params = [];

if (in_array($estado, ['Pendiente', 'Confirmada', 'Atendida', 'Cancelada'], true)) {
    $sql .= ' WHERE c.estado = ?';
    $params[] = $estado;
}

$sql .= ' ORDER BY c.fecha DESC, c.hora DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll();

$pageTitle = 'Citas';
$activePage = 'citas';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <form class="search-bar" method="get">
        <select name="estado">
            <option value="">Todos los estados</option>
            <?php foreach (['Pendiente', 'Confirmada', 'Atendida', 'Cancelada'] as $opcion): ?>
                <option <?= $estado === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit">Filtrar</button>
    </form>
    <a class="btn btn-primary" href="<?= e(url('citas/crear.php')) ?>">➕ Nueva cita</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Mascota</th>
            <th>Propietario</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($citas): ?>
            <?php foreach ($citas as $cita): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?> · <?= e(substr($cita['hora'], 0, 5)) ?></td>
                    <td><strong><?= e($cita['mascota']) ?></strong></td>
                    <td><?= e($cita['cliente']) ?></td>
                    <td><?= e($cita['motivo']) ?></td>
                    <td><span class="badge badge-<?= e(mb_strtolower($cita['estado'])) ?>"><?= e($cita['estado']) ?></span></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-warning btn-sm" href="<?= e(url('citas/editar.php?id=' . $cita['id'])) ?>">Editar</a>
                            <form class="inline-form" method="post" action="<?= e(url('citas/eliminar.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($cita['id']) ?>">
                                <button class="btn btn-danger btn-sm" data-confirm="¿Eliminar esta cita?" type="submit">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6"><div class="empty-state">No existen citas para mostrar.</div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
