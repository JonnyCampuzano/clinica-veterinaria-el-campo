<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BOOTSTRAP DEL MÓDULO DE HISTORIA CLÍNICA
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| PROTECCIÓN REAL DEL MÓDULO
|--------------------------------------------------------------------------
| Todo archivo de Historia Clínica que cargue este _bootstrap.php
| requiere como mínimo permiso para consultar historias clínicas.
|--------------------------------------------------------------------------
*/

require_permission('historias.ver');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            max-width:800px;
            margin:40px auto;
            padding:20px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fff1f2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error de conexión:</strong><br><br>
            El archivo <code>config/conexion.php</code> debe crear una
            conexión PDO llamada <code>$pdo</code>.
        </div>'
    );
}

function hc_e(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function hc_url(string $ruta): string
{
    if (function_exists('url')) {
        return (string) url($ruta);
    }

    $ruta = ltrim($ruta, '/');

    return '../' . $ruta;
}

function hc_redirigir(string $ruta): never
{
    header('Location: ' . hc_url($ruta));
    exit;
}

function hc_usuario_id_actual(): int
{
    if (function_exists('current_user')) {
        $usuario = current_user();

        if (is_array($usuario)) {
            foreach (
                ['id', 'usuario_id', 'id_usuario', 'user_id']
                as $clave
            ) {
                $valor = (int) ($usuario[$clave] ?? 0);

                if ($valor > 0) {
                    return $valor;
                }
            }
        }
    }

    foreach (
        ['usuario_id', 'id_usuario', 'user_id']
        as $clave
    ) {
        $valor = (int) ($_SESSION[$clave] ?? 0);

        if ($valor > 0) {
            return $valor;
        }
    }

    return 0;
}

function hc_csrf_token(string $clave): string
{
    if (empty($_SESSION[$clave])) {
        $_SESSION[$clave] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[$clave];
}

function hc_csrf_valido(string $clave, string $token): bool
{
    $tokenSesion = (string) ($_SESSION[$clave] ?? '');

    return $tokenSesion !== ''
        && $token !== ''
        && hash_equals($tokenSesion, $token);
}

function hc_regenerar_csrf(string $clave): void
{
    $_SESSION[$clave] = bin2hex(random_bytes(32));
}

function hc_flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash_historia_clinica'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];
}

function hc_tomar_flash(): array
{
    $flash = $_SESSION['flash_historia_clinica'] ?? [];

    unset($_SESSION['flash_historia_clinica']);

    return is_array($flash) ? $flash : [];
}

function hc_fecha_visible(mixed $fecha): string
{
    $valor = trim((string) $fecha);

    if ($valor === '') {
        return 'No registrada';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $valor);

    return $objeto instanceof DateTime
        ? $objeto->format('d/m/Y')
        : $valor;
}

function hc_numero_visible(
    mixed $valor,
    string $sufijo = '',
    int $decimales = 2
): string {
    if ($valor === null || $valor === '') {
        return 'No registrado';
    }

    return number_format(
        (float) $valor,
        $decimales,
        ',',
        '.'
    ) . $sufijo;
}

function hc_decimal_o_null(
    string $valor,
    float $minimo,
    float $maximo
): ?float {
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $normalizado = str_replace(',', '.', $valor);

    if (!is_numeric($normalizado)) {
        throw new InvalidArgumentException(
            'El valor numérico ingresado no es válido.'
        );
    }

    $numero = (float) $normalizado;

    if ($numero < $minimo || $numero > $maximo) {
        throw new InvalidArgumentException(
            'El valor numérico está fuera del rango permitido.'
        );
    }

    return $numero;
}