<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$counts = [
    'clientes' => (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
    'mascotas' => (int) $pdo->query('SELECT COUNT(*) FROM mascotas')->fetchColumn(),
    'citas_hoy' => (int) $pdo->query('SELECT COUNT(*) FROM citas WHERE fecha = CURDATE()')->fetchColumn(),
    'productos' => (int) $pdo->query('SELECT COUNT(*) FROM inventario')->fetchColumn()
];

$upcomingStmt = $pdo->query(
    "SELECT c.id, c.fecha, c.hora, c.estado, c.motivo,
            m.nombre AS mascota,
            CONCAT(cl.nombres, ' ', cl.apellidos) AS cliente
     FROM citas c
     INNER JOIN mascotas m ON m.id = c.mascota_id
     INNER JOIN clientes cl ON cl.id = m.cliente_id
     WHERE c.fecha >= CURDATE()
     ORDER BY c.fecha ASC, c.hora ASC
     LIMIT 6"
);
$upcoming = $upcomingStmt->fetchAll();

$lowStockStmt = $pdo->query(
    'SELECT id, nombre, stock, stock_minimo
     FROM inventario
     WHERE stock <= stock_minimo
     ORDER BY stock ASC, nombre ASC
     LIMIT 6'
);
$lowStock = $lowStockStmt->fetchAll();

$pageTitle = 'Panel principal';
$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="cards-grid">
    <a class="card stat-card" href="<?= e(url('clientes/index.php')) ?>">
        <span class="stat-icon">👥</span>
        <div><h3><?= $counts['clientes'] ?></h3><p>Clientes registrados</p></div>
    </a>
    <a class="card stat-card" href="<?= e(url('mascotas/index.php')) ?>">
        <span class="stat-icon">🐕</span>
        <div><h3><?= $counts['mascotas'] ?></h3><p>Mascotas registradas</p></div>
    </a>
    <a class="card stat-card" href="<?= e(url('citas/index.php')) ?>">
        <span class="stat-icon">📅</span>
        <div><h3><?= $counts['citas_hoy'] ?></h3><p>Citas programadas hoy</p></div>
    </a>
    <a class="card stat-card" href="<?= e(url('inventario/index.php')) ?>">
        <span class="stat-icon">📦</span>
        <div><h3><?= $counts['productos'] ?></h3><p>Productos en inventario</p></div>
    </a>
</div>

<div class="dashboard-grid">
    <article class="card">
        <div class="card-title">
            <h2>Próximas citas</h2>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('citas/crear.php')) ?>">Nueva cita</a>
        </div>

        <?php if ($upcoming): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Mascota</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $cita): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?> · <?= e(substr($cita['hora'], 0, 5)) ?></td>
                            <td><?= e($cita['mascota']) ?></td>
                            <td><?= e($cita['cliente']) ?></td>
                            <td><span class="badge badge-<?= e(mb_strtolower($cita['estado'])) ?>"><?= e($cita['estado']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">No existen citas próximas.</div>
        <?php endif; ?>
    </article>

    <article class="card">
        <div class="card-title">
            <h2>Stock bajo</h2>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('inventario/index.php')) ?>">Ver inventario</a>
        </div>

        <?php if ($lowStock): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr><th>Producto</th><th>Stock</th><th>Mínimo</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lowStock as $producto): ?>
                        <tr>
                            <td><?= e($producto['nombre']) ?></td>
                            <td class="low-stock"><?= e($producto['stock']) ?></td>
                            <td><?= e($producto['stock_minimo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">Todo el inventario tiene stock suficiente.</div>
        <?php endif; ?>
    </article>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
