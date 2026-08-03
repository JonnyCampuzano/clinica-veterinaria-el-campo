<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL DEL PROYECTO
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   CARGAR ARCHIVOS DEL SISTEMA
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
   CREAR TOKEN DE SEGURIDAD PARA ELIMINAR
===================================================== */

if (empty($_SESSION['csrf_clientes'])) {
    $_SESSION['csrf_clientes'] = bin2hex(
        random_bytes(32)
    );
}

/* =====================================================
   COMPROBAR CONEXIÓN PDO
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            margin:40px;
            padding:20px;
            border:1px solid #fca5a5;
            border-radius:12px;
            background:#fee2e2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error:</strong><br><br>
            No se encontró una conexión PDO válida.
        </div>'
    );
}

/* =====================================================
   BUSCADOR
===================================================== */

$buscar = trim(
    (string) ($_GET['buscar'] ?? '')
);

$clientes = [];
$mensajeError = '';

/* =====================================================
   CONSULTAR CLIENTES
===================================================== */

try {
    $sql = '
        SELECT
            c.id,
            c.nombres,
            c.apellidos,
            c.cedula,
            c.telefono,
            c.email,
            (
                SELECT COUNT(*)
                FROM mascotas m
                WHERE m.cliente_id = c.id
            ) AS total_mascotas
        FROM clientes c
    ';

    $parametros = [];

    if ($buscar !== '') {
        $sql .= '
            WHERE
                c.nombres LIKE :buscar_nombres
                OR c.apellidos LIKE :buscar_apellidos
                OR c.cedula LIKE :buscar_cedula
                OR c.telefono LIKE :buscar_telefono
                OR c.email LIKE :buscar_email
        ';

        $termino = '%' . $buscar . '%';

        $parametros = [
            'buscar_nombres' => $termino,
            'buscar_apellidos' => $termino,
            'buscar_cedula' => $termino,
            'buscar_telefono' => $termino,
            'buscar_email' => $termino
        ];
    }

    $sql .= ' ORDER BY c.id DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $clientes = $consulta->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error al consultar clientes: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar los clientes. ' .
        'Comprueba la tabla clientes y sus columnas.';
}

/* =====================================================
   DATOS DEL ENCABEZADO
===================================================== */

$pageTitle = 'Clientes';
$activePage = 'clientes';

require_once $raiz . '/includes/header.php';
?>

<div class="page-actions">

    <form
        class="search-bar"
        method="GET"
        action="<?= e(url('clientes/index.php')) ?>"
    >
        <input
            type="search"
            name="buscar"
            value="<?= e($buscar) ?>"
            placeholder="Buscar por nombre, cédula, teléfono o correo..."
            autocomplete="off"
        >

        <button
            class="btn btn-secondary"
            type="submit"
        >
            🔍 Buscar
        </button>

        <?php if ($buscar !== ''): ?>

            <a
                class="btn btn-secondary"
                href="<?= e(url('clientes/index.php')) ?>"
            >
                Limpiar
            </a>

        <?php endif; ?>
    </form>

    <a
        class="btn btn-primary"
        href="<?= e(url('clientes/crear.php')) ?>"
    >
        ➕ Nuevo cliente
    </a>

</div>

<?php if ($mensajeError !== ''): ?>

    <div class="alert alert-error">
        <?= e($mensajeError) ?>
    </div>

<?php endif; ?>

<div class="table-wrapper">

    <table>

        <thead>
        <tr>
            <th>Cliente</th>
            <th>Cédula</th>
            <th>Teléfono</th>
            <th>Correo</th>
            <th>Mascotas</th>
            <th>Acciones</th>
        </tr>
        </thead>

        <tbody>

        <?php if ($clientes !== []): ?>

            <?php foreach ($clientes as $cliente): ?>

                <?php
                $idCliente = (int) (
                    $cliente['id'] ?? 0
                );

                $nombres = trim(
                    (string) (
                        $cliente['nombres'] ?? ''
                    )
                );

                $apellidos = trim(
                    (string) (
                        $cliente['apellidos'] ?? ''
                    )
                );

                $nombreCompleto = trim(
                    $nombres . ' ' . $apellidos
                );

                if ($nombreCompleto === '') {
                    $nombreCompleto = 'Cliente sin nombre';
                }

                $cedula = trim(
                    (string) (
                        $cliente['cedula'] ?? ''
                    )
                );

                $telefono = trim(
                    (string) (
                        $cliente['telefono'] ?? ''
                    )
                );

                $email = trim(
                    (string) (
                        $cliente['email'] ?? ''
                    )
                );

                $totalMascotas = (int) (
                    $cliente['total_mascotas'] ?? 0
                );
                ?>

                <tr>

                    <td>
                        <strong>
                            <?= e($nombreCompleto) ?>
                        </strong>
                    </td>

                    <td>
                        <?= e(
                            $cedula !== ''
                                ? $cedula
                                : '—'
                        ) ?>
                    </td>

                    <td>
                        <?= e(
                            $telefono !== ''
                                ? $telefono
                                : '—'
                        ) ?>
                    </td>

                    <td>
                        <?= e(
                            $email !== ''
                                ? $email
                                : '—'
                        ) ?>
                    </td>

                    <td>
                        <span class="badge badge-info">
                            <?= $totalMascotas ?>
                        </span>
                    </td>

                    <td>

                        <div class="actions">

                            <!-- BOTÓN EDITAR -->
                            <a
                                class="btn btn-warning btn-sm"
                                href="<?= e(
                                    url(
                                        'clientes/editar.php?id=' .
                                        $idCliente
                                    )
                                ) ?>"
                            >
                                ✏️ Editar
                            </a>

                            <!-- FORMULARIO ELIMINAR -->
                            <form
                                class="inline-form"
                                method="POST"
                                action="<?= e(
                                    url('clientes/eliminar.php')
                                ) ?>"
                                onsubmit="return confirm(
                                    '¿Deseas eliminar este cliente?'
                                );"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(
                                        $_SESSION['csrf_clientes']
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $idCliente ?>"
                                >

                                <button
                                    class="btn btn-danger btn-sm"
                                    type="submit"
                                >
                                    🗑️ Eliminar
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php elseif ($mensajeError === ''): ?>

            <tr>
                <td colspan="6">

                    <div class="empty-state">

                        <?php if ($buscar !== ''): ?>

                            No se encontraron clientes para:

                            <strong>
                                “<?= e($buscar) ?>”
                            </strong>

                        <?php else: ?>

                            No existen clientes registrados.

                        <?php endif; ?>

                    </div>

                </td>
            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>