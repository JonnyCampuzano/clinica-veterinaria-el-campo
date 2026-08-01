<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'clinica_veterinaria',
    'username' => 'root',
    'password' => ''
];

$localConfig = __DIR__ . '/database.local.php';

if (file_exists($localConfig)) {
    $custom = require $localConfig;

    if (is_array($custom)) {
        $config = array_merge($config, $custom);
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['host'],
    $config['port'],
    $config['database']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    $installer = url('instalar.php');

    exit(
        '<h2>No se pudo conectar con MySQL</h2>' .
        '<p>Primero ejecuta el instalador del sistema.</p>' .
        '<p><a href="' . e($installer) . '">Abrir instalador</a></p>' .
        '<pre>' . e($exception->getMessage()) . '</pre>'
    );
}
