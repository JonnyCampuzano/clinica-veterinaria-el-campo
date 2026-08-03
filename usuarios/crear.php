<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL
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
        'message' => 'No tienes permiso para registrar usuarios.'
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
   DETECTAR COLUMNAS
===================================================== */

function detectar_columna_crear_usuario(
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

try {
    $columnasTabla = $pdo
        ->query('SHOW COLUMNS FROM usuarios')
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnaNombre = detectar_columna_crear_usuario(
        $columnasTabla,
        ['nombre', 'nombres', 'usuario']
    );

    $columnaCorreo = detectar_columna_crear_usuario(
        $columnasTabla,
        ['correo', 'email']
    );

    $columnaContrasena = detectar_columna_crear_usuario(
        $columnasTabla,
        ['contrasena', 'password', 'clave']
    );

    $columnaRol = detectar_columna_crear_usuario(
        $columnasTabla,
        ['rol']
    );

    $columnaEstado = detectar_columna_crear_usuario(
        $columnasTabla,
        ['estado']
    );

    if (
        $columnaNombre === null ||
        $columnaCorreo === null ||
        $columnaContrasena === null
    ) {
        throw new RuntimeException(
            'La tabla usuarios no contiene las columnas necesarias.'
        );
    }
} catch (Throwable $error) {
    exit(
        '<div style="
            margin:40px;
            padding:20px;
            border:1px solid #fca5a5;
            border-radius:12px;
            color:#991b1b;
            background:#fee2e2;
            font-family:Arial,sans-serif;
        ">
            <strong>Error en la tabla usuarios:</strong><br><br>
            La tabla debe tener nombre, correo y contraseña.
        </div>'
    );
}

/* =====================================================
   TOKEN DE SEGURIDAD
===================================================== */

if (empty($_SESSION['csrf_crear_usuario'])) {
    $_SESSION['csrf_crear_usuario'] = bin2hex(
        random_bytes(32)
    );
}

/* =====================================================
   DATOS DEL FORMULARIO
===================================================== */

$datos = [
    'nombre' => '',
    'correo' => '',
    'rol' => 'Usuario',
    'estado' => 'Activo'
];

$mensajeError = '';

$rolesPermitidos = [
    'Administrador',
    'Veterinario',
    'Recepcionista',
    'Usuario'
];

$estadosPermitidos = [
    'Activo',
    'Inactivo'
];

/* =====================================================
   PROCESAR REGISTRO
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre' => trim(
            (string) ($_POST['nombre'] ?? '')
        ),

        'correo' => trim(
            (string) ($_POST['correo'] ?? '')
        ),

        'rol' => trim(
            (string) ($_POST['rol'] ?? 'Usuario')
        ),

        'estado' => trim(
            (string) ($_POST['estado'] ?? 'Activo')
        )
    ];

    $contrasena = (string) (
        $_POST['contrasena'] ?? ''
    );

    $confirmarContrasena = (string) (
        $_POST['confirmar_contrasena'] ?? ''
    );

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_crear_usuario'] ?? ''
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
        $datos['nombre'] === '' ||
        $datos['correo'] === '' ||
        $contrasena === '' ||
        $confirmarContrasena === ''
    ) {
        $mensajeError =
            'Todos los campos obligatorios deben completarse.';

    } elseif (
        !filter_var(
            $datos['correo'],
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $mensajeError =
            'El correo electrónico no es válido.';

    } elseif (strlen($contrasena) < 8) {
        $mensajeError =
            'La contraseña debe tener al menos 8 caracteres.';

    } elseif ($contrasena !== $confirmarContrasena) {
        $mensajeError =
            'Las contraseñas no coinciden.';

    } elseif (
        !in_array(
            $datos['rol'],
            $rolesPermitidos,
            true
        )
    ) {
        $mensajeError =
            'El rol seleccionado no es válido.';

    } elseif (
        !in_array(
            $datos['estado'],
            $estadosPermitidos,
            true
        )
    ) {
        $mensajeError =
            'El estado seleccionado no es válido.';

    } else {
        try {
            /* =========================================
               COMPROBAR CORREO DUPLICADO
            ========================================= */

            $verificar = $pdo->prepare(
                "SELECT 1
                 FROM usuarios
                 WHERE `{$columnaCorreo}` = :correo
                 LIMIT 1"
            );

            $verificar->execute([
                'correo' => $datos['correo']
            ]);

            if ($verificar->fetchColumn()) {
                $mensajeError =
                    'Ya existe un usuario con ese correo electrónico.';
            } else {
                /* =====================================
                   PREPARAR COLUMNAS DEL INSERT
                ===================================== */

                $columnasInsertar = [
                    $columnaNombre,
                    $columnaCorreo,
                    $columnaContrasena
                ];

                $marcadores = [
                    ':nombre',
                    ':correo',
                    ':contrasena'
                ];

                $parametros = [
                    'nombre' => $datos['nombre'],
                    'correo' => $datos['correo'],
                    'contrasena' => password_hash(
                        $contrasena,
                        PASSWORD_DEFAULT
                    )
                ];

                if ($columnaRol !== null) {
                    $columnasInsertar[] = $columnaRol;
                    $marcadores[] = ':rol';
                    $parametros['rol'] = $datos['rol'];
                }

                if ($columnaEstado !== null) {
                    $columnasInsertar[] = $columnaEstado;
                    $marcadores[] = ':estado';
                    $parametros['estado'] = $datos['estado'];
                }

                $columnasSql = array_map(
                    static fn(string $columna): string =>
                        "`{$columna}`",
                    $columnasInsertar
                );

                $sql = '
                    INSERT INTO usuarios
                    (' . implode(', ', $columnasSql) . ')
                    VALUES
                    (' . implode(', ', $marcadores) . ')
                ';

                $registrar = $pdo->prepare($sql);
                $registrar->execute($parametros);

                $_SESSION['csrf_crear_usuario'] =
                    bin2hex(random_bytes(32));

                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' =>
                        'Usuario registrado correctamente.'
                ];

                header(
                    'Location: ' .
                    url('usuarios/index.php')
                );

                exit;
            }
        } catch (Throwable $error) {
            error_log(
                'Error al registrar usuario: ' .
                $error->getMessage()
            );

            $mensajeError =
                'No se pudo registrar el usuario. ' .
                'Comprueba la estructura de la tabla usuarios.';
        }
    }
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Registrar usuario';
$activePage = 'usuarios';

