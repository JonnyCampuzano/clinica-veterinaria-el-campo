<?php
declare(strict_types=1);

/**
 * Clínica Veterinaria El Campo
 * Cifrado de datos personales de clientes con AES-256-GCM.
 */

const CLIENT_CRYPTO_CIPHER = 'aes-256-gcm';
const CLIENT_CRYPTO_IV_LENGTH = 12;
const CLIENT_CRYPTO_TAG_LENGTH = 16;
const CLIENT_CRYPTO_AAD = 'clinica_veterinaria_el_campo|clientes|v1';

function client_crypto_config(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $file = __DIR__ . '/secretos.local.php';

    if (!is_file($file)) {
        throw new RuntimeException(
            'Falta config/secretos.local.php. Ejecuta: php tools/generar_claves.php'
        );
    }

    $config = require $file;

    if (!is_array($config)) {
        throw new RuntimeException('El archivo config/secretos.local.php no es válido.');
    }

    return $config;
}

function client_crypto_key(string $name): string
{
    $config = client_crypto_config();
    $encoded = $config[$name] ?? '';

    if (!is_string($encoded) || $encoded === '') {
        throw new RuntimeException("Falta la clave {$name}.");
    }

    $key = base64_decode($encoded, true);

    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException("La clave {$name} no es válida.");
    }

    return $key;
}

function client_is_encrypted(?string $value): bool
{
    return is_string($value) && str_starts_with($value, 'enc:v1:');
}

function encrypt_personal(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    if (client_is_encrypted($value)) {
        return $value;
    }

    $iv = random_bytes(CLIENT_CRYPTO_IV_LENGTH);
    $tag = '';

    $ciphertext = openssl_encrypt(
        $value,
        CLIENT_CRYPTO_CIPHER,
        client_crypto_key('encryption_key'),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        CLIENT_CRYPTO_AAD,
        CLIENT_CRYPTO_TAG_LENGTH
    );

    if ($ciphertext === false || strlen($tag) !== CLIENT_CRYPTO_TAG_LENGTH) {
        throw new RuntimeException('No se pudo cifrar el dato personal.');
    }

    return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_personal(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    // Compatibilidad temporal con registros antiguos todavía en texto plano.
    if (!client_is_encrypted($value)) {
        return $value;
    }

    $payload = base64_decode(substr($value, 7), true);

    if (
        $payload === false ||
        strlen($payload) <= CLIENT_CRYPTO_IV_LENGTH + CLIENT_CRYPTO_TAG_LENGTH
    ) {
        throw new RuntimeException('El dato cifrado está dañado.');
    }

    $iv = substr($payload, 0, CLIENT_CRYPTO_IV_LENGTH);
    $tag = substr($payload, CLIENT_CRYPTO_IV_LENGTH, CLIENT_CRYPTO_TAG_LENGTH);
    $ciphertext = substr(
        $payload,
        CLIENT_CRYPTO_IV_LENGTH + CLIENT_CRYPTO_TAG_LENGTH
    );

    $plain = openssl_decrypt(
        $ciphertext,
        CLIENT_CRYPTO_CIPHER,
        client_crypto_key('encryption_key'),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        CLIENT_CRYPTO_AAD
    );

    if ($plain === false) {
        throw new RuntimeException(
            'No se pudo descifrar el dato. Verifica que no hayas cambiado la clave.'
        );
    }

    return $plain;
}

function normalize_email(string $email): string
{
    $email = trim($email);

    return function_exists('mb_strtolower')
        ? mb_strtolower($email, 'UTF-8')
        : strtolower($email);
}

function normalize_cedula(string $cedula): string
{
    return preg_replace('/\D+/', '', trim($cedula)) ?? '';
}

function client_blind_index(string $value, string $context): string
{
    return hash_hmac(
        'sha256',
        $context . "\0" . $value,
        client_crypto_key('index_key')
    );
}

function email_index(string $email): string
{
    return client_blind_index(normalize_email($email), 'correo');
}

function cedula_index(string $cedula): string
{
    return client_blind_index(normalize_cedula($cedula), 'cedula');
}

function e_client(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
