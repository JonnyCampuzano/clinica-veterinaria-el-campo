<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Ejecutar desde CMD.\n");

require_once __DIR__ . '/../config/conexion.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit("ERROR: no existe \$pdo.\n");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    echo "Base de datos: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

    $tabla = $pdo->query(
        "SHOW TABLES LIKE 'reservas_citas'"
    )->fetchColumn();

    if ($tabla === false) {
        echo "reservas_citas: NO EXISTE\n";
        exit;
    }

    echo "reservas_citas: EXISTE\n\n";
    echo "COLUMNAS:\n";

    foreach ($pdo->query('SHOW COLUMNS FROM reservas_citas')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo "- {$c['Field']} ({$c['Type']})\n";
    }

    echo "\nREGISTROS: ";
    echo (int) $pdo->query('SELECT COUNT(*) FROM reservas_citas')->fetchColumn();
    echo "\n";

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
