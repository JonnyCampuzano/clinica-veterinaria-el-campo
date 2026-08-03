<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| INICIAR SESIÓN
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CARGAR CONFIGURACIÓN Y CONEXIÓN
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/conexion.php';

/*
|--------------------------------------------------------------------------
| PERMITIR ÚNICAMENTE SOLICITUD POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php?error=metodo_invalido');
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFICAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $_SESSION['error_login'] =
        'No fue posible conectarse con la base de datos.';

    redirect('login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| RECIBIR DATOS DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$correo = strtolower(
    trim((string) ($_POST['correo'] ?? ''))
);

$contrasena = (string) ($_POST['contrasena'] ?? '');

/*
|--------------------------------------------------------------------------
| VALIDAR CAMPOS
|--------------------------------------------------------------------------
*/

if ($correo === '' || $contrasena === '') {
    redirect('login.php?error=campos_vacios');
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirect('login.php?error=correo_invalido');
    exit;
}

try {
    /*
    |--------------------------------------------------------------------------
    | BUSCAR USUARIO POR CORREO
    |--------------------------------------------------------------------------
    */

    $consulta = $pdo->prepare(
        'SELECT
            id,
            nombre,
            correo,
            contrasena,
            rol,
            estado
         FROM usuarios
         WHERE correo = :correo
         LIMIT 1'
    );

    $consulta->execute([
        ':correo' => $correo
    ]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR QUE EL USUARIO EXISTA
    |--------------------------------------------------------------------------
    */

    if (!$usuario) {
        redirect('login.php?error=credenciales_invalidas');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR ESTADO DEL USUARIO
    |--------------------------------------------------------------------------
    */

    $estadoUsuario = strtolower(
        trim((string) ($usuario['estado'] ?? ''))
    );

    if ($estadoUsuario !== 'activo') {
        redirect('login.php?error=usuario_inactivo');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | COMPROBAR CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    $contrasenaGuardada = (string) (
        $usuario['contrasena'] ?? ''
    );

    if (
        $contrasenaGuardada === '' ||
        !password_verify(
            $contrasena,
            $contrasenaGuardada
        )
    ) {
        redirect('login.php?error=credenciales_invalidas');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARAR NOMBRE Y ROL
    |--------------------------------------------------------------------------
    */

    $nombreUsuario = trim(
        (string) ($usuario['nombre'] ?? '')
    );

    $rolUsuario = trim(
        (string) ($usuario['rol'] ?? '')
    );

    if ($nombreUsuario === '') {
        $nombreUsuario = 'Usuario';
    }

    if ($rolUsuario === '') {
        $rolUsuario = 'Usuario';
    }

    /*
    |--------------------------------------------------------------------------
    | REGENERAR ID DE SESIÓN
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    /*
    |--------------------------------------------------------------------------
    | GUARDAR DATOS DEL USUARIO EN LA SESIÓN
    |--------------------------------------------------------------------------
    */

    $_SESSION['usuario_id'] =
        (int) $usuario['id'];

    $_SESSION['id_usuario'] =
        (int) $usuario['id'];

    $_SESSION['nombre'] =
        $nombreUsuario;

    $_SESSION['usuario'] =
        $nombreUsuario;

    $_SESSION['correo'] =
        (string) $usuario['correo'];

    $_SESSION['rol'] =
        $rolUsuario;

    $_SESSION['autenticado'] = true;

    /*
    |--------------------------------------------------------------------------
    | REDIRECCIONAR AL PANEL
    |--------------------------------------------------------------------------
    */

    redirect('panel.php');
    exit;

} catch (PDOException $e) {
    /*
     * Guarda el error técnico en el registro de PHP.
     */

    error_log(
        'Error en procesar_login.php: ' .
        $e->getMessage()
    );

    $_SESSION['error_login'] =
        'Ocurrió un error al iniciar sesión. Intente nuevamente.';

    redirect('login.php');
    exit;
}