<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL DEL PROYECTO
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   CARGAR ARCHIVOS DEL SISTEMA
===================================================== */

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/* =====================================================
   PROTEGER LA PÁGINA
===================================================== */

require_login();

/* =====================================================
   COMPROBAR CONEXIÓN PDO
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            max-width:760px;
            margin:40px auto;
            padding:20px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fff1f2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error de conexión:</strong><br><br>
            El archivo <code>config/conexion.php</code> debe crear una
            conexión PDO llamada <code>$pdo</code>.
        </div>'
    );
}

/* =====================================================
   FUNCIÓN PARA ESCAPAR HTML
===================================================== */

function citaCrearEscapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =====================================================
   TOKEN CSRF
===================================================== */

if (empty($_SESSION['csrf_crear_cita'])) {
    $_SESSION['csrf_crear_cita'] = bin2hex(
        random_bytes(32)
    );
}

/* =====================================================
   VALORES INICIALES
===================================================== */

$datos = [
    'mascota_id' => 0,
    'fecha' => date('Y-m-d'),
    'hora' => '',
    'motivo' => '',
    'estado' => 'Pendiente',
];

$errores = [];
$mascotas = [];

$estadosPermitidos = [
    'Pendiente',
    'Confirmada',
    'Atendida',
    'Cancelada',
];

/* =====================================================
   CARGAR MASCOTAS Y PROPIETARIOS
===================================================== */

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
         ORDER BY
            c.nombres ASC,
            c.apellidos ASC,
            m.nombre ASC'
    );

    $mascotas = $consultaMascotas->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error cargando mascotas para crear cita: ' .
        $error->getMessage()
    );

    $errores[] =
        'No se pudieron cargar las mascotas. ' .
        'Revisa las tablas mascotas y clientes.';
}

