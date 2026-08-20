<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Ejecutar desde CMD.\n");

$target = __DIR__ . '/../config/secretos.local.php';

if (is_file($target)) {
    exit("Ya existe config/secretos.local.php. NO se reemplazó.\n");
}

$enc = base64_encode(random_bytes(32));
$idx = base64_encode(random_bytes(32));

$content = "<?php\nreturn [\n"
         . "    'encryption_key' => '{$enc}',\n"
         . "    'index_key' => '{$idx}',\n"
         . "];\n";

file_put_contents($target, $content, LOCK_EX);
echo "Claves creadas correctamente.\n";
