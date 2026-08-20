<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RUTA PRINCIPAL DEL PROYECTO
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__);

/*
|--------------------------------------------------------------------------
| INICIAR SESIÓN
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CARGAR ARCHIVOS DEL SISTEMA
|--------------------------------------------------------------------------
*/

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/config/crypto.php';
require_once $raiz . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| PROTEGER LA PÁGINA
|--------------------------------------------------------------------------
*/

require_login();

/*
|--------------------------------------------------------------------------
| COMPROBAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function citaEditarEscapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| RECIBIR IDENTIFICADOR
|--------------------------------------------------------------------------
*/

$idCita = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCita = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;
} else {
    $idCita = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    ) ?: 0;
}

if ($idCita <= 0) {
    header(
        'Location: ' .
        url('citas/index.php?error=id_invalido')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_editar_cita'])) {
    $_SESSION['csrf_editar_cita'] = bin2hex(
        random_bytes(32)
    );
}

/*
|--------------------------------------------------------------------------
| ESTADOS PERMITIDOS
|--------------------------------------------------------------------------
*/

$estadosPermitidos = [
    'Pendiente',
    'Confirmada',
    'Atendida',
    'Cancelada',
];

/*
|--------------------------------------------------------------------------
| CARGAR MASCOTAS Y PROPIETARIOS
|--------------------------------------------------------------------------
*/

$mascotas = [];
$errores = [];

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
                'Error al descifrar propietario de mascota ID ' .
                (int) ($mascota['id'] ?? 0) .
                ' al editar cita: ' .
                $errorDescifrado->getMessage()
            );
        }
    }

    /*
     * Los datos personales están cifrados en MySQL.
     * El orden alfabético se realiza después de descifrarlos.
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
        'Error cargando mascotas para editar cita: ' .
        $error->getMessage()
    );

    $errores[] =
        'No se pudieron cargar las mascotas. ' .
        'Revisa las tablas mascotas, clientes y la clave de cifrado.';
}

/*
|--------------------------------------------------------------------------
| CARGAR CITA ACTUAL
|--------------------------------------------------------------------------
*/

$citaActual = null;

try {
    $consultaCita = $pdo->prepare(
        'SELECT
            id,
            mascota_id,
            fecha,
            hora,
            motivo,
            estado
         FROM citas
         WHERE id = :id
         LIMIT 1'
    );

    $consultaCita->execute([
        ':id' => $idCita
    ]);

    $citaActual = $consultaCita->fetch(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error cargando cita para editar: ' .
        $error->getMessage()
    );

    $errores[] =
        'No se pudo cargar la cita seleccionada.';
}

