<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

/*
|--------------------------------------------------------------------------
| EVITAR REGRESAR AL LOGIN SI YA EXISTE UNA SESIÓN
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['usuario_id'])) {
    redirect('panel.php');
}

/*
|--------------------------------------------------------------------------
| RECIBIR MENSAJES
|--------------------------------------------------------------------------
*/

$error = trim((string) ($_GET['error'] ?? ''));
$msg   = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sistema Veterinario | Iniciar sesión</title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/login.css')) ?>"
    >

    <style>
        .boton-registrar-login {
            display: block;
            margin-top: 14px;
            padding: 15px;

            background: #10b981;
            color: #ffffff;

            text-align: center;
            text-decoration: none;
            font-weight: bold;

            border-radius: 10px;

            transition:
                background-color 0.25s ease,
                transform 0.25s ease;
        }

        .boton-registrar-login:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px;

            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 10px;

            color: #991b1b;
            text-align: center;
        }

        .alert-success {
            margin-bottom: 18px;
            padding: 14px;

            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 10px;

            color: #166534;
            text-align: center;
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <!-- PARTE IZQUIERDA -->
    <section class="login-hero">

        <div class="brand">
            <div class="brand-icon">🐾</div>
            <h1>Sistema Veterinario</h1>
        </div>

        <div class="welcome">
            <h2>Bienvenido</h2>

            <p>
                Inicie sesión para acceder al sistema.
            </p>
        </div>

        <img
            src="<?= e(url('assets/img/perro_gato.png')) ?>"
            alt="Perro y gato"
            class="pets-img"
        >

    </section>

    <!-- PARTE DERECHA -->
    <section class="login-panel">

        <form
    action="procesar_login.php"
    method="POST"
    class="login-card"
    autocomplete="on"
>

            <div class="lock-icon">🔒</div>

            <h2>Iniciar sesión</h2>

            <?php if ($msg === 'password_actualizada'): ?>
                <div class="alert-success">
                    Contraseña actualizada correctamente.
                </div>
            <?php endif; ?>

            <?php if ($msg === 'registro_exitoso'): ?>
                <div class="alert-success">
                    Usuario registrado correctamente. Ya puede iniciar sesión.
                </div>
            <?php endif; ?>

            <?php if ($error === 'campos_vacios'): ?>

                <div class="alert">
                    Complete todos los campos.
                </div>

            <?php elseif ($error === 'correo_invalido'): ?>

                <div class="alert">
                    Ingrese un correo electrónico válido.
                </div>

            <?php elseif ($error === 'credenciales_invalidas'): ?>

                <div class="alert">
                    Correo o contraseña incorrectos.
                </div>

            <?php elseif ($error === 'usuario_inactivo'): ?>

                <div class="alert">
                    El usuario está inactivo.
                </div>

            <?php elseif ($error === 'metodo_invalido'): ?>

                <div class="alert">
                    Solicitud no válida.
                </div>

            <?php elseif ($error === 'conexion'): ?>

                <div class="alert">
                    No fue posible conectarse con la base de datos.
                </div>

            <?php elseif ($error === 'sistema'): ?>

                <div class="alert">
                    Ocurrió un error en el sistema. Intente nuevamente.
                </div>

            <?php endif; ?>

            <label for="correo">
                Correo electrónico
            </label>

            <input
                type="email"
                id="correo"
                name="correo"
                placeholder="Ingrese su correo"
                autocomplete="email"
                required
            >

            <label for="contrasena">
                Contraseña
            </label>

            <input
                type="password"
                id="contrasena"
                name="contrasena"
                placeholder="Ingrese su contraseña"
                autocomplete="current-password"
                required
            >

            <div class="options">

                <label class="remember">
                    <input
                        type="checkbox"
                        name="recordarme"
                        value="1"
                    >

                    Recordarme
                </label>

                <a href="<?= e(url('recuperar.php')) ?>">
                    ¿Olvidó su contraseña?
                </a>

            </div>

            <button type="submit">
                Ingresar
            </button>

            <!-- En tu estructura el archivo se llama registrar.php -->
            <a
                href="<?= e(url('registrar.php')) ?>"
                class="boton-registrar-login"
            >
                Registrar nuevo usuario
            </a>

            <p class="footer">
                © <?= date('Y') ?> Sistema Veterinario
            </p>

        </form>

    </section>

</div>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>

</body>
</html>