require_once $raiz . '/includes/header.php';
?>

<div class="card form-card">

    <div class="card-title">

        <div>
            <h2>Registrar nuevo usuario</h2>

            <p>
                Crea una cuenta y asigna los permisos correspondientes.
            </p>
        </div>

    </div>

    <?php if ($mensajeError !== ''): ?>

        <div class="alert alert-error">
            <?= e($mensajeError) ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        class="form-grid"
        autocomplete="off"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(
                $_SESSION['csrf_crear_usuario']
            ) ?>"
        >

        <div class="form-group full">

            <label for="nombre">
                Nombre completo
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre"
                maxlength="120"
                placeholder="Ejemplo: Juan Carlos Pérez"
                value="<?= e($datos['nombre']) ?>"
                required
                autofocus
            >

        </div>

        <div class="form-group full">

            <label for="correo">
                Correo electrónico
            </label>

            <input
                type="email"
                id="correo"
                name="correo"
                maxlength="150"
                placeholder="usuario@correo.com"
                value="<?= e($datos['correo']) ?>"
                autocomplete="email"
                required
            >

        </div>

        <div class="form-group">

            <label for="contrasena">
                Contraseña
            </label>

            <input
                type="password"
                id="contrasena"
                name="contrasena"
                minlength="8"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
                required
            >

        </div>

        <div class="form-group">

            <label for="confirmar_contrasena">
                Confirmar contraseña
            </label>

            <input
                type="password"
                id="confirmar_contrasena"
                name="confirmar_contrasena"
                minlength="8"
                placeholder="Repita la contraseña"
                autocomplete="new-password"
                required
            >

        </div>

        <?php if ($columnaRol !== null): ?>

            <div class="form-group">

                <label for="rol">
                    Rol del usuario
                </label>

                <select
                    id="rol"
                    name="rol"
                    required
                >

                    <?php foreach ($rolesPermitidos as $rol): ?>

                        <option
                            value="<?= e($rol) ?>"
                            <?= $datos['rol'] === $rol
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($rol) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        <?php endif; ?>

        <?php if ($columnaEstado !== null): ?>

            <div class="form-group">

                <label for="estado">
                    Estado
                </label>

                <select
                    id="estado"
                    name="estado"
                    required
                >

                    <?php foreach ($estadosPermitidos as $estado): ?>

                        <option
                            value="<?= e($estado) ?>"
                            <?= $datos['estado'] === $estado
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($estado) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        <?php endif; ?>

        <div class="form-actions">

            <a
                class="btn btn-secondary"
                href="<?= e(url('usuarios/index.php')) ?>"
            >
                Cancelar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
            >
                💾 Registrar usuario
            </button>

        </div>

    </form>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>