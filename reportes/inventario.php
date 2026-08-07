<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$categoria = trim((string) ($_GET['categoria'] ?? ''));
$soloBajo = ($_GET['stock_bajo'] ?? '') === '1';
$exportar = ($_GET['exportar'] ?? '') === 'csv';

$inventario = [];
$categorias = [];
$error = '';

$tienePrecioVenta = rep_columna_existe($pdo, 'inventario', 'precio_venta');
$tienePrecio = rep_columna_existe($pdo, 'inventario', 'precio');
$tieneCodigo = rep_columna_existe($pdo, 'inventario', 'codigo');
$tieneEstado = rep_columna_existe($pdo, 'inventario', 'estado');

try {
    if (rep_tabla_existe($pdo, 'inventario')) {
        $categorias = $pdo->query(
            'SELECT DISTINCT categoria
             FROM inventario
             WHERE categoria IS NOT NULL
               AND categoria <> ""
             ORDER BY categoria'
        )->fetchAll(PDO::FETCH_COLUMN);

        $precioExpr = $tienePrecioVenta
            ? 'i.precio_venta'
            : ($tienePrecio ? 'i.precio' : '0');

        $codigoExpr = $tieneCodigo ? 'i.codigo' : 'NULL';
        $estadoExpr = $tieneEstado ? 'i.estado' : '"Activo"';

        $sql = "
            SELECT
                i.id,
                {$codigoExpr} AS codigo,
                i.nombre,
                i.categoria,
                i.stock,
                i.stock_minimo,
                {$precioExpr} AS precio_reporte,
                i.fecha_vencimiento,
                {$estadoExpr} AS estado_reporte
            FROM inventario i
            WHERE 1 = 1
        ";

        $params = [];

        if ($buscar !== '') {
            $t = '%' . $buscar . '%';

            $sql .= '
                AND (
                    i.nombre LIKE :nombre
                    OR i.categoria LIKE :categoria_buscar
            ';

            if ($tieneCodigo) {
                $sql .= ' OR i.codigo LIKE :codigo';
            }

            $sql .= ')';

            $params = [
                ':nombre' => $t,
                ':categoria_buscar' => $t,
            ];

            if ($tieneCodigo) {
                $params[':codigo'] = $t;
            }
        }

        if ($categoria !== '') {
            $sql .= ' AND i.categoria = :categoria';
            $params[':categoria'] = $categoria;
        }

        if ($soloBajo) {
            $sql .= ' AND i.stock <= i.stock_minimo';
        }

        $sql .= ' ORDER BY i.nombre ASC';

        $q = $pdo->prepare($sql);
        $q->execute($params);
        $inventario = $q->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Reporte inventario: ' . $e->getMessage());
    $error = 'No se pudo cargar el reporte de inventario.';
}

$resumen = [
    'productos' => count($inventario),
    'unidades' => 0,
    'stock_bajo' => 0,
    'valor' => 0.0,
];

foreach ($inventario as $producto) {
    $stock = (int) ($producto['stock'] ?? 0);
    $minimo = (int) ($producto['stock_minimo'] ?? 0);
    $precio = (float) ($producto['precio_reporte'] ?? 0);

    $resumen['unidades'] += $stock;
    $resumen['valor'] += $stock * $precio;

    if ($stock <= $minimo) {
        $resumen['stock_bajo']++;
    }
}

if ($exportar && $error === '') {
    $filas = [];

    foreach ($inventario as $p) {
        $filas[] = [
            (int) ($p['id'] ?? 0),
            (string) ($p['codigo'] ?? ''),
            (string) ($p['nombre'] ?? ''),
            (string) ($p['categoria'] ?? ''),
            (int) ($p['stock'] ?? 0),
            (int) ($p['stock_minimo'] ?? 0),
            (float) ($p['precio_reporte'] ?? 0),
            (string) ($p['fecha_vencimiento'] ?? ''),
            (string) ($p['estado_reporte'] ?? ''),
        ];
    }

    rep_csv(
        'reporte_inventario_' . date('Ymd_His') . '.csv',
        ['ID', 'Codigo', 'Producto', 'Categoria', 'Stock', 'Stock minimo', 'Precio', 'Vencimiento', 'Estado'],
        $filas
    );
}

$pageTitle = 'Reporte de inventario';
$activePage = 'reportes';
require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>
<div class="rep-page">
    <section class="rep-panel">
        <header class="rep-header">
            <div><h1>📦 Reporte de inventario</h1><p>Existencias, precios y alertas de stock.</p></div>
            <div class="rep-actions">
                <a class="rep-btn rep-btn-secondary" href="<?= rep_e(rep_url('reportes/index.php')) ?>">← Reportes</a>
                <button type="button" class="rep-btn rep-btn-dark" onclick="window.print()">🖨️ Imprimir</button>
                <a class="rep-btn rep-btn-success" href="<?= rep_e(rep_url('reportes/inventario.php?' . http_build_query(['buscar' => $buscar, 'categoria' => $categoria, 'stock_bajo' => $soloBajo ? '1' : '', 'exportar' => 'csv']))) ?>">⬇️ CSV</a>
            </div>
        </header>

        <div class="rep-stats">
            <article class="rep-stat"><span>Productos</span><strong><?= $resumen['productos'] ?></strong></article>
            <article class="rep-stat"><span>Unidades</span><strong><?= $resumen['unidades'] ?></strong></article>
            <article class="rep-stat"><span>Stock bajo</span><strong><?= $resumen['stock_bajo'] ?></strong></article>
            <article class="rep-stat"><span>Valor estimado</span><strong><?= rep_e(rep_dinero($resumen['valor'])) ?></strong></article>
        </div>

        <div class="rep-toolbar">
            <form method="GET" class="rep-filter">
                <div class="rep-field"><label for="buscar">Buscar</label><input type="search" id="buscar" name="buscar" value="<?= rep_e($buscar) ?>" placeholder="Producto o código"></div>
                <div class="rep-field">
                    <label for="categoria">Categoría</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $opcion): ?>
                            <option value="<?= rep_e($opcion) ?>" <?= $categoria === $opcion ? 'selected' : '' ?>><?= rep_e($opcion) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rep-field">
                    <label for="stock_bajo">Existencias</label>
                    <select id="stock_bajo" name="stock_bajo">
                        <option value="">Todos</option>
                        <option value="1" <?= $soloBajo ? 'selected' : '' ?>>Solo stock bajo</option>
                    </select>
                </div>
                <button class="rep-btn rep-btn-primary" type="submit">🔎 Aplicar</button>
            </form>
            <span class="rep-count"><?= count($inventario) ?> producto<?= count($inventario) === 1 ? '' : 's' ?></span>
        </div>

        <?php if ($error !== ''): ?><div class="rep-alert">⚠️ <?= rep_e($error) ?></div><?php endif; ?>

        <div class="rep-content">
            <?php if ($inventario !== []): ?>
                <div class="rep-table-wrap">
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <?php if ($tieneCodigo): ?><th>Código</th><?php endif; ?>
                                <th>Producto</th><th>Categoría</th><th>Stock</th><th>Mínimo</th><th>Precio</th><th>Vencimiento</th><th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventario as $p): ?>
                                <?php
                                $stock = (int) ($p['stock'] ?? 0);
                                $minimo = (int) ($p['stock_minimo'] ?? 0);
                                $stockBajo = $stock <= $minimo;
                                ?>
                                <tr>
                                    <?php if ($tieneCodigo): ?><td><?= rep_e($p['codigo'] ?? '—') ?></td><?php endif; ?>
                                    <td><span class="rep-main"><?= rep_e($p['nombre']) ?></span></td>
                                    <td><?= rep_e($p['categoria']) ?></td>
                                    <td><span class="rep-badge <?= $stockBajo ? 'rep-red' : 'rep-green' ?>"><?= $stock ?></span></td>
                                    <td><?= $minimo ?></td>
                                    <td><?= rep_e(rep_dinero($p['precio_reporte'])) ?></td>
                                    <td><?= rep_e(rep_fecha($p['fecha_vencimiento'])) ?></td>
                                    <td><?= rep_e($p['estado_reporte']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rep-empty"><span>📦</span><h2>Sin productos</h2><p>No existen productos para los filtros seleccionados.</p></div>
            <?php endif; ?>
        </div>

        <div class="rep-footer-note">Generado por <?= rep_e(rep_usuario()) ?> · <?= date('d/m/Y H:i') ?></div>
    </section>
</div>
<?php require_once $raiz . '/includes/footer.php'; ?>
