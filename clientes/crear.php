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
   FUNCIONES AUXILIARES
===================================================== */

function textoSeguroClienteCrear(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =====================================================
   MENSAJE DE ERROR
===================================================== */

$error = trim((string) ($_GET['error'] ?? ''));

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Registrar cliente';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<style>
    .cliente-form-page {
        width: min(980px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .cliente-form-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .cliente-form-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .cliente-form-title {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .cliente-form-icon {
        display: grid;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        place-items: center;
        border-radius: 14px;
        background: #e0ecff;
        font-size: 23px;
    }

    .cliente-form-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .cliente-form-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .cliente-form-back {
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

    .cliente-form-back:hover {
        background: #e2e8f0;
    }

    .cliente-form-body {
        padding: 28px;
    }

    .cliente-form-alert {
        margin-bottom: 22px;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 14px;
        font-weight: 700;
    }

    .cliente-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .cliente-form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cliente-form-group-full {
        grid-column: 1 / -1;
    }

    .cliente-form-group label {
        color: #1e293b;
        font-size: 13px;
        font-weight: 800;
    }

    .cliente-form-group label span {
        color: #dc2626;
    }

    .cliente-form-control {
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

    .cliente-form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    textarea.cliente-form-control {
        min-height: 105px;
        resize: vertical;
    }

    .cliente-form-help {
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.4;
    }

    .cliente-form-security {
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

    .cliente-form-security strong {
        color: #1e3a8a;
    }

    .cliente-form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid #e8eef5;
    }

    .cliente-form-btn {
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

    .cliente-form-btn-cancel {
        background: #eef2f7;
        color: #334155;
    }

    .cliente-form-btn-cancel:hover {
        background: #e2e8f0;
    }

    .cliente-form-btn-save {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .cliente-form-btn-save:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    @media (max-width: 760px) {
        .cliente-form-header {
            align-items: stretch;
            flex-direction: column;
        }

        .cliente-form-back {
            width: 100%;
            box-sizing: border-box;
        }

        .cliente-form-grid {
            grid-template-columns: 1fr;
        }

        .cliente-form-group-full {
            grid-column: auto;
        }

        .cliente-form-body,
        .cliente-form-header {
            padding-left: 18px;
            padding-right: 18px;
        }

        .cliente-form-actions {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .cliente-form-btn {
            width: 100%;
            box-sizing: border-box;
        }
    }
</style>

<div class="cliente-form-page">
    <section class="cliente-form-panel">

        <header class="cliente-form-header">
            <div class="cliente-form-title">
                <div class="cliente-form-icon">👤</div>

                <div>
                    <h1>Registrar cliente</h1>
                    <p>
                        Ingresa la información personal y de contacto del cliente.
                    </p>
                </div>
            </div>

            <a
                class="cliente-form-back"
                href="<?= textoSeguroClienteCrear(url('clientes/index.php')) ?>"
            >
                ← Volver a clientes
            </a>
        </header>

        <div class="cliente-form-body">

            <?php if ($error !== ''): ?>
                <div
                    class="cliente-form-alert"
                    role="alert"
                >
                    ⚠️ <?= textoSeguroClienteCrear($error) ?>
                </div>
            <?php endif; ?>

            <form
                action="<?= textoSeguroClienteCrear(url('clientes/guardar.php')) ?>"
                method="POST"
                autocomplete="off"
            >
                <div class="cliente-form-grid">

                    <div class="cliente-form-group">
                        <label for="nombres">
                            Nombres <span>*</span>
                        </label>

                        <input
                            class="cliente-form-control"
                            type="text"
                            id="nombres"
                            name="nombres"
                            maxlength="100"
                            placeholder="Ej. Juan Carlos"
                            required
                            autofocus
                        >
                    </div>

                    <div class="cliente-form-group">
                        <label for="apellidos">
                            Apellidos <span>*</span>
                        </label>

                        <input
                            class="cliente-form-control"
                            type="text"
                            id="apellidos"
                            name="apellidos"
                            maxlength="100"
                            placeholder="Ej. Pérez Gómez"
                            required
                        >
                    </div>

                    <div class="cliente-form-group">
                        <label for="cedula">Cédula</label>

                        <input
                            class="cliente-form-control"
                            type="text"
                            id="cedula"
                            name="cedula"
                            maxlength="13"
                            inputmode="numeric"
                            placeholder="Ej. 0951234567"
                        >

                        <small class="cliente-form-help">
                            Ingresa únicamente números.
                        </small>
                    </div>

                    <div class="cliente-form-group">
                        <label for="telefono">
                            Teléfono <span>*</span>
                        </label>

                        <input
                            class="cliente-form-control"
                            type="tel"
                            id="telefono"
                            name="telefono"
                            maxlength="30"
                            placeholder="Ej. 0991234567"
                            required
                        >
                    </div>

                    <div class="cliente-form-group cliente-form-group-full">
                        <label for="email">Correo electrónico</label>

                        <input
                            class="cliente-form-control"
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            placeholder="Ej. cliente@email.com"
                        >
                    </div>

                    <div class="cliente-form-group cliente-form-group-full">
                        <label for="direccion">Dirección</label>

                        <textarea
                            class="cliente-form-control"
                            id="direccion"
                            name="direccion"
                            maxlength="255"
                            placeholder="Ej. Nobol, Guayas - Av. Principal..."
                        ></textarea>
                    </div>

                </div>

                <div class="cliente-form-security">
                    <span>🔐</span>

                    <div>
                        <strong>Protección de datos personales.</strong><br>
                        Los nombres, apellidos, cédula, teléfono, correo y dirección
                        se cifran antes de almacenarse en la base de datos.
                    </div>
                </div>

                <div class="cliente-form-actions">
                    <a
                        class="cliente-form-btn cliente-form-btn-cancel"
                        href="<?= textoSeguroClienteCrear(url('clientes/index.php')) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="cliente-form-btn cliente-form-btn-save"
                        type="submit"
                    >
                        💾 Guardar cliente
                    </button>
                </div>
            </form>

        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>