<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

$error = '';

function ruta_base(): string
{
    $ruta = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));

    if ($ruta === '/' || $ruta === '.' || $ruta === '\\') {
        return '';
    }

    return rtrim($ruta, '/');
}

$baseUrl = ruta_base();

/*
|--------------------------------------------------------------------------
| SOLO PUEDE ENTRAR QUIEN YA VERIFICÓ EL CÓDIGO
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['recuperacion_verificada']) ||
    empty($_SESSION['recuperacion_usuario_id']) ||
    empty($_SESSION['recuperacion_token_id'])
) {
    header('Location: ' . $baseUrl . '/olvide_password.php');
    exit;
}

$usuarioId = (int) $_SESSION['recuperacion_usuario_id'];
$tokenId = (int) $_SESSION['recuperacion_token_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar_password'] ?? '');

    if ($password === '' || $confirmar === '') {

        $error = 'Debe completar los dos campos.';

    } elseif (strlen($password) < 8) {

        $error = 'La contraseña debe tener mínimo 8 caracteres.';

    } elseif ($password !== $confirmar) {

        $error = 'Las contraseñas no coinciden.';

    } elseif (!isset($pdo) || !($pdo instanceof PDO)) {

        $error = 'No fue posible conectar con la base de datos.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR Y VALIDAR LA SOLICITUD
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    usuario_id,
                    usado,
                    expira
                 FROM recuperacion_password
                 WHERE id = ?
                   AND usuario_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );

            $stmt->execute([
                $tokenId,
                $usuarioId
            ]);

            $recuperacion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$recuperacion) {

                throw new RuntimeException(
                    'La solicitud de recuperación no existe.'
                );
            }

            if ((int) $recuperacion['usado'] === 1) {

                throw new RuntimeException(
                    'Este código de recuperación ya fue utilizado.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDAR EXPIRACIÓN CON MYSQL
            |--------------------------------------------------------------------------
            */

            $stmtExpira = $pdo->prepare(
                'SELECT
                    CASE
                        WHEN expira < NOW() THEN 1
                        ELSE 0
                    END AS expirado
                 FROM recuperacion_password
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmtExpira->execute([$tokenId]);
            $estadoExpira = $stmtExpira->fetch(PDO::FETCH_ASSOC);

            if (
                !$estadoExpira ||
                (int) $estadoExpira['expirado'] === 1
            ) {

                throw new RuntimeException(
                    'El código de recuperación ha expirado.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFICAR QUE EL USUARIO SIGA EXISTIENDO
            |--------------------------------------------------------------------------
            */

            $stmtUsuarioExiste = $pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmtUsuarioExiste->execute([$usuarioId]);

            if (!$stmtUsuarioExiste->fetchColumn()) {

                throw new RuntimeException(
                    'El usuario asociado a esta recuperación ya no existe.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CREAR HASH DE LA NUEVA CONTRASEÑA
            |--------------------------------------------------------------------------
            */

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($passwordHash === false) {

                throw new RuntimeException(
                    'No fue posible procesar la nueva contraseña.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ACTUALIZAR CONTRASEÑA
            |--------------------------------------------------------------------------
            */

            $actualizar = $pdo->prepare(
                'UPDATE usuarios
                 SET password = ?
                 WHERE id = ?'
            );

            $actualizar->execute([
                $passwordHash,
                $usuarioId
            ]);

            /*
            |--------------------------------------------------------------------------
            | MARCAR TOKEN COMO UTILIZADO
            |--------------------------------------------------------------------------
            */

            $usarToken = $pdo->prepare(
                'UPDATE recuperacion_password
                 SET usado = 1
                 WHERE id = ?
                   AND usuario_id = ?'
            );

            $usarToken->execute([
                $tokenId,
                $usuarioId
            ]);

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | LIMPIAR SOLO VARIABLES DE RECUPERACIÓN
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['recuperacion_verificada'],
                $_SESSION['recuperacion_usuario_id'],
                $_SESSION['recuperacion_email'],
                $_SESSION['recuperacion_token_id']
            );

            $_SESSION['mensaje_login'] =
                'Contraseña restablecida correctamente. Ya puede iniciar sesión.';

            session_write_close();

            header(
                'Location: ' .
                $baseUrl .
                '/login.php'
            );

            exit;

        } catch (Throwable $e) {

            if (
                isset($pdo) &&
                $pdo instanceof PDO &&
                $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            error_log(
                'Error en nueva_contrasena.php: ' .
                $e->getMessage()
            );

            $error = $e->getMessage();
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
    <title>Nueva contraseña | Clínica Veterinaria El Campo</title>

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

        .icono {
            margin-bottom: 12px;
            text-align: center;
            font-size: 52px;
        }

        h1 {
            margin: 0 0 10px;
            text-align: center;
            color: #166534;
            font-size: 28px;
        }

        .descripcion {
            margin-bottom: 28px;
            text-align: center;
            color: #6b7280;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: 16px;
            outline: none;
        }

        input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
        }

        button {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #15803d;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #166534;
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

        .volver {
            display: block;
            margin-top: 22px;
            text-align: center;
            color: #15803d;
            font-weight: 700;
            text-decoration: none;
        }

        .volver:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="contenedor">

    <div class="icono">🔑</div>

    <h1>Crear nueva contraseña</h1>

    <p class="descripcion">
        El código fue verificado correctamente.
        Ingrese y confirme su nueva contraseña.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="password">Nueva contraseña</label>

        <input
            type="password"
            id="password"
            name="password"
            placeholder="Mínimo 8 caracteres"
            minlength="8"
            required
            autocomplete="new-password"
        >

        <label for="confirmar_password">
            Confirmar nueva contraseña
        </label>

        <input
            type="password"
            id="confirmar_password"
            name="confirmar_password"
            placeholder="Repita la nueva contraseña"
            minlength="8"
            required
            autocomplete="new-password"
        >

        <button type="submit">
            🔐 Cambiar contraseña
        </button>

    </form>

    <a
        href="<?= htmlspecialchars($baseUrl . '/login.php', ENT_QUOTES, 'UTF-8') ?>"
        class="volver"
    >
        ← Volver al inicio de sesión
    </a>

</div>

</body>
</html>