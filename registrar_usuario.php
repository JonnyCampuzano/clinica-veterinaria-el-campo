<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

/*
|--------------------------------------------------------------------------
| Verificar conexión PDO
|--------------------------------------------------------------------------
*/
if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        'Error: config/conexion.php debe crear una conexión PDO llamada $pdo.'
    );
}

/*
|--------------------------------------------------------------------------
| Función para escapar contenido HTML
|--------------------------------------------------------------------------
*/
function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Roles permitidos
|--------------------------------------------------------------------------
*/
$rolesPermitidos = [
    'Usuario',
    'Recepcionista',
    'Veterinario',
];

/*
|--------------------------------------------------------------------------
| Crear token CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_registro'])) {
    $_SESSION['csrf_registro'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$tipoMensaje = '';

$nombre = '';
$correo = '';
$rol = 'Usuario';

/*
|--------------------------------------------------------------------------
| Procesar formulario
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $correo = strtolower(trim((string) ($_POST['correo'] ?? '')));
    $rol = trim((string) ($_POST['rol'] ?? ''));

    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $confirmarContrasena = (string) (
        $_POST['confirmar_contrasena'] ?? ''
    );

    $csrf = (string) ($_POST['csrf'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validaciones
    |--------------------------------------------------------------------------
    */
    if (
        empty($_SESSION['csrf_registro']) ||
        !hash_equals($_SESSION['csrf_registro'], $csrf)
    ) {
        $mensaje = 'La solicitud no es válida. Actualiza la página e inténtalo nuevamente.';
        $tipoMensaje = 'error';
    } elseif ($nombre === '') {
        $mensaje = 'Ingresa el nombre completo del usuario.';
        $tipoMensaje = 'error';
    } elseif (mb_strlen($nombre) < 3) {
        $mensaje = 'El nombre debe contener al menos 3 caracteres.';
        $tipoMensaje = 'error';
    } elseif (mb_strlen($nombre) > 100) {
        $mensaje = 'El nombre no puede superar los 100 caracteres.';
        $tipoMensaje = 'error';
    } elseif ($correo === '') {
        $mensaje = 'Ingresa el correo electrónico.';
        $tipoMensaje = 'error';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Ingresa un correo electrónico válido.';
        $tipoMensaje = 'error';
    } elseif (mb_strlen($correo) > 150) {
        $mensaje = 'El correo no puede superar los 150 caracteres.';
        $tipoMensaje = 'error';
    } elseif (!in_array($rol, $rolesPermitidos, true)) {
        $mensaje = 'Selecciona un rol válido.';
        $tipoMensaje = 'error';
    } elseif ($contrasena === '') {
        $mensaje = 'Ingresa una contraseña.';
        $tipoMensaje = 'error';
    } elseif (strlen($contrasena) < 8) {
        $mensaje = 'La contraseña debe contener al menos 8 caracteres.';
        $tipoMensaje = 'error';
    } elseif (!preg_match('/[A-Z]/', $contrasena)) {
        $mensaje = 'La contraseña debe contener al menos una letra mayúscula.';
        $tipoMensaje = 'error';
    } elseif (!preg_match('/[a-z]/', $contrasena)) {
        $mensaje = 'La contraseña debe contener al menos una letra minúscula.';
        $tipoMensaje = 'error';
    } elseif (!preg_match('/[0-9]/', $contrasena)) {
        $mensaje = 'La contraseña debe contener al menos un número.';
        $tipoMensaje = 'error';
    } elseif ($contrasena !== $confirmarContrasena) {
        $mensaje = 'Las contraseñas no coinciden.';
        $tipoMensaje = 'error';
    } else {
        try {
            /*
            |------------------------------------------------------------------
            | Comprobar si el correo ya existe
            |------------------------------------------------------------------
            */
            $buscarUsuario = $pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE correo = :correo
                 LIMIT 1'
            );

            $buscarUsuario->execute([
                ':correo' => $correo,
            ]);

            if ($buscarUsuario->fetch(PDO::FETCH_ASSOC)) {
                $mensaje = 'El correo electrónico ya está registrado.';
                $tipoMensaje = 'error';
            } else {
                /*
                |--------------------------------------------------------------
                | Encriptar contraseña
                |--------------------------------------------------------------
                */
                $contrasenaHash = password_hash(
                    $contrasena,
                    PASSWORD_DEFAULT
                );

                if ($contrasenaHash === false) {
                    throw new RuntimeException(
                        'No se pudo proteger la contraseña.'
                    );
                }

                /*
                |--------------------------------------------------------------
                | Registrar usuario
                |--------------------------------------------------------------
                */
                $registrar = $pdo->prepare(
                    'INSERT INTO usuarios
                        (nombre, correo, contrasena, rol, estado)
                     VALUES
                        (:nombre, :correo, :contrasena, :rol, :estado)'
                );

                $registrar->execute([
                    ':nombre' => $nombre,
                    ':correo' => $correo,
                    ':contrasena' => $contrasenaHash,
                    ':rol' => $rol,
                    ':estado' => 'Activo',
                ]);

                /*
                |--------------------------------------------------------------
                | Limpiar formulario y renovar CSRF
                |--------------------------------------------------------------
                */
                $nombre = '';
                $correo = '';
                $rol = 'Usuario';

                $_SESSION['csrf_registro'] = bin2hex(
                    random_bytes(32)
                );

                $mensaje = 'Usuario registrado correctamente. Ya puede iniciar sesión.';
                $tipoMensaje = 'exito';
            }
        } catch (PDOException $e) {
            $mensaje = 'No se pudo registrar el usuario. Verifica la conexión y la estructura de la tabla usuarios.';
            $tipoMensaje = 'error';
        } catch (Throwable $e) {
            $mensaje = 'Ocurrió un error inesperado al registrar el usuario.';
            $tipoMensaje = 'error';
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
    <title>Registrar usuario</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(
                135deg,
                #e9f7f3 0%,
                #f8fbff 50%,
                #eaf4ff 100%
            );
            color: #1e293b;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .registro-page {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
        }

        .registro-card {
            width: 100%;
            max-width: 520px;
            padding: 36px;
            background: #ffffff;
            border: 1px solid #dbe5ee;
            border-radius: 20px;
            box-shadow: 0 20px 55px rgba(30, 64, 90, 0.15);
        }

        .registro-icono {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            background: #e6f8f2;
            font-size: 32px;
        }

        .registro-card h1 {
            margin-bottom: 9px;
            text-align: center;
            font-size: 28px;
            color: #172033;
        }

        .descripcion {
            margin-bottom: 26px;
            text-align: center;
            color: #64748b;
            font-size: 15px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            outline: none;
            background: #ffffff;
            color: #172033;
            font-size: 15px;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease;
        }

        select.form-control {
            cursor: pointer;
        }

        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.13);
        }

        .ayuda-contrasena {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .btn-registrar {
            width: 100%;
            min-height: 50px;
            margin-top: 5px;
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            background: #10b981;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            transition: background 0.2s ease,
                        transform 0.2s ease;
        }

        .btn-registrar:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-registrar:active {
            transform: translateY(0);
        }

        .mensaje {
            margin-bottom: 20px;
            padding: 13px 15px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.5;
        }

        .mensaje-error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }

        .mensaje-exito {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            color: #166534;
        }

        .volver-login {
            display: block;
            margin-top: 23px;
            text-align: center;
            color: #0f766e;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .volver-login:hover {
            text-decoration: underline;
        }

        .copyright {
            margin-top: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        @media (max-width: 540px) {
            .registro-page {
                padding: 15px;
            }

            .registro-card {
                padding: 28px 21px;
                border-radius: 15px;
            }

            .registro-card h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="registro-page">
    <main class="registro-card">
        <div class="registro-icono" aria-hidden="true">
            👤
        </div>

        <h1>Registrar nuevo usuario</h1>

        <p class="descripcion">
            Complete la información para crear una cuenta
            en el Sistema Veterinario.
        </p>

        <?php if ($mensaje !== ''): ?>
            <div
                class="mensaje <?= $tipoMensaje === 'exito'
                    ? 'mensaje-exito'
                    : 'mensaje-error' ?>"
                role="alert"
            >
                <?= e($mensaje) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" autocomplete="off">
            <input
                type="hidden"
                name="csrf"
                value="<?= e((string) $_SESSION['csrf_registro']) ?>"
            >

            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input
                    class="form-control"
                    type="text"
                    id="nombre"
                    name="nombre"
                    placeholder="Ejemplo: Juan Pérez"
                    value="<?= e($nombre) ?>"
                    minlength="3"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="correo">Correo electrónico</label>
                <input
                    class="form-control"
                    type="email"
                    id="correo"
                    name="correo"
                    placeholder="ejemplo@correo.com"
                    value="<?= e($correo) ?>"
                    maxlength="150"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="rol">Rol</label>
                <select
                    class="form-control"
                    id="rol"
                    name="rol"
                    required
                >
                    <option value="" disabled <?= $rol === '' ? 'selected' : '' ?>>
                        Seleccione un rol
                    </option>

                    <?php foreach ($rolesPermitidos as $rolPermitido): ?>
                        <option
                            value="<?= e($rolPermitido) ?>"
                            <?= $rol === $rolPermitido ? 'selected' : '' ?>
                        >
                            <?= e($rolPermitido) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña</label>
                <input
                    class="form-control"
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    placeholder="Ingrese una contraseña segura"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

                <small class="ayuda-contrasena">
                    Debe tener mínimo 8 caracteres, una mayúscula,
                    una minúscula y un número.
                </small>
            </div>

            <div class="form-group">
                <label for="confirmar_contrasena">
                    Confirmar contraseña
                </label>
                <input
                    class="form-control"
                    type="password"
                    id="confirmar_contrasena"
                    name="confirmar_contrasena"
                    placeholder="Repita la contraseña"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn-registrar">
                Registrar usuario
            </button>
        </form>

        <a href="login.php" class="volver-login">
            ← Volver al inicio de sesión
        </a>

        <p class="copyright">
            © 2026 Sistema Veterinario
        </p>
    </main>
</div>
</body>
</html>