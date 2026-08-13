<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/*
|--------------------------------------------------------------------------
| PROTECCIÓN REAL DE LA URL
|--------------------------------------------------------------------------
| Solo los roles con permiso inventario.editar pueden modificar productos.
|--------------------------------------------------------------------------
*/

require_permission('inventario.editar');


$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['id'] ?? 0)
    : (
        filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        ) ?: 0
    );

if ($id <= 0) {
    inv_flash('error', 'El producto seleccionado no es válido.');
    inv_redirigir('inventario/index.php');
}

$columnas = inv_columnas($pdo);
$csrfClave = 'csrf_editar_inventario';
$csrfToken = inv_csrf_token($csrfClave);

$producto = null;
$errores = [];

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
        'Error cargando producto para editar: ' .
        $error->getMessage()
    );
}

if (!is_array($producto)) {
    inv_flash('error', 'El producto no existe.');
    inv_redirigir('inventario/index.php');
}

$datos = [
    'codigo' => (string) ($producto['codigo'] ?? ''),
    'nombre' => (string) ($producto['nombre'] ?? ''),
    'categoria' =>
        (string) ($producto['categoria'] ?? ''),
    'descripcion' =>
        (string) ($producto['descripcion'] ?? ''),
    'stock' => (string) ($producto['stock'] ?? '0'),
    'stock_minimo' =>
        (string) ($producto['stock_minimo'] ?? '5'),
    'precio' => (string) ($producto['precio'] ?? '0.00'),
    'precio_compra' =>
        (string) ($producto['precio_compra'] ?? '0.00'),
    'precio_venta' =>
        (string) ($producto['precio_venta'] ?? '0.00'),
    'fecha_vencimiento' =>
        (string) ($producto['fecha_vencimiento'] ?? ''),
    'estado' =>
        (string) ($producto['estado'] ?? 'disponible'),
];

$stockAnterior = (int) ($producto['stock'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim(
            (string) ($_POST[$campo] ?? $valor)
        );
    }

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!inv_csrf_valido($csrfClave, $tokenFormulario)) {
        $errores[] =
            'La sesión del formulario expiró. ' .
            'Actualiza la página.';
    }

    if (
        isset($columnas['codigo'])
        && $datos['codigo'] === ''
    ) {
        $errores[] = 'Ingresa el código del producto.';
    }

    if ($datos['nombre'] === '') {
        $errores[] = 'Ingresa el nombre del producto.';
    }

    if ($datos['categoria'] === '') {
        $errores[] = 'Ingresa la categoría.';
    }

    $stockNuevo = inv_entero_no_negativo(
        $datos['stock'],
        'El stock',
        $errores
    );

    $stockMinimo = inv_entero_no_negativo(
        $datos['stock_minimo'],
        'El stock mínimo',
        $errores
    );

    $precioSimple = inv_decimal(
        $datos['precio'],
        'El precio',
        $errores
    );

    $precioCompra = inv_decimal(
        $datos['precio_compra'],
        'El precio de compra',
        $errores
    );

    $precioVenta = inv_decimal(
        $datos['precio_venta'],
        'El precio de venta',
        $errores
    );

    if (
        isset($columnas['codigo'])
        && $datos['codigo'] !== ''
    ) {
        try {
            $verificar = $pdo->prepare(
                'SELECT id
                 FROM inventario
                 WHERE codigo = :codigo
                   AND id <> :id
                 LIMIT 1'
            );

            $verificar->execute([
                ':codigo' => $datos['codigo'],
                ':id' => $id,
            ]);

            if ($verificar->fetchColumn()) {
                $errores[] =
                    'Ya existe otro producto con ese código.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando código al editar: ' .
                $error->getMessage()
            );
        }
    }

    if ($errores === []) {
        try {
            $asignaciones = [
                'nombre = :nombre',
                'categoria = :categoria',
                'stock = :stock',
                'stock_minimo = :stock_minimo',
                'fecha_vencimiento = :fecha_vencimiento',
            ];

            $valores = [
                ':nombre' => $datos['nombre'],
                ':categoria' => $datos['categoria'],
                ':stock' => $stockNuevo,
                ':stock_minimo' => $stockMinimo,
                ':fecha_vencimiento' =>
                    $datos['fecha_vencimiento'] !== ''
                        ? $datos['fecha_vencimiento']
                        : null,
                ':id' => $id,
            ];

            if (isset($columnas['codigo'])) {
                $asignaciones[] = 'codigo = :codigo';
                $valores[':codigo'] = $datos['codigo'];
            }

            if (isset($columnas['descripcion'])) {
                $asignaciones[] =
                    'descripcion = :descripcion';
                $valores[':descripcion'] =
                    $datos['descripcion'] !== ''
                        ? $datos['descripcion']
                        : null;
            }

            if (isset($columnas['precio'])) {
                $asignaciones[] = 'precio = :precio';
                $valores[':precio'] = $precioSimple;
            }

            if (isset($columnas['precio_compra'])) {
                $asignaciones[] =
                    'precio_compra = :precio_compra';
                $valores[':precio_compra'] =
                    $precioCompra;
            }

            if (isset($columnas['precio_venta'])) {
                $asignaciones[] =
                    'precio_venta = :precio_venta';
                $valores[':precio_venta'] =
                    $precioVenta;
            }

            if (isset($columnas['estado'])) {
                $asignaciones[] = 'estado = :estado';
                $valores[':estado'] =
                    inv_estado_segun_stock(
                        $stockNuevo,
                        $datos['estado']
                    );
            }

            $sql =
                'UPDATE inventario
                 SET ' .
                 implode(', ', $asignaciones) .
                ' WHERE id = :id
                  LIMIT 1';

            $pdo->beginTransaction();

            $actualizar = $pdo->prepare($sql);
            $actualizar->execute($valores);

            inv_registrar_movimiento(
                $pdo,
                $id,
                $stockAnterior,
                $stockNuevo,
                'Actualización manual del producto'
            );

            $pdo->commit();

            inv_regenerar_csrf($csrfClave);

            inv_flash(
                'success',
                'Producto actualizado correctamente.'
            );

            inv_redirigir('inventario/index.php');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error actualizando producto: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo actualizar el producto.';
        }
    }
}

