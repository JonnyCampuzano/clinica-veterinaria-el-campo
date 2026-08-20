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
require_once $raiz . '/config/crypto.php';
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

function escaparEliminarMascota(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirigirMascotas(string $parametro = ''): never
{
    $ruta = 'mascotas/index.php';

    if ($parametro !== '') {
        $ruta .= '?' . $parametro;
    }

    header('Location: ' . url($ruta));
    exit;
}

/* =====================================================
   OBTENER ID
===================================================== */

$idMascota = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['id'] ?? 0)
    : (int) ($_GET['id'] ?? 0);

if ($idMascota <= 0) {
    redirigirMascotas('error=id_invalido');
}

/* =====================================================
   TOKEN CSRF
===================================================== */

if (empty($_SESSION['csrf_eliminar_mascota'])) {
    $_SESSION['csrf_eliminar_mascota'] = bin2hex(
        random_bytes(32)
    );
}

/* =====================================================
   CARGAR MASCOTA
===================================================== */

$mensajeError = '';
$mascota = false;

try {
    $consulta = $pdo->prepare(
        'SELECT
            m.id,
            m.nombre,
            m.especie,
            m.raza,
            m.sexo,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos
         FROM mascotas AS m
         INNER JOIN clientes AS c
            ON c.id = m.cliente_id
         WHERE m.id = :id
         LIMIT 1'
    );

    $consulta->execute([
        ':id' => $idMascota,
    ]);

    $mascota = $consulta->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error al cargar mascota para eliminar: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudo cargar la información de la mascota.';
}

if (!is_array($mascota)) {
    redirigirMascotas('error=no_encontrada');
}

/* =====================================================
   DESCIFRAR DATOS DEL PROPIETARIO
===================================================== */

try {
    $mascota['cliente_nombres'] = decrypt_personal(
        $mascota['cliente_nombres'] ?? null
    );

    $mascota['cliente_apellidos'] = decrypt_personal(
        $mascota['cliente_apellidos'] ?? null
    );
} catch (Throwable $errorDescifrado) {
    error_log(
        'Error al descifrar propietario al eliminar mascota ID ' .
        $idMascota .
        ': ' .
        $errorDescifrado->getMessage()
    );

    $mascota['cliente_nombres'] = 'Dato protegido';
    $mascota['cliente_apellidos'] = '';
}

/* =====================================================
   ELIMINAR MASCOTA
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_eliminar_mascota'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $mensajeError =
            'La sesión del formulario expiró. ' .
            'Recarga la página e inténtalo nuevamente.';
    } else {
        try {
            $pdo->beginTransaction();

            /*
             * Bloqueamos la fila durante la eliminación para evitar que
             * cambie entre la confirmación y el DELETE.
             */
            $bloquear = $pdo->prepare(
                'SELECT id
                 FROM mascotas
                 WHERE id = :id
                 FOR UPDATE'
            );

            $bloquear->execute([
                ':id' => $idMascota,
            ]);

            if (!$bloquear->fetchColumn()) {
                $pdo->rollBack();
                redirigirMascotas('error=no_encontrada');
            }

            $eliminar = $pdo->prepare(
                'DELETE FROM mascotas
                 WHERE id = :id
                 LIMIT 1'
            );

            $eliminar->execute([
                ':id' => $idMascota,
            ]);

            if ($eliminar->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se eliminó ningún registro.'
                );
            }

            $pdo->commit();

            $_SESSION['csrf_eliminar_mascota'] = bin2hex(
                random_bytes(32)
            );

            redirigirMascotas('msg=eliminada');
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error PDO al eliminar mascota: ' .
                $error->getMessage()
            );

            $codigoSql = (string) $error->getCode();
            $codigoMySql = (int) (
                $error->errorInfo[1] ?? 0
            );

            if (
                $codigoSql === '23000' ||
                $codigoMySql === 1451
            ) {
                $mensajeError =
                    'No se puede eliminar esta mascota porque tiene ' .
                    'citas, consultas o registros clínicos relacionados. ' .
                    'Conserva la mascota o elimina primero esas relaciones.';
            } else {
                $mensajeError =
                    'No se pudo eliminar la mascota. ' .
                    'Revisa la conexión y la estructura de las tablas.';
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error inesperado al eliminar mascota: ' .
                $error->getMessage()
            );

            $mensajeError =
                'Ocurrió un error inesperado al eliminar la mascota.';
        }
    }
}

/* =====================================================
   DATOS PARA LA VISTA
===================================================== */

$nombreMascota = trim(
    (string) ($mascota['nombre'] ?? '')
);

$propietario = trim(
    (string) ($mascota['cliente_nombres'] ?? '') . ' ' .
    (string) ($mascota['cliente_apellidos'] ?? '')
);

