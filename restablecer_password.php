<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/conexion.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    header('Location: olvide_password.php');
    exit;
}

$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
    SELECT
        rp.id,
        rp.usuario_id,
        rp.expira,
        rp.usado,
        u.correo
    FROM recuperacion_password rp
    INNER JOIN usuarios u
        ON u.id = rp.usuario_id
    WHERE rp.token = ?
    LIMIT 1
");

$stmt->execute([$tokenHash]);

$recuperacion = $stmt->fetch(PDO::FETCH_ASSOC);

$tokenValido = true;

if (!$recuperacion) {

    $tokenValido = false;

} elseif ((int)$recuperacion['usado'] === 1) {

    $tokenValido = false;

} elseif (strtotime($recuperacion['expira']) < time()) {

    $tokenValido = false;
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

    <title>Nueva contraseña</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f8fc;
        }

        .contenedor {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .tarjeta {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 20px;
            padding: 45px;
            border: 1px solid #d7e2f1;
            box-shadow: 0 12px 35px rgba(0,0,0,.06);
        }

        h1 {
            text-align: center;
        }

        .descripcion {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
        }

        label {
            display: block;
            font-weight: bold;
            margin: 18px 0 8px;
        }

        input {
            width: 100%;
            padding: 16px;
            border: 1px solid #bfd0eb;
            border-radius: 10px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 16px;
            border: 0;
            border-radius: 10px;
            background: #2f855a;
            color: white;
            font-weight: bold;
            font-size: 17px;
            cursor: pointer;
            margin-top: 25px;
        }

        button:hover {
            background: #276749;
        }

        .error {
            padding: 18px;
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            text-align: center;
        }

        .volver {
            text-align: center;
            margin-top: 25px;
        }

        a {
            color: #2f855a;
            text-decoration: none;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <div class="tarjeta">

        <?php if (!$tokenValido): ?>

            <div class="error">

                ⚠️ El enlace de recuperación es inválido,
                ya fue utilizado o ha expirado.

            </div>

            <div class="volver">

                <a href="olvide_password.php">
                    Solicitar otro enlace
                </a>

            </div>

        <?php else: ?>

            <h1>🔐 Nueva contraseña</h1>

            <p class="descripcion">
                Crea una nueva contraseña para tu cuenta.
            </p>

            <form
                action="procesar_restablecer.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars($token) ?>"
                >


                <label for="password">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Nueva contraseña"
                    minlength="8"
                    required
                >


                <label for="confirmar_password">
                    Confirmar contraseña
                </label>

                <input
                    type="password"
                    id="confirmar_password"
                    name="confirmar_password"
                    placeholder="Repita la contraseña"
                    minlength="8"
                    required
                >


                <button type="submit">
                    Cambiar contraseña
                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

</body>
</html>