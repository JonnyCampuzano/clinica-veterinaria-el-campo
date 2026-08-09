<?php
declare(strict_types=1);

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
| CONEXIÓN A LA BASE DE DATOS
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/conexion.php';

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';
$exito = '';

$nombre = '';
$email = '';
$rolSeleccionado = 'recepcionista';

/*
|--------------------------------------------------------------------------
| PROCESAR REGISTRO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim(
        (string) ($_POST['nombre'] ?? '')
    );

    $email = trim(
        (string) ($_POST['email'] ?? '')
    );

    $rolSeleccionado = strtolower(
        trim(
            (string) ($_POST['rol'] ?? '')
        )
    );

    $password = (string) (
        $_POST['password'] ?? ''
    );

    $confirmarPassword = (string) (
        $_POST['confirmar_password'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | ROLES PERMITIDOS EN REGISTRO PÚBLICO
    |--------------------------------------------------------------------------
    */

    $rolesPermitidos = [
        'medico',
        'recepcionista'
    ];

    /*
    |--------------------------------------------------------------------------
    | VALIDACIONES
    |--------------------------------------------------------------------------
    */

    if (
        $nombre === ''
        || $email === ''
        || $rolSeleccionado === ''
        || $password === ''
        || $confirmarPassword === ''
    ) {

        $error = 'Todos los campos son obligatorios.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error = 'El correo electrónico no es válido.';

    } elseif (
        !in_array(
            $rolSeleccionado,
            $rolesPermitidos,
            true
        )
    ) {

        $error = 'El rol seleccionado no es válido.';

    } elseif (
        strlen($password) < 6
    ) {

        $error =
            'La contraseña debe tener mínimo 6 caracteres.';

    } elseif (
        $password !== $confirmarPassword
    ) {

        $error =
            'Las contraseñas no coinciden.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | COMPROBAR SI EL CORREO YA EXISTE
            |--------------------------------------------------------------------------
            */

            $sqlBuscar = "
                SELECT id
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ";

            $stmtBuscar =
                $pdo->prepare($sqlBuscar);

            $stmtBuscar->execute([
                ':email' => $email
            ]);

            $usuarioExistente =
                $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            if ($usuarioExistente) {

                $error =
                    'Ya existe un usuario registrado con ese correo.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CONVERTIR ROL AL FORMATO DE MYSQL
                |--------------------------------------------------------------------------
                */

                $rolBaseDatos = match ($rolSeleccionado) {

                    'medico'
                        => 'Medico',

                    'recepcionista'
                        => 'Recepcionista',

                    default
                        => 'Recepcionista'
                };

                /*
                |--------------------------------------------------------------------------
                | ENCRIPTAR CONTRASEÑA
                |--------------------------------------------------------------------------
                */

                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                /*
                |--------------------------------------------------------------------------
                | ESTADO
                |--------------------------------------------------------------------------
                */

                $estado = 'Activo';

                /*
                |--------------------------------------------------------------------------
                | INSERTAR USUARIO
                |--------------------------------------------------------------------------
                */

                $sqlInsertar = "
                    INSERT INTO usuarios
                    (
                        nombre,
                        email,
                        password,
                        rol,
                        estado
                    )
                    VALUES
                    (
                        :nombre,
                        :email,
                        :password,
                        :rol,
                        :estado
                    )
                ";

                $stmtInsertar =
                    $pdo->prepare($sqlInsertar);

                $stmtInsertar->execute([

                    ':nombre' =>
                        $nombre,

                    ':email' =>
                        $email,

                    ':password' =>
                        $passwordHash,

                    ':rol' =>
                        $rolBaseDatos,

                    ':estado' =>
                        $estado
                ]);

                /*
                |--------------------------------------------------------------------------
                | REGISTRO EXITOSO
                |--------------------------------------------------------------------------
                */

                $exito =
                    'Usuario registrado correctamente. '
                    . 'Ya puedes iniciar sesión.';

                $nombre = '';
                $email = '';
                $rolSeleccionado = 'recepcionista';
            }

        } catch (PDOException $e) {

            $error =
                'No fue posible registrar el usuario.';
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
        Crear usuario | Sistema Veterinario
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
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f1f7ff,
                    #f8fbff
                );

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 30px;
        }

        .card {

            width: 100%;

            max-width: 560px;

            background: #ffffff;

            border:
                1px solid #d7e3f3;

            border-radius: 20px;

            padding:
                40px 45px;

            box-shadow:
                0 20px 55px
                rgba(40, 78, 130, 0.13);
        }

        /*
        |--------------------------------------------------------------------------
        | ICONO
        |--------------------------------------------------------------------------
        */

        .icono {

            width: 66px;

            height: 66px;

            margin:
                0 auto 18px;

            border-radius: 16px;

            background: #edf8f1;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 30px;
        }

        /*
        |--------------------------------------------------------------------------
        | TITULO
        |--------------------------------------------------------------------------
        */

        h1 {

            text-align: center;

            color: #174c9b;

            font-size: 30px;

            font-weight: 800;

            margin-bottom: 10px;
        }

        .subtitulo {

            text-align: center;

            color: #748096;

            font-size: 17px;

            margin-bottom: 34px;
        }

        /*
        |--------------------------------------------------------------------------
        | MENSAJES
        |--------------------------------------------------------------------------
        */

        .error {

            padding: 13px;

            margin-bottom: 22px;

            background: #fff1f1;

            border:
                1px solid #ffb9b9;

            border-radius: 10px;

            color: #bd2424;

            font-size: 14px;

            text-align: center;
        }

        .exito {

            padding: 13px;

            margin-bottom: 22px;

            background: #edfaf2;

            border:
                1px solid #a4dab6;

            border-radius: 10px;

            color: #18723a;

            font-size: 14px;

            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | CAMPOS
        |--------------------------------------------------------------------------
        */

        .grupo {

            margin-bottom: 22px;
        }

        .grupo label {

            display: block;

            margin-bottom: 9px;

            color: #111111;

            font-size: 16px;

            font-weight: 700;
        }

        .campo {

            width: 100%;

            height: 62px;

            padding:
                0 18px;

            border:
                1px solid #bad0ee;

            border-radius: 11px;

            background: #ffffff;

            color: #20293b;

            font-size: 16px;

            outline: none;

            transition:
                border-color .2s,
                box-shadow .2s;
        }

        .campo:focus {

            border-color: #2f7d4a;

            box-shadow:
                0 0 0 3px
                rgba(47, 125, 74, 0.12);
        }

        /*
        |--------------------------------------------------------------------------
        | SELECT ROL
        |--------------------------------------------------------------------------
        */

        select.campo {

            cursor: pointer;

            appearance: auto;

            background: #ffffff;
        }

        /*
        |--------------------------------------------------------------------------
        | BOTÓN
        |--------------------------------------------------------------------------
        */

        .btn {

            width: 100%;

            min-height: 62px;

            margin-top: 2px;

            border: none;

            border-radius: 11px;

            background: #2f7d4a;

            color: #ffffff;

            font-family: inherit;

            font-size: 18px;

            font-weight: 700;

            cursor: pointer;

            transition:
                background .2s,
                transform .2s,
                box-shadow .2s;
        }

        .btn:hover {

            background: #27693e;

            transform:
                translateY(-1px);

            box-shadow:
                0 7px 18px
                rgba(47, 125, 74, 0.20);
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

            color: #08752f;

            font-size: 16px;

            font-weight: 700;

            text-decoration: none;
        }

        .volver a:hover {

            text-decoration: underline;
        }

        /*
        |--------------------------------------------------------------------------
        | NOTA
        |--------------------------------------------------------------------------
        */

        .nota {

            margin-top: 22px;

            padding: 12px 14px;

            background: #f5f8fc;

            border-radius: 8px;

            color: #6b778c;

            font-size: 13px;

            line-height: 1.5;

            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            body {

                padding: 15px;
            }

            .card {

                padding:
                    30px 22px;
            }

            h1 {

                font-size: 25px;
            }

            .campo {

                height: 56px;
            }

            .btn {

                min-height: 56px;
            }
        }

    </style>

</head>

<body>

<div class="card">

    <div class="icono">
        👤
    </div>

    <h1>
        Crear usuario
    </h1>

    <p class="subtitulo">
        Sistema Veterinario
    </p>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ÉXITO -->

    <?php if ($exito !== ''): ?>

        <div class="exito">

            <?= htmlspecialchars(
                $exito,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- FORMULARIO -->

    <form
        method="POST"
        action=""
    >


        <!-- NOMBRE -->

        <div class="grupo">

            <label for="nombre">
                Nombre completo
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre"
                class="campo"
                placeholder="Ingrese su nombre"
                value="<?= htmlspecialchars(
                    $nombre,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
                autofocus
            >

        </div>


        <!-- CORREO -->

        <div class="grupo">

            <label for="email">
                Correo electrónico
            </label>

            <input
                type="email"
                id="email"
                name="email"
                class="campo"
                placeholder="ejemplo@gmail.com"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <!-- ROL -->

        <div class="grupo">

            <label for="rol">
                Rol
            </label>

            <select
                id="rol"
                name="rol"
                class="campo"
                required
            >

                <option
                    value=""
                    disabled
                >
                    Seleccione un rol
                </option>

                <option
                    value="recepcionista"
                    <?= $rolSeleccionado === 'recepcionista'
                        ? 'selected'
                        : '' ?>
                >
                    Recepcionista
                </option>

                <option
                    value="medico"
                    <?= $rolSeleccionado === 'medico'
                        ? 'selected'
                        : '' ?>
                >
                    Médico Veterinario
                </option>

            </select>

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
                class="campo"
                placeholder="Mínimo 6 caracteres"
                required
            >

        </div>


        <!-- CONFIRMAR CONTRASEÑA -->

        <div class="grupo">

            <label for="confirmar_password">
                Confirmar contraseña
            </label>

            <input
                type="password"
                id="confirmar_password"
                name="confirmar_password"
                class="campo"
                placeholder="Repita la contraseña"
                required
            >

        </div>


        <!-- BOTÓN -->

        <button
            type="submit"
            class="btn"
        >
            Registrar usuario
        </button>

    </form>


    <!-- VOLVER -->

    <div class="volver">

        <a href="login.php">
            ← Volver a iniciar sesión
        </a>

    </div>


    <div class="nota">

        Los usuarios pueden registrarse como
        <strong>Recepcionista</strong> o
        <strong>Médico Veterinario</strong>.

    </div>

</div>

</body>

</html>