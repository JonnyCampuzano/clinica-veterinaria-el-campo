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
   BUSCADOR Y MENSAJES
===================================================== */

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$mensajeCodigo = trim((string) ($_GET['msg'] ?? ''));

$mensajesExito = [
    'creada' => 'Mascota registrada correctamente.',
    'actualizada' => 'Mascota actualizada correctamente.',
    'eliminada' => 'Mascota eliminada correctamente.',
];

$mensajeExito = $mensajesExito[$mensajeCodigo] ?? '';
$mensajeError = '';
$mascotas = [];

/* =====================================================
   CONSULTAR MASCOTAS
===================================================== */

try {
    $sql = '
        SELECT
            m.id,
            m.cliente_id,
            m.nombre,
            m.especie,
            m.raza,
            m.sexo,
            m.fecha_nacimiento,
            m.peso,
            m.color,
            m.alergias,
            m.observaciones,
            m.created_at,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula,
            c.telefono AS cliente_telefono
        FROM mascotas AS m
        INNER JOIN clientes AS c
            ON c.id = m.cliente_id
    ';

    $parametros = [];

    if ($buscar !== '') {
        $sql .= '
            WHERE
                m.nombre LIKE :buscar_nombre
                OR m.especie LIKE :buscar_especie
                OR COALESCE(m.raza, \'\') LIKE :buscar_raza
                OR m.sexo LIKE :buscar_sexo
                OR c.nombres LIKE :buscar_cliente_nombres
                OR c.apellidos LIKE :buscar_cliente_apellidos
                OR COALESCE(c.cedula, \'\') LIKE :buscar_cedula
        ';

        $termino = '%' . $buscar . '%';

        $parametros = [
            ':buscar_nombre' => $termino,
            ':buscar_especie' => $termino,
            ':buscar_raza' => $termino,
            ':buscar_sexo' => $termino,
            ':buscar_cliente_nombres' => $termino,
            ':buscar_cliente_apellidos' => $termino,
            ':buscar_cedula' => $termino,
        ];
    }

    $sql .= ' ORDER BY m.id DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $mascotas = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error al consultar mascotas: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar las mascotas. ' .
        'Revisa la tabla mascotas y la conexión a la base de datos.';
}

/* =====================================================
   FUNCIONES AUXILIARES DE ESTA PÁGINA
===================================================== */

function textoSeguro(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function valorVisible(mixed $valor, string $alternativa = 'No registrado'): string
{
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

function fechaVisible(mixed $fecha): string
{
    $valor = trim((string) $fecha);

    if ($valor === '') {
        return 'No registrada';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $valor);

    return $objeto instanceof DateTime
        ? $objeto->format('d/m/Y')
        : $valor;
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Mascotas';
$activePage = 'mascotas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .mascotas-page {
        width: min(1180px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .mascotas-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .mascotas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 26px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .mascotas-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .mascotas-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .mascotas-btn {
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

    .mascotas-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .mascotas-btn-primary:hover {
        background: #1d4ed8;
    }

    .mascotas-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 26px;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .mascotas-search {
        display: flex;
        flex: 1;
        gap: 10px;
    }

    .mascotas-search input {
        width: 100%;
        min-height: 42px;
        padding: 10px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        color: #0f172a;
        font: inherit;
        outline: none;
    }

    .mascotas-search input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .mascotas-search button {
        background: #0f766e;
        color: #ffffff;
    }

    .mascotas-clear {
        background: #e9eef5;
        color: #334155;
    }

    .mascotas-count {
        white-space: nowrap;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .mascotas-alert {
        margin: 20px 26px 0;
        padding: 13px 16px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 700;
    }

    .mascotas-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .mascotas-alert-error {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #b91c1c;
    }

    .mascotas-content {
        padding: 24px 26px 28px;
    }

    .mascotas-table-wrapper {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
    }

    .mascotas-table {
        width: 100%;
        min-width: 1000px;
        border-collapse: collapse;
    }

    .mascotas-table th,
    .mascotas-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .mascotas-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 800;
    }

    .mascotas-table tbody tr:hover {
        background: #f8fbff;
    }

    .mascotas-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .mascota-identidad {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .mascota-avatar {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        place-items: center;
        border-radius: 12px;
        background: #e0ecff;
        font-size: 20px;
    }

    .mascota-identidad strong {
        display: block;
        color: #0f2747;
        font-size: 14px;
    }

    .mascota-identidad small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .mascota-propietario strong {
        display: block;
        color: #1e293b;
    }

    .mascota-propietario small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .mascota-etiqueta {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 12px;
        font-weight: 800;
    }

    .mascota-actions {
        display: flex;
        gap: 8px;
        white-space: nowrap;
    }

    .mascota-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .mascota-action-edit {
        background: #fff7ed;
        color: #c2410c;
    }

    .mascota-action-delete {
        background: #fff1f2;
        color: #be123c;
    }

    .mascotas-empty {
        padding: 46px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        text-align: center;
        background: #fbfdff;
    }

    .mascotas-empty span {
        display: block;
        margin-bottom: 12px;
        font-size: 38px;
    }

    .mascotas-empty h2 {
        margin: 0 0 8px;
        color: #0f2747;
        font-size: 20px;
    }

    .mascotas-empty p {
        margin: 0 0 18px;
        color: #64748b;
    }

    @media (max-width: 760px) {
        .mascotas-header,
        .mascotas-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .mascotas-btn-primary {
            width: 100%;
        }

        .mascotas-search {
            flex-wrap: wrap;
        }

        .mascotas-search input {
            flex-basis: 100%;
        }

        .mascotas-search button,
        .mascotas-clear {
            flex: 1;
        }

        .mascotas-header,
        .mascotas-toolbar,
        .mascotas-content {
            padding-left: 18px;
            padding-right: 18px;
        }

        .mascotas-alert {
            margin-left: 18px;
            margin-right: 18px;
        }
    }
</style>

<div class="mascotas-page">
    <section class="mascotas-panel">
        <header class="mascotas-header">
            <div>
                <h1>🐾 Mascotas registradas</h1>
                <p>
                    Consulta y administra las mascotas de los clientes.
                </p>
            </div>

            <a
                class="mascotas-btn mascotas-btn-primary"
                href="<?= textoSeguro(url('mascotas/crear.php')) ?>"
            >
                ＋ Registrar mascota
            </a>
        </header>

        <div class="mascotas-toolbar">
            <form
                class="mascotas-search"
                method="GET"
                action=""
            >
                <input
                    type="search"
                    name="buscar"
                    value="<?= textoSeguro($buscar) ?>"
                    placeholder="Buscar por mascota, especie, raza, propietario o cédula"
                    aria-label="Buscar mascotas"
                >

                <button
                    class="mascotas-btn"
                    type="submit"
                >
                    🔎 Buscar
                </button>

                <?php if ($buscar !== ''): ?>
                    <a
                        class="mascotas-btn mascotas-clear"
                        href="<?= textoSeguro(url('mascotas/index.php')) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>

            <div class="mascotas-count">
                <?= count($mascotas) ?>
                mascota<?= count($mascotas) === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if ($mensajeExito !== ''): ?>
            <div
                class="mascotas-alert mascotas-alert-success"
                role="alert"
            >
                ✅ <?= textoSeguro($mensajeExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div
                class="mascotas-alert mascotas-alert-error"
                role="alert"
            >
                ⚠️ <?= textoSeguro($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="mascotas-content">
            <?php if ($mascotas !== []): ?>
                <div class="mascotas-table-wrapper">
                    <table class="mascotas-table">
                        <thead>
                            <tr>
                                <th>Mascota</th>
                                <th>Propietario</th>
                                <th>Especie / raza</th>
                                <th>Sexo</th>
                                <th>Nacimiento</th>
                                <th>Peso</th>
                                <th>Color</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($mascotas as $mascota): ?>
                                <?php
                                $propietario = trim(
                                    (string) ($mascota['cliente_nombres'] ?? '') .
                                    ' ' .
                                    (string) ($mascota['cliente_apellidos'] ?? '')
                                );

                                $pesoTexto = $mascota['peso'] !== null
                                    && $mascota['peso'] !== ''
                                        ? number_format(
                                            (float) $mascota['peso'],
                                            2,
                                            ',',
                                            '.'
                                        ) . ' kg'
                                        : 'No registrado';
                                ?>

                                <tr>
                                    <td>
                                        <div class="mascota-identidad">
                                            <span class="mascota-avatar">🐾</span>
                                            <div>
                                                <strong>
                                                    <?= textoSeguro(
                                                        valorVisible(
                                                            $mascota['nombre'] ?? ''
                                                        )
                                                    ) ?>
                                                </strong>
                                                <small>
                                                    ID #<?= (int) ($mascota['id'] ?? 0) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="mascota-propietario">
                                            <strong>
                                                <?= textoSeguro(
                                                    valorVisible($propietario)
                                                ) ?>
                                            </strong>
                                            <small>
                                                Cédula:
                                                <?= textoSeguro(
                                                    valorVisible(
                                                        $mascota['cliente_cedula'] ?? '',
                                                        'No registrada'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= textoSeguro(
                                                valorVisible(
                                                    $mascota['especie'] ?? ''
                                                )
                                            ) ?>
                                        </strong>
                                        <br>
                                        <small>
                                            <?= textoSeguro(
                                                valorVisible(
                                                    $mascota['raza'] ?? '',
                                                    'Sin raza registrada'
                                                )
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="mascota-etiqueta">
                                            <?= textoSeguro(
                                                valorVisible(
                                                    $mascota['sexo'] ?? ''
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= textoSeguro(
                                            fechaVisible(
                                                $mascota['fecha_nacimiento'] ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td><?= textoSeguro($pesoTexto) ?></td>

                                    <td>
                                        <?= textoSeguro(
                                            valorVisible(
                                                $mascota['color'] ?? '',
                                                'No registrado'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <div class="mascota-actions">
                                            <a
                                                class="mascota-action mascota-action-edit"
                                                href="<?= textoSeguro(
                                                    url(
                                                        'mascotas/editar.php?id=' .
                                                        (int) ($mascota['id'] ?? 0)
                                                    )
                                                ) ?>"
                                            >
                                                ✏️ Editar
                                            </a>

                                            <a
                                                class="mascota-action mascota-action-delete"
                                                href="<?= textoSeguro(
                                                    url(
                                                        'mascotas/eliminar.php?id=' .
                                                        (int) ($mascota['id'] ?? 0)
                                                    )
                                                ) ?>"
                                                onclick="return confirm('¿Seguro que deseas eliminar esta mascota?');"
                                            >
                                                🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="mascotas-empty">
                    <span>🐶</span>

                    <?php if ($buscar !== ''): ?>
                        <h2>No se encontraron resultados</h2>
                        <p>
                            No hay mascotas que coincidan con la búsqueda
                            “<?= textoSeguro($buscar) ?>”.
                        </p>

                        <a
                            class="mascotas-btn mascotas-clear"
                            href="<?= textoSeguro(url('mascotas/index.php')) ?>"
                        >
                            Mostrar todas
                        </a>
                    <?php else: ?>
                        <h2>Todavía no hay mascotas registradas</h2>
                        <p>
                            Registra la primera mascota para comenzar.
                        </p>

                        <a
                            class="mascotas-btn mascotas-btn-primary"
                            href="<?= textoSeguro(url('mascotas/crear.php')) ?>"
                        >
                            ＋ Registrar mascota
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