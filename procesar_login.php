<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/conexion.php';

/*
|--------------------------------------------------------------------------
| ASEGURAR QUE LA SESIÓN ESTÉ ACTIVA
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| REDIRECCIÓN LOCAL
|--------------------------------------------------------------------------
*/

function irA(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

/*
|--------------------------------------------------------------------------
| SOLO ACEPTAR ENVÍOS POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    irA('login.php?error=metodo_invalido');
}

/*
|--------------------------------------------------------------------------
| RECIBIR LOS DATOS
|--------------------------------------------------------------------------
*/

$correo = trim((string) ($_POST['correo'] ?? ''));
$contrasena = (string) ($_POST['contrasena'] ?? '');

/*
|--------------------------------------------------------------------------
| VALIDAR LOS DATOS
|--------------------------------------------------------------------------
*/

if ($correo === '' || $contrasena === '') {
    irA('login.php?error=campos_vacios');
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    irA('login.php?error=correo_invalido');
}

/*
|--------------------------------------------------------------------------
| COMPROBAR LA CONEXIÓN
|--------------------------------------------------------------------------
*/

if (!$conexion instanceof mysqli) {
    error_log(
        'No existe una conexión válida en config/conexion.php.'
    );

    irA('login.php?error=conexion');
}

try {
    /*
    |--------------------------------------------------------------------------
    | BUSCAR EL USUARIO
    |--------------------------------------------------------------------------
    | En tu tabla la columna principal se llama "id".
    */

    $sql = '
        SELECT
            id AS id_usuario,
            nombre,
            correo,
            contrasena,
            rol,
            estado
        FROM usuarios
        WHERE correo = ?
        LIMIT 1
    ';

    $consulta = $conexion->prepare($sql);
    $consulta->bind_param('s', $correo);
    $consulta->execute();
    $consulta->store_result();

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR SI EL USUARIO EXISTE
    |--------------------------------------------------------------------------
    */

    if ($consulta->num_rows !== 1) {
        $consulta->close();

        irA('login.php?error=credenciales_invalidas');
    }

    /*
    |--------------------------------------------------------------------------
    | OBTENER LOS DATOS
    |--------------------------------------------------------------------------
    */

    $consulta->bind_result(
        $idUsuario,
        $nombreUsuario,
        $correoGuardado,
        $contrasenaGuardada,
        $rolUsuario,
        $estadoUsuario
    );

    $consulta->fetch();
    $consulta->close();

    /*
    |--------------------------------------------------------------------------
    | VALIDAR EL ESTADO
    |--------------------------------------------------------------------------
    */

    $estadoUsuario = strtolower(
        trim((string) $estadoUsuario)
    );

    if ($estadoUsuario !== 'activo') {
        irA('login.php?error=usuario_inactivo');
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDAR LA CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    $contrasenaGuardada = (string) $contrasenaGuardada;

    $contrasenaCorrecta = false;

    /*
    | Para contraseñas guardadas con password_hash().
    */

    if (password_verify($contrasena, $contrasenaGuardada)) {
        $contrasenaCorrecta = true;
    }

    /*
    | Compatibilidad temporal con contraseñas guardadas como texto.
    */

    if (
        !$contrasenaCorrecta &&
        hash_equals($contrasenaGuardada, $contrasena)
    ) {
        $contrasenaCorrecta = true;
    }

    if (!$contrasenaCorrecta) {
        irA('login.php?error=credenciales_invalidas');
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR LA SESIÓN
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $idUsuario;
    $_SESSION['id_usuario'] = (int) $idUsuario;

    $_SESSION['usuario'] = (string) $nombreUsuario;
    $_SESSION['nombre'] = (string) $nombreUsuario;

    $_SESSION['correo'] = (string) $correoGuardado;
    $_SESSION['rol'] = (string) $rolUsuario;
    $_SESSION['inicio_sesion'] = time();

    /*
    |--------------------------------------------------------------------------
    | ENTRAR AL PANEL
    |--------------------------------------------------------------------------
    */

    irA('panel.php');

} catch (mysqli_sql_exception $error) {
    error_log(
        'Error SQL durante el inicio de sesión: ' .
        $error->getMessage()
    );

    irA('login.php?error=conexion');

} catch (Throwable $error) {
    error_log(
        'Error general durante el inicio de sesión: ' .
        $error->getMessage()
    );

    irA('login.php?error=sistema');
}