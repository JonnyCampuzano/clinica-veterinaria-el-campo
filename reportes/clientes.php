<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$exportar = ($_GET['exportar'] ?? '') === 'csv';
$clientes = [];
$error = '';

try {
    $sql = '
        SELECT
            c.id,
            c.nombres,
            c.apellidos,
            c.cedula,
            c.telefono,
            c.email,
            c.direccion,
            COUNT(m.id) AS total_mascotas
        FROM clientes c
        LEFT JOIN mascotas m
            ON m.cliente_id = c.id
        WHERE 1 = 1
    ';

    $params = [];

    if ($buscar !== '') {
        $t = '%' . $buscar . '%';

        $sql .= '
            AND (
                c.nombres LIKE :nombres
                OR c.apellidos LIKE :apellidos
                OR c.cedula LIKE :cedula
                OR c.telefono LIKE :telefono
                OR c.email LIKE :email
                OR c.direccion LIKE :direccion
            )
        ';

        $params = [
            ':nombres' => $t,
            ':apellidos' => $t,
            ':cedula' => $t,
            ':telefono' => $t,
            ':email' => $t,
            ':direccion' => $t,
        ];
    }

    $sql .= '
        GROUP BY
            c.id,
            c.nombres,
            c.apellidos,
            c.cedula,
            c.telefono,
            c.email,
            c.direccion
        ORDER BY c.id DESC
    ';

    $q = $pdo->prepare($sql);
    $q->execute($params);
    $clientes = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Reporte clientes: ' . $e->getMessage());
    $error = 'No se pudo cargar el reporte de clientes.';
}

if ($exportar && $error === '') {
    $filas = [];

    foreach ($clientes as $c) {
        $filas[] = [
            (int) ($c['id'] ?? 0),
            trim((string) ($c['nombres'] ?? '') . ' ' . (string) ($c['apellidos'] ?? '')),
            (string) ($c['cedula'] ?? ''),
            (string) ($c['telefono'] ?? ''),
            (string) ($c['email'] ?? ''),
            (string) ($c['direccion'] ?? ''),
            (int) ($c['total_mascotas'] ?? 0),
        ];
    }

    rep_csv(
        'reporte_clientes_' . date('Ymd_His') . '.csv',
        ['ID', 'Cliente', 'Cedula', 'Telefono', 'Correo', 'Direccion', 'Mascotas'],
        $filas
    );
}

$pageTitle = 'Reporte de clientes';
$activePage = 'reportes';
require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>
<div class="rep-page">
    <section class="rep-panel">
        <header class="rep-header">
            <div>
                <h1>👥 Reporte de clientes</h1>
                <p>Propietarios y mascotas registradas.</p>
            </div>

            <div class="rep-actions">
                <a class="rep-btn rep-btn-secondary" href="<?= rep_e(rep_url('reportes/index.php')) ?>">← Reportes</a>
                <button type="button" class="rep-btn rep-btn-dark" onclick="window.print()">🖨️ Imprimir</button>
                <a class="rep-btn rep-btn-success" href="<?= rep_e(rep_url('reportes/clientes.php?' . http_build_query(['buscar' => $buscar, 'exportar' => 'csv']))) ?>">⬇️ CSV</a>
            </div>
        </header>

        <div class="rep-toolbar">
            <form method="GET" class="rep-filter" style="grid-template-columns:minmax(220px,1fr) auto">
                <div class="rep-field">
                    <label for="buscar">Buscar</label>
                    <input type="search" id="buscar" name="buscar" value="<?= rep_e($buscar) ?>" placeholder="Nombre, cédula, teléfono, correo o dirección">
                </div>
                <button class="rep-btn rep-btn-primary" type="submit">🔎 Buscar</button>
            </form>

            <span class="rep-count">
                <?= count($clientes) ?> cliente<?= count($clientes) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($error !== ''): ?>
            <div class="rep-alert">⚠️ <?= rep_e($error) ?></div>
        <?php endif; ?>

        <div class="rep-content">
            <?php if ($clientes !== []): ?>
                <div class="rep-table-wrap">
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Dirección</th>
                                <th>Mascotas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                                <?php $nombre = trim((string) $c['nombres'] . ' ' . (string) $c['apellidos']); ?>
                                <tr>
                                    <td><span class="rep-main"><?= rep_e($nombre) ?></span><span class="rep-sub">ID #<?= (int) $c['id'] ?></span></td>
                                    <td><?= rep_e($c['cedula'] ?? '—') ?></td>
                                    <td><?= rep_e($c['telefono'] ?? '—') ?></td>
                                    <td><?= rep_e($c['email'] ?? '—') ?></td>
                                    <td><?= rep_e($c['direccion'] ?? '—') ?></td>
                                    <td><span class="rep-badge rep-blue"><?= (int) $c['total_mascotas'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rep-empty"><span>👥</span><h2>Sin resultados</h2><p>No existen clientes para el filtro seleccionado.</p></div>
            <?php endif; ?>
        </div>

        <div class="rep-footer-note">Generado por <?= rep_e(rep_usuario()) ?> · <?= date('d/m/Y H:i') ?></div>
    </section>
</div>
<?php require_once $raiz . '/includes/footer.php'; ?>