/* =====================================================
   PROCESAR FORMULARIO
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'mascota_id' => (int) ($_POST['mascota_id'] ?? 0),
        'fecha' => trim((string) ($_POST['fecha'] ?? '')),
        'hora' => trim((string) ($_POST['hora'] ?? '')),
        'motivo' => trim((string) ($_POST['motivo'] ?? '')),
        'estado' => trim((string) ($_POST['estado'] ?? 'Pendiente')),
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_crear_cita'] ?? ''
    );

    /* Validar token */
    if (
        $tokenSesion === '' ||
        $tokenFormulario === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $errores[] =
            'La sesión del formulario expiró. ' .
            'Recarga la página e inténtalo nuevamente.';
    }

    /* Validar mascota */
    if ($datos['mascota_id'] <= 0) {
        $errores[] = 'Selecciona una mascota.';
    }

    /* Validar fecha */
    $fechaValida = false;

    if ($datos['fecha'] === '') {
        $errores[] = 'Selecciona la fecha de la cita.';
    } else {
        $fechaObjeto = DateTime::createFromFormat(
            'Y-m-d',
            $datos['fecha']
        );

        $fechaValida = $fechaObjeto instanceof DateTime
            && $fechaObjeto->format('Y-m-d') === $datos['fecha'];

        if (!$fechaValida) {
            $errores[] = 'La fecha de la cita no es válida.';
        } elseif ($datos['fecha'] < date('Y-m-d')) {
            $errores[] = 'La fecha de la cita no puede ser anterior a hoy.';
        }
    }

    /* Validar hora */
    if ($datos['hora'] === '') {
        $errores[] = 'Selecciona la hora de la cita.';
    } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $datos['hora'])) {
        $errores[] = 'La hora de la cita no es válida.';
    }

    /* Validar motivo */
    if ($datos['motivo'] === '') {
        $errores[] = 'Ingresa el motivo de la cita.';
    } elseif (strlen($datos['motivo']) > 1000) {
        $errores[] = 'El motivo no puede superar los 1000 caracteres.';
    }

    /* Validar estado */
    if (!in_array($datos['estado'], $estadosPermitidos, true)) {
        $errores[] = 'Selecciona un estado válido.';
    }

    /* Confirmar que la mascota existe */
    if ($datos['mascota_id'] > 0) {
        try {
            $verificarMascota = $pdo->prepare(
                'SELECT id
                 FROM mascotas
                 WHERE id = :id
                 LIMIT 1'
            );

            $verificarMascota->execute([
                ':id' => $datos['mascota_id'],
            ]);

            if (!$verificarMascota->fetchColumn()) {
                $errores[] = 'La mascota seleccionada no existe.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando mascota para cita: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo comprobar la mascota seleccionada.';
        }
    }

    /* Verificar cita duplicada */
    if (
        $errores === [] &&
        $fechaValida
    ) {
        try {
            $verificarDuplicado = $pdo->prepare(
                'SELECT id
                 FROM citas
                 WHERE mascota_id = :mascota_id
                   AND fecha = :fecha
                   AND TIME_FORMAT(hora, "%H:%i") = :hora
                 LIMIT 1'
            );

            $verificarDuplicado->execute([
                ':mascota_id' => $datos['mascota_id'],
                ':fecha' => $datos['fecha'],
                ':hora' => $datos['hora'],
            ]);

            if ($verificarDuplicado->fetchColumn()) {
                $errores[] =
                    'Ya existe una cita para esa mascota ' .
                    'en la misma fecha y hora.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando cita duplicada: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo comprobar si la cita ya existe.';
        }
    }

    /* Guardar cita */
    if ($errores === []) {
        try {
            $registrar = $pdo->prepare(
                'INSERT INTO citas
                    (
                        mascota_id,
                        fecha,
                        hora,
                        motivo,
                        estado
                    )
                 VALUES
                    (
                        :mascota_id,
                        :fecha,
                        :hora,
                        :motivo,
                        :estado
                    )'
            );

            $registrar->execute([
                ':mascota_id' => $datos['mascota_id'],
                ':fecha' => $datos['fecha'],
                ':hora' => $datos['hora'],
                ':motivo' => $datos['motivo'],
                ':estado' => $datos['estado'],
            ]);

            $_SESSION['csrf_crear_cita'] = bin2hex(
                random_bytes(32)
            );

            header('Location: index.php?msg=creada');
            exit;
        } catch (Throwable $error) {
            error_log(
                'Error registrando cita: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo registrar la cita. ' .
                'Revisa la estructura de la tabla citas.';
        }
    }
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Registrar cita';
$activePage = 'citas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .cita-form-page {
        width: min(980px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .cita-form-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
    }

    .cita-form-head h1 {
        margin: 0 0 7px;
        color: #08264f;
        font-size: 27px;
    }

    .cita-form-head p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .cita-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #334155;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
    }

    .cita-form-card {
        padding: 28px;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .cita-alert {
        margin-bottom: 22px;
        padding: 15px 17px;
        border: 1px solid #fecaca;
        border-radius: 12px;
        background: #fff1f2;
        color: #991b1b;
        font-size: 14px;
    }

    .cita-alert strong {
        display: block;
        margin-bottom: 8px;
    }

    .cita-alert ul {
        margin: 0;
        padding-left: 21px;
    }

    .cita-empty {
        margin-bottom: 22px;
        padding: 17px;
        border: 1px solid #fde68a;
        border-radius: 12px;
        background: #fffbeb;
        color: #854d0e;
        font-size: 14px;
    }

    .cita-empty a {
        color: #1d4ed8;
        font-weight: 800;
    }

    .cita-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .cita-field {
        display: grid;
        gap: 8px;
    }

    .cita-field-full {
        grid-column: 1 / -1;
    }

    .cita-field label {
        color: #263b58;
        font-size: 14px;
        font-weight: 800;
    }

    .cita-field input,
    .cita-field select,
    .cita-field textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        outline: none;
    }

    .cita-field input:focus,
    .cita-field select:focus,
    .cita-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .cita-field textarea {
        min-height: 120px;
        resize: vertical;
    }

    .cita-help {
        color: #64748b;
        font-size: 12px;
    }

    .cita-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .cita-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 43px;
        padding: 10px 18px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .cita-btn-cancel {
        background: #e9eef5;
        color: #334155;
    }

    .cita-btn-save {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .cita-btn-save:hover {
        background: #1d4ed8;
    }

    .cita-btn-save:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    @media (max-width: 720px) {
        .cita-form-head {
            align-items: stretch;
            flex-direction: column;
        }

        .cita-back {
            width: 100%;
        }

        .cita-grid {
            grid-template-columns: 1fr;
        }

        .cita-field-full {
            grid-column: auto;
        }

        .cita-actions {
            flex-direction: column-reverse;
        }

        .cita-btn {
            width: 100%;
        }
    }
</style>

<div class="cita-form-page">
    <div class="cita-form-head">
        <div>
            <h1>📅 Registrar nueva cita</h1>
            <p>
                Selecciona la mascota y completa los datos de la atención.
            </p>
        </div>

        <a
            class="cita-back"
            href="<?= citaCrearEscapar(url('citas/index.php')) ?>"
        >
            ← Volver a citas
        </a>
    </div>

    <section class="cita-form-card">
        <?php if ($errores !== []): ?>
            <div class="cita-alert" role="alert">
                <strong>Revisa la información:</strong>

                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= citaCrearEscapar($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($mascotas === []): ?>
            <div class="cita-empty">
                No hay mascotas disponibles para agendar una cita.
                Primero registra un cliente y una mascota.
                <a href="<?= citaCrearEscapar(url('mascotas/crear.php')) ?>">
                    Registrar mascota
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= citaCrearEscapar(
                    $_SESSION['csrf_crear_cita']
                ) ?>"
            >

            <div class="cita-grid">
                <div class="cita-field cita-field-full">
                    <label for="mascota_id">
                        Mascota y propietario
                    </label>

                    <select
                        id="mascota_id"
                        name="mascota_id"
                        required
                        <?= $mascotas === [] ? 'disabled' : '' ?>
                    >
                        <option value="">
                            Seleccione una mascota
                        </option>

                        <?php foreach ($mascotas as $mascota): ?>
                            <?php
                            $propietario = trim(
                                (string) ($mascota['cliente_nombres'] ?? '') .
                                ' ' .
                                (string) ($mascota['cliente_apellidos'] ?? '')
                            );

                            $descripcion = trim(
                                (string) ($mascota['mascota'] ?? '') .
                                ' — ' .
                                (string) ($mascota['especie'] ?? '') .
                                ' — Propietario: ' .
                                $propietario
                            );
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
                                <?= citaCrearEscapar($descripcion) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span class="cita-help">
                        La cita queda relacionada con la mascota seleccionada.
                    </span>
                </div>

                <div class="cita-field">
                    <label for="fecha">Fecha</label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= citaCrearEscapar($datos['fecha']) ?>"
                        required
                    >
                </div>

                <div class="cita-field">
                    <label for="hora">Hora</label>

                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        value="<?= citaCrearEscapar($datos['hora']) ?>"
                        required
                    >
                </div>

                <div class="cita-field cita-field-full">
                    <label for="motivo">Motivo de la cita</label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        maxlength="1000"
                        placeholder="Ejemplo: vacunación, control general, revisión de piel..."
                        required
                    ><?= citaCrearEscapar($datos['motivo']) ?></textarea>
                </div>

                <div class="cita-field cita-field-full">
                    <label for="estado">Estado inicial</label>

                    <select
                        id="estado"
                        name="estado"
                        required
                    >
                        <?php foreach ($estadosPermitidos as $estado): ?>
                            <option
                                value="<?= citaCrearEscapar($estado) ?>"
                                <?=
                                    $datos['estado'] === $estado
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= citaCrearEscapar($estado) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cita-actions">
                <a
                    class="cita-btn cita-btn-cancel"
                    href="<?= citaCrearEscapar(url('citas/index.php')) ?>"
                >
                    Cancelar
                </a>

                <button
                    class="cita-btn cita-btn-save"
                    type="submit"
                    <?= $mascotas === [] ? 'disabled' : '' ?>
                >
                    💾 Registrar cita
                </button>
            </div>
        </form>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>