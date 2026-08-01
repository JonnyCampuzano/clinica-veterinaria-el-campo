<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $host = trim($_POST['host'] ?? 'localhost');
    $port = (int) ($_POST['port'] ?? 3306);
    $username = trim($_POST['username'] ?? 'root');
    $password = $_POST['password'] ?? '';

    if ($host === '' || $username === '' || $port < 1) {
        $error = 'Completa correctamente los datos de conexión.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);

        $mysqli = @new mysqli($host, $username, $password, '', $port);

        if ($mysqli->connect_errno) {
            $error = 'No se pudo conectar con MySQL: ' . $mysqli->connect_error;
        } else {
            $mysqli->set_charset('utf8mb4');
            $sqlFile = __DIR__ . '/database/clinica_veterinaria.sql';
            $sql = file_get_contents($sqlFile);

            if ($sql === false) {
                $error = 'No se encontró el archivo de la base de datos.';
            } elseif (!$mysqli->multi_query($sql)) {
                $error = 'No se pudo instalar la base de datos: ' . $mysqli->error;
            } else {
                do {
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                } while ($mysqli->more_results() && $mysqli->next_result());

                if ($mysqli->errno) {
                    $error = 'La instalación terminó con un error: ' . $mysqli->error;
                } else {
                    $localConfig = "<?php\nreturn " . var_export([
                        'host' => $host,
                        'port' => (string) $port,
                        'database' => 'clinica_veterinaria',
                        'username' => $username,
                        'password' => $password
                    ], true) . ";\n";

                    $saved = file_put_contents(__DIR__ . '/config/database.local.php', $localConfig);

                    if ($saved === false) {
                        $error = 'La base se creó, pero no se pudo guardar la configuración local.';
                    } else {
                        $success = 'Instalación completada. Ya puedes iniciar sesión.';
                    }
                }
            }

            $mysqli->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="install-page">
    <div class="install-box">
        <div class="login-logo">
            <span>🛠️</span>
            <h1>Instalador del sistema</h1>
            <p>Apache y MySQL deben estar encendidos en XAMPP.</p>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <a class="btn btn-primary" style="width:100%" href="<?= e(url('login.php')) ?>">Ir al inicio de sesión</a>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="login-form" method="post">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="host">Servidor MySQL</label>
                    <input id="host" name="host" value="<?= e($_POST['host'] ?? 'localhost') ?>" required>
                </div>

                <div class="form-group">
                    <label for="port">Puerto</label>
                    <input id="port" name="port" type="number" value="<?= e($_POST['port'] ?? '3306') ?>" required>
                </div>

                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input id="username" name="username" value="<?= e($_POST['username'] ?? 'root') ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña MySQL</label>
                    <input id="password" name="password" type="password" placeholder="Déjala vacía si XAMPP no tiene contraseña">
                </div>

                <button class="btn btn-primary" type="submit">Crear base de datos</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
