<?php
declare(strict_types=1);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '1');

/*
|--------------------------------------------------------------------------
| CARGAR CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| FUNCIONES LOCALES
|--------------------------------------------------------------------------
*/

function redirigir(string $ruta): never
{
    $ruta = trim($ruta);

    if ($ruta === '') {
        $ruta = 'login.php';
    }

    if (!headers_sent()) {
        header('Location: ' . $ruta);
        exit;
    }

    $rutaSegura = htmlspecialchars($ruta, ENT_QUOTES, 'UTF-8');

    exit(
        '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">' .
        '<meta http-equiv="refresh" content="0;url=' . $rutaSegura . '">' .
        '<title>Redirigiendo...</title></head><body>' .
        '<script>window.location.href=' . json_encode($ruta) . ';</script>' .
        '<p>Redirigiendo... <a href="' . $rutaSegura . '">Continuar</a></p>' .
        '</body></html>'
    );
}

function volverAlLogin(string $error): never
{
    redirigir('login.php?error=' . rawurlencode($error));
}

function detectarColumna(
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

function normalizarTexto(string $valor): string
{
    $valor = trim($valor);

    if (function_exists('mb_strtolower')) {
        $valor = mb_strtolower($valor, 'UTF-8');
    } else {
        $valor = strtolower($valor);
    }

    return strtr($valor, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n'
    ]);
}

/*
|--------------------------------------------------------------------------
| ACEPTAR SOLO POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverAlLogin('metodo_invalido');
}

/*
|--------------------------------------------------------------------------
| RECIBIR FORMULARIO
| Compatible con name="correo" o name="email".
|--------------------------------------------------------------------------
*/

$correo = trim(
    (string) (
        $_POST['correo']
        ?? $_POST['email']
        ?? ''
    )
);

$contrasena = (string) (
    $_POST['contrasena']
    ?? $_POST['password']
    ?? $_POST['clave']
    ?? ''
);

if ($correo === '' || $contrasena === '') {
    volverAlLogin('campos_vacios');
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    volverAlLogin('correo_invalido');
}

