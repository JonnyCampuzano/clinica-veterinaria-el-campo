<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$columnas = inv_columnas($pdo);
$csrfClave = 'csrf_crear_inventario';
$csrfToken = inv_csrf_token($csrfClave);

$datos = [
    'codigo' => '',
    'nombre' => '',
    'categoria' => '',
    'descripcion' => '',
    'stock' => '0',
    'stock_minimo' => '5',
    'precio' => '0.00',
    'precio_compra' => '0.00',
    'precio_venta' => '0.00',
    'fecha_vencimiento' => '',
    'estado' => 'disponible',
];

$errores = [];

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

    $stock = inv_entero_no_negativo(
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
        $datos['fecha_vencimiento'] !== ''
        && !DateTime::createFromFormat(
            'Y-m-d',
            $datos['fecha_vencimiento']
        )
    ) {
        $errores[] =
            'La fecha de vencimiento no es válida.';
    }

    if (
        isset($columnas['estado'])
        && !in_array(
            $datos['estado'],
            ['disponible', 'agotado', 'inactivo'],
            true
        )
    ) {
        $errores[] = 'Selecciona un estado válido.';
    }

    if (
        isset($columnas['codigo'])
        && $datos['codigo'] !== ''
    ) {
        try {
            $verificarCodigo = $pdo->prepare(
                'SELECT id
                 FROM inventario
                 WHERE codigo = :codigo
                 LIMIT 1'
            );

            $verificarCodigo->execute([
                ':codigo' => $datos['codigo'],
            ]);

            if ($verificarCodigo->fetchColumn()) {
                $errores[] =
                    'Ya existe un producto con ese código.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando código de inventario: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo verificar el código.';
        }
    }

    if ($errores === []) {
        try {
            $campos = [
                'nombre',
                'categoria',
                'stock',
                'stock_minimo',
                'fecha_vencimiento',
            ];

            $valores = [
                ':nombre' => $datos['nombre'],
                ':categoria' => $datos['categoria'],
                ':stock' => $stock,
                ':stock_minimo' => $stockMinimo,
                ':fecha_vencimiento' =>
                    $datos['fecha_vencimiento'] !== ''
                        ? $datos['fecha_vencimiento']
                        : null,
            ];

            if (isset($columnas['codigo'])) {
                $campos[] = 'codigo';
                $valores[':codigo'] = $datos['codigo'];
            }

            if (isset($columnas['descripcion'])) {
                $campos[] = 'descripcion';
                $valores[':descripcion'] =
                    $datos['descripcion'] !== ''
                        ? $datos['descripcion']
                        : null;
            }

            if (isset($columnas['precio'])) {
                $campos[] = 'precio';
                $valores[':precio'] = $precioSimple;
            }

            if (isset($columnas['precio_compra'])) {
                $campos[] = 'precio_compra';
                $valores[':precio_compra'] = $precioCompra;
            }

            if (isset($columnas['precio_venta'])) {
                $campos[] = 'precio_venta';
                $valores[':precio_venta'] = $precioVenta;
            }

            if (isset($columnas['estado'])) {
                $campos[] = 'estado';
                $valores[':estado'] =
                    inv_estado_segun_stock(
                        $stock,
                        $datos['estado']
                    );
            }

            $marcadores = array_map(
                static fn (string $campo): string =>
                    ':' . $campo,
                $campos
            );

            $sql =
                'INSERT INTO inventario (' .
                implode(', ', $campos) .
                ') VALUES (' .
                implode(', ', $marcadores) .
                ')';

            $pdo->beginTransaction();

            $insertar = $pdo->prepare($sql);
            $insertar->execute($valores);

            $productoId = (int) $pdo->lastInsertId();

            inv_registrar_movimiento(
                $pdo,
                $productoId,
                0,
                $stock,
                'Registro inicial del producto'
            );

            $pdo->commit();

            inv_regenerar_csrf($csrfClave);

            inv_flash(
                'success',
                'Producto registrado correctamente.'
            );

            inv_redirigir('inventario/index.php');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error registrando producto: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo registrar el producto. ' .
                'Revisa la estructura de la tabla inventario.';
        }
    }
}

$pageTitle = 'Registrar producto';
$activePage = 'inventario';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="inv-page">
    <section class="inv-panel">
        <header class="inv-header">
            <div class="inv-header-copy">
                <h1>📦 Registrar producto</h1>

                <p>
                    Completa la información del nuevo producto.
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
                            placeholder="Ejemplo: MED-001"
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
                        placeholder="Medicamento, vacuna, alimento..."
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

                        <span class="inv-help">
                            Si el stock queda en cero, el estado
                            cambia automáticamente a agotado.
                        </span>
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
                    class="inv-btn inv-btn-primary"
                >
                    💾 Registrar producto
                </button>
            </div>
        </form>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>
