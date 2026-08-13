<?php
declare(strict_types=1);

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');


/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| SESIÓN
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| REDIRECCIÓN
|--------------------------------------------------------------------------
*/

function login_redirigir(string $ruta): never
{
    $ruta = trim($ruta);

    if ($ruta === '') {
        $ruta = 'login.php';
    }

    if (!headers_sent()) {

        header('Location: ' . $ruta);
        exit;
    }

    $rutaSegura = htmlspecialchars(
        $ruta,
        ENT_QUOTES,
        'UTF-8'
    );

    exit(
        '<!DOCTYPE html>' .
        '<html lang="es">' .
        '<head>' .
        '<meta charset="UTF-8">' .
        '<meta http-equiv="refresh" content="0;url=' .
        $rutaSegura .
        '">' .
        '<title>Redirigiendo...</title>' .
        '</head>' .
        '<body>' .
        '<script>' .
        'window.location.href=' .
        json_encode($ruta) .
        ';' .
        '</script>' .
        '<p>Redirigiendo... ' .
        '<a href="' .
        $rutaSegura .
        '">Continuar</a>' .
        '</p>' .
        '</body>' .
        '</html>'
    );
}


/*
|--------------------------------------------------------------------------
| VOLVER AL LOGIN
|--------------------------------------------------------------------------
*/

function login_volver(string $error): never
{
    login_redirigir(
        'login.php?error=' .
        rawurlencode($error)
    );
}


/*
|--------------------------------------------------------------------------
| DETECTAR COLUMNA
|--------------------------------------------------------------------------
*/

