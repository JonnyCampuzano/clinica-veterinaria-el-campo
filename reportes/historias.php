<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$tabla = rep_tabla_historia($pdo);
[$desde, $hasta] = rep_rango();
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$exportar = ($_GET['exportar'] ?? '') === 'csv';
$historias = [];
$error = '';

if ($tabla === null) {
    $error = 'No se encontró la tabla historias_clinicas.';
} else {
    try {
        $sql = "
            SELECT
                h.id,
                h.fecha,
                h.motivo,
                h.diagnostico,
                h.tratamiento,
                h.peso,
                h.temperatura,
                h.proxima_cita,
                m.nombre AS mascota_nombre,
                m.especie AS mascota_especie,
                c.nombres AS cliente_nombres,
                c.apellidos AS cliente_apellidos,
                u.nombre AS usuario_nombre
            FROM {$tabla} h
            INNER JOIN mascotas m
                ON m.id = h.mascota_id
            INNER JOIN clientes c
                ON c.id = m.cliente_id
            LEFT JOIN usuarios u
                ON u.id = h.usuario_id
            WHERE h.fecha BETWEEN :desde AND :hasta
        ";

        $params = [
            ':desde' => $desde,
            ':hasta' => $hasta,
        ];

        if ($buscar !== '') {
            $t = '%' . $buscar . '%';
            $sql .= '
                AND (
                    m.nombre LIKE :mascota
                    OR c.nombres LIKE :cliente_nombres
                    OR c.apellidos LIKE :cliente_apellidos
                    OR h.motivo LIKE :motivo
                    OR h.diagnostico LIKE :diagnostico
                    OR h.tratamiento LIKE :tratamiento
                    OR u.nombre LIKE :usuario
                )
            ';
            $params += [
                ':mascota' => $t,
                ':cliente_nombres' => $t,
                ':cliente_apellidos' => $t,
                ':motivo' => $t,
                ':diagnostico' => $t,
                ':tratamiento' => $t,
                ':usuario' => $t,
            ];
        }

        $sql .= ' ORDER BY h.fecha DESC, h.id DESC';
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $historias = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Reporte historias: ' . $e->getMessage());
        $error = 'No se pudo cargar el reporte de historias clínicas.';
    }
}

if ($exportar && $error === '') {
    $filas = [];

    foreach ($historias as $h) {
        $filas[] = [
            (int) $h['id'],
            (string) $h['fecha'],
            trim((string) $h['cliente_nombres'] . ' ' . (string) $h['cliente_apellidos']),
            (string) $h['mascota_nombre'],
            (string) $h['motivo'],
            (string) $h['diagnostico'],
            (string) $h['tratamiento'],
            (string) $h['peso'],
            (string) $h['temperatura'],
            (string) $h['proxima_cita'],
            (string) ($h['usuario_nombre'] ?? ''),
        ];
    }

    rep_csv(
        'reporte_historias_' . date('Ymd_His') . '.csv',
        ['ID', 'Fecha', 'Propietario', 'Mascota', 'Motivo', 'Diagnostico', 'Tratamiento', 'Peso', 'Temperatura', 'Proxima cita', 'Veterinario'],
        $filas
    );
}

$pageTitle = 'Reporte de historias clínicas';
$activePage = 'reportes';
require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>
<div class="rep-page">
    <section class="rep-panel">
        <header class="rep-header">
            <div><h1>📋 Reporte de historias clínicas</h1><p>Del <?= rep_e(rep_fecha($desde)) ?> al <?= rep_e(rep_fecha($hasta)) ?>.</p></div>
            <div class="rep-actions">
                <a class="rep-btn rep-btn-secondary" href="<?= rep_e(rep_url('reportes/index.php')) ?>">← Reportes</a>
                <button type="button" class="rep-btn rep-btn-dark" onclick="window.print()">🖨️ Imprimir</button>
                <a class="rep-btn rep-btn-success" href="<?= rep_e(rep_url('reportes/historias.php?' . http_build_query(['desde' => $desde, 'hasta' => $hasta, 'buscar' => $buscar, 'exportar' => 'csv']))) ?>">⬇️ CSV</a>
            </div>
        </header>

        <div class="rep-toolbar">
            <form method="GET" class="rep-filter">
                <div class="rep-field"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= rep_e($desde) ?>"></div>
                <div class="rep-field"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= rep_e($hasta) ?>"></div>
                <div class="rep-field"><label for="buscar">Buscar</label><input type="search" id="buscar" name="buscar" value="<?= rep_e($buscar) ?>" placeholder="Mascota, cliente, diagnóstico o veterinario"></div>
                <button class="rep-btn rep-btn-primary" type="submit">🔎 Aplicar</button>
            </form>
            <span class="rep-count"><?= count($historias) ?> registro<?= count($historias) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($error !== ''): ?><div class="rep-alert">⚠️ <?= rep_e($error) ?></div><?php endif; ?>

        <div class="rep-content">
            <?php if ($historias !== []): ?>
                <div class="rep-table-wrap">
                    <table class="rep-table">
                        <thead><tr><th>Fecha</th><th>Mascota</th><th>Propietario</th><th>Motivo</th><th>Diagnóstico</th><th>Veterinario</th><th>Próximo control</th></tr></thead>
                        <tbody>
                            <?php foreach ($historias as $h): ?>
                                <?php $propietario = trim((string) $h['cliente_nombres'] . ' ' . (string) $h['cliente_apellidos']); ?>
                                <tr>
                                    <td><?= rep_e(rep_fecha($h['fecha'])) ?></td>
                                    <td><span class="rep-main"><?= rep_e($h['mascota_nombre']) ?></span><span class="rep-sub"><?= rep_e($h['mascota_especie']) ?></span></td>
                                    <td><?= rep_e($propietario) ?></td>
                                    <td><?= rep_e($h['motivo']) ?></td>
                                    <td><?= rep_e($h['diagnostico']) ?></td>
                                    <td><?= rep_e($h['usuario_nombre'] ?? '—') ?></td>
                                    <td><?= rep_e(rep_fecha($h['proxima_cita'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rep-empty"><span>📋</span><h2>Sin historias clínicas</h2><p>No existen registros para los filtros seleccionados.</p></div>
            <?php endif; ?>
        </div>

        <div class="rep-footer-note">Generado por <?= rep_e(rep_usuario()) ?> · <?= date('d/m/Y H:i') ?></div>
    </section>
</div>
<?php require_once $raiz . '/includes/footer.php'; ?>
