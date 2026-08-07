<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
) ?: 0;

if ($id <= 0) {
    hc_flash('error', 'El identificador no es válido.');
    hc_redirigir('consultas/index.php');
}

$historia = null;

try {
    $consulta = $pdo->prepare(
        'SELECT
            co.id,
            co.fecha,
            co.motivo,
            co.diagnostico,
            co.tratamiento,
            co.peso,
            co.temperatura,
            co.proxima_cita,
            co.created_at,
            m.nombre AS mascota_nombre,
            m.especie AS mascota_especie,
            m.raza AS mascota_raza,
            m.sexo AS mascota_sexo,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula,
            c.telefono AS cliente_telefono,
            c.email AS cliente_email,
            u.nombre AS usuario_nombre,
            u.rol AS usuario_rol
         FROM historias_clinicas co
         INNER JOIN mascotas m
            ON m.id = co.mascota_id
         INNER JOIN clientes c
            ON c.id = m.cliente_id
         LEFT JOIN usuarios u
            ON u.id = co.usuario_id
         WHERE co.id = :id
         LIMIT 1'
    );

    $consulta->execute([':id' => $id]);

    $historia = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error consultando historia clínica: ' .
        $error->getMessage()
    );
}

if (!is_array($historia)) {
    hc_flash(
        'error',
        'La historia clínica solicitada no existe.'
    );

    hc_redirigir('consultas/index.php');
}

$propietario = trim(
    (string) ($historia['cliente_nombres'] ?? '') .
    ' ' .
    (string) ($historia['cliente_apellidos'] ?? '')
);

$pageTitle = 'Detalle de historia clínica';
$activePage = 'consultas';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="hc-page">
    <section class="hc-panel">
        <header class="hc-header">
            <div>
                <h1>📄 Historia clínica #<?= $id ?></h1>
                <p>
                    Información médica completa de la atención registrada.
                </p>
            </div>

            <div class="hc-actions">
                <a
                    class="hc-btn hc-btn-warning"
                    href="<?= hc_e(
                        hc_url('consultas/editar.php?id=' . $id)
                    ) ?>"
                >
                    ✏️ Editar
                </a>

                <a
                    class="hc-btn hc-btn-secondary"
                    href="<?= hc_e(hc_url('consultas/index.php')) ?>"
                >
                    Volver
                </a>
            </div>
        </header>

        <div class="hc-content">
            <div class="hc-detail-grid">
                <article class="hc-detail">
                    <span>Fecha de atención</span>
                    <strong>
                        <?= hc_e(
                            hc_fecha_visible($historia['fecha'] ?? '')
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail">
                    <span>Próxima cita</span>
                    <strong>
                        <?= hc_e(
                            hc_fecha_visible(
                                $historia['proxima_cita'] ?? ''
                            )
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
                    <p>
                        <?= hc_e(
                            trim(
                                (string) (
                                    $historia['mascota_especie'] ?? ''
                                ) .
                                ' · ' .
                                (string) (
                                    $historia['mascota_raza'] ?? ''
                                ) .
                                ' · ' .
                                (string) (
                                    $historia['mascota_sexo'] ?? ''
                                )
                            )
                        ) ?>
                    </p>
                </article>

                <article class="hc-detail">
                    <span>Propietario</span>
                    <strong>
                        <?= hc_e(
                            $propietario !== ''
                                ? $propietario
                                : 'No registrado'
                        ) ?>
                    </strong>
                    <p>
                        Cédula:
                        <?= hc_e(
                            $historia['cliente_cedula']
                            ?? 'No registrada'
                        ) ?>
                        · Teléfono:
                        <?= hc_e(
                            $historia['cliente_telefono']
                            ?? 'No registrado'
                        ) ?>
                    </p>
                </article>

                <article class="hc-detail">
                    <span>Peso</span>
                    <strong>
                        <?= hc_e(
                            hc_numero_visible(
                                $historia['peso'] ?? null,
                                ' kg'
                            )
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail">
                    <span>Temperatura</span>
                    <strong>
                        <?= hc_e(
                            hc_numero_visible(
                                $historia['temperatura'] ?? null,
                                ' °C',
                                1
                            )
                        ) ?>
                    </strong>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Motivo de consulta</span>
                    <p><?= hc_e($historia['motivo'] ?? '') ?></p>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Diagnóstico</span>
                    <p><?= hc_e($historia['diagnostico'] ?? '') ?></p>
                </article>

                <article class="hc-detail hc-detail-full">
                    <span>Tratamiento</span>
                    <p><?= hc_e($historia['tratamiento'] ?? '') ?></p>
                </article>

                <article class="hc-detail">
                    <span>Profesional responsable</span>
                    <strong>
                        <?= hc_e(
                            $historia['usuario_nombre']
                            ?? 'No registrado'
                        ) ?>
                    </strong>
                    <p>
                        <?= hc_e(
                            $historia['usuario_rol'] ?? ''
                        ) ?>
                    </p>
                </article>

                <article class="hc-detail">
                    <span>Fecha de registro</span>
                    <strong>
                        <?= hc_e(
                            $historia['created_at'] ?? 'No registrada'
                        ) ?>
                    </strong>
                </article>
            </div>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>
