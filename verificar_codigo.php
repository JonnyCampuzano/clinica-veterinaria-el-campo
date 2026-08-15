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

if (
    !isset($_SESSION['recuperacion_usuario_id']) ||
    !isset($_SESSION['recuperacion_email'])
) {
    header('Location: ' . $baseUrl . '/olvide_password.php');
    exit;
}

$usuarioId = (int) $_SESSION['recuperacion_usuario_id'];
$email = (string) $_SESSION['recuperacion_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = trim((string) ($_POST['codigo'] ?? ''));

    if ($codigo === '') {

        $error = 'Ingrese el código de recuperación.';

    } elseif (!preg_match('/^[0-9]{6}$/', $codigo)) {

        $error = 'El código debe contener exactamente 6 números.';

    } elseif (!isset($pdo) || !($pdo instanceof PDO)) {

        $error = 'No fue posible conectar con la base de datos.';

    } else {

        try {

            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    usuario_id,
                    token,
                    expira,
                    usado,
                    CASE
                        WHEN expira < NOW() THEN 1
                        ELSE 0
                    END AS expirado
                 FROM recuperacion_password
                 WHERE usuario_id = ?
                 ORDER BY id DESC
                 LIMIT 1'
            );

            $stmt->execute([$usuarioId]);

            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {

                $error = 'No existe un código de recuperación activo.';

            } elseif ((int) $registro['usado'] === 1) {

                $error = 'Este código ya fue utilizado. Solicite uno nuevo.';

            } elseif ((int) $registro['expirado'] === 1) {

                $error = 'El código ha expirado. Solicite uno nuevo.';

            } else {

                $codigoHash = hash('sha256', $codigo);

                if (!hash_equals(
                    (string) $registro['token'],
                    $codigoHash
                )) {

                    $error = 'El código ingresado es incorrecto.';

                } else {

                    $_SESSION['recuperacion_verificada'] = true;
                    $_SESSION['recuperacion_token_id'] = (int) $registro['id'];

                    /*
                    |----------------------------------------------------------
                    | GUARDAR SESIÓN ANTES DE REDIRIGIR
                    |----------------------------------------------------------
                    */
                    session_write_close();

                    header(
                        'Location: ' .
                        $baseUrl .
                        '/nueva_contrasena.php'
                    );

                    exit;
                }
            }

        } catch (Throwable $e) {

            error_log(
                'Error en verificar_codigo.php: ' .
                $e->getMessage()
            );

            $error = 'Ocurrió un error al verificar el código.';
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
    <title>Verificar código | Clínica Veterinaria El Campo</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #e8f5e9, #f5f7fa);
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

        .contenedor {
            width: 100%;
            max-width: 455px;
            margin: 20px;
            padding: 36px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .icono {
            margin-bottom: 12px;
            text-align: center;
            font-size: 50px;
        }

        h1 {
            margin: 0 0 10px;
            text-align: center;
            font-size: 27px;
            color: #166534;
        }

        .descripcion {
            margin-bottom: 25px;
            text-align: center;
            color: #6b7280;
            line-height: 1.5;
        }

        .correo {
            margin-bottom: 25px;
            padding: 10px;
            border-radius: 8px;
            background: #f0fdf4;
            color: #166534;
            font-weight: 700;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 15px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            text-align: center;
            font-size: 25px;
            letter-spacing: 8px;
            font-weight: 700;
            outline: none;
        }

        input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
        }

        button {
            width: 100%;
            margin-top: 20px;
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

    <div class="icono">🔐</div>

    <h1>Verificar código</h1>

    <p class="descripcion">
        Ingrese el código de 6 dígitos generado
        para recuperar su contraseña.
    </p>

    <div class="correo">
        <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="codigo">Código de recuperación</label>

        <input
            type="text"
            id="codigo"
            name="codigo"
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]{6}"
            placeholder="000000"
            autocomplete="one-time-code"
            required
            autofocus
        >

        <button type="submit">
            ✅ Verificar código
        </button>

    </form>

    <a
        href="<?= htmlspecialchars($baseUrl . '/olvide_password.php', ENT_QUOTES, 'UTF-8') ?>"
        class="volver"
    >
        ← Solicitar otro código
    </a>

</div>

</body>
</html>