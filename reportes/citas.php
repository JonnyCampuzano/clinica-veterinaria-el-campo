<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

[$desde, $hasta] = rep_rango();
$estado = trim((string) ($_GET['estado'] ?? ''));
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$exportar = ($_GET['exportar'] ?? '') === 'csv';

$estadosPermitidos = [
    '',
    'Pendiente',
    'Confirmada',
    'Atendida',
    'Cancelada',
];

if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

$citas = [];
$error = '';
$resumen = [
    'total' => 0,
    'Pendiente' => 0,
    'Confirmada' => 0,
    'Atendida' => 0,
];

try {
    $sql = '
        SELECT
            ci.id,
            ci.fecha,
            ci.hora,
            ci.motivo,
            ci.estado,
            m.nombre AS mascota_nombre,
            m.especie AS mascota_especie,
            m.raza AS mascota_raza,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.telefono AS cliente_telefono
        FROM citas ci
        INNER JOIN mascotas m
            ON m.id = ci.mascota_id
        INNER JOIN clientes c
            ON c.id = m.cliente_id
        WHERE ci.fecha BETWEEN :desde AND :hasta
    ';

    $params = [
        ':desde' => $desde,
        ':hasta' => $hasta,
    ];

    if ($estado !== '') {
        $sql .= ' AND ci.estado = :estado';
        $params[':estado'] = $estado;
    }

    if ($buscar !== '') {
        $t = '%' . $buscar . '%';
        $sql .= '
            AND (
                m.nombre LIKE :mascota
                OR c.nombres LIKE :cliente_nombres
                OR c.apellidos LIKE :cliente_apellidos
                OR ci.motivo LIKE :motivo
            )
        ';
        $params += [
            ':mascota' => $t,
            ':cliente_nombres' => $t,
            ':cliente_apellidos' => $t,
            ':motivo' => $t,
        ];
    }

    $sql .= ' ORDER BY ci.fecha DESC, ci.hora DESC, ci.id DESC';

    $q = $pdo->prepare($sql);
    $q->execute($params);
    $citas = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($citas as $cita) {
        $resumen['total']++;
        $estadoActual = (string) ($cita['estado'] ?? '');
        if (isset($resumen[$estadoActual])) {
            $resumen[$estadoActual]++;
        }
    }
} catch (Throwable $e) {
    error_log('Reporte citas: ' . $e->getMessage());
    $error = 'No se pudo cargar el reporte de citas.';
}

if ($exportar && $error === '') {
    $filas = [];

    foreach ($citas as $cita) {
        $filas[] = [
            (int) ($cita['id'] ?? 0),
            (string) ($cita['fecha'] ?? ''),
            rep_hora($cita['hora'] ?? ''),
            trim((string) ($cita['cliente_nombres'] ?? '') . ' ' . (string) ($cita['cliente_apellidos'] ?? '')),
            (string) ($cita['mascota_nombre'] ?? ''),
            (string) ($cita['mascota_especie'] ?? ''),
            (string) ($cita['motivo'] ?? ''),
            (string) ($cita['estado'] ?? ''),
        ];
    }

    rep_csv(
        'reporte_citas_' . date('Ymd_His') . '.csv',
        ['ID', 'Fecha', 'Hora', 'Propietario', 'Mascota', 'Especie', 'Motivo', 'Estado'],
        $filas
    );
}

$pageTitle = 'Reporte de citas';
$activePage = 'reportes';
require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>
<div class="rep-page">
    <section class="rep-panel">
        <header class="rep-header">
            <div>
                <h1>📅 Reporte de citas</h1>
                <p>Del <?= rep_e(rep_fecha($desde)) ?> al <?= rep_e(rep_fecha($hasta)) ?>.</p>
            </div>

            <div class="rep-actions">
                <a class="rep-btn rep-btn-secondary" href="<?= rep_e(rep_url('reportes/index.php')) ?>">← Reportes</a>
                <button type="button" class="rep-btn rep-btn-dark" onclick="window.print()">🖨️ Imprimir</button>
                <a class="rep-btn rep-btn-success" href="<?= rep_e(rep_url('reportes/citas.php?' . http_build_query(['desde' => $desde, 'hasta' => $hasta, 'estado' => $estado, 'buscar' => $buscar, 'exportar' => 'csv']))) ?>">⬇️ CSV</a>
            </div>
        </header>

        <div class="rep-stats">
            <article class="rep-stat"><span>Total</span><strong><?= $resumen['total'] ?></strong></article>
            <article class="rep-stat"><span>Pendientes</span><strong><?= $resumen['Pendiente'] ?></strong></article>
            <article class="rep-stat"><span>Confirmadas</span><strong><?= $resumen['Confirmada'] ?></strong></article>
            <article class="rep-stat"><span>Atendidas</span><strong><?= $resumen['Atendida'] ?></strong></article>
        </div>

        <div class="rep-toolbar">
            <form method="GET" class="rep-filter">
                <div class="rep-field"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= rep_e($desde) ?>"></div>
                <div class="rep-field"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= rep_e($hasta) ?>"></div>
                <div class="rep-field"><label for="buscar">Buscar</label><input type="search" id="buscar" name="buscar" value="<?= rep_e($buscar) ?>" placeholder="Mascota, propietario o motivo"></div>
                <div class="rep-field">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <?php foreach (array_slice($estadosPermitidos, 1) as $opcion): ?>
                            <option value="<?= rep_e($opcion) ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= rep_e($opcion) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="rep-btn rep-btn-primary" type="submit">🔎 Aplicar</button>
            </form>

            <span class="rep-count"><?= count($citas) ?> cita<?= count($citas) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($error !== ''): ?><div class="rep-alert">⚠️ <?= rep_e($error) ?></div><?php endif; ?>

        <div class="rep-content">
            <?php if ($citas !== []): ?>
                <div class="rep-table-wrap">
                    <table class="rep-table">
                        <thead><tr><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Propietario</th><th>Motivo</th><th>Estado</th></tr></thead>
                        <tbody>
                            <?php foreach ($citas as $cita): ?>
                                <?php
                                $propietario = trim((string) $cita['cliente_nombres'] . ' ' . (string) $cita['cliente_apellidos']);
                                $clase = match ((string) $cita['estado']) {
                                    'Atendida' => 'rep-green',
                                    'Confirmada' => 'rep-blue',
                                    'Cancelada' => 'rep-red',
                                    default => 'rep-yellow',
                                };
                                ?>
                                <tr>
                                    <td><?= rep_e(rep_fecha($cita['fecha'])) ?></td>
                                    <td><?= rep_e(rep_hora($cita['hora'])) ?></td>
                                    <td><span class="rep-main"><?= rep_e($cita['mascota_nombre']) ?></span><span class="rep-sub"><?= rep_e(trim((string) $cita['mascota_especie'] . ' ' . (string) $cita['mascota_raza'])) ?></span></td>
                                    <td><span class="rep-main"><?= rep_e($propietario) ?></span><span class="rep-sub"><?= rep_e($cita['cliente_telefono']) ?></span></td>
                                    <td><?= rep_e($cita['motivo']) ?></td>
                                    <td><span class="rep-badge <?= $clase ?>"><?= rep_e($cita['estado']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rep-empty"><span>📅</span><h2>Sin citas</h2><p>No existen citas para los filtros seleccionados.</p></div>
            <?php endif; ?>
        </div>

        <div class="rep-footer-note">Generado por <?= rep_e(rep_usuario()) ?> · <?= date('d/m/Y H:i') ?></div>
    </section>
</div>
<?php require_once $raiz . '/includes/footer.php'; ?>