function login_detectar_columna(
    array $columnas,
    array $opciones
): ?string {

    foreach ($opciones as $opcion) {

        if (
            in_array(
                $opcion,
                $columnas,
                true
            )
        ) {
            return $opcion;
        }
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| NORMALIZAR TEXTO
|--------------------------------------------------------------------------
*/

function login_normalizar_texto(
    string $valor
): string {

    /*
    | Eliminar espacios normales y espacios invisibles.
    */

    $valor = str_replace(
        "\xC2\xA0",
        ' ',
        $valor
    );

    $valor = trim($valor);


    /*
    | Convertir espacios múltiples en uno.
    */

    $valor = preg_replace(
        '/\s+/u',
        ' ',
        $valor
    ) ?? $valor;


    /*
    | Minúsculas.
    */

    if (function_exists('mb_strtolower')) {

        $valor = mb_strtolower(
            $valor,
            'UTF-8'
        );

    } else {

        $valor = strtolower($valor);
    }


    /*
    | Quitar tildes.
    */

    $valor = strtr(
        $valor,
        [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n'
        ]
    );


    return trim($valor);
}


/*
|--------------------------------------------------------------------------
| SOLO POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {

    login_volver(
        'metodo_invalido'
    );
}


/*
|--------------------------------------------------------------------------
| RECIBIR DATOS
|--------------------------------------------------------------------------
*/

$correo = strtolower(
    trim(
        (string) (
            $_POST['correo']
            ?? $_POST['email']
            ?? ''
        )
    )
);


$contrasena = (string) (
    $_POST['contrasena']
    ?? $_POST['password']
    ?? $_POST['clave']
    ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDAR CAMPOS
|--------------------------------------------------------------------------
*/

if (
    $correo === ''
    || $contrasena === ''
) {

    login_volver(
        'campos_vacios'
    );
}


if (
    !filter_var(
        $correo,
        FILTER_VALIDATE_EMAIL
    )
) {

    login_volver(
        'correo_invalido'
    );
}


/*
|--------------------------------------------------------------------------
| VALIDAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (
    !isset($pdo)
    || !($pdo instanceof PDO)
) {

    error_log(
        'LOGIN: No existe una conexión PDO válida.'
    );

    login_volver(
        'conexion_invalida'
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | SABER QUÉ BASE DE DATOS ESTÁ USANDO
    |--------------------------------------------------------------------------
    |
    | Esto es útil para detectar si config/conexion.php está conectado
    | accidentalmente a otra base.
    |
    */

    $baseActual = '';

    try {

        $baseActual = (string) $pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();

    } catch (Throwable $e) {

        $baseActual = '';
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER COLUMNAS DE usuarios
    |--------------------------------------------------------------------------
    */

    $columnas = $pdo
        ->query(
            'SHOW COLUMNS FROM usuarios'
        )
        ->fetchAll(
            PDO::FETCH_COLUMN
        );


    $columnas = array_map(

        static fn(
            mixed $columna
        ): string =>
            (string) $columna,

        $columnas
    );


    /*
    |--------------------------------------------------------------------------
    | DETECTAR COLUMNAS
    |--------------------------------------------------------------------------
    */

    $columnaId =
        login_detectar_columna(
            $columnas,
            [
                'id',
                'id_usuario',
                'usuario_id'
            ]
        );


    $columnaNombre =
        login_detectar_columna(
            $columnas,
            [
                'nombre',
                'nombres',
                'usuario',
                'nombre_usuario'
            ]
        );


    $columnaCorreo =
        login_detectar_columna(
            $columnas,
            [
                'email',
                'correo'
            ]
        );


    $columnaContrasena =
        login_detectar_columna(
            $columnas,
            [
                'password',
                'contrasena',
                'clave',
                'contrasena_hash',
                'password_hash'
            ]
        );


    $columnaRol =
        login_detectar_columna(
            $columnas,
            [
                'rol'
            ]
        );


    $columnaEstado =
        login_detectar_columna(
            $columnas,
            [
                'estado',
                'activo'
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ESTRUCTURA
    |--------------------------------------------------------------------------
    */

    if (
        $columnaId === null
        || $columnaNombre === null
        || $columnaCorreo === null
        || $columnaContrasena === null
        || $columnaRol === null
    ) {

        error_log(
            'LOGIN: estructura incorrecta de usuarios. '
            . 'Base: '
            . $baseActual
            . '. Columnas: '
            . implode(', ', $columnas)
        );


        login_volver(
            'estructura_usuarios_invalida'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PREPARAR SELECT
    |--------------------------------------------------------------------------
    */

    $campos = [

        "`{$columnaId}` AS id",

        "`{$columnaNombre}` AS nombre",

        "`{$columnaCorreo}` AS correo",

        "`{$columnaContrasena}` AS contrasena",

        "`{$columnaRol}` AS rol"

    ];


    if ($columnaEstado !== null) {

        $campos[] =
            "`{$columnaEstado}` AS estado";

    } else {

        $campos[] =
            "'Activo' AS estado";
    }


    /*
    |--------------------------------------------------------------------------
    | BUSCAR USUARIO
    |--------------------------------------------------------------------------
    */

    $sql = '

        SELECT
            ' . implode(
                ', ',
                $campos
            ) . '

        FROM usuarios

        WHERE `' .
        $columnaCorreo .
        '` = :correo

        LIMIT 1
    ';


    $consulta =
        $pdo->prepare($sql);


    $consulta->execute([
        ':correo' => $correo
    ]);


    $usuario =
        $consulta->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | USUARIO NO EXISTE
    |--------------------------------------------------------------------------
    */

    if (!is_array($usuario)) {

        error_log(
            'LOGIN: usuario no encontrado: '
            . $correo
            . ' | Base: '
            . $baseActual
        );


        login_volver(
            'credenciales_invalidas'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ESTADO
    |--------------------------------------------------------------------------
    */

    $estado =
        login_normalizar_texto(
            (string) (
                $usuario['estado']
                ?? 'activo'
            )
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


    if (
        in_array(
            $estado,
            $estadosBloqueados,
            true
        )
    ) {

        login_volver(
            'usuario_inactivo'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CONTRASEÑA GUARDADA
    |--------------------------------------------------------------------------
    */

    $contrasenaGuardada =
        (string) (
            $usuario['contrasena']
            ?? ''
        );


    if ($contrasenaGuardada === '') {

        login_volver(
            'credenciales_invalidas'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DETECTAR SI ESTÁ CIFRADA
    |--------------------------------------------------------------------------
    */

    $informacionHash =
        password_get_info(
            $contrasenaGuardada
        );


    $esHash =
        !empty(
            $informacionHash['algo']
        );


    /*
    |--------------------------------------------------------------------------
    | COMPROBAR CONTRASEÑA
    |--------------------------------------------------------------------------
    */

    if ($esHash) {

        $contrasenaCorrecta =
            password_verify(
                $contrasena,
                $contrasenaGuardada
            );

    } else {

        /*
        | Compatibilidad temporal con contraseñas antiguas
        | guardadas como texto plano.
        */

        $contrasenaCorrecta =
            hash_equals(
                $contrasenaGuardada,
                $contrasena
            );
    }


    if (!$contrasenaCorrecta) {

        login_volver(
            'credenciales_invalidas'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIGRAR CONTRASEÑA ANTIGUA A HASH
    |--------------------------------------------------------------------------
    */

    if (!$esHash) {

        try {

            $nuevoHash =
                password_hash(
                    $contrasena,
                    PASSWORD_DEFAULT
                );


            $actualizar =
                $pdo->prepare(
                    "
                    UPDATE usuarios

                    SET
                        `{$columnaContrasena}`
                        = :password

                    WHERE
                        `{$columnaId}`
                        = :id
                    "
                );


            $actualizar->execute([

                ':password' =>
                    $nuevoHash,

                ':id' =>
                    (int) (
                        $usuario['id']
                        ?? 0
                    )

            ]);


        } catch (Throwable $e) {

            error_log(
                'LOGIN: no se pudo actualizar '
                . 'la contraseña antigua: '
                . $e->getMessage()
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DATOS DEL USUARIO
    |--------------------------------------------------------------------------
    */

    $idUsuario =
        (int) (
            $usuario['id']
            ?? 0
        );


    $nombreUsuario =
        trim(
            (string) (
                $usuario['nombre']
                ?? ''
            )
        );


    if ($nombreUsuario === '') {

        $nombreUsuario =
            $correo;
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER ROL EXACTO DE MYSQL
    |--------------------------------------------------------------------------
    */

    $rolMysql =
        (string) (
            $usuario['rol']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | NORMALIZAR ROL
    |--------------------------------------------------------------------------
    |
    | Ejemplos:
    |
    | Cliente             -> cliente
    | Administrador       -> administrador
    | Médico              -> medico
    | Médico Veterinario  -> medico veterinario
    | Recepcionista       -> recepcionista
    |
    */

    $rolNormalizado =
        login_normalizar_texto(
            $rolMysql
        );


    /*
    |--------------------------------------------------------------------------
    | CONVERTIR A ROL INTERNO
    |--------------------------------------------------------------------------
    */

    $rolInterno = match ($rolNormalizado) {

        'cliente'
            => 'cliente',

        'administrador',
        'admin'
            => 'administrador',

        'recepcionista',
        'recepcion'
            => 'recepcionista',

        'medico',
        'medico veterinario',
        'veterinario'
            => 'medico',

        default
            => ''
    };


    /*
    |--------------------------------------------------------------------------
    | VALIDAR ROL ANTES DE CREAR SESIÓN
    |--------------------------------------------------------------------------
    */

    if ($rolInterno === '') {

        error_log(
            'LOGIN: ROL NO RECONOCIDO.'
            . ' Valor MySQL=['
            . $rolMysql
            . ']'
            . ' Normalizado=['
            . $rolNormalizado
            . ']'
            . ' Usuario=['
            . $correo
            . ']'
            . ' Base=['
            . $baseActual
            . ']'
        );


        login_volver(
            'rol_no_autorizado'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR SESIÓN
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);


    $_SESSION['usuario_id'] =
        $idUsuario;


    $_SESSION['id_usuario'] =
        $idUsuario;


    $_SESSION['nombre'] =
        $nombreUsuario;


    $_SESSION['usuario'] =
        $nombreUsuario;


    $_SESSION['correo'] =
        (string) (
            $usuario['correo']
            ?? $correo
        );


    /*
    |--------------------------------------------------------------------------
    | IMPORTANTE
    |--------------------------------------------------------------------------
    |
    | Guardamos el rol NORMALIZADO.
    |
    | Carla:
    | MySQL = Cliente
    | sesión = cliente
    |
    */

    $_SESSION['rol'] =
        $rolInterno;


    $_SESSION['autenticado'] =
        true;


    /*
    |--------------------------------------------------------------------------
    | CLIENTE
    |--------------------------------------------------------------------------
    */

    if ($rolInterno === 'cliente') {

        login_redirigir(
            'reservar.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMINISTRADOR
    |--------------------------------------------------------------------------
    */

    if (
        $rolInterno ===
        'administrador'
    ) {

        login_redirigir(
            'panel.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RECEPCIONISTA
    |--------------------------------------------------------------------------
    */

    if (
        $rolInterno ===
        'recepcionista'
    ) {

        login_redirigir(
            'panel.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MÉDICO
    |--------------------------------------------------------------------------
    */

    if (
        $rolInterno ===
        'medico'
    ) {

        login_redirigir(
            'panel.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SEGURIDAD
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];

    session_destroy();


    login_volver(
        'rol_no_autorizado'
    );


} catch (PDOException $error) {

    error_log(
        'LOGIN PDO: '
        . $error->getMessage()
    );


    login_volver(
        'error_base_datos'
    );


} catch (Throwable $error) {

    error_log(
        'LOGIN GENERAL: '
        . $error->getMessage()
    );


    login_volver(
        'error_interno'
    );
}