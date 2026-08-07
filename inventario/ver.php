<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
) ?: 0;

if ($id <= 0) {
    inv_flash('error', 'El producto seleccionado no es válido.');
    inv_redirigir('inventario/index.php');
}

$producto = null;
$movimientos = [];
$columnas = inv_columnas($pdo);
$precioColumna = inv_columna_precio_venta($pdo);
$fechaRegistroColumna =
    inv_columna_fecha_registro($pdo);

try {
    $consulta = $pdo->prepare(
        'SELECT *
         FROM inventario
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([':id' => $id]);

    $producto = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error cargando detalle del producto: ' .
        $error->getMessage()
    );
}

if (!is_array($producto)) {
    inv_flash('error', 'El producto no existe.');
    inv_redirigir('inventario/index.php');
}

if (inv_tabla_existe($pdo, 'movimientos_inventario')) {
    try {
        $consultaMovimientos = $pdo->prepare(
            'SELECT *
             FROM movimientos_inventario
             WHERE producto_id = :producto_id
             ORDER BY id DESC
             LIMIT 20'
        );

        $consultaMovimientos->execute([
            ':producto_id' => $id,
        ]);

        $movimientos = $consultaMovimientos->fetchAll(
            PDO::FETCH_ASSOC
        );
    } catch (Throwable $error) {
        error_log(
            'Error cargando movimientos de inventario: ' .
            $error->getMessage()
        );
    }
}

$precio = $precioColumna !== null
    ? ($producto[$precioColumna] ?? 0)
    : 0;

$pageTitle = 'Detalle de producto';
$activePage = 'inventario';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="inv-page">
    <section class="inv-panel">
        <header class="inv-header">
            <div class="inv-header-copy">
                <h1>
                    📦 <?= inv_e(
                        $producto['nombre'] ?? 'Producto'
                    ) ?>
                </h1>

                <p>
                    Información completa del producto #<?= $id ?>.
                </p>
            </div>

            <div class="inv-header-actions">
                <a
                    class="inv-btn inv-btn-warning"
                    href="<?= inv_e(
                        inv_url(
                            'inventario/editar.php?id=' . $id
                        )
                    ) ?>"
                >
                    ✏️ Editar
                </a>

                <a
                    class="inv-btn inv-btn-secondary"
                    href="<?= inv_e(
                        inv_url('inventario/index.php')
                    ) ?>"
                >
                    Volver
                </a>
            </div>
        </header>

        <div class="inv-content">
            <div class="inv-detail-grid">
                <?php if (isset($columnas['codigo'])): ?>
                    <article class="inv-detail">
                        <span>Código</span>
                        <strong>
                            <?= inv_e(
                                $producto['codigo'] ?? 'No registrado'
                            ) ?>
                        </strong>
                    </article>
                <?php endif; ?>

                <article class="inv-detail">
                    <span>Categoría</span>
                    <strong>
                        <?= inv_e(
                            $producto['categoria']
                            ?? 'No registrada'
                        ) ?>
                    </strong>
                </article>

                <article class="inv-detail">
                    <span>Stock actual</span>
                    <strong>
                        <?= (int) ($producto['stock'] ?? 0) ?>
                    </strong>
                </article>

                <article class="inv-detail">
                    <span>Stock mínimo</span>
                    <strong>
                        <?= (int) (
                            $producto['stock_minimo'] ?? 0
                        ) ?>
                    </strong>
                </article>

                <article class="inv-detail">
                    <span>Precio de venta</span>
                    <strong><?= inv_e(inv_dinero($precio)) ?></strong>
                </article>

                <?php if (
                    isset($columnas['precio_compra'])
                ): ?>
                    <article class="inv-detail">
                        <span>Precio de compra</span>
                        <strong>
                            <?= inv_e(
                                inv_dinero(
                                    $producto['precio_compra']
                                    ?? 0
                                )
                            ) ?>
                        </strong>
                    </article>
                <?php endif; ?>

                <article class="inv-detail">
                    <span>Fecha de vencimiento</span>
                    <strong>
                        <?= inv_e(
                            inv_fecha_visible(
                                $producto[
                                    'fecha_vencimiento'
                                ] ?? ''
                            )
                        ) ?>
                    </strong>
                </article>

                <?php if (isset($columnas['estado'])): ?>
                    <article class="inv-detail">
                        <span>Estado</span>
                        <strong>
                            <?= inv_e(
                                ucfirst(
                                    (string) (
                                        $producto['estado']
                                        ?? 'disponible'
                                    )
                                )
                            ) ?>
                        </strong>
                    </article>
                <?php endif; ?>

                <?php if (
                    $fechaRegistroColumna !== null
                ): ?>
                    <article class="inv-detail">
                        <span>Fecha de registro</span>
                        <strong>
                            <?= inv_e(
                                $producto[
                                    $fechaRegistroColumna
                                ] ?? 'No registrada'
                            ) ?>
                        </strong>
                    </article>
                <?php endif; ?>

                <?php if (
                    isset($columnas['descripcion'])
                ): ?>
                    <article
                        class="
                            inv-detail
                            inv-detail-full
                        "
                    >
                        <span>Descripción</span>
                        <p>
                            <?= inv_e(
                                $producto['descripcion']
                                ?? 'Sin descripción'
                            ) ?>
                        </p>
                    </article>
                <?php endif; ?>
            </div>

            <?php if ($movimientos !== []): ?>
                <h2 style="
                    margin:28px 0 14px;
                    color:#0f2747;
                    font-size:20px;
                ">
                    Historial de movimientos
                </h2>

                <div class="inv-table-wrapper">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock anterior</th>
                                <th>Stock nuevo</th>
                                <th>Motivo</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach (
                                $movimientos
                                as $movimiento
                            ): ?>
                                <tr>
                                    <td>
                                        <?= inv_e(
                                            ucfirst(
                                                (string) (
                                                    $movimiento['tipo']
                                                    ?? ''
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) (
                                            $movimiento['cantidad']
                                            ?? 0
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) (
                                            $movimiento[
                                                'stock_anterior'
                                            ] ?? 0
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) (
                                            $movimiento['stock_nuevo']
                                            ?? 0
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= inv_e(
                                            $movimiento['motivo']
                                            ?? 'Sin motivo'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= inv_e(
                                            $movimiento[
                                                'fecha_movimiento'
                                            ] ?? ''
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>