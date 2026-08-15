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
| CONFIGURACIÓN
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| RUTA AUTOMÁTICA DEL PROYECTO
|--------------------------------------------------------------------------
*/

$rutaBase = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/')
);

$rutaBase = rtrim($rutaBase, '/');

if ($rutaBase === '.' || $rutaBase === '/') {
    $rutaBase = '';
}


/*
|--------------------------------------------------------------------------
| SI YA INICIÓ SESIÓN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['usuario_id'])) {

    /*
    |--------------------------------------------------------------------------
    | Si existe la función home_by_role()
    |--------------------------------------------------------------------------
    */

    if (function_exists('home_by_role')) {

        $destino = home_by_role();

        if ($destino !== '') {

            header(
                'Location: ' .
                $rutaBase .
                '/' .
                ltrim($destino, '/')
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Respaldo
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' .
        $rutaBase .
        '/panel.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ERROR RECIBIDO DESDE procesar_login.php
|--------------------------------------------------------------------------
*/

$codigoError = trim(
    (string) (
        $_GET['error']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| MENSAJES DE ERROR
|--------------------------------------------------------------------------
*/

$mensajesError = [

    'metodo_invalido' =>
        'La solicitud de inicio de sesión no es válida.',

    'campos_vacios' =>
        'Debes ingresar tu correo y contraseña.',

    'correo_invalido' =>
        'Ingresa un correo electrónico válido.',

    'conexion_invalida' =>
        'No fue posible conectar con la base de datos.',

    'estructura_usuarios_invalida' =>
        'La estructura de la tabla usuarios no es válida.',

    'credenciales_invalidas' =>
        'Correo o contraseña incorrectos.',

    'usuario_inactivo' =>
        'Este usuario se encuentra inactivo.',

    'rol_no_autorizado' =>
        'El usuario no tiene un rol válido o autorizado.',

    'error_base_datos' =>
        'Ocurrió un problema al consultar la base de datos.',

    'error_interno' =>
        'Ocurrió un error interno al iniciar sesión.'
];


$error = '';

if (
    $codigoError !== ''
    && isset($mensajesError[$codigoError])
) {

    $error =
        $mensajesError[$codigoError];
}


/*
|--------------------------------------------------------------------------
| CORREO ANTERIOR
|--------------------------------------------------------------------------
|
| Si posteriormente quieres mantener el correo después de un error,
| procesar_login.php puede enviarlo por GET.
|
*/

$emailIngresado = trim(
    (string) (
        $_GET['email']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| MENSAJE DE ÉXITO
|--------------------------------------------------------------------------
|
| Se utiliza, por ejemplo, después de restablecer correctamente
| la contraseña desde procesar_restablecer.php.
|
*/

$mensajeExito = trim(
    (string) (
        $_SESSION['mensaje_login']
        ?? ''
    )
);

if ($mensajeExito !== '') {
    unset($_SESSION['mensaje_login']);
}

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Iniciar sesión | Sistema Veterinario
    </title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background: #f7faff;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENEDOR GENERAL
        |--------------------------------------------------------------------------
        */

        .pagina-login {

            min-height: 100vh;

            display: grid;

            grid-template-columns:
                1fr 1fr;
        }


        /*
        |--------------------------------------------------------------------------
        | LADO IZQUIERDO
        |--------------------------------------------------------------------------
        */

        .lado-izquierdo {

            background: #f1f7ff;

            border-right:
                1px solid #d9e6f8;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 40px;
        }


        .bienvenida {

            width: 100%;

            max-width: 620px;

            text-align: center;
        }


        .logo {

            width: 82px;
            height: 82px;

            margin:
                0 auto 18px;

            border:
                2px solid #2563eb;

            border-radius: 50%;

            background: white;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 33px;
        }


        .nombre-sistema {

            color: #1854a6;

            font-size: 35px;

            margin-bottom: 30px;
        }


        .bienvenida h2 {

            color: #173f88;

            font-size: 26px;

            margin-bottom: 10px;
        }


        .bienvenida p {

            color: #45536c;

            font-size: 17px;

            margin-bottom: 40px;
        }


        .mascotas {

            display: block;

            width: 100%;

            max-width: 470px;

            height: auto;

            margin: 0 auto;
        }


        /*
        |--------------------------------------------------------------------------
        | LADO DERECHO
        |--------------------------------------------------------------------------
        */

        .lado-derecho {

            background: #f8fbff;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 35px;
        }


        .tarjeta {

            width: 100%;

            max-width: 450px;

            background: white;

            border:
                1px solid #dce7f5;

            border-radius: 18px;

            padding: 40px;

            box-shadow:
                0 20px 55px
                rgba(40, 78, 130, 0.12);
        }


        .candado {

            width: 58px;
            height: 58px;

            margin:
                0 auto 18px;

            border-radius: 15px;

            background: #eff6ff;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 23px;
        }


        .titulo {

            text-align: center;

            font-size: 28px;

            color: #1f2937;

            margin-bottom: 32px;
        }


        /*
        |--------------------------------------------------------------------------
        | MENSAJE DE ÉXITO
        |--------------------------------------------------------------------------
        */

        .mensaje-exito {

            padding: 14px;

            margin-bottom: 25px;

            background: #ecfdf5;

            border:
                1px solid #a7f3d0;

            border-radius: 10px;

            color: #047857;

            text-align: center;

            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {

            padding: 14px;

            margin-bottom: 25px;

            background: #fff1f1;

            border:
                1px solid #ffb9b9;

            border-radius: 10px;

            color: #c42020;

            text-align: center;

            font-weight: 600;
        }


        /*
        |--------------------------------------------------------------------------
        | FORMULARIO
        |--------------------------------------------------------------------------
        */

        .grupo {

            margin-bottom: 22px;
        }


        .grupo label {

            display: block;

            margin-bottom: 8px;

            font-weight: 700;

            color: #26364a;
        }


        .grupo input {

            width: 100%;

            height: 60px;

            padding:
                0 18px;

            border:
                1px solid #bfd0ea;

            border-radius: 10px;

            outline: none;

            font-size: 16px;

            background: white;

            transition:
                border-color 0.2s,
                box-shadow 0.2s;
        }


        .grupo input:focus {

            border-color: #2f7d4a;

            box-shadow:
                0 0 0 3px
                rgba(47, 125, 74, 0.12);
        }


        /*
        |--------------------------------------------------------------------------
        | BOTÓN
        |--------------------------------------------------------------------------
        */

        .btn-login {

            width: 100%;

            min-height: 60px;

            border: none;

            border-radius: 10px;

            background: #2f7d4a;

            color: white;

            font-size: 17px;

            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s,
                transform 0.1s;
        }


        .btn-login:hover {

            background: #27693e;
        }


        .btn-login:active {

            transform:
                translateY(1px);
        }


        /*
        |--------------------------------------------------------------------------
        | ENLACES
        |--------------------------------------------------------------------------
        */

        .links {

            margin-top: 27px;

            display: flex;

            justify-content: center;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;
        }


        .links a {

            color: #147539;

            font-weight: 700;

            text-decoration: none;
        }


        .links a:hover {

            text-decoration: underline;
        }


        .separador {

            color: #999;
        }


        /*
        |--------------------------------------------------------------------------
        | VOLVER
        |--------------------------------------------------------------------------
        */

        .volver {

            margin-top: 28px;

            text-align: center;
        }


        .volver a {

            color: #444;

            text-decoration: none;
        }


        .volver a:hover {

            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {

            margin-top: 35px;

            text-align: center;

            color: #9aa4b5;

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {

            .pagina-login {

                display: block;
            }


            .lado-izquierdo {

                display: none;
            }


            .lado-derecho {

                min-height: 100vh;

                padding: 20px;
            }


            .tarjeta {

                padding:
                    30px 24px;
            }
        }

    </style>

</head>


<body>


<div class="pagina-login">


    <!--
    |--------------------------------------------------------------------------
    | LADO IZQUIERDO
    |--------------------------------------------------------------------------
    -->


    <section class="lado-izquierdo">


        <div class="bienvenida">


            <div class="logo">
                🐾
            </div>


            <h1 class="nombre-sistema">

                Sistema Veterinario

            </h1>


            <h2>

                Bienvenido

            </h2>


            <p>

                Inicie sesión para acceder al sistema

            </p>


            <img

                src="<?= htmlspecialchars(
                    $rutaBase .
                    '/assets/img/perro_gato.png',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

                alt="Perro y gato"

                class="mascotas"

            >


        </div>


    </section>



    <!--
    |--------------------------------------------------------------------------
    | LADO DERECHO
    |--------------------------------------------------------------------------
    -->


    <section class="lado-derecho">


        <div class="tarjeta">


            <div class="candado">

                🔒

            </div>


            <h2 class="titulo">

                Iniciar sesión

            </h2>



            <!--
            |--------------------------------------------------------------------------
            | MENSAJE DE ÉXITO
            |--------------------------------------------------------------------------
            -->


            <?php if ($mensajeExito !== ''): ?>


                <div class="mensaje-exito">


                    <?= htmlspecialchars(
                        $mensajeExito,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                </div>


            <?php endif; ?>



            <!--
            |--------------------------------------------------------------------------
            | MENSAJE DE ERROR
            |--------------------------------------------------------------------------
            -->


            <?php if ($error !== ''): ?>


                <div class="error">


                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                </div>


            <?php endif; ?>



            <!--
            |--------------------------------------------------------------------------
            | FORMULARIO
            |--------------------------------------------------------------------------
            -->


            <form

                action="<?= htmlspecialchars(
                    $rutaBase .
                    '/procesar_login.php',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"

                method="POST"

                autocomplete="on"

            >



                <!-- CORREO -->


                <div class="grupo">


                    <label for="email">

                        Correo electrónico

                    </label>


                    <input

                        type="email"

                        id="email"

                        name="email"

                        placeholder="Ingrese su correo"

                        value="<?= htmlspecialchars(
                            $emailIngresado,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"

                        autocomplete="email"

                        required

                        autofocus

                    >


                </div>



                <!-- CONTRASEÑA -->


                <div class="grupo">


                    <label for="password">

                        Contraseña

                    </label>


                    <input

                        type="password"

                        id="password"

                        name="password"

                        placeholder="Ingrese su contraseña"

                        autocomplete="current-password"

                        required

                    >


                </div>



                <!-- BOTÓN -->


                <button

                    type="submit"

                    class="btn-login"

                >

                    Iniciar sesión

                </button>


            </form>



            <!--
            |--------------------------------------------------------------------------
            | ENLACES
            |--------------------------------------------------------------------------
            -->


            <div class="links">


                <a

                    href="<?= htmlspecialchars(
                        $rutaBase .
                        '/olvide_password.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"

                >

                    ¿Olvidaste tu contraseña?


                </a>



                <span class="separador">

                    |

                </span>



                <a

                    href="<?= htmlspecialchars(
                        $rutaBase .
                        '/registrar_usuario.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"

                >

                    Crear usuario

                </a>


            </div>



            <!--
            |--------------------------------------------------------------------------
            | VOLVER
            |--------------------------------------------------------------------------
            -->


            <div class="volver">


                <a

                    href="<?= htmlspecialchars(
                        $rutaBase .
                        '/index.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"

                >

                    ← Volver a la página principal

                </a>


            </div>



            <!--
            |--------------------------------------------------------------------------
            | FOOTER
            |--------------------------------------------------------------------------
            -->


            <div class="footer">


                © <?= date('Y') ?>

                Sistema Veterinario


            </div>


        </div>


    </section>


</div>


</body>

</html>