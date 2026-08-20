<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Ejecutar desde CMD.\n");

require_once __DIR__ . '/../config/conexion.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit("ERROR: config/conexion.php debe crear $pdo.\n");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "Base de datos: {$db}\n";

    $required = ['id','nombres','apellidos','cedula','telefono','email','direccion','created_at'];
    $cols = $pdo->query("SHOW COLUMNS FROM clientes")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($required as $col) {
        if (!in_array($col, $cols, true)) {
            throw new RuntimeException("Falta la columna requerida: {$col}");
        }
    }

    $pdo->exec(
        "ALTER TABLE clientes
         MODIFY nombres VARCHAR(512) NOT NULL,
         MODIFY apellidos VARCHAR(512) NOT NULL,
         MODIFY cedula VARCHAR(512) NULL,
         MODIFY telefono VARCHAR(512) NOT NULL,
         MODIFY email VARCHAR(512) NULL,
         MODIFY direccion VARCHAR(512) NULL"
    );

    $checkCol = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=:db AND TABLE_NAME='clientes' AND COLUMN_NAME=:col"
    );

    foreach ([
        'cedula_hash' => "ALTER TABLE clientes ADD COLUMN cedula_hash CHAR(64) NULL AFTER cedula",
        'email_hash'  => "ALTER TABLE clientes ADD COLUMN email_hash CHAR(64) NULL AFTER email",
    ] as $col => $sql) {
        $checkCol->execute([':db'=>$db, ':col'=>$col]);
        if ((int)$checkCol->fetchColumn() === 0) {
            $pdo->exec($sql);
            echo "Columna creada: {$col}\n";
        } else {
            echo "Columna ya existente: {$col}\n";
        }
    }

    echo "Tabla clientes preparada correctamente.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
