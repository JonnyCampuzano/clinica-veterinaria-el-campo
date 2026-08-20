<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/config/crypto.php';

$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ((int) ($_POST['id'] ?? 0))
    : (filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0);

if ($id <= 0) {
    hc_flash('error', 'El identificador no es válido.');
    hc_redirigir('consultas/index.php');
}

$csrfClave = 'csrf_editar_historia';
$csrfToken = hc_csrf_token($csrfClave);

$errores = [];
$mascotas = [];
$historia = null;

try {
    $consultaMascotas = $pdo->query(
        'SELECT
            m.id,
            m.nombre AS mascota,
            m.especie,
            m.raza,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula
         FROM mascotas m
         INNER JOIN clientes c
            ON c.id = m.cliente_id
         ORDER BY m.nombre ASC'
    );

    $mascotasCifradas = $consultaMascotas->fetchAll(
        PDO::FETCH_ASSOC
    );

    $mascotas = [];

    foreach ($mascotasCifradas as $mascota) {
        try {
            $mascota['cliente_nombres'] = decrypt_personal(
                $mascota['cliente_nombres'] ?? null
            );

            $mascota['cliente_apellidos'] = decrypt_personal(
                $mascota['cliente_apellidos'] ?? null
            );

            $mascota['cliente_cedula'] = decrypt_personal(
                $mascota['cliente_cedula'] ?? null
            );

            $mascotas[] = $mascota;
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Error descifrando propietario de mascota ID ' .
                (int) ($mascota['id'] ?? 0) .
                ' al editar historia clínica: ' .
                $errorDescifrado->getMessage()
            );
        }
    }

    /*
     * Como los nombres están cifrados en MySQL,
     * el orden se realiza después de descifrarlos.
     */
    usort(
        $mascotas,
        static function (array $a, array $b): int {
            $propietarioA = trim(
                (string) ($a['cliente_nombres'] ?? '') .
                ' ' .
                (string) ($a['cliente_apellidos'] ?? '')
            );

            $propietarioB = trim(
                (string) ($b['cliente_nombres'] ?? '') .
                ' ' .
                (string) ($b['cliente_apellidos'] ?? '')
            );

            $comparacion = strcasecmp(
                $propietarioA,
                $propietarioB
            );

            if ($comparacion !== 0) {
                return $comparacion;
            }

            return strcasecmp(
                (string) ($a['mascota'] ?? ''),
                (string) ($b['mascota'] ?? '')
            );
        }
    );
} catch (Throwable $error) {
    error_log(
        'Error cargando mascotas para editar historia: ' .
        $error->getMessage()
    );

    $errores[] =
        'No se pudieron cargar las mascotas. ' .
        'Revisa la conexión y la clave de cifrado.';
}

