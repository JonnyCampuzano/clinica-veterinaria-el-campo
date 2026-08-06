<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL DEL PROYECTO
   Este archivo debe estar en: usuarios/index.php
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
   PROTEGER EL MÓDULO
   Solo el administrador puede acceder a Usuarios.
===================================================== */

require_role('Administrador');

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
   COMPROBAR CONEXIÓN PDO
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            margin:40px;
            padding:20px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fef2f2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error de conexión:</strong><br><br>
            No se encontró una conexión PDO válida.
        </div>'
    );
}

/* =====================================================
   DETECTAR COLUMNAS DE LA TABLA USUARIOS
===================================================== */

if (!function_exists('detectar_columna_usuarios')) {
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
}

/* =====================================================
   VARIABLES
===================================================== */

$usuarios = [];
$mensajeError = '';
$buscar = trim((string) ($_GET['buscar'] ?? ''));

/* =====================================================
   CONSULTAR USUARIOS
===================================================== */

try {
    $columnasTabla = $pdo
        ->query('SHOW COLUMNS FROM usuarios')
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnasTabla = array_map(
        static fn(mixed $columna): string => (string) $columna,
        $columnasTabla
    );

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
        ['email', 'correo']
    );

    $columnaRol = detectar_columna_usuarios(
        $columnasTabla,
        ['rol']
    );

    $columnaEstado = detectar_columna_usuarios(
        $columnasTabla,
        ['estado', 'activo']
    );

    if (
        $columnaId === null ||
        $columnaNombre === null ||
        $columnaCorreo === null
    ) {
        throw new RuntimeException(
            'La tabla usuarios no contiene las columnas obligatorias.'
        );
    }

    $campos = [
        "`{$columnaId}` AS id",
        "`{$columnaNombre}` AS nombre",
        "`{$columnaCorreo}` AS correo"
    ];

    $campos[] = $columnaRol !== null
        ? "`{$columnaRol}` AS rol"
        : "'Sin rol' AS rol";

    $campos[] = $columnaEstado !== null
        ? "`{$columnaEstado}` AS estado"
        : "'Activo' AS estado";

    $sql = 'SELECT ' . implode(', ', $campos) . ' FROM usuarios';
    $parametros = [];

    if ($buscar !== '') {
        $condiciones = [
            "`{$columnaNombre}` LIKE :buscar_nombre",
            "`{$columnaCorreo}` LIKE :buscar_correo"
        ];

        $termino = '%' . $buscar . '%';

        $parametros = [
            ':buscar_nombre' => $termino,
            ':buscar_correo' => $termino
        ];

        if ($columnaRol !== null) {
            $condiciones[] = "`{$columnaRol}` LIKE :buscar_rol";
            $parametros[':buscar_rol'] = $termino;
        }

        if ($columnaEstado !== null) {
            $condiciones[] = "CAST(`{$columnaEstado}` AS CHAR) LIKE :buscar_estado";
            $parametros[':buscar_estado'] = $termino;
        }

        $sql .= ' WHERE ' . implode(' OR ', $condiciones);
    }

    $sql .= " ORDER BY `{$columnaId}` DESC";

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error al consultar usuarios: ' . $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar los usuarios. ' .
        'Comprueba la estructura de la tabla usuarios.';
}

/* =====================================================
   MENSAJES DEL SISTEMA
===================================================== */

$mensajeExito = '';

if (isset($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $tipoFlash = (string) ($_SESSION['flash']['type'] ?? '');
    $textoFlash = trim(
        (string) ($_SESSION['flash']['message'] ?? '')
    );

    if ($tipoFlash === 'success') {
        $mensajeExito = $textoFlash;
    } elseif ($tipoFlash === 'error' && $mensajeError === '') {
        $mensajeError = $textoFlash;
    }

    unset($_SESSION['flash']);
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
            placeholder="Buscar por nombre, correo, rol o estado..."
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

<?php if ($mensajeExito !== ''): ?>

    <div class="alert alert-success">
        <?= e($mensajeExito) ?>
    </div>

<?php endif; ?>

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
                <th>ID</th>
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
                    $idUsuario = (int) ($usuario['id'] ?? 0);

                    $nombre = trim(
                        (string) ($usuario['nombre'] ?? '')
                    );

                    $correo = trim(
                        (string) ($usuario['correo'] ?? '')
                    );

                    $rol = trim(
                        (string) ($usuario['rol'] ?? 'Sin rol')
                    );

                    $estadoOriginal = trim(
                        (string) ($usuario['estado'] ?? 'Activo')
                    );

                    $estadoNormalizado = strtolower($estadoOriginal);

                    $usuarioActivo = in_array(
                        $estadoNormalizado,
                        ['activo', '1', 'si', 'sí', 'true'],
                        true
                    );
                    ?>

                    <tr>

                        <td>
                            <?= $idUsuario ?>
                        </td>

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
                                <?= e(
                                    $rol !== ''
                                        ? $rol
                                        : 'Sin rol'
                                ) ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($usuarioActivo): ?>

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
                    <td colspan="5">

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