$pageTitle = 'Eliminar mascota';
$activePage = 'mascotas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .eliminar-mascota-page {
        width: min(760px, 100%);
        margin: 0 auto;
        padding: 18px 0 42px;
    }

    .eliminar-mascota-card {
        overflow: hidden;
        border: 1px solid #fecaca;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 16px 40px rgba(127, 29, 29, 0.10);
    }

    .eliminar-mascota-header {
        padding: 26px 28px;
        border-bottom: 1px solid #fee2e2;
        background: linear-gradient(135deg, #fff7f7, #ffffff);
    }

    .eliminar-mascota-icono {
        display: grid;
        width: 58px;
        height: 58px;
        margin-bottom: 16px;
        place-items: center;
        border-radius: 16px;
        background: #fee2e2;
        font-size: 28px;
    }

    .eliminar-mascota-header h1 {
        margin: 0 0 8px;
        color: #7f1d1d;
        font-size: 26px;
    }

    .eliminar-mascota-header p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .eliminar-mascota-body {
        padding: 28px;
    }

    .eliminar-mascota-alerta {
        margin-bottom: 20px;
        padding: 14px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-weight: 700;
        line-height: 1.5;
    }

    .eliminar-mascota-datos {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }

    .eliminar-mascota-dato {
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
    }

    .eliminar-mascota-dato span {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .eliminar-mascota-dato strong {
        color: #1e293b;
        font-size: 15px;
    }

    .eliminar-mascota-aviso {
        margin: 0;
        padding: 15px 16px;
        border-left: 4px solid #ef4444;
        border-radius: 8px;
        background: #fff7ed;
        color: #7c2d12;
        line-height: 1.6;
    }

    .eliminar-mascota-acciones {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .eliminar-mascota-btn {
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

    .eliminar-mascota-cancelar {
        background: #e9eef5;
        color: #334155;
    }

    .eliminar-mascota-confirmar {
        background: #dc2626;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(220, 38, 38, 0.22);
    }

    .eliminar-mascota-confirmar:hover {
        background: #b91c1c;
    }

    @media (max-width: 620px) {
        .eliminar-mascota-datos {
            grid-template-columns: 1fr;
        }

        .eliminar-mascota-acciones {
            flex-direction: column-reverse;
        }

        .eliminar-mascota-btn {
            width: 100%;
        }
    }
</style>

<div class="eliminar-mascota-page">
    <section class="eliminar-mascota-card">
        <header class="eliminar-mascota-header">
            <div class="eliminar-mascota-icono">🗑️</div>

            <h1>Eliminar mascota</h1>

            <p>
                Confirma la eliminación del registro seleccionado.
            </p>
        </header>

        <div class="eliminar-mascota-body">
            <?php if ($mensajeError !== ''): ?>
                <div
                    class="eliminar-mascota-alerta"
                    role="alert"
                >
                    ⚠️ <?= escaparEliminarMascota($mensajeError) ?>
                </div>
            <?php endif; ?>

            <div class="eliminar-mascota-datos">
                <div class="eliminar-mascota-dato">
                    <span>Mascota</span>
                    <strong>
                        <?= escaparEliminarMascota(
                            $nombreMascota !== ''
                                ? $nombreMascota
                                : 'Sin nombre'
                        ) ?>
                    </strong>
                </div>

                <div class="eliminar-mascota-dato">
                    <span>Propietario</span>
                    <strong>
                        <?= escaparEliminarMascota(
                            $propietario !== ''
                                ? $propietario
                                : 'No registrado'
                        ) ?>
                    </strong>
                </div>

                <div class="eliminar-mascota-dato">
                    <span>Especie</span>
                    <strong>
                        <?= escaparEliminarMascota(
                            (string) ($mascota['especie'] ?? 'No registrada')
                        ) ?>
                    </strong>
                </div>

                <div class="eliminar-mascota-dato">
                    <span>Raza</span>
                    <strong>
                        <?= escaparEliminarMascota(
                            trim((string) ($mascota['raza'] ?? '')) !== ''
                                ? (string) $mascota['raza']
                                : 'No registrada'
                        ) ?>
                    </strong>
                </div>
            </div>

            <p class="eliminar-mascota-aviso">
                <strong>Esta acción no se puede deshacer.</strong>
                Si la mascota tiene registros clínicos relacionados,
                MySQL puede impedir la eliminación para proteger el historial.
            </p>

            <form method="POST">
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $idMascota ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparEliminarMascota(
                        $_SESSION['csrf_eliminar_mascota']
                    ) ?>"
                >

                <div class="eliminar-mascota-acciones">
                    <a
                        class="eliminar-mascota-btn eliminar-mascota-cancelar"
                        href="<?= escaparEliminarMascota(
                            url('mascotas/index.php')
                        ) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="eliminar-mascota-btn eliminar-mascota-confirmar"
                        type="submit"
                    >
                        🗑️ Sí, eliminar mascota
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>