/*
|--------------------------------------------------------------------------
| COMPROBAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log(
        'Login: config/conexion.php no creó una conexión PDO válida.'
    );

    volverAlLogin('conexion_invalida');
}

try {
    /*
    |----------------------------------------------------------------------
    | DETECTAR LAS COLUMNAS REALES DE usuarios
    |----------------------------------------------------------------------
    */

    $columnas = $pdo
        ->query('SHOW COLUMNS FROM usuarios')
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnas = array_map(
        static fn(mixed $columna): string => (string) $columna,
        $columnas
    );

    $columnaId = detectarColumna(
        $columnas,
        ['id', 'id_usuario', 'usuario_id']
    );

    $columnaNombre = detectarColumna(
        $columnas,
        ['nombre', 'nombres', 'usuario', 'nombre_usuario']
    );

    $columnaCorreo = detectarColumna(
        $columnas,
        ['correo', 'email']
    );

    $columnaContrasena = detectarColumna(
        $columnas,
        [
            'contrasena',
            'password',
            'clave',
            'contrasena_hash',
            'password_hash'
        ]
    );

    $columnaRol = detectarColumna(
        $columnas,
        ['rol']
    );

    $columnaEstado = detectarColumna(
        $columnas,
        ['estado', 'activo']
    );

    if (
        $columnaId === null ||
        $columnaNombre === null ||
        $columnaCorreo === null ||
        $columnaContrasena === null
    ) {
        error_log(
            'Login: faltan columnas obligatorias en usuarios. Columnas: ' .
            implode(', ', $columnas)
        );

        volverAlLogin('estructura_usuarios_invalida');
    }

    $campos = [
        "`{$columnaId}` AS id",
        "`{$columnaNombre}` AS nombre",
        "`{$columnaCorreo}` AS correo",
        "`{$columnaContrasena}` AS contrasena"
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
        WHERE `' . $columnaCorreo . '` = :correo
        LIMIT 1
    ';

    $consulta = $pdo->prepare($sql);
    $consulta->execute([
        ':correo' => $correo
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!is_array($usuario)) {
        volverAlLogin('credenciales_invalidas');
    }

    /*
    |----------------------------------------------------------------------
    | VALIDAR ESTADO
    |----------------------------------------------------------------------
    */

    $estado = normalizarTexto(
        (string) ($usuario['estado'] ?? 'activo')
    );

    $estadosBloqueados = [
        '0',
        'inactivo',
        'inactiva',
        'bloqueado',
        'bloqueada',
        'deshabilitado',
        'deshabilitada',
        'disabled'
    ];

    if (in_array($estado, $estadosBloqueados, true)) {
        volverAlLogin('usuario_inactivo');
    }

    /*
    |----------------------------------------------------------------------
    | VALIDAR CONTRASEÑA
    | Admite hashes de password_hash(). También permite migrar una contraseña
    | antigua guardada como texto plano hacia un hash seguro.
    |----------------------------------------------------------------------
    */

    $contrasenaGuardada = (string) (
        $usuario['contrasena'] ?? ''
    );

    if ($contrasenaGuardada === '') {
        volverAlLogin('credenciales_invalidas');
    }

    $informacionHash = password_get_info($contrasenaGuardada);
    $esHash = !empty($informacionHash['algo']);

    if ($esHash) {
        $contrasenaCorrecta = password_verify(
            $contrasena,
            $contrasenaGuardada
        );
    } else {
        $contrasenaCorrecta = hash_equals(
            $contrasenaGuardada,
            $contrasena
        );
    }

    if (!$contrasenaCorrecta) {
        volverAlLogin('credenciales_invalidas');
    }

    /* Actualizar automáticamente una contraseña antigua a password_hash(). */
    if (!$esHash) {
        try {
            $nuevoHash = password_hash(
                $contrasena,
                PASSWORD_DEFAULT
            );

            $actualizar = $pdo->prepare(
                "UPDATE usuarios
                 SET `{$columnaContrasena}` = :contrasena
                 WHERE `{$columnaId}` = :id"
            );

            $actualizar->execute([
                ':contrasena' => $nuevoHash,
                ':id' => (int) $usuario['id']
            ]);
        } catch (Throwable $errorActualizacion) {
            error_log(
                'Login: no se pudo actualizar el hash: ' .
                $errorActualizacion->getMessage()
            );
        }
    }

    /*
    |----------------------------------------------------------------------
    | CREAR LA SESIÓN
    |----------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $idUsuario = (int) ($usuario['id'] ?? 0);

    $nombreUsuario = trim(
        (string) ($usuario['nombre'] ?? '')
    );

    if ($nombreUsuario === '') {
        $nombreUsuario = $correo;
    }

    $rolUsuario = trim(
        (string) ($usuario['rol'] ?? 'Usuario')
    );

    if ($rolUsuario === '') {
        $rolUsuario = 'Usuario';
    }

    $_SESSION['usuario_id'] = $idUsuario;
    $_SESSION['id_usuario'] = $idUsuario;

    $_SESSION['nombre'] = $nombreUsuario;
    $_SESSION['usuario'] = $nombreUsuario;

    $_SESSION['correo'] = (string) (
        $usuario['correo'] ?? $correo
    );

    $_SESSION['rol'] = $rolUsuario;
    $_SESSION['autenticado'] = true;

    /*
    |----------------------------------------------------------------------
    | REDIRECCIÓN AL DASHBOARD
    | No usa auth_redirect(), por lo tanto evita el error anterior.
    |----------------------------------------------------------------------
    */

    redirigir('panel.php');
} catch (PDOException $error) {
    error_log(
        'Error PDO durante el inicio de sesión: ' .
        $error->getMessage()
    );

    volverAlLogin('error_base_datos');
} catch (Throwable $error) {
    error_log(
        'Error general durante el inicio de sesión: ' .
        $error->getMessage()
    );

    volverAlLogin('error_interno');
}