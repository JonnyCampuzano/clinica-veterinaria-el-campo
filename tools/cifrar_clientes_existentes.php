<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Ejecutar desde CMD.\n");

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/crypto.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $pdo->query(
    "SELECT id,nombres,apellidos,cedula,telefono,email,direccion FROM clientes"
)->fetchAll(PDO::FETCH_ASSOC);

$update = $pdo->prepare(
    "UPDATE clientes SET
        nombres=:nombres,
        apellidos=:apellidos,
        cedula=:cedula,
        cedula_hash=:cedula_hash,
        telefono=:telefono,
        email=:email,
        email_hash=:email_hash,
        direccion=:direccion
     WHERE id=:id"
);

$procesados = 0;
$omitidos = 0;
$pdo->beginTransaction();

try {
    foreach ($rows as $r) {
        if (client_is_encrypted($r['nombres'] ?? null)) {
            $omitidos++;
            continue;
        }

        $cedula = normalize_cedula((string)($r['cedula'] ?? ''));
        $email = normalize_email((string)($r['email'] ?? ''));

        $update->execute([
            ':nombres' => encrypt_personal((string)$r['nombres']),
            ':apellidos' => encrypt_personal((string)$r['apellidos']),
            ':cedula' => encrypt_personal($cedula),
            ':cedula_hash' => $cedula !== '' ? cedula_index($cedula) : null,
            ':telefono' => encrypt_personal((string)$r['telefono']),
            ':email' => encrypt_personal($email),
            ':email_hash' => $email !== '' ? email_index($email) : null,
            ':direccion' => encrypt_personal((string)($r['direccion'] ?? '')),
            ':id' => (int)$r['id'],
        ]);

        $procesados++;
    }

    $pdo->commit();
    echo "Migración terminada.\n";
    echo "Clientes cifrados: {$procesados}\n";
    echo "Clientes ya cifrados omitidos: {$omitidos}\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
