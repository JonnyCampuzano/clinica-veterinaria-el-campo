<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

$mensaje = '';
$error = '';
$codigoPrueba = '';

function ruta_base(): string
{
    $ruta = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    if ($ruta === '/' || $ruta === '.' || $ruta === '\\') {
        return '';
    }

    return rtrim($ruta, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string) ($_POST['email'] ?? ''));

    if ($email === '') {
        $error = 'Ingrese su correo electrónico.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingrese un correo electrónico válido.';
    } elseif (!isset($pdo) || !($pdo instanceof PDO)) {
        $error = 'No fue posible conectar con la base de datos.';
    } else {

        try {

            $stmt = $pdo->prepare(
                'SELECT id, nombre, email, estado
                 FROM usuarios
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {

                $error = 'No existe un usuario registrado con ese correo.';

            } elseif ((string) $usuario['estado'] !== 'Activo') {

                $error = 'El usuario se encuentra inactivo.';

            } else {

                $codigo = (string) random_int(100000, 999999);
                $tokenHash = hash('sha256', $codigo);
                $usuarioId = (int) $usuario['id'];

                $pdo->beginTransaction();

                $eliminar = $pdo->prepare(
                    'DELETE FROM recuperacion_password
                     WHERE usuario_id = ?'
                );

                $eliminar->execute([$usuarioId]);

                $insertar = $pdo->prepare(
                    'INSERT INTO recuperacion_password
                        (usuario_id, token, expira, usado)
                     VALUES
                        (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)'
                );

                $insertar->execute([
                    $usuarioId,
                    $tokenHash
                ]);

                $pdo->commit();

                $_SESSION['recuperacion_usuario_id'] = $usuarioId;
                $_SESSION['recuperacion_email'] = (string) $usuario['email'];

                unset(
                    $_SESSION['recuperacion_verificada'],
                    $_SESSION['recuperacion_token_id']
                );

                /*
                |--------------------------------------------------------------
                | SOLO PARA PRUEBAS EN LOCALHOST
                |--------------------------------------------------------------
                | En producción este código debe enviarse por correo y no
                | mostrarse en pantalla.
                */
                $codigoPrueba = $codigo;

                $mensaje = 'Código de recuperación generado correctamente.';
            }

        } catch (Throwable $e) {

            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error en olvide_password.php: ' .
                $e->getMessage()
            );

            $error = 'No fue posible generar el código de recuperación.';
        }
    }
}

$baseUrl = ruta_base();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Recuperar contraseña | Clínica Veterinaria El Campo</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e8f5e9, #f5f7fa);
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        .contenedor {
            width: 100%;
            max-width: 470px;
            margin: 20px;
            padding: 38px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .logo {
            text-align: center;
            font-size: 54px;
            margin-bottom: 10px;
        }

        h1 {
            margin: 0 0 10px;
            text-align: center;
            color: #166534;
            font-size: 28px;
        }

        .descripcion {
            margin: 0 0 28px;
            text-align: center;
            color: #6b7280;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input[type="email"] {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: 16px;
            outline: none;
        }

        input[type="email"]:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
        }

        button,
        .continuar {
            width: 100%;
            display: block;
            padding: 14px;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            margin-top: 20px;
            background: #15803d;
            color: #ffffff;
        }

        button:hover {
            background: #166534;
        }

        .mensaje {
            margin-bottom: 20px;
            padding: 12px;
            border: 1px solid #86efac;
            border-radius: 8px;
            background: #dcfce7;
            color: #166534;
            text-align: center;
        }

        .error {
            margin-bottom: 20px;
            padding: 12px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            text-align: center;
        }

        .codigo {
            margin-top: 18px;
            padding: 18px;
            border: 2px dashed #22c55e;
            border-radius: 10px;
            background: #f0fdf4;
            text-align: center;
        }

        .codigo span {
            display: block;
            margin: 8px 0;
            color: #166534;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 7px;
        }

        .continuar {
            margin-top: 18px;
            background: #2563eb;
            color: #ffffff;
        }

        .continuar:hover {
            background: #1d4ed8;
        }

        .volver {
            display: block;
            margin-top: 22px;
            text-align: center;
            text-decoration: none;
            color: #15803d;
            font-weight: 700;
        }

        .volver:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="contenedor">

    <div class="logo">🐾</div>

    <h1>Recuperar contraseña</h1>

    <p class="descripcion">
        Ingrese el correo electrónico asociado a su cuenta.
        Se generará un código de recuperación de 6 dígitos.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($codigoPrueba === ''): ?>

        <form method="POST" action="">
            <label for="email">Correo electrónico</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="ejemplo@correo.com"
                required
                autocomplete="email"
            >

            <button type="submit">
                🔐 Generar código
            </button>
        </form>

    <?php else: ?>

        <div class="codigo">
            Código de recuperación:
            <span>
                <?= htmlspecialchars($codigoPrueba, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <small>Este código caduca en 15 minutos.</small>
        </div>

        <a
            href="<?= htmlspecialchars($baseUrl . '/verificar_codigo.php', ENT_QUOTES, 'UTF-8') ?>"
            class="continuar"
        >
            Continuar →
        </a>

    <?php endif; ?>

    <a
        href="<?= htmlspecialchars($baseUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>"
        class="volver"
    >
        ← Volver a iniciar sesión
    </a>

</div>

</body>
</html>