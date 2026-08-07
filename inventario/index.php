<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$categoriaFiltro = trim(
    (string) ($_GET['categoria'] ?? '')
);
$estadoFiltro = trim(
    (string) ($_GET['estado'] ?? '')
);

$flash = inv_tomar_flash();
$mensajeExito = '';
$mensajeError = '';

if (($flash['tipo'] ?? '') === 'success') {
    $mensajeExito = (string) ($flash['mensaje'] ?? '');
}

if (($flash['tipo'] ?? '') === 'error') {
    $mensajeError = (string) ($flash['mensaje'] ?? '');
}

$columnas = inv_columnas($pdo);
$precioColumna = inv_columna_precio_venta($pdo);

$productos = [];
$categorias = [];
$estadisticas = [
    'total' => 0,
    'bajo_stock' => 0,
    'agotados' => 0,
    'valor' => 0.0,
];

try {
    $consultaCategorias = $pdo->query(
        'SELECT DISTINCT categoria
         FROM inventario
         WHERE categoria IS NOT NULL
           AND TRIM(categoria) <> ""
         ORDER BY categoria ASC'
    );

    $categorias = $consultaCategorias->fetchAll(
        PDO::FETCH_COLUMN
    );
} catch (Throwable $error) {
    error_log(
        'Error cargando categorías de inventario: ' .
        $error->getMessage()
    );
}

try {
    $precioExpresion = $precioColumna !== null
        ? 'COALESCE(' . $precioColumna . ', 0)'
        : '0';

    $consultaEstadisticas = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            COALESCE(
                SUM(stock > 0 AND stock <= stock_minimo),
                0
            ) AS bajo_stock,
            COALESCE(SUM(stock <= 0), 0) AS agotados,
            COALESCE(
                SUM(stock * ' . $precioExpresion . '),
                0
            ) AS valor
         FROM inventario'
    );

    $fila = $consultaEstadisticas->fetch(PDO::FETCH_ASSOC);

    if (is_array($fila)) {
        $estadisticas = [
            'total' => (int) ($fila['total'] ?? 0),
            'bajo_stock' =>
                (int) ($fila['bajo_stock'] ?? 0),
            'agotados' =>
                (int) ($fila['agotados'] ?? 0),
            'valor' => (float) ($fila['valor'] ?? 0),
        ];
    }
} catch (Throwable $error) {
    error_log(
        'Error cargando estadísticas de inventario: ' .
        $error->getMessage()
    );
}

try {
    $select = [
        'id',
        'nombre',
        'categoria',
        'stock',
        'stock_minimo',
        'fecha_vencimiento',
    ];

    foreach (
        [
            'codigo',
            'descripcion',
            'precio',
            'precio_compra',
            'precio_venta',
            'estado',
            'fecha_registro',
            'created_at',
        ]
        as $columna
    ) {
        if (isset($columnas[$columna])) {
            $select[] = $columna;
        }
    }

    $sql =
        'SELECT ' .
        implode(', ', $select) .
        ' FROM inventario
         WHERE 1 = 1';

    $parametros = [];

    if ($buscar !== '') {
        $condiciones = [
            'nombre LIKE :buscar_nombre',
            'categoria LIKE :buscar_categoria',
        ];

        $parametros[':buscar_nombre'] =
            '%' . $buscar . '%';
        $parametros[':buscar_categoria'] =
            '%' . $buscar . '%';

        if (isset($columnas['codigo'])) {
            $condiciones[] =
                'codigo LIKE :buscar_codigo';
            $parametros[':buscar_codigo'] =
                '%' . $buscar . '%';
        }

        if (isset($columnas['descripcion'])) {
            $condiciones[] =
                'descripcion LIKE :buscar_descripcion';
            $parametros[':buscar_descripcion'] =
                '%' . $buscar . '%';
        }

        $sql .=
            ' AND (' .
            implode(' OR ', $condiciones) .
            ')';
    }

    if ($categoriaFiltro !== '') {
        $sql .= ' AND categoria = :categoria';
        $parametros[':categoria'] = $categoriaFiltro;
    }

    if ($estadoFiltro === 'bajo_stock') {
        $sql .=
            ' AND stock > 0
              AND stock <= stock_minimo';
    } elseif ($estadoFiltro === 'agotado') {
        $sql .= ' AND stock <= 0';
    } elseif (
        in_array(
            $estadoFiltro,
            ['disponible', 'inactivo'],
            true
        )
        && isset($columnas['estado'])
    ) {
        $sql .= ' AND estado = :estado';
        $parametros[':estado'] = $estadoFiltro;
    }

    $sql .= ' ORDER BY nombre ASC, id DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $productos = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error cargando inventario: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudo cargar el inventario. ' .
        'Revisa la tabla inventario y la conexión.';
}

