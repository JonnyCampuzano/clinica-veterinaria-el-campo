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
   COMPROBAR CONEXIÓN PDO
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Error: config/conexion.php debe crear una conexión PDO llamada $pdo.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =====================================================
   BUSCADOR Y MENSAJES
===================================================== */

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$mensajeCodigo = trim((string) ($_GET['msg'] ?? $_GET['ok'] ?? ''));

$mensajesExito = [
    'creado' => 'Cliente registrado correctamente.',
    'creada' => 'Cliente registrado correctamente.',
    'actualizado' => 'Cliente actualizado correctamente.',
    'actualizada' => 'Cliente actualizado correctamente.',
    'eliminado' => 'Cliente eliminado correctamente.',
    'eliminada' => 'Cliente eliminado correctamente.',
];

$mensajeExito = $mensajesExito[$mensajeCodigo] ?? '';
$mensajeError = '';
$clientes = [];

/* =====================================================
   CONSULTAR Y DESCIFRAR CLIENTES
===================================================== */

try {
    $consulta = $pdo->query(
        'SELECT
            id,
            nombres,
            apellidos,
            cedula,
            telefono,
            email,
            direccion,
            created_at
         FROM clientes
         ORDER BY id DESC'
    );

    $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {
        try {
            $cliente = [
                'id' => (int) ($fila['id'] ?? 0),
                'nombres' => decrypt_personal($fila['nombres'] ?? null),
                'apellidos' => decrypt_personal($fila['apellidos'] ?? null),
                'cedula' => decrypt_personal($fila['cedula'] ?? null),
                'telefono' => decrypt_personal($fila['telefono'] ?? null),
                'email' => decrypt_personal($fila['email'] ?? null),
                'direccion' => decrypt_personal($fila['direccion'] ?? null),
                'created_at' => $fila['created_at'] ?? null,
            ];
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Error al descifrar cliente ID ' .
                (int) ($fila['id'] ?? 0) .
                ': ' .
                $errorDescifrado->getMessage()
            );

            $cliente = [
                'id' => (int) ($fila['id'] ?? 0),
                'nombres' => 'Dato protegido',
                'apellidos' => '',
                'cedula' => '',
                'telefono' => '',
                'email' => '',
                'direccion' => '',
                'created_at' => $fila['created_at'] ?? null,
            ];
        }

        /*
         * Los datos personales están cifrados en MySQL,
         * por eso la búsqueda se realiza después de descifrarlos.
         */
        if ($buscar !== '') {
            $campos = [
                $cliente['nombres'],
                $cliente['apellidos'],
                trim($cliente['nombres'] . ' ' . $cliente['apellidos']),
                $cliente['cedula'],
                $cliente['telefono'],
                $cliente['email'],
                $cliente['direccion'],
            ];

            $coincide = false;

            foreach ($campos as $campo) {
                $texto = (string) $campo;

                if (
                    function_exists('mb_stripos')
                        ? mb_stripos($texto, $buscar, 0, 'UTF-8') !== false
                        : stripos($texto, $buscar) !== false
                ) {
                    $coincide = true;
                    break;
                }
            }

            if (!$coincide) {
                continue;
            }
        }

        $clientes[] = $cliente;
    }
} catch (Throwable $error) {
    error_log('Error al consultar clientes: ' . $error->getMessage());

    $mensajeError =
        'No se pudieron cargar los clientes. ' .
        'Revisa la tabla clientes, la clave de cifrado y la conexión.';
}

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function textoSeguroCliente(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function valorVisibleCliente(
    mixed $valor,
    string $alternativa = 'No registrado'
): string {
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

/* =====================================================
   ENCABEZADO DEL SISTEMA
===================================================== */

$pageTitle = 'Clientes';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<style>
    .clientes-page {
        width: min(1180px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .clientes-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .clientes-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 26px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .clientes-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .clientes-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .clientes-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 16px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .clientes-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .clientes-btn-primary:hover {
        background: #1d4ed8;
    }

    .clientes-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 26px;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .clientes-search {
        display: flex;
        flex: 1;
        gap: 10px;
    }

    .clientes-search input {
        width: 100%;
        min-height: 42px;
        padding: 10px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        color: #0f172a;
        font: inherit;
        outline: none;
    }

    .clientes-search input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .clientes-search button {
        background: #0f766e;
        color: #ffffff;
    }

    .clientes-clear {
        background: #e9eef5;
        color: #334155;
    }

    .clientes-count {
        white-space: nowrap;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .clientes-alert {
        margin: 20px 26px 0;
        padding: 13px 16px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 700;
    }

    .clientes-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .clientes-alert-error {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #b91c1c;
    }

    .clientes-content {
        padding: 24px 26px 28px;
    }

    .clientes-table-wrapper {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
    }

    .clientes-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
    }

    .clientes-table th,
    .clientes-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .clientes-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 800;
    }

    .clientes-table tbody tr:hover {
        background: #f8fbff;
    }

    .clientes-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .cliente-identidad {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .cliente-avatar {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        place-items: center;
        border-radius: 12px;
        background: #e0ecff;
        font-size: 20px;
    }

    .cliente-identidad strong {
        display: block;
        color: #0f2747;
        font-size: 14px;
    }

    .cliente-identidad small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .cliente-info strong {
        display: block;
        color: #1e293b;
    }

    .cliente-info small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .cliente-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 8px;
        background: #fff7ed;
        color: #c2410c;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .clientes-empty {
        padding: 46px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        text-align: center;
        background: #fbfdff;
    }

    .clientes-empty span {
        display: block;
        margin-bottom: 12px;
        font-size: 38px;
    }

    .clientes-empty h2 {
        margin: 0 0 8px;
        color: #0f2747;
        font-size: 20px;
    }

    .clientes-empty p {
        margin: 0 0 18px;
        color: #64748b;
    }

    @media (max-width: 760px) {
        .clientes-header,
        .clientes-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .clientes-btn-primary {
            width: 100%;
        }

        .clientes-search {
            flex-wrap: wrap;
        }

        .clientes-search input {
            flex-basis: 100%;
        }

        .clientes-search button,
        .clientes-clear {
            flex: 1;
        }

        .clientes-header,
        .clientes-toolbar,
        .clientes-content {
            padding-left: 18px;
            padding-right: 18px;
        }

        .clientes-alert {
            margin-left: 18px;
            margin-right: 18px;
        }
    }
</style>

<div class="clientes-page">
    <section class="clientes-panel">

        <header class="clientes-header">
            <div>
                <h1>👥 Clientes registrados</h1>
                <p>Consulta y administra la información de los clientes.</p>
            </div>

            <a
                class="clientes-btn clientes-btn-primary"
                href="<?= textoSeguroCliente(url('clientes/crear.php')) ?>"
            >
                ＋ Registrar cliente
            </a>
        </header>

        <div class="clientes-toolbar">
            <form
                class="clientes-search"
                method="GET"
                action=""
            >
                <input
                    type="search"
                    name="buscar"
                    value="<?= textoSeguroCliente($buscar) ?>"
                    placeholder="Buscar por nombre, apellido, cédula, teléfono, email o dirección"
                    aria-label="Buscar clientes"
                >

                <button
                    class="clientes-btn"
                    type="submit"
                >
                    🔎 Buscar
                </button>

                <?php if ($buscar !== ''): ?>
                    <a
                        class="clientes-btn clientes-clear"
                        href="<?= textoSeguroCliente(url('clientes/index.php')) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>

            <div class="clientes-count">
                <?= count($clientes) ?>
                cliente<?= count($clientes) === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if ($mensajeExito !== ''): ?>
            <div
                class="clientes-alert clientes-alert-success"
                role="alert"
            >
                ✅ <?= textoSeguroCliente($mensajeExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div
                class="clientes-alert clientes-alert-error"
                role="alert"
            >
                ⚠️ <?= textoSeguroCliente($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="clientes-content">
            <?php if ($clientes !== []): ?>
                <div class="clientes-table-wrapper">
                    <table class="clientes-table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th>Dirección</th>
                                <th>Acción</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td>
                                        <div class="cliente-identidad">
                                            <span class="cliente-avatar">👤</span>

                                            <div>
                                                <strong>
                                                    <?= textoSeguroCliente(
                                                        valorVisibleCliente(
                                                            trim(
                                                                (string) ($cliente['nombres'] ?? '') .
                                                                ' ' .
                                                                (string) ($cliente['apellidos'] ?? '')
                                                            )
                                                        )
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    ID #<?= (int) ($cliente['id'] ?? 0) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cliente-info">
                                            <strong>
                                                <?= textoSeguroCliente(
                                                    valorVisibleCliente(
                                                        $cliente['cedula'] ?? '',
                                                        'No registrada'
                                                    )
                                                ) ?>
                                            </strong>
                                        </div>
                                    </td>

                                    <td>
                                        <?= textoSeguroCliente(
                                            valorVisibleCliente(
                                                $cliente['telefono'] ?? '',
                                                'No registrado'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= textoSeguroCliente(
                                            valorVisibleCliente(
                                                $cliente['email'] ?? '',
                                                'No registrado'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= textoSeguroCliente(
                                            valorVisibleCliente(
                                                $cliente['direccion'] ?? '',
                                                'No registrada'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <a
                                            class="cliente-action"
                                            href="<?= textoSeguroCliente(
                                                url(
                                                    'clientes/editar.php?id=' .
                                                    (int) ($cliente['id'] ?? 0)
                                                )
                                            ) ?>"
                                        >
                                            ✏️ Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="clientes-empty">
                    <span>👥</span>

                    <?php if ($buscar !== ''): ?>
                        <h2>No se encontraron resultados</h2>

                        <p>
                            No hay clientes que coincidan con la búsqueda
                            “<?= textoSeguroCliente($buscar) ?>”.
                        </p>

                        <a
                            class="clientes-btn clientes-clear"
                            href="<?= textoSeguroCliente(url('clientes/index.php')) ?>"
                        >
                            Mostrar todos
                        </a>

                    <?php else: ?>
                        <h2>Todavía no hay clientes registrados</h2>

                        <p>
                            Registra el primer cliente para comenzar.
                        </p>

                        <a
                            class="clientes-btn clientes-btn-primary"
                            href="<?= textoSeguroCliente(url('clientes/crear.php')) ?>"
                        >
                            ＋ Registrar cliente
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>
