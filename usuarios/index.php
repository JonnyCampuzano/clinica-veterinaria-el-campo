<?php
declare(strict_types=1);

/* =====================================================
   RUTA DEL PROYECTO
===================================================== */

$raiz = dirname(__DIR__);

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

require_login();

/* =====================================================
   FUNCIONES DE RESPALDO
===================================================== */

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

if (!function_exists('url')) {
    function url(string $ruta = ''): string
    {
        $base = defined('APP_URL')
            ? rtrim((string) APP_URL, '/')
            : '/clinica_veterinaria_el_campo';

        $ruta = ltrim($ruta, '/');

        return $ruta === ''
            ? $base
            : $base . '/' . $ruta;
    }
}

/* =====================================================
   VALIDAR ADMINISTRADOR
===================================================== */

$rolActual = strtolower(
    trim((string) ($_SESSION['rol'] ?? ''))
);

if (
    !in_array(
        $rolActual,
        ['admin', 'administrador'],
        true
    )
) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'No tienes permiso para administrar usuarios.'
    ];

    header('Location: ' . url('panel.php'));
    exit;
}

/* =====================================================
   COMPROBAR CONEXIÓN
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No se encontró una conexión PDO válida.');
}

/* =====================================================
   DETECTAR COLUMNAS DE LA TABLA
===================================================== */

function detectar_columna_usuarios(
    array $columnas,
    array $opciones
): ?string {
    foreach ($opciones as $opcion) {
        if (in_array($opcion, $columnas, true)) {
            return $opcion;
        }
    }

    return null;
}

$usuarios = [];
$mensajeError = '';
$buscar = trim((string) ($_GET['buscar'] ?? ''));

try {
    $columnasTabla = $pdo
        ->query('SHOW COLUMNS FROM usuarios')
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnaId = detectar_columna_usuarios(
        $columnasTabla,
        ['id', 'id_usuario', 'usuario_id']
    );

    $columnaNombre = detectar_columna_usuarios(
        $columnasTabla,
        ['nombre', 'nombres', 'usuario']
    );

    $columnaCorreo = detectar_columna_usuarios(
        $columnasTabla,
        ['correo', 'email']
    );

    $columnaRol = detectar_columna_usuarios(
        $columnasTabla,
        ['rol']
    );

    $columnaEstado = detectar_columna_usuarios(
        $columnasTabla,
        ['estado']
    );

    if (
        $columnaId === null ||
        $columnaNombre === null ||
        $columnaCorreo === null
    ) {
        throw new RuntimeException(
            'La tabla usuarios no tiene las columnas necesarias.'
        );
    }

    $campos = [
        "`{$columnaId}` AS id",
        "`{$columnaNombre}` AS nombre",
        "`{$columnaCorreo}` AS correo"
    ];

    $campos[] = $columnaRol !== null
        ? "`{$columnaRol}` AS rol"
        : "'Usuario' AS rol";

    $campos[] = $columnaEstado !== null
        ? "`{$columnaEstado}` AS estado"
        : "'Activo' AS estado";

    $sql = '
        SELECT ' . implode(', ', $campos) . '
        FROM usuarios
    ';

    $parametros = [];

    if ($buscar !== '') {
        $condiciones = [
            "`{$columnaNombre}` LIKE :buscar_nombre",
            "`{$columnaCorreo}` LIKE :buscar_correo"
        ];

        $termino = '%' . $buscar . '%';

        $parametros = [
            'buscar_nombre' => $termino,
            'buscar_correo' => $termino
        ];

        if ($columnaRol !== null) {
            $condiciones[] =
                "`{$columnaRol}` LIKE :buscar_rol";

            $parametros['buscar_rol'] = $termino;
        }

        $sql .= '
            WHERE ' . implode(' OR ', $condiciones);
    }

    $sql .= " ORDER BY `{$columnaId}` DESC";

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $usuarios = $consulta->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $error) {
    error_log(
        'Error al consultar usuarios: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar los usuarios. ' .
        'Comprueba la tabla usuarios.';
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Usuarios';
$activePage = 'usuarios';

require_once $raiz . '/includes/header.php';
?>

<div class="page-actions">

    <form
        class="search-bar"
        method="GET"
        action="<?= e(url('usuarios/index.php')) ?>"
    >
        <input
            type="search"
            name="buscar"
            value="<?= e($buscar) ?>"
            placeholder="Buscar por nombre, correo o rol..."
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
                href="<?= e(url('usuarios/index.php')) ?>"
            >
                Limpiar
            </a>

        <?php endif; ?>
    </form>

    <a
        class="btn btn-primary"
        href="<?= e(url('usuarios/crear.php')) ?>"
    >
        ➕ Registrar nuevo usuario
    </a>

</div>

<?php if ($mensajeError !== ''): ?>

    <div class="alert alert-error">
        <?= e($mensajeError) ?>
    </div>

<?php endif; ?>

<div class="card">

    <div class="card-title">

        <div>
            <h2>Usuarios registrados</h2>

            <p>
                <?= count($usuarios) ?>
                usuario<?= count($usuarios) === 1 ? '' : 's' ?>
                en el sistema
            </p>
        </div>

    </div>

    <div class="table-wrapper">

        <table>

            <thead>
            <tr>
                <th>Usuario</th>
                <th>Correo electrónico</th>
                <th>Rol</th>
                <th>Estado</th>
            </tr>
            </thead>

            <tbody>

            <?php if ($usuarios !== []): ?>

                <?php foreach ($usuarios as $usuario): ?>

                    <?php
                    $nombre = trim(
                        (string) ($usuario['nombre'] ?? '')
                    );

                    $correo = trim(
                        (string) ($usuario['correo'] ?? '')
                    );

                    $rol = trim(
                        (string) ($usuario['rol'] ?? 'Usuario')
                    );

                    $estado = trim(
                        (string) ($usuario['estado'] ?? 'Activo')
                    );

                    $estadoMinuscula = strtolower($estado);
                    ?>

                    <tr>

                        <td>
                            <strong>
                                <?= e(
                                    $nombre !== ''
                                        ? $nombre
                                        : 'Usuario sin nombre'
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= e(
                                $correo !== ''
                                    ? $correo
                                    : '—'
                            ) ?>
                        </td>

                        <td>
                            <span class="badge badge-info">
                                <?= e($rol) ?>
                            </span>
                        </td>

                        <td>

                            <?php if (
                                in_array(
                                    $estadoMinuscula,
                                    ['activo', '1'],
                                    true
                                )
                            ): ?>

                                <span class="badge badge-success">
                                    Activo
                                </span>

                            <?php else: ?>

                                <span class="badge badge-danger">
                                    Inactivo
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php elseif ($mensajeError === ''): ?>

                <tr>
                    <td colspan="4">

                        <div class="empty-state">

                            <?php if ($buscar !== ''): ?>

                                No se encontraron usuarios para:

                                <strong>
                                    “<?= e($buscar) ?>”
                                </strong>

                            <?php else: ?>

                                No existen usuarios registrados.

                            <?php endif; ?>

                        </div>

                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>