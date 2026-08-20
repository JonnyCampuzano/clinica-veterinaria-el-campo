<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   CARGAR ARCHIVOS DEL SISTEMA
===================================================== */

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/config/crypto.php';
require_once $raiz . '/includes/auth.php';

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   PROTEGER LA PÁGINA
===================================================== */

if (function_exists('require_login')) {
    require_login();
} elseif (
    empty($_SESSION['usuario_id']) &&
    empty($_SESSION['id_usuario']) &&
    empty($_SESSION['usuario'])
) {
    if (function_exists('redirect')) {
        redirect('login.php');
    }

    header('Location: ../login.php');
    exit;
}

/* =====================================================
   VALIDAR CONEXIÓN
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Error: config/conexion.php debe crear una conexión PDO llamada $pdo.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function clienteEditarEscapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function clienteEditarUrl(string $ruta): string
{
    if (function_exists('url')) {
        return url($ruta);
    }

    return '../' . ltrim($ruta, '/');
}

/* =====================================================
   CARGAR CLIENTE
===================================================== */

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: ' . clienteEditarUrl('clientes/index.php?error=id_invalido'));
    exit;
}

$cliente = null;
$mensajeError = trim((string) ($_GET['error'] ?? ''));

try {
    $stmt = $pdo->prepare(
        'SELECT
            id,
            nombres,
            apellidos,
            cedula,
            telefono,
            email,
            direccion
         FROM clientes
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $id,
    ]);

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($fila)) {
        header('Location: ' . clienteEditarUrl('clientes/index.php?error=no_encontrado'));
        exit;
    }

    $cliente = [
        'id' => (int) ($fila['id'] ?? 0),
        'nombres' => decrypt_personal($fila['nombres'] ?? null),
        'apellidos' => decrypt_personal($fila['apellidos'] ?? null),
        'cedula' => decrypt_personal($fila['cedula'] ?? null),
        'telefono' => decrypt_personal($fila['telefono'] ?? null),
        'email' => decrypt_personal($fila['email'] ?? null),
        'direccion' => decrypt_personal($fila['direccion'] ?? null),
    ];
} catch (Throwable $error) {
    error_log('Error cargando cliente para editar: ' . $error->getMessage());
    $mensajeError = 'No se pudo cargar la información del cliente.';
}