try {
    $consultaHistoria = $pdo->prepare(
        'SELECT
            id,
            mascota_id,
            usuario_id,
            fecha,
            motivo,
            diagnostico,
            tratamiento,
            peso,
            temperatura,
            proxima_cita
         FROM historias_clinicas
         WHERE id = :id
         LIMIT 1'
    );

    $consultaHistoria->execute([':id' => $id]);

    $historia = $consultaHistoria->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error cargando historia clínica para editar: ' .
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

$datos = [
    'mascota_id' => (int) ($historia['mascota_id'] ?? 0),
    'fecha' => (string) ($historia['fecha'] ?? ''),
    'motivo' => (string) ($historia['motivo'] ?? ''),
    'diagnostico' =>
        (string) ($historia['diagnostico'] ?? ''),
    'tratamiento' =>
        (string) ($historia['tratamiento'] ?? ''),
    'peso' => (string) ($historia['peso'] ?? ''),
    'temperatura' =>
        (string) ($historia['temperatura'] ?? ''),
    'proxima_cita' =>
        (string) ($historia['proxima_cita'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'mascota_id' => (int) ($_POST['mascota_id'] ?? 0),
        'fecha' => trim((string) ($_POST['fecha'] ?? '')),
        'motivo' => trim((string) ($_POST['motivo'] ?? '')),
        'diagnostico' => trim(
            (string) ($_POST['diagnostico'] ?? '')
        ),
        'tratamiento' => trim(
            (string) ($_POST['tratamiento'] ?? '')
        ),
        'peso' => trim((string) ($_POST['peso'] ?? '')),
        'temperatura' => trim(
            (string) ($_POST['temperatura'] ?? '')
        ),
        'proxima_cita' => trim(
            (string) ($_POST['proxima_cita'] ?? '')
        ),
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!hc_csrf_valido($csrfClave, $tokenFormulario)) {
        $errores[] =
            'La sesión del formulario expiró. ' .
            'Actualiza la página.';
    }

    if ($datos['mascota_id'] <= 0) {
        $errores[] = 'Selecciona una mascota.';
    }

    if ($datos['fecha'] === '') {
        $errores[] = 'Selecciona la fecha de atención.';
    } elseif ($datos['fecha'] > date('Y-m-d')) {
        $errores[] =
            'La fecha de atención no puede ser futura.';
    }

    if ($datos['motivo'] === '') {
        $errores[] = 'Escribe el motivo de consulta.';
    }

    if ($datos['diagnostico'] === '') {
        $errores[] = 'Escribe el diagnóstico.';
    }

    if ($datos['tratamiento'] === '') {
        $errores[] = 'Escribe el tratamiento.';
    }

    if (
        $datos['proxima_cita'] !== '' &&
        $datos['proxima_cita'] < $datos['fecha']
    ) {
        $errores[] =
            'La próxima cita no puede ser anterior ' .
            'a la fecha de atención.';
    }

    $peso = null;
    $temperatura = null;

    try {
        $peso = hc_decimal_o_null(
            $datos['peso'],
            0.01,
            9999.99
        );
    } catch (InvalidArgumentException) {
        $errores[] =
            'El peso debe ser un número válido mayor que cero.';
    }

    try {
        $temperatura = hc_decimal_o_null(
            $datos['temperatura'],
            25.0,
            50.0
        );
    } catch (InvalidArgumentException) {
        $errores[] =
            'La temperatura debe estar entre 25 y 50 °C.';
    }

    if ($errores === []) {
        try {
            $actualizar = $pdo->prepare(
                'UPDATE historias_clinicas
                 SET
                    mascota_id = :mascota_id,
                    fecha = :fecha,
                    motivo = :motivo,
                    diagnostico = :diagnostico,
                    tratamiento = :tratamiento,
                    peso = :peso,
                    temperatura = :temperatura,
                    proxima_cita = :proxima_cita
                 WHERE id = :id
                 LIMIT 1'
            );

            $actualizar->execute([
                ':mascota_id' => $datos['mascota_id'],
                ':fecha' => $datos['fecha'],
                ':motivo' => $datos['motivo'],
                ':diagnostico' => $datos['diagnostico'],
                ':tratamiento' => $datos['tratamiento'],
                ':peso' => $peso,
                ':temperatura' => $temperatura,
                ':proxima_cita' =>
                    $datos['proxima_cita'] !== ''
                        ? $datos['proxima_cita']
                        : null,
                ':id' => $id,
            ]);

            hc_regenerar_csrf($csrfClave);

            hc_flash(
                'success',
                'Historia clínica actualizada correctamente.'
            );

            hc_redirigir('consultas/index.php');
        } catch (Throwable $error) {
            error_log(
                'Error actualizando historia clínica: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo actualizar la historia clínica.';
        }
    }
}

$pageTitle = 'Editar historia clínica';
$activePage = 'consultas';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="hc-page">
    <section class="hc-panel">
        <header class="hc-header hc-edit-header">
            <div class="hc-header-copy">
                <h1>✏️ Editar historia clínica</h1>

                <p>
                    Actualiza los datos médicos del registro
                    #<?= $id ?>.
                </p>
            </div>

            <div class="hc-header-controls">
                <span class="hc-record-badge">
                    Registro #<?= $id ?>
                </span>

                <a
                    class="hc-btn hc-btn-secondary"
                    href="<?= hc_e(
                        hc_url('consultas/index.php')
                    ) ?>"
                >
                    ← Volver
                </a>
            </div>
        </header>

        <?php if ($errores !== []): ?>
            <div class="hc-alert hc-alert-error">
                <strong>Revisa la información:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= hc_e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="hc-form hc-edit-form" autocomplete="off">
            <input type="hidden" name="id" value="<?= $id ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= hc_e($csrfToken) ?>"
            >

            <div class="hc-form-grid hc-edit-grid">
                <div class="hc-field hc-field-full">
                    <label for="mascota_id">
                        Mascota y propietario
                    </label>

                    <select
                        id="mascota_id"
                        name="mascota_id"
                        required
                    >
                        <?php foreach ($mascotas as $mascota): ?>
                            <?php
                            $propietario = trim(
                                (string) (
                                    $mascota['cliente_nombres'] ?? ''
                                ) .
                                ' ' .
                                (string) (
                                    $mascota['cliente_apellidos'] ?? ''
                                )
                            );

                            $descripcion =
                                $propietario .
                                ' — ' .
                                (string) ($mascota['mascota'] ?? '') .
                                ' (' .
                                (string) ($mascota['especie'] ?? '');

                            $raza = trim(
                                (string) ($mascota['raza'] ?? '')
                            );

                            if ($raza !== '') {
                                $descripcion .= ' / ' . $raza;
                            }

                            $descripcion .= ')';

                            $cedula = trim(
                                (string) (
                                    $mascota['cliente_cedula'] ?? ''
                                )
                            );

                            if ($cedula !== '') {
                                $descripcion .=
                                    ' · Cédula: ' . $cedula;
                            }
                            ?>

                            <option
                                value="<?= (int) ($mascota['id'] ?? 0) ?>"
                                <?=
                                    $datos['mascota_id'] ===
                                    (int) ($mascota['id'] ?? 0)
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= hc_e($descripcion) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hc-field">
                    <label for="fecha">Fecha de atención</label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= hc_e($datos['fecha']) ?>"
                        required
                    >
                </div>

                <div class="hc-field">
                    <label for="proxima_cita">Próxima cita</label>

                    <input
                        type="date"
                        id="proxima_cita"
                        name="proxima_cita"
                        value="<?= hc_e($datos['proxima_cita']) ?>"
                    >
                </div>

                <div class="hc-field">
                    <label for="peso">Peso (kg)</label>

                    <input
                        type="number"
                        id="peso"
                        name="peso"
                        min="0.01"
                        max="9999.99"
                        step="0.01"
                        value="<?= hc_e($datos['peso']) ?>"
                    >
                </div>

                <div class="hc-field">
                    <label for="temperatura">
                        Temperatura (°C)
                    </label>

                    <input
                        type="number"
                        id="temperatura"
                        name="temperatura"
                        min="25"
                        max="50"
                        step="0.1"
                        value="<?= hc_e($datos['temperatura']) ?>"
                    >
                </div>

                <div class="hc-field hc-field-full">
                    <label for="motivo">Motivo</label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        required
                    ><?= hc_e($datos['motivo']) ?></textarea>
                </div>

                <div class="hc-field hc-field-full">
                    <label for="diagnostico">Diagnóstico</label>

                    <textarea
                        id="diagnostico"
                        name="diagnostico"
                        required
                    ><?= hc_e($datos['diagnostico']) ?></textarea>
                </div>

                <div class="hc-field hc-field-full">
                    <label for="tratamiento">Tratamiento</label>

                    <textarea
                        id="tratamiento"
                        name="tratamiento"
                        required
                    ><?= hc_e($datos['tratamiento']) ?></textarea>
                </div>
            </div>

            <div class="hc-form-actions">
                <a
                    class="hc-btn hc-btn-secondary"
                    href="<?= hc_e(hc_url('consultas/index.php')) ?>"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="hc-btn hc-btn-warning"
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