$pageTitle = 'Editar producto';
$activePage = 'inventario';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="inv-page">
    <section class="inv-panel">
        <header class="inv-header">
            <div class="inv-header-copy">
                <h1>✏️ Editar producto</h1>

                <p>
                    Actualiza la información del producto #<?= $id ?>.
                </p>
            </div>

            <div class="inv-header-actions">
                <a
                    class="inv-btn inv-btn-secondary"
                    href="<?= inv_e(
                        inv_url('inventario/index.php')
                    ) ?>"
                >
                    ← Volver
                </a>
            </div>
        </header>

        <?php if ($errores !== []): ?>
            <div class="inv-alert inv-alert-error">
                <strong>Revisa la información:</strong>

                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= inv_e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            class="inv-form"
            autocomplete="off"
        >
            <input type="hidden" name="id" value="<?= $id ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= inv_e($csrfToken) ?>"
            >

            <div class="inv-form-grid">
                <?php if (isset($columnas['codigo'])): ?>
                    <div class="inv-field">
                        <label for="codigo">Código</label>

                        <input
                            type="text"
                            id="codigo"
                            name="codigo"
                            maxlength="50"
                            value="<?= inv_e($datos['codigo']) ?>"
                            required
                        >
                    </div>
                <?php endif; ?>

                <div class="inv-field">
                    <label for="nombre">Nombre</label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="150"
                        value="<?= inv_e($datos['nombre']) ?>"
                        required
                    >
                </div>

                <div class="inv-field">
                    <label for="categoria">Categoría</label>

                    <input
                        type="text"
                        id="categoria"
                        name="categoria"
                        maxlength="100"
                        value="<?= inv_e($datos['categoria']) ?>"
                        required
                    >
                </div>

                <div class="inv-field">
                    <label for="stock">Stock actual</label>

                    <input
                        type="number"
                        id="stock"
                        name="stock"
                        min="0"
                        step="1"
                        value="<?= inv_e($datos['stock']) ?>"
                        required
                    >
                </div>

                <div class="inv-field">
                    <label for="stock_minimo">Stock mínimo</label>

                    <input
                        type="number"
                        id="stock_minimo"
                        name="stock_minimo"
                        min="0"
                        step="1"
                        value="<?= inv_e(
                            $datos['stock_minimo']
                        ) ?>"
                        required
                    >
                </div>

                <?php if (isset($columnas['precio'])): ?>
                    <div class="inv-field">
                        <label for="precio">Precio</label>

                        <input
                            type="number"
                            id="precio"
                            name="precio"
                            min="0"
                            step="0.01"
                            value="<?= inv_e($datos['precio']) ?>"
                            required
                        >
                    </div>
                <?php endif; ?>

                <?php if (
                    isset($columnas['precio_compra'])
                ): ?>
                    <div class="inv-field">
                        <label for="precio_compra">
                            Precio de compra
                        </label>

                        <input
                            type="number"
                            id="precio_compra"
                            name="precio_compra"
                            min="0"
                            step="0.01"
                            value="<?= inv_e(
                                $datos['precio_compra']
                            ) ?>"
                            required
                        >
                    </div>
                <?php endif; ?>

                <?php if (
                    isset($columnas['precio_venta'])
                ): ?>
                    <div class="inv-field">
                        <label for="precio_venta">
                            Precio de venta
                        </label>

                        <input
                            type="number"
                            id="precio_venta"
                            name="precio_venta"
                            min="0"
                            step="0.01"
                            value="<?= inv_e(
                                $datos['precio_venta']
                            ) ?>"
                            required
                        >
                    </div>
                <?php endif; ?>

                <div class="inv-field">
                    <label for="fecha_vencimiento">
                        Fecha de vencimiento
                    </label>

                    <input
                        type="date"
                        id="fecha_vencimiento"
                        name="fecha_vencimiento"
                        value="<?= inv_e(
                            $datos['fecha_vencimiento']
                        ) ?>"
                    >
                </div>

                <?php if (isset($columnas['estado'])): ?>
                    <div class="inv-field">
                        <label for="estado">Estado</label>

                        <select
                            id="estado"
                            name="estado"
                            required
                        >
                            <option
                                value="disponible"
                                <?=
                                    $datos['estado'] ===
                                    'disponible'
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                Disponible
                            </option>

                            <option
                                value="inactivo"
                                <?=
                                    $datos['estado'] ===
                                    'inactivo'
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                Inactivo
                            </option>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if (
                    isset($columnas['descripcion'])
                ): ?>
                    <div class="inv-field inv-field-full">
                        <label for="descripcion">
                            Descripción
                        </label>

                        <textarea
                            id="descripcion"
                            name="descripcion"
                            maxlength="2000"
                        ><?= inv_e(
                            $datos['descripcion']
                        ) ?></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <div class="inv-form-actions">
                <a
                    class="inv-btn inv-btn-secondary"
                    href="<?= inv_e(
                        inv_url('inventario/index.php')
                    ) ?>"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="inv-btn inv-btn-warning"
                >
                    💾 Guardar cambios
                </button>
            </div>
        </form>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>