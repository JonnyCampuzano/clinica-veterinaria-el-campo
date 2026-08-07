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
require_once $raiz . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| VALIDAR SESIÓN
|--------------------------------------------------------------------------
*/

require_login();

/*
|--------------------------------------------------------------------------
| VALIDAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        'No se encontró una conexión PDO válida. ' .
        'Revisa config/conexion.php.'
    );
}

/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA ESCAPAR TEXTO
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| CREAR TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_eliminar_cita'])) {
    $_SESSION['csrf_eliminar_cita'] = bin2hex(
        random_bytes(32)
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
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'La cita seleccionada no es válida.'
    ];

    header(
        'Location: ' . url('citas/index.php')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| CONSULTAR LA CITA
|--------------------------------------------------------------------------
*/

$cita = null;
$mensajeError = '';

try {
    $consulta = $pdo->prepare(
        'SELECT
            ci.id,
            ci.mascota_id,
            ci.fecha,
            ci.hora,
            ci.motivo,
            ci.estado,
            ma.nombre AS mascota_nombre,
            ma.especie AS mascota_especie,
            cl.nombres AS cliente_nombres,
            cl.apellidos AS cliente_apellidos
         FROM citas ci
         INNER JOIN mascotas ma
            ON ma.id = ci.mascota_id
         INNER JOIN clientes cl
            ON cl.id = ma.cliente_id
         WHERE ci.id = :id
         LIMIT 1'
    );

    $consulta->execute([
        ':id' => $idCita
    ]);

    $cita = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error consultando cita para eliminar: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudo cargar la información de la cita.';
}

