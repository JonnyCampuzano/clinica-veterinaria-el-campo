<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   SESIÓN Y ARCHIVOS DEL SISTEMA
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
        'No se encontró una conexión PDO válida. ' .
        'Revisa el archivo config/conexion.php.'
    );
}

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function escaparMascota(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function longitudMascota(string $valor): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($valor)
        : strlen($valor);
}

/* =====================================================
   OBTENER ID DE LA MASCOTA
===================================================== */

$idMascota = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idMascota = (int) ($_POST['id'] ?? 0);
} else {
    $idMascota = (int) ($_GET['id'] ?? 0);
}

if ($idMascota <= 0) {
    header('Location: ' . url('mascotas/index.php?error=id_invalido'));
    exit;
}

/* =====================================================
   TOKEN CSRF
===================================================== */

if (empty($_SESSION['csrf_editar_mascota'])) {
    $_SESSION['csrf_editar_mascota'] = bin2hex(
        random_bytes(32)
    );
}

/* =====================================================
   CARGAR CLIENTES
===================================================== */

$clientes = [];
$mensajeError = '';

try {
    $consultaClientes = $pdo->query(
        'SELECT
            id,
            nombres,
            apellidos,
            cedula
         FROM clientes
         ORDER BY nombres ASC, apellidos ASC'
    );

    $clientes = $consultaClientes->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error al cargar clientes en editar mascota: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar los propietarios registrados.';
}

/* =====================================================
   CARGAR MASCOTA
===================================================== */

try {
    $consultaMascota = $pdo->prepare(
        'SELECT
            id,
            cliente_id,
            nombre,
            especie,
            raza,
            sexo,
            fecha_nacimiento,
            peso,
            color,
            alergias,
            observaciones
         FROM mascotas
         WHERE id = :id
         LIMIT 1'
    );

    $consultaMascota->execute([
        ':id' => $idMascota,
    ]);

    $mascota = $consultaMascota->fetch(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error al cargar mascota para editar: ' .
        $error->getMessage()
    );

    $mascota = false;
    $mensajeError =
        'No se pudo cargar la información de la mascota.';
}

if (!is_array($mascota)) {
    header('Location: ' . url('mascotas/index.php?error=no_encontrada'));
    exit;
}

/* =====================================================
   DATOS DEL FORMULARIO
===================================================== */

$datos = [
    'cliente_id' => (int) ($mascota['cliente_id'] ?? 0),
    'nombre' => (string) ($mascota['nombre'] ?? ''),
    'especie' => (string) ($mascota['especie'] ?? ''),
    'raza' => (string) ($mascota['raza'] ?? ''),
    'sexo' => (string) ($mascota['sexo'] ?? ''),
    'fecha_nacimiento' => (string) (
        $mascota['fecha_nacimiento'] ?? ''
    ),
    'peso' => $mascota['peso'] !== null
        ? (string) $mascota['peso']
        : '',
    'color' => (string) ($mascota['color'] ?? ''),
    'alergias' => (string) ($mascota['alergias'] ?? ''),
    'observaciones' => (string) (
        $mascota['observaciones'] ?? ''
    ),
];

$sexosPermitidos = ['Macho', 'Hembra'];

