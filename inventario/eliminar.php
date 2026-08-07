<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

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

$csrfClave = 'csrf_eliminar_inventario';
$csrfToken = inv_csrf_token($csrfClave);

$producto = null;
$mensajeError = '';

try {
    $consulta = $pdo->prepare(
        'SELECT id, nombre, categoria, stock
         FROM inventario
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([':id' => $id]);

    $producto = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error cargando producto para eliminar: ' .
        $error->getMessage()
    );
}

if (!is_array($producto)) {
    inv_flash(
        'error',
        'El producto no existe o ya fue eliminado.'
    );

    inv_redirigir('inventario/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!inv_csrf_valido($csrfClave, $tokenFormulario)) {
        $mensajeError =
            'La sesión del formulario expiró. ' .
            'Actualiza la página.';
    } else {
        try {
            $eliminar = $pdo->prepare(
                'DELETE FROM inventario
                 WHERE id = :id
                 LIMIT 1'
            );

            $eliminar->execute([':id' => $id]);

            if ($eliminar->rowCount() < 1) {
                throw new RuntimeException(
                    'El producto ya no existe.'
                );
            }

            inv_regenerar_csrf($csrfClave);

            inv_flash(
                'success',
                'Producto eliminado correctamente.'
            );

            inv_redirigir('inventario/index.php');
        } catch (PDOException $error) {
            error_log(
                'Error eliminando producto: ' .
                $error->getMessage()
            );

            $mensajeError =
                $error->getCode() === '23000'
                    ? 'No se puede eliminar porque el producto ' .
                      'está relacionado con otros registros.'
                    : 'No se pudo eliminar el producto.';
        } catch (Throwable $error) {
            error_log(
                'Error inesperado eliminando producto: ' .
                $error->getMessage()
            );

            $mensajeError = $error->getMessage();
        }
    }
}

$pageTitle = 'Eliminar producto';
$activePage = 'inventario';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="inv-page">
    <section class="inv-panel">
        <header class="inv-header">
            <div class="inv-header-copy">
                <h1>🗑️ Eliminar producto</h1>

                <p>
                    Confirma la eliminación definitiva del producto.
                </p>
            </div>
        </header>

        <?php if ($mensajeError !== ''): ?>
            <div class="inv-alert inv-alert-error">
                ⚠️ <?= inv_e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="inv-content">
            <div class="inv-detail-grid">
                <article class="inv-detail">
                    <span>Producto</span>
                    <strong>
                        <?= inv_e(
                            $producto['nombre'] ?? 'No registrado'
                        ) ?>
                    </strong>
                </article>

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
                    <span>Identificador</span>
                    <strong>#<?= $id ?></strong>
                </article>
            </div>

            <div
                class="inv-alert inv-alert-error"
                style="margin:20px 0 0;"
            >
                Esta acción no se puede deshacer.
            </div>

            <form
                method="POST"
                onsubmit="
                    return confirm(
                        '¿Confirmas que deseas eliminar este producto?'
                    );
                "
            >
                <input type="hidden" name="id" value="<?= $id ?>">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= inv_e($csrfToken) ?>"
                >

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
                        class="inv-btn inv-btn-danger"
                    >
                        🗑️ Sí, eliminar
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>
