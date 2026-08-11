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
| CONEXIÓN
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| RUTA AUTOMÁTICA DEL PROYECTO
|--------------------------------------------------------------------------
|
| Esto evita errores 404 / Not Found aunque cambies
| el nombre de la carpeta del proyecto.
|
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
    header('Location: ' . $rutaBase . '/panel.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';
$emailIngresado = '';

/*
|--------------------------------------------------------------------------
| PROCESAR LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailIngresado = trim(
        (string) ($_POST['email'] ?? '')
    );

    $password = (string) (
        $_POST['password'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | VALIDAR CAMPOS
    |--------------------------------------------------------------------------
    */

    if ($emailIngresado === '' || $password === '') {

        $error = 'Debes ingresar tu correo y contraseña.';

    } elseif (
        !filter_var(
            $emailIngresado,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error = 'Ingresa un correo electrónico válido.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | BUSCAR USUARIO
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    id,
                    nombre,
                    email,
                    password,
                    rol,
                    estado
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':email' => $emailIngresado
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | VALIDAR
            |--------------------------------------------------------------------------
            */

            if (!$usuario) {

                $error = 'Correo o contraseña incorrectos.';

            } elseif (
                !password_verify(
                    $password,
                    (string) $usuario['password']
                )
            ) {

                $error = 'Correo o contraseña incorrectos.';

            } elseif (
                strtolower(
                    trim(
                        (string) $usuario['estado']
                    )
                ) !== 'activo'
            ) {

                $error = 'Este usuario se encuentra inactivo.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | NORMALIZAR ROL
                |--------------------------------------------------------------------------
                */

                $rol = strtolower(
                    trim(
                        (string) $usuario['rol']
                    )
                );

                $rol = str_replace(
                    ['á', 'é', 'í', 'ó', 'ú'],
                    ['a', 'e', 'i', 'o', 'u'],
                    $rol
                );

                $rol = match ($rol) {

                    'administrador',
                    'admin'
                        => 'administrador',

                    'medico',
                    'medico veterinario',
                    'veterinario'
                        => 'medico',

                    'recepcionista',
                    'recepcion'
                        => 'recepcionista',

                    'cliente',
                    
                        => 'cliente',   

                    default => ''
                };

                if ($rol === '') {

                    $error = 'El usuario no tiene un rol válido.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CREAR SESIÓN
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    $_SESSION['usuario_id'] =
                        (int) $usuario['id'];

                    $_SESSION['id_usuario'] =
                        (int) $usuario['id'];

                    $_SESSION['user_id'] =
                        (int) $usuario['id'];

                    $_SESSION['id'] =
                        (int) $usuario['id'];

                    $_SESSION['nombre'] =
                        (string) $usuario['nombre'];

                    $_SESSION['usuario'] =
                        (string) $usuario['nombre'];

                    $_SESSION['email'] =
                        (string) $usuario['email'];

                    /*
                     * Compatibilidad con otros archivos
                     * que usen "correo".
                     */

                    $_SESSION['correo'] =
                        (string) $usuario['email'];

                    $_SESSION['rol'] = $rol;

                    $_SESSION['estado'] =
                        (string) $usuario['estado'];

                    $_SESSION['logueado'] = true;

                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECCIONAR
                    |--------------------------------------------------------------------------
                    */

                    header('Location: ' . $rutaBase . '/' . home_by_role());
                    exit;
                }
            }

        } catch (PDOException $e) {

            $error =
                'No fue posible iniciar sesión. '
                . 'Verifica los datos registrados.';
        }
    }
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

        .pagina-login {

            min-height: 100vh;

            display: grid;

            grid-template-columns:
                1fr 1fr;
        }

        /* IZQUIERDA */

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

        /* DERECHA */

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

            margin-bottom: 32px;
        }

        .error {

            padding: 14px;

            margin-bottom: 25px;

            background: #fff1f1;

            border:
                1px solid #ffb9b9;

            border-radius: 10px;

            color: #c42020;

            text-align: center;
        }

        .grupo {

            margin-bottom: 22px;
        }

        .grupo label {

            display: block;

            margin-bottom: 8px;

            font-weight: 700;
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
        }

        .grupo input:focus {

            border-color: #2f7d4a;

            box-shadow:
                0 0 0 3px
                rgba(47, 125, 74, 0.12);
        }

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
        }

        .btn-login:hover {

            background: #27693e;
        }

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

        .volver {

            margin-top: 28px;

            text-align: center;
        }

        .volver a {

            color: #444;

            text-decoration: none;
        }

        .footer {

            margin-top: 35px;

            text-align: center;

            color: #9aa4b5;

            font-size: 13px;
        }

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
        }

    </style>

</head>

<body>

<div class="pagina-login">


    <!-- LADO IZQUIERDO -->

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
                    $rutaBase . '/assets/img/perro_gato.png',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                alt="Perro y gato"
                class="mascotas"
            >

        </div>

    </section>


    <!-- LADO DERECHO -->

    <section class="lado-derecho">

        <div class="tarjeta">

            <div class="candado">
                🔒
            </div>

            <h2 class="titulo">
                Iniciar sesión
            </h2>


            <?php if ($error !== ''): ?>

                <div class="error">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                action=""
                method="POST"
            >

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
                        required
                        autofocus
                    >

                </div>


                <div class="grupo">

                    <label for="password">
                        Contraseña
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingrese su contraseña"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn-login"
                >
                    Iniciar sesión
                </button>

            </form>


            <div class="links">

                <a
                    href="<?= htmlspecialchars(
                        $rutaBase . '/recuperar_password.php',
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
                        $rutaBase . '/registrar_usuario.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    Crear usuario
                </a>

            </div>


            <div class="volver">

                <a
                    href="<?= htmlspecialchars(
                        $rutaBase . '/index.php',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    ← Volver a la página principal
                </a>

            </div>


            <div class="footer">

                © <?= date('Y') ?>
                Sistema Veterinario

            </div>

        </div>

    </section>

</div>

</body>
</html>