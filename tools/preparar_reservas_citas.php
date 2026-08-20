<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este archivo se ejecuta desde CMD.\n");
}

require_once __DIR__ . '/../config/conexion.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit("ERROR: config/conexion.php debe crear la variable \$pdo.\n");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($db === '') {
        throw new RuntimeException('No hay una base de datos seleccionada.');
    }

    echo "Base de datos: {$db}\n";

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS reservas_citas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NULL,
            nombre_cliente VARCHAR(512) NULL,
            correo_cliente VARCHAR(512) NULL,
            nombre_mascota VARCHAR(120) NOT NULL DEFAULT '',
            especie VARCHAR(80) NOT NULL DEFAULT '',
            fecha DATE NULL,
            hora TIME NULL,
            motivo TEXT NULL,
            estado ENUM('Pendiente','Confirmada','Cancelada') NOT NULL DEFAULT 'Pendiente',
            fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservas_fecha (fecha),
            INDEX idx_reservas_estado (estado),
            INDEX idx_reservas_usuario (usuario_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $columnasNecesarias = [
        'usuario_id' =>
            "ALTER TABLE reservas_citas ADD COLUMN usuario_id INT UNSIGNED NULL AFTER id",
        'nombre_cliente' =>
            "ALTER TABLE reservas_citas ADD COLUMN nombre_cliente VARCHAR(512) NULL AFTER usuario_id",
        'correo_cliente' =>
            "ALTER TABLE reservas_citas ADD COLUMN correo_cliente VARCHAR(512) NULL AFTER nombre_cliente",
        'nombre_mascota' =>
            "ALTER TABLE reservas_citas ADD COLUMN nombre_mascota VARCHAR(120) NOT NULL DEFAULT '' AFTER correo_cliente",
        'especie' =>
            "ALTER TABLE reservas_citas ADD COLUMN especie VARCHAR(80) NOT NULL DEFAULT '' AFTER nombre_mascota",
        'fecha' =>
            "ALTER TABLE reservas_citas ADD COLUMN fecha DATE NULL AFTER especie",
        'hora' =>
            "ALTER TABLE reservas_citas ADD COLUMN hora TIME NULL AFTER fecha",
        'motivo' =>
            "ALTER TABLE reservas_citas ADD COLUMN motivo TEXT NULL AFTER hora",
        'estado' =>
            "ALTER TABLE reservas_citas ADD COLUMN estado ENUM('Pendiente','Confirmada','Cancelada') NOT NULL DEFAULT 'Pendiente' AFTER motivo",
        'fecha_registro' =>
            "ALTER TABLE reservas_citas ADD COLUMN fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER estado",
    ];

    $check = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :db
           AND TABLE_NAME = 'reservas_citas'
           AND COLUMN_NAME = :columna"
    );

    foreach ($columnasNecesarias as $columna => $sql) {
        $check->execute([
            ':db' => $db,
            ':columna' => $columna,
        ]);

        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec($sql);
            echo "Columna creada: {$columna}\n";
        } else {
            echo "Columna existente: {$columna}\n";
        }
    }

    // Asegurar longitud suficiente para valores cifrados.
    $pdo->exec(
        "ALTER TABLE reservas_citas
         MODIFY nombre_cliente VARCHAR(512) NULL,
         MODIFY correo_cliente VARCHAR(512) NULL,
         MODIFY nombre_mascota VARCHAR(120) NOT NULL DEFAULT '',
         MODIFY especie VARCHAR(80) NOT NULL DEFAULT '',
         MODIFY motivo TEXT NULL"
    );

    echo "\nTabla reservas_citas preparada correctamente.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
