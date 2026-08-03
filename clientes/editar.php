<?php
declare(strict_types=1);

$raiz = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

require_login();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No se encontró una conexión PDO válida.');
}

if (empty($_SESSION['csrf_editar_cliente'])) {
    $_SESSION['csrf_editar_cliente'] = bin2hex(random_bytes(32));
}

$idCliente = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $idCliente = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );
}

if (!$idCliente || $idCliente <= 0) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'El cliente solicitado no existe.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

$mensajeError = '';

try {
    $consulta = $pdo->prepare(
        'SELECT
            id,
            nombres,
            apellidos,
            cedula,
            telefono,
            email
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );

    $consulta->execute([
        'id' => $idCliente
    ]);

    $cliente = $consulta->fetch();

    if (!$cliente) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'El cliente solicitado no existe.'
        ];

        header(
            'Location: ' .
            url('clientes/index.php')
        );

        exit;
    }
} catch (Throwable $error) {
    error_log(
        'Error al consultar cliente: ' .
        $error->getMessage()
    );

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'No se pudo consultar el cliente.'
    ];

    header(
        'Location: ' .
        url('clientes/index.php')
    );

    exit;
}

$datos = [
    'nombres' => (string) $cliente['nombres'],
    'apellidos' => (string) $cliente['apellidos'],
    'cedula' => (string) $cliente['cedula'],
    'telefono' => (string) $cliente['telefono'],
    'email' => (string) $cliente['email']
];

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

        'email' => trim(
            (string) ($_POST['email'] ?? '')
        )
    ];

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_editar_cliente'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals(
            $tokenSesion,
            $tokenFormulario
        )
    ) {
        $mensajeError =
            'La sesión del formulario expiró. Recarga la página.';
    } elseif (
        $datos['nombres'] === '' ||
        $datos['apellidos'] === '' ||
        $datos['cedula'] === '' ||
        $datos['telefono'] === '' ||
        $datos['email'] === ''
    ) {
        $mensajeError =
            'Todos los campos son obligatorios.';
    } elseif (
        !preg_match(
            '/^[0-9]{10}$/',
            $datos['cedula']
        )
    ) {
        $mensajeError =
            'La cédula debe contener exactamente 10 números.';
    } elseif (
        !preg_match(
            '/^[0-9]{10}$/',
            $datos['telefono']
        )
    ) {
        $mensajeError =
            'El teléfono debe contener exactamente 10 números.';
    } elseif (
        !filter_var(
            $datos['email'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $mensajeError =
            'El correo electrónico no es válido.';
    } else {
        try {
            $verificar = $pdo->prepare(
                'SELECT id
                 FROM clientes
                 WHERE
                    (cedula = :cedula OR email = :email)
                    AND id <> :id
                 LIMIT 1'
            );

            $verificar->execute([
                'cedula' => $datos['cedula'],
                'email' => $datos['email'],
                'id' => $idCliente
            ]);

            if ($verificar->fetch()) {
                $mensajeError =
                    'La cédula o el correo pertenecen a otro cliente.';
            } else {
                $actualizar = $pdo->prepare(
                    'UPDATE clientes
                     SET
                        nombres = :nombres,
                        apellidos = :apellidos,
                        cedula = :cedula,
                        telefono = :telefono,
                        email = :email
                     WHERE id = :id'
                );

                $actualizar->execute([
                    'nombres' => $datos['nombres'],
                    'apellidos' => $datos['apellidos'],
                    'cedula' => $datos['cedula'],
                    'telefono' => $datos['telefono'],
                    'email' => $datos['email'],
                    'id' => $idCliente
                ]);

                $_SESSION['csrf_editar_cliente'] =
                    bin2hex(random_bytes(32));

                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => 'Cliente actualizado correctamente.'
                ];

                header(
                    'Location: ' .
                    url('clientes/index.php')
                );

                exit;
            }
        } catch (Throwable $error) {
            error_log(
                'Error al actualizar cliente: ' .
                $error->getMessage()
            );

            $mensajeError =
                'No se pudo actualizar el cliente.';
        }
    }
}

