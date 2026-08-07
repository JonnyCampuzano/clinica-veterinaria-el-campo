<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ((int) ($_POST['id'] ?? 0))
    : (filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0);

if ($id <= 0) {
    hc_flash('error', 'El identificador no es válido.');
    hc_redirigir('consultas/index.php');
}

$csrfClave = 'csrf_eliminar_historia';
$csrfToken = hc_csrf_token($csrfClave);

$historia = null;
$mensajeError = '';

try {
    $consulta = $pdo->prepare(
        'SELECT
            co.id,
            co.fecha,
            co.motivo,
            co.diagnostico,
            m.nombre AS mascota_nombre,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos
         FROM historias_clinicas co
         INNER JOIN mascotas m
            ON m.id = co.mascota_id
         INNER JOIN clientes c
            ON c.id = m.cliente_id
         WHERE co.id = :id
         LIMIT 1'
    );

    $consulta->execute([':id' => $id]);

    $historia = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error cargando historia clínica para eliminar: ' .
        $error->getMessage()
    );
}

if (!is_array($historia)) {
    hc_flash(
        'error',
        'La historia clínica no existe o ya fue eliminada.'
    );

    hc_redirigir('consultas/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!hc_csrf_valido($csrfClave, $tokenFormulario)) {
        $mensajeError =
            'La sesión del formulario expiró. ' .
            'Actualiza la página.';
    } else {
        try {
            $eliminar = $pdo->prepare(
                'DELETE FROM historias_clinicas
                 WHERE id = :id
                 LIMIT 1'
            );

            $eliminar->execute([':id' => $id]);

            if ($eliminar->rowCount() < 1) {
                throw new RuntimeException(
                    'El registro ya no existe.'
                );
            }

            hc_regenerar_csrf($csrfClave);

            hc_flash(
                'success',
                'Historia clínica eliminada correctamente.'
            );

            hc_redirigir('consultas/index.php');
        } catch (PDOException $error) {
            error_log(
                'Error eliminando historia clínica: ' .
                $error->getMessage()
            );

            $mensajeError =
                $error->getCode() === '23000'
                    ? 'No se puede eliminar porque el registro ' .
                      'tiene información relacionada.'
                    : 'No se pudo eliminar la historia clínica.';
        } catch (Throwable $error) {
            error_log(
                'Error inesperado eliminando historia: ' .
                $error->getMessage()
            );

            $mensajeError = $error->getMessage();
        }
    }
}

$propietario = trim(
    (string) ($historia['cliente_nombres'] ?? '') .
    ' ' .
    (string) ($historia['cliente_apellidos'] ?? '')
);

$pageTitle = 'Eliminar historia clínica';
$activePage = 'consultas';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="hc-page">
    <section class="hc-panel">
        <header class="hc-header">
            <div>
                <h1>🗑️ Eliminar historia clínica</h1>
                <p>
                    Confirma la eliminación definitiva del registro #<?= $id ?>.
                </p>
            </div>
        </header>

        <?php if ($mensajeError !== ''): ?>
            <div class="hc-alert hc-alert-error">
                ⚠️ <?= hc_e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="hc-content">
            <div class="hc-detail-grid">
                <article class="hc-detail">
                    <span>Fecha</span>
                    <strong>
                        <?= hc_e(
                            hc_fecha_visible($historia['fecha'] ?? '')
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail">
                    <span>Mascota</span>
                    <strong>
                        <?= hc_e(
                            $historia['mascota_nombre']
                            ?? 'No registrada'
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Propietario</span>
                    <strong>
                        <?= hc_e(
                            $propietario !== ''
                                ? $propietario
                                : 'No registrado'
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Motivo</span>
                    <p><?= hc_e($historia['motivo'] ?? '') ?></p>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Diagnóstico</span>
                    <p><?= hc_e($historia['diagnostico'] ?? '') ?></p>
                </article>
            </div>

            <div class="hc-alert hc-alert-error" style="margin:20px 0 0;">
                Esta acción no se puede deshacer.
            </div>

            <form
                method="POST"
                onsubmit="
                    return confirm(
                        '¿Confirmas que deseas eliminar esta historia clínica?'
                    );
                "
            >
                <input type="hidden" name="id" value="<?= $id ?>">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= hc_e($csrfToken) ?>"
                >

                <div class="hc-form-actions">
                    <a
                        class="hc-btn hc-btn-secondary"
                        href="<?= hc_e(
                            hc_url('consultas/index.php')
                        ) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="hc-btn hc-btn-danger"
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