/* =====================================================
   ACTUALIZAR MASCOTA
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'cliente_id' => (int) ($_POST['cliente_id'] ?? 0),
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'especie' => trim((string) ($_POST['especie'] ?? '')),
        'raza' => trim((string) ($_POST['raza'] ?? '')),
        'sexo' => trim((string) ($_POST['sexo'] ?? '')),
        'fecha_nacimiento' => trim(
            (string) ($_POST['fecha_nacimiento'] ?? '')
        ),
        'peso' => trim((string) ($_POST['peso'] ?? '')),
        'color' => trim((string) ($_POST['color'] ?? '')),
        'alergias' => trim(
            (string) ($_POST['alergias'] ?? '')
        ),
        'observaciones' => trim(
            (string) ($_POST['observaciones'] ?? '')
        ),
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_editar_mascota'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $mensajeError =
            'La sesión del formulario expiró. ' .
            'Recarga la página e inténtalo nuevamente.';
    } elseif ($datos['cliente_id'] <= 0) {
        $mensajeError =
            'Selecciona el propietario de la mascota.';
    } elseif ($datos['nombre'] === '') {
        $mensajeError =
            'Ingresa el nombre de la mascota.';
    } elseif (longitudMascota($datos['nombre']) > 100) {
        $mensajeError =
            'El nombre no puede superar los 100 caracteres.';
    } elseif ($datos['especie'] === '') {
        $mensajeError =
            'Ingresa la especie de la mascota.';
    } elseif (longitudMascota($datos['especie']) > 60) {
        $mensajeError =
            'La especie no puede superar los 60 caracteres.';
    } elseif (
        !in_array(
            $datos['sexo'],
            $sexosPermitidos,
            true
        )
    ) {
        $mensajeError =
            'Selecciona un sexo válido.';
    } elseif (longitudMascota($datos['raza']) > 100) {
        $mensajeError =
            'La raza no puede superar los 100 caracteres.';
    } elseif (longitudMascota($datos['color']) > 80) {
        $mensajeError =
            'El color no puede superar los 80 caracteres.';
    } else {
        $fechaNacimiento = null;
        $peso = null;

        if ($datos['fecha_nacimiento'] !== '') {
            $fechaObjeto = DateTime::createFromFormat(
                'Y-m-d',
                $datos['fecha_nacimiento']
            );

            $fechaValida =
                $fechaObjeto instanceof DateTime &&
                $fechaObjeto->format('Y-m-d') ===
                    $datos['fecha_nacimiento'];

            if (!$fechaValida) {
                $mensajeError =
                    'La fecha de nacimiento no es válida.';
            } elseif (
                $datos['fecha_nacimiento'] > date('Y-m-d')
            ) {
                $mensajeError =
                    'La fecha de nacimiento no puede ser futura.';
            } else {
                $fechaNacimiento =
                    $datos['fecha_nacimiento'];
            }
        }

        if (
            $mensajeError === '' &&
            $datos['peso'] !== ''
        ) {
            $pesoNormalizado = str_replace(
                ',',
                '.',
                $datos['peso']
            );

            if (!is_numeric($pesoNormalizado)) {
                $mensajeError =
                    'El peso debe ser un número válido.';
            } else {
                $peso = (float) $pesoNormalizado;

                if ($peso < 0 || $peso > 9999.99) {
                    $mensajeError =
                        'El peso está fuera del rango permitido.';
                }
            }
        }

        if ($mensajeError === '') {
            try {
                $verificarCliente = $pdo->prepare(
                    'SELECT id
                     FROM clientes
                     WHERE id = :id
                     LIMIT 1'
                );

                $verificarCliente->execute([
                    ':id' => $datos['cliente_id'],
                ]);

                if (!$verificarCliente->fetchColumn()) {
                    $mensajeError =
                        'El propietario seleccionado no existe.';
                } else {
                    $actualizar = $pdo->prepare(
                        'UPDATE mascotas
                         SET
                            cliente_id = :cliente_id,
                            nombre = :nombre,
                            especie = :especie,
                            raza = :raza,
                            sexo = :sexo,
                            fecha_nacimiento = :fecha_nacimiento,
                            peso = :peso,
                            color = :color,
                            alergias = :alergias,
                            observaciones = :observaciones
                         WHERE id = :id'
                    );

                    $actualizar->execute([
                        ':cliente_id' => $datos['cliente_id'],
                        ':nombre' => $datos['nombre'],
                        ':especie' => $datos['especie'],
                        ':raza' => $datos['raza'] !== ''
                            ? $datos['raza']
                            : null,
                        ':sexo' => $datos['sexo'],
                        ':fecha_nacimiento' => $fechaNacimiento,
                        ':peso' => $peso,
                        ':color' => $datos['color'] !== ''
                            ? $datos['color']
                            : null,
                        ':alergias' => $datos['alergias'] !== ''
                            ? $datos['alergias']
                            : null,
                        ':observaciones' =>
                            $datos['observaciones'] !== ''
                                ? $datos['observaciones']
                                : null,
                        ':id' => $idMascota,
                    ]);

                    $_SESSION['csrf_editar_mascota'] =
                        bin2hex(random_bytes(32));

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'message' =>
                            'Mascota actualizada correctamente.',
                    ];

                    header(
                        'Location: ' .
                        url('mascotas/index.php?msg=actualizada')
                    );
                    exit;
                }
            } catch (Throwable $error) {
                error_log(
                    'Error al actualizar mascota: ' .
                    $error->getMessage()
                );

                $mensajeError =
                    'No se pudo actualizar la mascota. ' .
                    'Revisa la tabla mascotas y sus columnas.';
            }
        }
    }
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Editar mascota';
$activePage = 'mascotas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .editar-mascota-page {
        width: min(1050px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .editar-mascota-card {
        overflow: hidden;
        border: 1px solid #dce5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.09);
    }

    .editar-mascota-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .editar-mascota-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .editar-mascota-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .editar-mascota-back {
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

    .editar-mascota-alert {
        margin: 22px 28px 0;
        padding: 14px 17px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 700;
    }

    .editar-mascota-form {
        padding: 28px;
    }

    .editar-mascota-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .editar-mascota-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .editar-mascota-field.full {
        grid-column: 1 / -1;
    }

    .editar-mascota-field label {
        color: #334155;
        font-size: 14px;
        font-weight: 800;
    }

    .editar-mascota-field input,
    .editar-mascota-field select,
    .editar-mascota-field textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .editar-mascota-field textarea {
        min-height: 100px;
        resize: vertical;
    }

    .editar-mascota-field input:focus,
    .editar-mascota-field select:focus,
    .editar-mascota-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .editar-mascota-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .editar-mascota-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 10px 18px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .editar-mascota-cancel {
        background: #e9eef5;
        color: #334155;
    }

    .editar-mascota-save {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .editar-mascota-save:hover {
        background: #1d4ed8;
    }

    @media (max-width: 720px) {
        .editar-mascota-header {
            align-items: stretch;
            flex-direction: column;
        }

        .editar-mascota-grid {
            grid-template-columns: 1fr;
        }

        .editar-mascota-field.full {
            grid-column: auto;
        }

        .editar-mascota-header,
        .editar-mascota-form {
            padding-left: 20px;
            padding-right: 20px;
        }

        .editar-mascota-alert {
            margin-left: 20px;
            margin-right: 20px;
        }

        .editar-mascota-actions {
            flex-direction: column-reverse;
        }

        .editar-mascota-button,
        .editar-mascota-back {
            width: 100%;
        }
    }
</style>

<div class="editar-mascota-page">
    <section class="editar-mascota-card">
        <header class="editar-mascota-header">
            <div>
                <h1>✏️ Editar mascota</h1>
                <p>
                    Actualiza los datos de
                    <strong><?= escaparMascota($datos['nombre']) ?></strong>.
                </p>
            </div>

            <a
                class="editar-mascota-back"
                href="<?= escaparMascota(url('mascotas/index.php')) ?>"
            >
                ← Volver a mascotas
            </a>
        </header>

        <?php if ($mensajeError !== ''): ?>
            <div
                class="editar-mascota-alert"
                role="alert"
            >
                ⚠️ <?= escaparMascota($mensajeError) ?>
            </div>
        <?php endif; ?>

        <form
            class="editar-mascota-form"
            method="POST"
            autocomplete="off"
        >
            <input
                type="hidden"
                name="id"
                value="<?= (int) $idMascota ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escaparMascota(
                    $_SESSION['csrf_editar_mascota']
                ) ?>"
            >

            <div class="editar-mascota-grid">
                <div class="editar-mascota-field full">
                    <label for="cliente_id">Propietario</label>

                    <select
                        id="cliente_id"
                        name="cliente_id"
                        required
                    >
                        <option value="">
                            Selecciona un propietario
                        </option>

                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                            $nombreCliente = trim(
                                (string) ($cliente['nombres'] ?? '') .
                                ' ' .
                                (string) ($cliente['apellidos'] ?? '')
                            );

                            $cedulaCliente = trim(
                                (string) ($cliente['cedula'] ?? '')
                            );
                            ?>

                            <option
                                value="<?= (int) ($cliente['id'] ?? 0) ?>"
                                <?=
                                    (int) $datos['cliente_id'] ===
                                    (int) ($cliente['id'] ?? 0)
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= escaparMascota($nombreCliente) ?>
                                <?= $cedulaCliente !== ''
                                    ? ' · ' . escaparMascota($cedulaCliente)
                                    : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="editar-mascota-field">
                    <label for="nombre">Nombre</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        maxlength="100"
                        value="<?= escaparMascota($datos['nombre']) ?>"
                        required
                    >
                </div>

                <div class="editar-mascota-field">
                    <label for="especie">Especie</label>
                    <input
                        type="text"
                        id="especie"
                        name="especie"
                        maxlength="60"
                        value="<?= escaparMascota($datos['especie']) ?>"
                        placeholder="Ejemplo: Perro, gato"
                        required
                    >
                </div>

                <div class="editar-mascota-field">
                    <label for="raza">Raza</label>
                    <input
                        type="text"
                        id="raza"
                        name="raza"
                        maxlength="100"
                        value="<?= escaparMascota($datos['raza']) ?>"
                        placeholder="Ejemplo: Labrador"
                    >
                </div>

                <div class="editar-mascota-field">
                    <label for="sexo">Sexo</label>
                    <select
                        id="sexo"
                        name="sexo"
                        required
                    >
                        <option value="">Selecciona</option>
                        <option
                            value="Macho"
                            <?= $datos['sexo'] === 'Macho'
                                ? 'selected'
                                : '' ?>
                        >
                            Macho
                        </option>
                        <option
                            value="Hembra"
                            <?= $datos['sexo'] === 'Hembra'
                                ? 'selected'
                                : '' ?>
                        >
                            Hembra
                        </option>
                    </select>
                </div>

                <div class="editar-mascota-field">
                    <label for="fecha_nacimiento">
                        Fecha de nacimiento
                    </label>
                    <input
                        type="date"
                        id="fecha_nacimiento"
                        name="fecha_nacimiento"
                        max="<?= date('Y-m-d') ?>"
                        value="<?= escaparMascota(
                            $datos['fecha_nacimiento']
                        ) ?>"
                    >
                </div>

                <div class="editar-mascota-field">
                    <label for="peso">Peso (kg)</label>
                    <input
                        type="number"
                        id="peso"
                        name="peso"
                        min="0"
                        max="9999.99"
                        step="0.01"
                        value="<?= escaparMascota($datos['peso']) ?>"
                        placeholder="Ejemplo: 12.50"
                    >
                </div>

                <div class="editar-mascota-field">
                    <label for="color">Color</label>
                    <input
                        type="text"
                        id="color"
                        name="color"
                        maxlength="80"
                        value="<?= escaparMascota($datos['color']) ?>"
                        placeholder="Ejemplo: Café"
                    >
                </div>

                <div class="editar-mascota-field full">
                    <label for="alergias">Alergias</label>
                    <textarea
                        id="alergias"
                        name="alergias"
                        placeholder="Escribe las alergias conocidas"
                    ><?= escaparMascota($datos['alergias']) ?></textarea>
                </div>

                <div class="editar-mascota-field full">
                    <label for="observaciones">Observaciones</label>
                    <textarea
                        id="observaciones"
                        name="observaciones"
                        placeholder="Información adicional de la mascota"
                    ><?= escaparMascota(
                        $datos['observaciones']
                    ) ?></textarea>
                </div>
            </div>

            <div class="editar-mascota-actions">
                <a
                    class="editar-mascota-button editar-mascota-cancel"
                    href="<?= escaparMascota(
                        url('mascotas/index.php')
                    ) ?>"
                >
                    Cancelar
                </a>

                <button
                    class="editar-mascota-button editar-mascota-save"
                    type="submit"
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