$pageTitle = 'Editar cliente';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<style>
    .cliente-form-container {
        display: flex;
        justify-content: center;
        padding: 10px 0 40px;
    }

    .cliente-form-card {
        width: min(850px, 100%);
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #dce5f0;
        border-radius: 18px;
        box-shadow: 0 14px 35px rgba(15, 35, 65, 0.09);
    }

    .cliente-form-header {
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
    }

    .cliente-form-header h2 {
        margin: 0 0 6px;
        color: #06234a;
    }

    .cliente-form-header p {
        margin: 0;
        color: #64748b;
    }

    .cliente-form {
        padding: 28px;
    }

    .cliente-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .cliente-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cliente-group-full {
        grid-column: 1 / -1;
    }

    .cliente-group label {
        color: #334155;
        font-weight: 700;
    }

    .cliente-group input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        font: inherit;
        outline: none;
    }

    .cliente-group input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.13);
    }

    .cliente-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .cliente-form-button {
        display: inline-flex;
        justify-content: center;
        align-items: center;
        min-height: 43px;
        padding: 10px 18px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .cliente-button-cancel {
        background: #e9eef5;
        color: #334155;
    }

    .cliente-button-save {
        background: #c47b13;
        color: #ffffff;
    }

    .cliente-error {
        margin: 22px 28px 0;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 10px;
        background: #fff1f2;
        color: #b91c1c;
        font-weight: 700;
    }

    @media (max-width: 700px) {
        .cliente-grid {
            grid-template-columns: 1fr;
        }

        .cliente-group-full {
            grid-column: auto;
        }
    }
</style>

<div class="cliente-form-container">

    <div class="cliente-form-card">

        <div class="cliente-form-header">
            <h2>Editar información del cliente</h2>

            <p>
                Modifica los datos y guarda los cambios.
            </p>
        </div>

        <?php if ($mensajeError !== ''): ?>
            <div class="cliente-error">
                ⚠️ <?= e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            class="cliente-form"
            autocomplete="off"
        >
            <input
                type="hidden"
                name="id"
                value="<?= $idCliente ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_editar_cliente']) ?>"
            >

            <div class="cliente-grid">

                <div class="cliente-group">
                    <label for="nombres">Nombres</label>

                    <input
                        type="text"
                        id="nombres"
                        name="nombres"
                        maxlength="100"
                        value="<?= e($datos['nombres']) ?>"
                        required
                    >
                </div>

                <div class="cliente-group">
                    <label for="apellidos">Apellidos</label>

                    <input
                        type="text"
                        id="apellidos"
                        name="apellidos"
                        maxlength="100"
                        value="<?= e($datos['apellidos']) ?>"
                        required
                    >
                </div>

                <div class="cliente-group">
                    <label for="cedula">Cédula</label>

                    <input
                        type="text"
                        id="cedula"
                        name="cedula"
                        maxlength="10"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        value="<?= e($datos['cedula']) ?>"
                        required
                    >
                </div>

                <div class="cliente-group">
                    <label for="telefono">Teléfono</label>

                    <input
                        type="text"
                        id="telefono"
                        name="telefono"
                        maxlength="10"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        value="<?= e($datos['telefono']) ?>"
                        required
                    >
                </div>

                <div class="cliente-group cliente-group-full">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        value="<?= e($datos['email']) ?>"
                        required
                    >
                </div>

            </div>

            <div class="cliente-form-actions">

                <a
                    class="cliente-form-button cliente-button-cancel"
                    href="<?= e(url('clientes/index.php')) ?>"
                >
                    Cancelar
                </a>

                <button
                    class="cliente-form-button cliente-button-save"
                    type="submit"
                >
                    💾 Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    document
        .querySelectorAll('#cedula, #telefono')
        .forEach((campo) => {
            campo.addEventListener('input', () => {
                campo.value = campo.value
                    .replace(/\D/g, '')
                    .slice(0, 10);
            });
        });
</script>

<?php
require_once $raiz . '/includes/footer.php';
?>