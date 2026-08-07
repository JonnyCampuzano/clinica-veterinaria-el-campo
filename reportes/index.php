<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$hist = rep_tabla_historia($pdo);
$stats = [
    'clientes' => 0,
    'mascotas' => 0,
    'citas_hoy' => 0,
    'historias' => 0,
    'inventario' => 0,
    'stock_bajo' => 0,
    'usuarios' => 0,
    'citas' => 0,
];
$error = '';

try {
    foreach (['clientes', 'mascotas', 'usuarios'] as $tabla) {
        if (rep_tabla_existe($pdo, $tabla)) {
            $stats[$tabla] = (int) $pdo
                ->query("SELECT COUNT(*) FROM {$tabla}")
                ->fetchColumn();
        }
    }

    if (rep_tabla_existe($pdo, 'citas')) {
        $stats['citas'] = (int) $pdo
            ->query('SELECT COUNT(*) FROM citas')
            ->fetchColumn();

        $stats['citas_hoy'] = (int) $pdo
            ->query('SELECT COUNT(*) FROM citas WHERE fecha = CURDATE()')
            ->fetchColumn();
    }

    if ($hist !== null) {
        $stats['historias'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM {$hist}")
            ->fetchColumn();
    }

    if (rep_tabla_existe($pdo, 'inventario')) {
        $stats['inventario'] = (int) $pdo
            ->query('SELECT COUNT(*) FROM inventario')
            ->fetchColumn();

        if (
            rep_columna_existe($pdo, 'inventario', 'stock') &&
            rep_columna_existe($pdo, 'inventario', 'stock_minimo')
        ) {
            $stats['stock_bajo'] = (int) $pdo
                ->query('SELECT COUNT(*) FROM inventario WHERE stock <= stock_minimo')
                ->fetchColumn();
        }
    }
} catch (Throwable $e) {
    error_log('Reportes index: ' . $e->getMessage());
    $error = 'No fue posible cargar todas las estadísticas.';
}

$pageTitle = 'Reportes';
$activePage = 'reportes';
require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>
<div class="rep-page">
    <section class="rep-panel">
        <header class="rep-header">
            <div>
                <h1>📊 Reportes del sistema</h1>
                <p>
                    Información consolidada de clientes, citas,
                    historias clínicas e inventario.
                </p>
            </div>

            <div class="rep-actions">
                <a
                    class="rep-btn rep-btn-secondary"
                    href="<?= rep_e(rep_url('panel.php')) ?>"
                >
                    ← Dashboard
                </a>
            </div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="rep-alert">⚠️ <?= rep_e($error) ?></div>
        <?php endif; ?>

        <div class="rep-stats">
            <?php
            $tarjetas = [
                'Clientes' => $stats['clientes'],
                'Mascotas' => $stats['mascotas'],
                'Citas hoy' => $stats['citas_hoy'],
                'Historias clínicas' => $stats['historias'],
                'Total citas' => $stats['citas'],
                'Productos inventario' => $stats['inventario'],
                'Stock bajo' => $stats['stock_bajo'],
                'Usuarios' => $stats['usuarios'],
            ];
            ?>

            <?php foreach ($tarjetas as $titulo => $valor): ?>
                <article class="rep-stat">
                    <span><?= rep_e($titulo) ?></span>
                    <strong><?= (int) $valor ?></strong>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="rep-modules">
            <a
                class="rep-module"
                href="<?= rep_e(rep_url('reportes/clientes.php')) ?>"
            >
                <div class="rep-module-icon">👥</div>
                <div>
                    <h2>Reporte de clientes</h2>
                    <p>Propietarios, contacto y mascotas asociadas.</p>
                </div>
            </a>

            <a
                class="rep-module"
                href="<?= rep_e(rep_url('reportes/citas.php')) ?>"
            >
                <div class="rep-module-icon">📅</div>
                <div>
                    <h2>Reporte de citas</h2>
                    <p>Agenda por fechas, estado, mascota y propietario.</p>
                </div>
            </a>

            <a
                class="rep-module"
                href="<?= rep_e(rep_url('reportes/historias.php')) ?>"
            >
                <div class="rep-module-icon">📋</div>
                <div>
                    <h2>Historias clínicas</h2>
                    <p>Diagnósticos, tratamientos y profesional responsable.</p>
                </div>
            </a>

            <a
                class="rep-module"
                href="<?= rep_e(rep_url('reportes/inventario.php')) ?>"
            >
                <div class="rep-module-icon">📦</div>
                <div>
                    <h2>Reporte de inventario</h2>
                    <p>Stock, mínimos, precios y vencimientos.</p>
                </div>
            </a>
        </div>

        <div class="rep-footer-note">
            Generado por <?= rep_e(rep_usuario()) ?>
            · <?= date('d/m/Y H:i') ?>
        </div>
    </section>
</div>
<?php require_once $raiz . '/includes/footer.php'; ?>