if (!is_array($cliente)) {
    $cliente = [
        'id' => $id,
        'nombres' => '',
        'apellidos' => '',
        'cedula' => '',
        'telefono' => '',
        'email' => '',
        'direccion' => '',
    ];
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Editar cliente';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<style>
    .cliente-edit-page {
        width: min(980px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .cliente-edit-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .cliente-edit-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .cliente-edit-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .cliente-edit-icon {
        display: grid;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        place-items: center;
        border-radius: 14px;
        background: #e0ecff;
        font-size: 23px;
    }

    .cliente-edit-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .cliente-edit-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .cliente-edit-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 9px 14px;
        border-radius: 10px;
        background: #eef2f7;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        transition: .2s ease;
    }

    .cliente-edit-back:hover {
        background: #e2e8f0;
    }

    .cliente-edit-body {
        padding: 28px;
    }

    .cliente-edit-alert {
        margin-bottom: 22px;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 700;
    }

    .cliente-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .cliente-edit-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cliente-edit-group-full {
        grid-column: 1 / -1;
    }

    .cliente-edit-group label {
        color: #1e293b;
        font-size: 13px;
        font-weight: 800;
    }

    .cliente-edit-group label span {
        color: #dc2626;
    }

    .cliente-edit-control {
        width: 100%;
        box-sizing: border-box;
        min-height: 44px;
        padding: 11px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        font-size: 14px;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .cliente-edit-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    textarea.cliente-edit-control {
        min-height: 105px;
        resize: vertical;
    }

    .cliente-edit-help {
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.4;
    }

    .cliente-edit-security {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 24px;
        padding: 14px 16px;
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        background: #eff6ff;
        color: #1e40af;
        font-size: 13px;
        line-height: 1.5;
    }

    .cliente-edit-security strong {
        color: #1e3a8a;
    }

    .cliente-edit-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e8eef5;
    }

    .cliente-edit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 17px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        transition: .2s ease;
    }

    .cliente-edit-btn-cancel {
        background: #eef2f7;
        color: #334155;
    }

    .cliente-edit-btn-cancel:hover {
        background: #e2e8f0;
    }

    .cliente-edit-btn-save {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .cliente-edit-btn-save:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    @media (max-width: 760px) {
        .cliente-edit-header {
            align-items: stretch;
            flex-direction: column;
        }

        .cliente-edit-back {
            width: 100%;
            box-sizing: border-box;
        }

        .cliente-edit-grid {
            grid-template-columns: 1fr;
        }

        .cliente-edit-group-full {
            grid-column: auto;
        }

        .cliente-edit-body,
        .cliente-edit-header {
            padding-left: 18px;
            padding-right: 18px;
        }

        .cliente-edit-actions {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .cliente-edit-btn {
            width: 100%;
            box-sizing: border-box;
        }
    }
</style>

<div class="cliente-edit-page">
    <section class="cliente-edit-panel">

        <header class="cliente-edit-header">
            <div class="cliente-edit-title">
                <div class="cliente-edit-icon">✏️</div>

                <div>
                    <h1>Editar cliente</h1>
                    <p>
                        Actualiza la información personal y de contacto de
                        <strong><?= clienteEditarEscapar(
                            trim(
                                (string) ($cliente['nombres'] ?? '') . ' ' .
                                (string) ($cliente['apellidos'] ?? '')
                            )
                        ) ?></strong>.
                    </p>
                </div>
            </div>

            <a
                class="cliente-edit-back"
                href="<?= clienteEditarEscapar(
                    clienteEditarUrl('clientes/index.php')
                ) ?>"
            >
                ← Volver a clientes
            </a>
        </header>

        <div class="cliente-edit-body">

            <?php if ($mensajeError !== ''): ?>
                <div
                    class="cliente-edit-alert"
                    role="alert"
                >
                    ⚠️ <?= clienteEditarEscapar($mensajeError) ?>
                </div>
            <?php endif; ?>

            <form
                action="<?= clienteEditarEscapar(
                    clienteEditarUrl('clientes/actualizar.php')
                ) ?>"
                method="POST"
                autocomplete="off"
            >
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) ($cliente['id'] ?? 0) ?>"
                >

                <div class="cliente-edit-grid">

                    <div class="cliente-edit-group">
                        <label for="nombres">
                            Nombres <span>*</span>
                        </label>

                        <input
                            class="cliente-edit-control"
                            type="text"
                            id="nombres"
                            name="nombres"
                            maxlength="100"
                            value="<?= clienteEditarEscapar($cliente['nombres'] ?? '') ?>"
                            placeholder="Ej. Juan Carlos"
                            required
                            autofocus
                        >
                    </div>

                    <div class="cliente-edit-group">
                        <label for="apellidos">
                            Apellidos <span>*</span>
                        </label>

                        <input
                            class="cliente-edit-control"
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            maxlength="100"
                            value="<?= clienteEditarEscapar($cliente['apellidos'] ?? '') ?>"
                            placeholder="Ej. Pérez Gómez"
                            required
                        >
                    </div>

                    <div class="cliente-edit-group">
                        <label for="cedula">Cédula</label>

                        <input
                            class="cliente-edit-control"
                            type="text"
                            id="cedula"
                            name="cedula"
                            maxlength="13"
                            inputmode="numeric"
                            value="<?= clienteEditarEscapar($cliente['cedula'] ?? '') ?>"
                            placeholder="Ej. 0951234567"
                        >

                        <small class="cliente-edit-help">
                            Ingresa únicamente números.
                        </small>
                    </div>

                    <div class="cliente-edit-group">
                        <label for="telefono">
                            Teléfono <span>*</span>
                        </label>

                        <input
                            class="cliente-edit-control"
                            type="tel"
                            id="telefono"
                            name="telefono"
                            maxlength="30"
                            value="<?= clienteEditarEscapar($cliente['telefono'] ?? '') ?>"
                            placeholder="Ej. 0991234567"
                            required
                        >
                    </div>

                    <div class="cliente-edit-group cliente-edit-group-full">
                        <label for="email">Correo electrónico</label>

                        <input
                            class="cliente-edit-control"
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?= clienteEditarEscapar($cliente['email'] ?? '') ?>"
                            placeholder="Ej. cliente@email.com"
                        >
                    </div>

                    <div class="cliente-edit-group cliente-edit-group-full">
                        <label for="direccion">Dirección</label>

                        <textarea
                            class="cliente-edit-control"
                            id="direccion"
                            name="direccion"
                            maxlength="255"
                            placeholder="Ej. Nobol, Guayas - Av. Principal..."
                        ><?= clienteEditarEscapar($cliente['direccion'] ?? '') ?></textarea>
                    </div>

                </div>

                <div class="cliente-edit-security">
                    <span>🔐</span>

                    <div>
                        <strong>Protección de datos personales.</strong><br>
                        Los cambios guardados en nombres, apellidos, cédula,
                        teléfono, correo y dirección se almacenan cifrados en la base de datos.
                    </div>
                </div>

                <div class="cliente-edit-actions">
                    <a
                        class="cliente-edit-btn cliente-edit-btn-cancel"
                        href="<?= clienteEditarEscapar(
                            clienteEditarUrl('clientes/index.php')
                        ) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="cliente-edit-btn cliente-edit-btn-save"
                        type="submit"
                    >
                        💾 Guardar cambios
                    </button>
                </div>
            </form>

        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>