<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/config/crypto.php';

$csrfClave = 'csrf_crear_historia';
$csrfToken = hc_csrf_token($csrfClave);

$usuarioId = hc_usuario_id_actual();

$datos = [
    'mascota_id' => 0,
    'fecha' => date('Y-m-d'),
    'motivo' => '',
    'diagnostico' => '',
    'tratamiento' => '',
    'peso' => '',
    'temperatura' => '',
    'proxima_cita' => '',
];

$errores = [];
$mascotas = [];

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

    $mascotasCifradas = $consultaMascotas->fetchAll(PDO::FETCH_ASSOC);
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
                ' al crear historia clínica: ' .
                $errorDescifrado->getMessage()
            );
        }
    }

    /*
     * Como los datos personales están cifrados en MySQL,
     * el orden alfabético se hace después de descifrarlos.
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

            $comparacion = strcasecmp($propietarioA, $propietarioB);

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
        'Error cargando mascotas para historia clínica: ' .
        $error->getMessage()
    );

    $errores[] =
        'No se pudieron cargar las mascotas registradas. ' .
        'Revisa la conexión y la clave de cifrado.';
}

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

    if ($usuarioId <= 0) {
        $errores[] =
            'No fue posible identificar al usuario conectado. ' .
            'Cierra sesión e ingresa nuevamente.';
    }

    if ($datos['mascota_id'] <= 0) {
        $errores[] = 'Selecciona una mascota.';
    }

    if ($datos['fecha'] === '') {
        $errores[] = 'Selecciona la fecha de atención.';
    } else {
        $fechaObjeto = DateTime::createFromFormat(
            'Y-m-d',
            $datos['fecha']
        );

        $fechaValida =
            $fechaObjeto instanceof DateTime &&
            $fechaObjeto->format('Y-m-d') === $datos['fecha'];

        if (!$fechaValida) {
            $errores[] = 'La fecha de atención no es válida.';
        } elseif ($datos['fecha'] > date('Y-m-d')) {
            $errores[] =
                'La fecha de atención no puede ser futura.';
        }
    }

    foreach (
        [
            'motivo' => 'Escribe el motivo de la consulta.',
            'diagnostico' => 'Escribe el diagnóstico.',
            'tratamiento' => 'Escribe el tratamiento.',
        ]
        as $campo => $mensaje
    ) {
        if ($datos[$campo] === '') {
            $errores[] = $mensaje;
        }
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

    if ($datos['mascota_id'] > 0) {
        try {
            $verificarMascota = $pdo->prepare(
                'SELECT id
                 FROM mascotas
                 WHERE id = :id
                 LIMIT 1'
            );

            $verificarMascota->execute([
                ':id' => $datos['mascota_id']
            ]);

            if (!$verificarMascota->fetchColumn()) {
                $errores[] =
                    'La mascota seleccionada no existe.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando mascota: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo validar la mascota seleccionada.';
        }
    }

    if ($errores === []) {
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO historias_clinicas
                    (
                        mascota_id,
                        usuario_id,
                        fecha,
                        motivo,
                        diagnostico,
                        tratamiento,
                        peso,
                        temperatura,
                        proxima_cita
                    )
                 VALUES
                    (
                        :mascota_id,
                        :usuario_id,
                        :fecha,
                        :motivo,
                        :diagnostico,
                        :tratamiento,
                        :peso,
                        :temperatura,
                        :proxima_cita
                    )'
            );

            $insertar->execute([
                ':mascota_id' => $datos['mascota_id'],
                ':usuario_id' => $usuarioId,
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
            ]);

            hc_regenerar_csrf($csrfClave);

            hc_flash(
                'success',
                'Historia clínica registrada correctamente.'
            );

            hc_redirigir('consultas/index.php');
        } catch (Throwable $error) {
            error_log(
                'Error registrando historia clínica: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo registrar la historia clínica. ' .
                'Revisa la estructura de la tabla consultas.';
        }
    }
}

$pageTitle = 'Nueva historia clínica';
$activePage = 'consultas';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="hc-page">
    <section class="hc-panel">
        <header class="hc-header">
            <div>
                <h1>🩺 Nueva historia clínica</h1>
                <p>
                    Registra el motivo, diagnóstico, tratamiento
                    y datos clínicos de la mascota.
                </p>
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

        <form method="POST" class="hc-form" autocomplete="off">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= hc_e($csrfToken) ?>"
            >

            <div class="hc-form-grid">
                <div class="hc-field hc-field-full">
                    <label for="mascota_id">
                        Mascota y propietario
                    </label>

                    <select
                        id="mascota_id"
                        name="mascota_id"
                        required
                    >
                        <option value="">Seleccione una mascota</option>

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
                        placeholder="Ejemplo: 12.50"
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
                        placeholder="Ejemplo: 38.5"
                    >
                </div>

                <div class="hc-field hc-field-full">
                    <label for="motivo">Motivo de consulta</label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        maxlength="2000"
                        required
                    ><?= hc_e($datos['motivo']) ?></textarea>
                </div>

                <div class="hc-field hc-field-full">
                    <label for="diagnostico">Diagnóstico</label>

                    <textarea
                        id="diagnostico"
                        name="diagnostico"
                        maxlength="5000"
                        required
                    ><?= hc_e($datos['diagnostico']) ?></textarea>
                </div>

                <div class="hc-field hc-field-full">
                    <label for="tratamiento">Tratamiento</label>

                    <textarea
                        id="tratamiento"
                        name="tratamiento"
                        maxlength="5000"
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
                    class="hc-btn hc-btn-primary"
                    <?= $mascotas === [] ? 'disabled' : '' ?>
                >
                    💾 Registrar historia clínica
                </button>
            </div>
        </form>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>