$pageTitle = 'Inventario';
$activePage = 'inventario';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="inv-page">
    <section class="inv-panel">
        <header class="inv-header">
            <div class="inv-header-copy">
                <h1>📦 Inventario</h1>

                <p>
                    Administra productos, medicamentos,
                    precios y existencias.
                </p>
            </div>

            <div class="inv-header-actions">
                <a
                    class="inv-btn inv-btn-primary"
                    href="<?= inv_e(
                        inv_url('inventario/crear.php')
                    ) ?>"
                >
                    ＋ Registrar producto
                </a>
            </div>
        </header>

        <div class="inv-stats">
            <article class="inv-stat">
                <span>Total productos</span>
                <strong><?= $estadisticas['total'] ?></strong>
            </article>

            <article class="inv-stat">
                <span>Bajo stock</span>
                <strong><?= $estadisticas['bajo_stock'] ?></strong>
            </article>

            <article class="inv-stat">
                <span>Agotados</span>
                <strong><?= $estadisticas['agotados'] ?></strong>
            </article>

            <article class="inv-stat">
                <span>Valor estimado</span>
                <strong>
                    <?= inv_e(
                        inv_dinero($estadisticas['valor'])
                    ) ?>
                </strong>
            </article>
        </div>

        <div class="inv-toolbar">
            <form method="GET" class="inv-search">
                <input
                    type="search"
                    name="buscar"
                    value="<?= inv_e($buscar) ?>"
                    placeholder="Buscar código, producto o categoría"
                >

                <select name="categoria">
                    <option value="">Todas las categorías</option>

                    <?php foreach ($categorias as $categoria): ?>
                        <option
                            value="<?= inv_e($categoria) ?>"
                            <?=
                                $categoriaFiltro === $categoria
                                    ? 'selected'
                                    : ''
                            ?>
                        >
                            <?= inv_e($categoria) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="estado">
                    <option value="">Todos los estados</option>

                    <?php if (isset($columnas['estado'])): ?>
                        <option
                            value="disponible"
                            <?=
                                $estadoFiltro === 'disponible'
                                    ? 'selected'
                                    : ''
                            ?>
                        >
                            Disponible
                        </option>
                    <?php endif; ?>

                    <option
                        value="bajo_stock"
                        <?=
                            $estadoFiltro === 'bajo_stock'
                                ? 'selected'
                                : ''
                        ?>
                    >
                        Bajo stock
                    </option>

                    <option
                        value="agotado"
                        <?=
                            $estadoFiltro === 'agotado'
                                ? 'selected'
                                : ''
                        ?>
                    >
                        Agotado
                    </option>

                    <?php if (isset($columnas['estado'])): ?>
                        <option
                            value="inactivo"
                            <?=
                                $estadoFiltro === 'inactivo'
                                    ? 'selected'
                                    : ''
                            ?>
                        >
                            Inactivo
                        </option>
                    <?php endif; ?>
                </select>

                <button
                    type="submit"
                    class="inv-btn inv-btn-primary"
                >
                    🔎 Buscar
                </button>

                <?php if (
                    $buscar !== ''
                    || $categoriaFiltro !== ''
                    || $estadoFiltro !== ''
                ): ?>
                    <a
                        class="inv-btn inv-btn-secondary"
                        href="<?= inv_e(
                            inv_url('inventario/index.php')
                        ) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>

            <span class="inv-count">
                <?= count($productos) ?>
                producto<?= count($productos) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($mensajeExito !== ''): ?>
            <div class="inv-alert inv-alert-success">
                ✅ <?= inv_e($mensajeExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div class="inv-alert inv-alert-error">
                ⚠️ <?= inv_e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="inv-content">
            <?php if ($productos !== []): ?>
                <div class="inv-table-wrapper">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <?php if (isset($columnas['codigo'])): ?>
                                    <th>Código</th>
                                <?php endif; ?>

                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Stock mínimo</th>
                                <th>Precio</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <?php
                                $stock = (int) (
                                    $producto['stock'] ?? 0
                                );
                                $stockMinimo = (int) (
                                    $producto['stock_minimo'] ?? 0
                                );

                                $estado = isset($columnas['estado'])
                                    ? (string) (
                                        $producto['estado']
                                        ?? 'disponible'
                                    )
                                    : (
                                        $stock <= 0
                                            ? 'agotado'
                                            : 'disponible'
                                    );

                                if ($estado === 'inactivo') {
                                    $claseEstado = 'inv-badge-off';
                                } elseif ($stock <= 0) {
                                    $claseEstado = 'inv-badge-out';
                                    $estadoTexto = 'Agotado';
                                } elseif ($stock <= $stockMinimo) {
                                    $claseEstado = 'inv-badge-low';
                                    $estadoTexto = 'Bajo stock';
                                } else {
                                    $claseEstado = 'inv-badge-ok';
                                    $estadoTexto = ucfirst($estado);
                                }

                                if (!isset($estadoTexto)) {
                                    $estadoTexto = ucfirst($estado);
                                }

                                $precio = $precioColumna !== null
                                    ? (
                                        $producto[$precioColumna]
                                        ?? 0
                                    )
                                    : 0;
                                ?>
                                <tr>
                                    <?php if (
                                        isset($columnas['codigo'])
                                    ): ?>
                                        <td>
                                            <span class="inv-primary">
                                                <?= inv_e(
                                                    $producto['codigo']
                                                    ?? '—'
                                                ) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <span class="inv-primary">
                                            <?= inv_e(
                                                $producto['nombre']
                                                ?? 'Producto'
                                            ) ?>
                                        </span>

                                        <?php if (
                                            isset(
                                                $columnas['descripcion']
                                            )
                                        ): ?>
                                            <span class="inv-secondary">
                                                <?= inv_e(
                                                    mb_strimwidth(
                                                        (string) (
                                                            $producto[
                                                                'descripcion'
                                                            ] ?? ''
                                                        ),
                                                        0,
                                                        60,
                                                        '…',
                                                        'UTF-8'
                                                    )
                                                ) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= inv_e(
                                            $producto['categoria']
                                            ?? 'Sin categoría'
                                        ) ?>
                                    </td>

                                    <td>
                                        <strong><?= $stock ?></strong>
                                    </td>

                                    <td><?= $stockMinimo ?></td>

                                    <td>
                                        <?= inv_e(inv_dinero($precio)) ?>
                                    </td>

                                    <td>
                                        <?= inv_e(
                                            inv_fecha_visible(
                                                $producto[
                                                    'fecha_vencimiento'
                                                ] ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <span
                                            class="
                                                inv-badge
                                                <?= inv_e(
                                                    $claseEstado
                                                ) ?>
                                            "
                                        >
                                            <?= inv_e($estadoTexto) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="inv-actions">
                                            <a
                                                class="
                                                    inv-action
                                                    inv-action-view
                                                "
                                                href="<?= inv_e(
                                                    inv_url(
                                                        'inventario/ver.php?id=' .
                                                        (int) (
                                                            $producto['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                👁 Ver
                                            </a>

                                            <a
                                                class="
                                                    inv-action
                                                    inv-action-edit
                                                "
                                                href="<?= inv_e(
                                                    inv_url(
                                                        'inventario/editar.php?id=' .
                                                        (int) (
                                                            $producto['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                ✏️ Editar
                                            </a>

                                            <a
                                                class="
                                                    inv-action
                                                    inv-action-delete
                                                "
                                                href="<?= inv_e(
                                                    inv_url(
                                                        'inventario/eliminar.php?id=' .
                                                        (int) (
                                                            $producto['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php unset($estadoTexto); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="inv-empty">
                    <span>📦</span>

                    <h2>No hay productos registrados</h2>

                    <p>
                        Registra el primer producto del inventario.
                    </p>

                    <a
                        class="inv-btn inv-btn-primary"
                        href="<?= inv_e(
                            inv_url('inventario/crear.php')
                        ) ?>"
                    >
                        ＋ Registrar producto
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>