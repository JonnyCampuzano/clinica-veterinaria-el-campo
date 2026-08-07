<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   CARGAR ARCHIVOS
===================================================== */

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/* =====================================================
   VALIDAR USUARIO
===================================================== */

require_login();

/* =====================================================
   COMPROBAR CONEXIÓN
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        'No se encontró una conexión PDO válida.'
    );
}

/* =====================================================
   TOKEN DE SEGURIDAD
===================================================== */

if (empty($_SESSION['csrf_crear_cliente'])) {
    $_SESSION['csrf_crear_cliente'] =
        bin2hex(random_bytes(32));
}

/* =====================================================
   DATOS DEL FORMULARIO
===================================================== */

$datos = [
    'nombres' => '',
    'apellidos' => '',
    'cedula' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => ''
];

$mensajeError = '';

/* =====================================================
   REGISTRAR CLIENTE
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombres' => trim(
            (string) ($_POST['nombres'] ?? '')
        ),
        'apellidos' => trim(
            (string) ($_POST['apellidos'] ?? '')
        ),
        'cedula' => trim(
            (string) ($_POST['cedula'] ?? '')
        ),
        'telefono' => trim(
            (string) ($_POST['telefono'] ?? '')
        ),
        'email' => strtolower(
            trim((string) ($_POST['email'] ?? ''))
        ),
        'direccion' => trim(
            (string) ($_POST['direccion'] ?? '')
        )
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_crear_cliente'] ?? ''
    );

    /* =================================================
       VALIDACIONES
    ================================================= */

    if (
        $tokenSesion === '' ||
        $tokenFormulario === '' ||
        !hash_equals($tokenSesion, $tokenFormulario)
    ) {
        $mensajeError =
            'La sesión del formulario expiró. Recarga la página.';
    } elseif (
        $datos['nombres'] === '' ||
        $datos['apellidos'] === '' ||
        $datos['cedula'] === '' ||
        $datos['telefono'] === '' ||
        $datos['email'] === '' ||
        $datos['direccion'] === ''
    ) {
        $mensajeError = 'Todos los campos son obligatorios.';
    } elseif (strlen($datos['nombres']) > 100) {
        $mensajeError = 'Los nombres no pueden superar 100 caracteres.';
    } elseif (strlen($datos['apellidos']) > 100) {
        $mensajeError = 'Los apellidos no pueden superar 100 caracteres.';
    } elseif (!preg_match('/^[0-9]{10}$/', $datos['cedula'])) {
        $mensajeError =
            'La cédula debe contener exactamente 10 números.';
    } elseif (!preg_match('/^[0-9]{10}$/', $datos['telefono'])) {
        $mensajeError =
            'El teléfono debe contener exactamente 10 números.';
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $mensajeError = 'El correo electrónico no es válido.';
    } elseif (strlen($datos['email']) > 150) {
        $mensajeError = 'El correo no puede superar 150 caracteres.';
    } elseif (strlen($datos['direccion']) > 255) {
        $mensajeError = 'La dirección no puede superar 255 caracteres.';
    } else {
        try {
            /* =========================================
               COMPROBAR DUPLICADOS
            ========================================= */

            $verificar = $pdo->prepare(
                'SELECT id
                 FROM clientes
                 WHERE cedula = :cedula
                    OR email = :email
                 LIMIT 1'
            );

            $verificar->execute([
                ':cedula' => $datos['cedula'],
                ':email' => $datos['email']
            ]);

            if ($verificar->fetch(PDO::FETCH_ASSOC)) {
                $mensajeError =
                    'Ya existe un cliente con esa cédula o correo.';
            } else {
                /* =====================================
                   INSERTAR CLIENTE
                ===================================== */

                $registrar = $pdo->prepare(
                    'INSERT INTO clientes (
                        nombres,
                        apellidos,
                        cedula,
                        telefono,
                        email,
                        direccion
                    ) VALUES (
                        :nombres,
                        :apellidos,
                        :cedula,
                        :telefono,
                        :email,
                        :direccion
                    )'
                );

                $registrar->execute([
                    ':nombres' => $datos['nombres'],
                    ':apellidos' => $datos['apellidos'],
                    ':cedula' => $datos['cedula'],
                    ':telefono' => $datos['telefono'],
                    ':email' => $datos['email'],
                    ':direccion' => $datos['direccion']
                ]);

                $_SESSION['csrf_crear_cliente'] =
                    bin2hex(random_bytes(32));

                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Cliente registrado correctamente.'
                ];

                header(
                    'Location: ' . url('clientes/index.php')
                );
                exit;
            }
        } catch (PDOException $error) {
            error_log(
                'Error PDO al registrar cliente: ' .
                $error->getMessage()
            );

            if ($error->getCode() === '23000') {
                $mensajeError =
                    'La cédula o el correo ya están registrados.';
            } else {
                $mensajeError =
                    'No se pudo registrar el cliente. Revisa la conexión y la tabla clientes.';
            }
        } catch (Throwable $error) {
            error_log(
                'Error inesperado al registrar cliente: ' .
                $error->getMessage()
            );

            $mensajeError =
                'Ocurrió un error inesperado al registrar el cliente.';
        }
    }
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Registrar nuevo cliente';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<style>
    .registro-cliente-wrapper {
        display: flex;
        justify-content: center;
        padding: 10px 0 40px;
    }

    .registro-cliente-card {
        width: min(850px, 100%);
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #dce5f0;
        border-radius: 18px;
        box-shadow:
            0 14px 35px
            rgba(15, 35, 65, 0.09);
    }

    .registro-cliente-header {
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background:
            linear-gradient(
                135deg,
                #ffffff,
                #f8fbff
            );
    }

    .registro-cliente-header h2 {
        margin: 0 0 6px;
        color: #06234a;
        font-size: 22px;
    }

    .registro-cliente-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .registro-alerta {
        margin: 22px 28px 0;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 10px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 700;
    }

    .registro-cliente-form {
        padding: 28px;
    }

    .registro-grid {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .registro-grupo {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .registro-grupo-completo {
        grid-column: 1 / -1;
    }

    .registro-grupo label {
        color: #334155;
        font-size: 14px;
        font-weight: 700;
    }

    .registro-grupo input {
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

    .registro-grupo input:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 3px
            rgba(37, 99, 235, 0.13);
    }

    .registro-ayuda {
        color: #64748b;
        font-size: 12px;
    }

    .registro-acciones {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .registro-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 43px;
        padding: 10px 18px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .registro-btn-cancelar {
        background: #e9eef5;
        color: #334155;
    }

    .registro-btn-guardar {
        background: #2563eb;
        color: #ffffff;
        box-shadow:
            0 8px 18px
            rgba(37, 99, 235, 0.22);
    }

    .registro-btn-guardar:hover {
        background: #1d4ed8;
    }

    @media (max-width: 700px) {
        .registro-grid {
            grid-template-columns: 1fr;
        }

        .registro-grupo-completo {
            grid-column: auto;
        }

        .registro-cliente-header,
        .registro-cliente-form {
            padding-left: 20px;
            padding-right: 20px;
        }

        .registro-acciones {
            flex-direction: column-reverse;
        }

        .registro-btn {
            width: 100%;
        }
    }
</style>

<div class="registro-cliente-wrapper">

    <div class="registro-cliente-card">

        <div class="registro-cliente-header">

            <h2>
                Datos del nuevo cliente
            </h2>

            <p>
                Complete la información del propietario.
            </p>

        </div>

        <?php if ($mensajeError !== ''): ?>

            <div
                class="registro-alerta"
                role="alert"
            >
                ⚠️ <?= e($mensajeError) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            class="registro-cliente-form"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?=
                    e(
                        $_SESSION[
                            'csrf_crear_cliente'
                        ]
                    )
                ?>"
            >

            <div class="registro-grid">

                <div class="registro-grupo">

                    <label for="nombres">
                        Nombres
                    </label>

                    <input
                        type="text"
                        id="nombres"
                        name="nombres"
                        maxlength="100"
                        placeholder="Ejemplo: Juan Carlos"
                        value="<?= e($datos['nombres']) ?>"
                        required
                        autofocus
                    >

                </div>

                <div class="registro-grupo">

                    <label for="apellidos">
                        Apellidos
                    </label>

                    <input
                        type="text"
                        id="apellidos"
                        name="apellidos"
                        maxlength="100"
                        placeholder="Ejemplo: Pérez Gómez"
                        value="<?= e($datos['apellidos']) ?>"
                        required
                    >

                </div>

                <div class="registro-grupo">

                    <label for="cedula">
                        Cédula
                    </label>

                    <input
                        type="text"
                        id="cedula"
                        name="cedula"
                        maxlength="10"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        placeholder="0912345678"
                        value="<?= e($datos['cedula']) ?>"
                        required
                    >

                    <span class="registro-ayuda">
                        Debe contener 10 números.
                    </span>

                </div>

                <div class="registro-grupo">

                    <label for="telefono">
                        Teléfono
                    </label>

                    <input
                        type="text"
                        id="telefono"
                        name="telefono"
                        maxlength="10"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        placeholder="0991234567"
                        value="<?= e($datos['telefono']) ?>"
                        required
                    >

                    <span class="registro-ayuda">
                        Debe contener 10 números.
                    </span>

                </div>

                <div
                    class="
                        registro-grupo
                        registro-grupo-completo
                    "
                >

                    <label for="email">
                        Correo electrónico
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        placeholder="cliente@correo.com"
                        value="<?= e($datos['email']) ?>"
                        required
                    >

                </div>

                <div
                    class="
                        registro-grupo
                        registro-grupo-completo
                    "
                >

                    <label for="direccion">
                        Dirección
                    </label>

                    <input
                        type="text"
                        id="direccion"
                        name="direccion"
                        maxlength="255"
                        placeholder="Ejemplo: Av. Principal y calle 10"
                        value="<?= e($datos['direccion']) ?>"
                        required
                    >

                </div>

            </div>

            <div class="registro-acciones">

                <a
                    class="
                        registro-btn
                        registro-btn-cancelar
                    "
                    href="<?=
                        e(
                            url(
                                'clientes/index.php'
                            )
                        )
                    ?>"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="
                        registro-btn
                        registro-btn-guardar
                    "
                >
                    💾 Registrar cliente
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    document
        .querySelectorAll(
            '#cedula, #telefono'
        )
        .forEach((campo) => {
            campo.addEventListener(
                'input',
                () => {
                    campo.value = campo.value
                        .replace(/\D/g, '')
                        .slice(0, 10);
                }
            );
        });
</script>

<?php
require_once $raiz . '/includes/footer.php';
?>