if (!is_array($cita)) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $mensajeError !== ''
            ? $mensajeError
            : 'La cita no existe o ya fue eliminada.'
    ];

    header(
        'Location: ' . url('citas/index.php')
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| ELIMINAR LA CITA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_eliminar_cita'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $mensajeError =
            'La sesión del formulario expiró. ' .
            'Actualiza la página e inténtalo nuevamente.';
    } else {
        try {
            $eliminar = $pdo->prepare(
                'DELETE FROM citas
                 WHERE id = :id
                 LIMIT 1'
            );

            $eliminar->execute([
                ':id' => $idCita
            ]);

            if ($eliminar->rowCount() < 1) {
                throw new RuntimeException(
                    'La cita no existe o ya fue eliminada.'
                );
            }

            $_SESSION['csrf_eliminar_cita'] = bin2hex(
                random_bytes(32)
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Cita eliminada correctamente.'
            ];

            header(
                'Location: ' . url('citas/index.php?msg=eliminada')
            );
            exit;
        } catch (PDOException $error) {
            error_log(
                'Error eliminando cita: ' .
                $error->getMessage()
            );

            if ($error->getCode() === '23000') {
                $mensajeError =
                    'No se puede eliminar esta cita porque tiene ' .
                    'información relacionada en otro módulo.';
            } else {
                $mensajeError =
                    'No se pudo eliminar la cita. ' .
                    'Revisa la conexión y la estructura de la tabla citas.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error inesperado eliminando cita: ' .
                $error->getMessage()
            );

            $mensajeError = $error->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| PREPARAR DATOS PARA MOSTRAR
|--------------------------------------------------------------------------
*/

$clienteNombre = trim(
    (string) ($cita['cliente_nombres'] ?? '') .
    ' ' .
    (string) ($cita['cliente_apellidos'] ?? '')
);

if ($clienteNombre === '') {
    $clienteNombre = 'Cliente no registrado';
}

$fechaTexto = (string) ($cita['fecha'] ?? '');

$fechaObjeto = DateTime::createFromFormat(
    'Y-m-d',
    $fechaTexto
);

if ($fechaObjeto instanceof DateTime) {
    $fechaTexto = $fechaObjeto->format('d/m/Y');
}

$horaTexto = substr(
    (string) ($cita['hora'] ?? ''),
    0,
    5
);

$pageTitle = 'Eliminar cita';
$activePage = 'citas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .eliminar-cita-page {
        width: min(760px, 100%);
        margin: 0 auto;
        padding: 12px 0 42px;
    }

    .eliminar-cita-card {
        overflow: hidden;
        border: 1px solid #fecaca;
        border-radius: 18px;
        background: #ffffff;
        box-shadow:
            0 18px 45px
            rgba(127, 29, 29, 0.12);
    }

    .eliminar-cita-header {
        padding: 28px;
        text-align: center;
        border-bottom: 1px solid #fee2e2;
        background:
            linear-gradient(
                135deg,
                #fff7f7,
                #ffffff
            );
    }

    .eliminar-cita-icono {
        display: grid;
        width: 68px;
        height: 68px;
        margin: 0 auto 16px;
        place-items: center;
        border-radius: 50%;
        background: #fee2e2;
        font-size: 31px;
    }

    .eliminar-cita-header h1 {
        margin: 0 0 8px;
        color: #991b1b;
        font-size: 26px;
    }

    .eliminar-cita-header p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .eliminar-cita-alerta {
        margin: 22px 26px 0;
        padding: 14px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 700;
    }

    .eliminar-cita-contenido {
        padding: 26px;
    }

    .eliminar-cita-resumen {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 15px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
    }

    .eliminar-cita-dato {
        display: grid;
        gap: 5px;
    }

    .eliminar-cita-dato.completo {
        grid-column: 1 / -1;
    }

    .eliminar-cita-dato span {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .eliminar-cita-dato strong {
        color: #172033;
        font-size: 15px;
        line-height: 1.45;
    }

    .eliminar-cita-advertencia {
        margin: 20px 0 0;
        padding: 14px 16px;
        border-left: 4px solid #dc2626;
        border-radius: 8px;
        background: #fef2f2;
        color: #7f1d1d;
        line-height: 1.55;
    }

    .eliminar-cita-acciones {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .eliminar-cita-btn {
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

    .eliminar-cita-cancelar {
        background: #e9eef5;
        color: #334155;
    }

    .eliminar-cita-confirmar {
        background: #dc2626;
        color: #ffffff;
        box-shadow:
            0 8px 18px
            rgba(220, 38, 38, 0.22);
    }

    .eliminar-cita-confirmar:hover {
        background: #b91c1c;
    }

    @media (max-width: 650px) {
        .eliminar-cita-resumen {
            grid-template-columns: 1fr;
        }

        .eliminar-cita-dato.completo {
            grid-column: auto;
        }

        .eliminar-cita-header,
        .eliminar-cita-contenido {
            padding-left: 20px;
            padding-right: 20px;
        }

        .eliminar-cita-acciones {
            flex-direction: column-reverse;
        }

        .eliminar-cita-btn {
            width: 100%;
        }
    }
</style>

<div class="eliminar-cita-page">
    <section class="eliminar-cita-card">
        <header class="eliminar-cita-header">
            <div class="eliminar-cita-icono">🗑️</div>

            <h1>Eliminar cita</h1>

            <p>
                Revisa la información antes de confirmar
                la eliminación definitiva.
            </p>
        </header>

        <?php if ($mensajeError !== ''): ?>
            <div
                class="eliminar-cita-alerta"
                role="alert"
            >
                ⚠️ <?= e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="eliminar-cita-contenido">
            <div class="eliminar-cita-resumen">
                <div class="eliminar-cita-dato">
                    <span>Identificador</span>
                    <strong>#<?= (int) ($cita['id'] ?? 0) ?></strong>
                </div>

                <div class="eliminar-cita-dato">
                    <span>Estado</span>
                    <strong>
                        <?= e($cita['estado'] ?? 'No registrado') ?>
                    </strong>
                </div>

                <div class="eliminar-cita-dato">
                    <span>Fecha</span>
                    <strong><?= e($fechaTexto) ?></strong>
                </div>

                <div class="eliminar-cita-dato">
                    <span>Hora</span>
                    <strong><?= e($horaTexto) ?></strong>
                </div>

                <div class="eliminar-cita-dato">
                    <span>Mascota</span>
                    <strong>
                        <?= e($cita['mascota_nombre'] ?? 'No registrada') ?>
                        ·
                        <?= e($cita['mascota_especie'] ?? 'Sin especie') ?>
                    </strong>
                </div>

                <div class="eliminar-cita-dato">
                    <span>Propietario</span>
                    <strong><?= e($clienteNombre) ?></strong>
                </div>

                <div class="eliminar-cita-dato completo">
                    <span>Motivo</span>
                    <strong>
                        <?= e($cita['motivo'] ?? 'No registrado') ?>
                    </strong>
                </div>
            </div>

            <p class="eliminar-cita-advertencia">
                <strong>Atención:</strong>
                esta acción eliminará la cita de la base de datos
                y no se puede deshacer.
            </p>

            <form
                method="POST"
                action="<?= e(
                    url(
                        'citas/eliminar.php?id=' .
                        (int) ($cita['id'] ?? 0)
                    )
                ) ?>"
                onsubmit="
                    return confirm(
                        '¿Confirmas que deseas eliminar esta cita?'
                    );
                "
            >
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) ($cita['id'] ?? 0) ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(
                        $_SESSION['csrf_eliminar_cita']
                    ) ?>"
                >

                <div class="eliminar-cita-acciones">
                    <a
                        class="
                            eliminar-cita-btn
                            eliminar-cita-cancelar
                        "
                        href="<?= e(url('citas/index.php')) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="
                            eliminar-cita-btn
                            eliminar-cita-confirmar
                        "
                    >
                        🗑️ Sí, eliminar cita
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>