if (!is_array($citaActual)) {
    header(
        'Location: ' .
        url('citas/index.php?error=no_encontrada')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| VALORES DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$datos = [
    'mascota_id' => (int) (
        $citaActual['mascota_id'] ?? 0
    ),
    'fecha' => (string) (
        $citaActual['fecha'] ?? ''
    ),
    'hora' => substr(
        (string) ($citaActual['hora'] ?? ''),
        0,
        5
    ),
    'motivo' => (string) (
        $citaActual['motivo'] ?? ''
    ),
    'estado' => (string) (
        $citaActual['estado'] ?? 'Pendiente'
    ),
];

/*
|--------------------------------------------------------------------------
| PROCESAR ACTUALIZACIÓN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'mascota_id' => (int) (
            $_POST['mascota_id'] ?? 0
        ),
        'fecha' => trim(
            (string) ($_POST['fecha'] ?? '')
        ),
        'hora' => trim(
            (string) ($_POST['hora'] ?? '')
        ),
        'motivo' => trim(
            (string) ($_POST['motivo'] ?? '')
        ),
        'estado' => trim(
            (string) ($_POST['estado'] ?? '')
        ),
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_editar_cita'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDAR TOKEN
    |--------------------------------------------------------------------------
    */

    if (
        $tokenSesion === '' ||
        $tokenFormulario === '' ||
        !hash_equals(
            $tokenSesion,
            $tokenFormulario
        )
    ) {
        $errores[] =
            'La sesión del formulario expiró. ' .
            'Actualiza la página e inténtalo nuevamente.';
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR MASCOTA
    |--------------------------------------------------------------------------
    */

    if ($datos['mascota_id'] <= 0) {
        $errores[] = 'Selecciona una mascota.';
    } else {
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
                'Error validando mascota de la cita: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo validar la mascota seleccionada.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR FECHA
    |--------------------------------------------------------------------------
    */

    if ($datos['fecha'] === '') {
        $errores[] = 'Selecciona la fecha de la cita.';
    } else {
        $fechaObjeto = DateTime::createFromFormat(
            'Y-m-d',
            $datos['fecha']
        );

        $fechaValida =
            $fechaObjeto instanceof DateTime &&
            $fechaObjeto->format('Y-m-d') ===
                $datos['fecha'];

        if (!$fechaValida) {
            $errores[] =
                'La fecha de la cita no es válida.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR HORA
    |--------------------------------------------------------------------------
    */

    if ($datos['hora'] === '') {
        $errores[] = 'Selecciona la hora de la cita.';
    } else {
        $horaObjeto = DateTime::createFromFormat(
            'H:i',
            $datos['hora']
        );

        $horaValida =
            $horaObjeto instanceof DateTime &&
            $horaObjeto->format('H:i') ===
                $datos['hora'];

        if (!$horaValida) {
            $errores[] =
                'La hora de la cita no es válida.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR MOTIVO
    |--------------------------------------------------------------------------
    */

    if ($datos['motivo'] === '') {
        $errores[] =
            'Escribe el motivo de la cita.';
    } elseif (
        strlen($datos['motivo']) > 1000
    ) {
        $errores[] =
            'El motivo no puede superar 1000 caracteres.';
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR ESTADO
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $datos['estado'],
            $estadosPermitidos,
            true
        )
    ) {
        $errores[] =
            'Selecciona un estado válido.';
    }

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR CITA DUPLICADA
    |--------------------------------------------------------------------------
    */

    if ($errores === []) {
        try {
            $verificarDuplicada = $pdo->prepare(
                'SELECT id
                 FROM citas
                 WHERE mascota_id = :mascota_id
                   AND fecha = :fecha
                   AND hora = :hora
                   AND id <> :id
                 LIMIT 1'
            );

            $verificarDuplicada->execute([
                ':mascota_id' =>
                    $datos['mascota_id'],
                ':fecha' =>
                    $datos['fecha'],
                ':hora' =>
                    $datos['hora'],
                ':id' =>
                    $idCita
            ]);

            if (
                $verificarDuplicada->fetchColumn()
            ) {
                $errores[] =
                    'La mascota ya tiene otra cita ' .
                    'registrada en esa fecha y hora.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error verificando cita duplicada: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo comprobar la disponibilidad ' .
                'de la fecha y hora seleccionadas.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR CITA
    |--------------------------------------------------------------------------
    */

    if ($errores === []) {
        try {
            $actualizar = $pdo->prepare(
                'UPDATE citas
                 SET
                    mascota_id = :mascota_id,
                    fecha = :fecha,
                    hora = :hora,
                    motivo = :motivo,
                    estado = :estado
                 WHERE id = :id
                 LIMIT 1'
            );

            $actualizar->execute([
                ':mascota_id' =>
                    $datos['mascota_id'],
                ':fecha' =>
                    $datos['fecha'],
                ':hora' =>
                    $datos['hora'],
                ':motivo' =>
                    $datos['motivo'],
                ':estado' =>
                    $datos['estado'],
                ':id' =>
                    $idCita
            ]);

            $_SESSION['csrf_editar_cita'] =
                bin2hex(random_bytes(32));

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' =>
                    'Cita actualizada correctamente.'
            ];

            header(
                'Location: ' .
                url(
                    'citas/index.php?msg=actualizada'
                )
            );
            exit;
        } catch (PDOException $error) {
            error_log(
                'Error actualizando cita: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo actualizar la cita. ' .
                'Revisa la conexión y la estructura ' .
                'de la tabla citas.';
        } catch (Throwable $error) {
            error_log(
                'Error inesperado actualizando cita: ' .
                $error->getMessage()
            );

            $errores[] =
                'Ocurrió un error inesperado al ' .
                'actualizar la cita.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| ENCABEZADO
|--------------------------------------------------------------------------
*/

$pageTitle = 'Editar cita';
$activePage = 'citas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .editar-cita-page {
        width: min(960px, 100%);
        margin: 0 auto;
        padding: 10px 0 42px;
    }

    .editar-cita-card {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow:
            0 15px 38px
            rgba(15, 35, 65, 0.09);
    }

    .editar-cita-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 25px 28px;
        border-bottom: 1px solid #e2e8f0;
        background:
            linear-gradient(
                135deg,
                #ffffff,
                #f7fbff
            );
    }

    .editar-cita-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .editar-cita-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .editar-cita-identificador {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        padding: 8px 13px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    .editar-cita-alerta {
        margin: 22px 28px 0;
        padding: 15px 17px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #991b1b;
    }

    .editar-cita-alerta strong {
        display: block;
        margin-bottom: 7px;
    }

    .editar-cita-alerta ul {
        margin: 0;
        padding-left: 20px;
    }

    .editar-cita-form {
        padding: 28px;
    }

    .editar-cita-grid {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .editar-cita-field {
        display: grid;
        gap: 8px;
    }

    .editar-cita-field-full {
        grid-column: 1 / -1;
    }

    .editar-cita-field label {
        color: #334155;
        font-size: 14px;
        font-weight: 800;
    }

    .editar-cita-field input,
    .editar-cita-field select,
    .editar-cita-field textarea {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        outline: none;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .editar-cita-field input:focus,
    .editar-cita-field select:focus,
    .editar-cita-field textarea:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 3px
            rgba(37, 99, 235, 0.12);
    }

    .editar-cita-field textarea {
        min-height: 120px;
        resize: vertical;
    }

    .editar-cita-help {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }

    .editar-cita-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .editar-cita-btn {
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

    .editar-cita-cancelar {
        background: #e9eef5;
        color: #334155;
    }

    .editar-cita-guardar {
        background: #f59e0b;
        color: #ffffff;
        box-shadow:
            0 8px 18px
            rgba(245, 158, 11, 0.24);
    }

    .editar-cita-guardar:hover {
        background: #d97706;
    }

    .editar-cita-guardar:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    @media (max-width: 700px) {
        .editar-cita-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .editar-cita-grid {
            grid-template-columns: 1fr;
        }

        .editar-cita-field-full {
            grid-column: auto;
        }

        .editar-cita-header,
        .editar-cita-form {
            padding-left: 20px;
            padding-right: 20px;
        }

        .editar-cita-actions {
            flex-direction: column-reverse;
        }

        .editar-cita-btn {
            width: 100%;
        }
    }
</style>

<div class="editar-cita-page">
    <section class="editar-cita-card">
        <header class="editar-cita-header">
            <div>
                <h1>✏️ Editar cita</h1>

                <p>
                    Actualiza la mascota, la fecha,
                    la hora, el motivo o el estado.
                </p>
            </div>

            <span class="editar-cita-identificador">
                Cita #<?= $idCita ?>
            </span>
        </header>

        <?php if ($errores !== []): ?>
            <div
                class="editar-cita-alerta"
                role="alert"
            >
                <strong>
                    Revisa la siguiente información:
                </strong>

                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li>
                            <?= citaEditarEscapar($error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            class="editar-cita-form"
            autocomplete="off"
        >
            <input
                type="hidden"
                name="id"
                value="<?= $idCita ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= citaEditarEscapar(
                    $_SESSION['csrf_editar_cita']
                ) ?>"
            >

            <div class="editar-cita-grid">
                <div
                    class="
                        editar-cita-field
                        editar-cita-field-full
                    "
                >
                    <label for="mascota_id">
                        Mascota y propietario
                    </label>

                    <select
                        id="mascota_id"
                        name="mascota_id"
                        required
                    >
                        <option value="">
                            Selecciona una mascota
                        </option>

                        <?php foreach ($mascotas as $mascota): ?>
                            <?php
                            $propietario = trim(
                                (string) (
                                    $mascota[
                                        'cliente_nombres'
                                    ] ?? ''
                                ) .
                                ' ' .
                                (string) (
                                    $mascota[
                                        'cliente_apellidos'
                                    ] ?? ''
                                )
                            );

                            $descripcion =
                                $propietario .
                                ' — ' .
                                (string) (
                                    $mascota['mascota'] ?? ''
                                ) .
                                ' (' .
                                (string) (
                                    $mascota['especie'] ?? ''
                                );

                            $raza = trim(
                                (string) (
                                    $mascota['raza'] ?? ''
                                )
                            );

                            if ($raza !== '') {
                                $descripcion .=
                                    ' / ' . $raza;
                            }

                            $descripcion .= ')';

                            $cedula = trim(
                                (string) (
                                    $mascota[
                                        'cliente_cedula'
                                    ] ?? ''
                                )
                            );

                            if ($cedula !== '') {
                                $descripcion .=
                                    ' · Cédula: ' . $cedula;
                            }
                            ?>

                            <option
                                value="<?=
                                    (int) (
                                        $mascota['id'] ?? 0
                                    )
                                ?>"
                                <?=
                                    $datos['mascota_id'] ===
                                    (int) (
                                        $mascota['id'] ?? 0
                                    )
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= citaEditarEscapar(
                                    $descripcion
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span class="editar-cita-help">
                        La cita quedará relacionada con
                        la mascota seleccionada.
                    </span>
                </div>

                <div class="editar-cita-field">
                    <label for="fecha">
                        Fecha
                    </label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        value="<?= citaEditarEscapar(
                            $datos['fecha']
                        ) ?>"
                        required
                    >
                </div>

                <div class="editar-cita-field">
                    <label for="hora">
                        Hora
                    </label>

                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        value="<?= citaEditarEscapar(
                            $datos['hora']
                        ) ?>"
                        required
                    >
                </div>

                <div
                    class="
                        editar-cita-field
                        editar-cita-field-full
                    "
                >
                    <label for="motivo">
                        Motivo de la cita
                    </label>

                    <textarea
                        id="motivo"
                        name="motivo"
                        maxlength="1000"
                        placeholder="
Ejemplo: vacunación, control general o revisión de piel
                        "
                        required
                    ><?= citaEditarEscapar(
                        $datos['motivo']
                    ) ?></textarea>
                </div>

                <div
                    class="
                        editar-cita-field
                        editar-cita-field-full
                    "
                >
                    <label for="estado">
                        Estado
                    </label>

                    <select
                        id="estado"
                        name="estado"
                        required
                    >
                        <?php foreach (
                            $estadosPermitidos
                            as $estado
                        ): ?>
                            <option
                                value="<?= citaEditarEscapar(
                                    $estado
                                ) ?>"
                                <?=
                                    $datos['estado'] ===
                                    $estado
                                        ? 'selected'
                                        : ''
                                ?>
                            >
                                <?= citaEditarEscapar(
                                    $estado
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="editar-cita-actions">
                <a
                    class="
                        editar-cita-btn
                        editar-cita-cancelar
                    "
                    href="<?= citaEditarEscapar(
                        url('citas/index.php')
                    ) ?>"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="
                        editar-cita-btn
                        editar-cita-guardar
                    "
                    <?= $mascotas === []
                        ? 'disabled'
                        : ''
                    ?>
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