<?php
declare(strict_types=1);

$raiz = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

require_login();

if (function_exists('can') && !can('reportes.ver')) {
    $rol = function_exists('current_role')
        ? strtolower(trim((string) current_role()))
        : strtolower(trim((string) ($_SESSION['rol'] ?? '')));

    if (!in_array($rol, ['administrador', 'admin'], true)) {
        header('Location: ' . (function_exists('url')
            ? url('panel.php?error=sin_permiso')
            : '../panel.php?error=sin_permiso'));
        exit;
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No se encontró una conexión PDO válida. Revisa config/conexion.php.');
}

function rep_e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function rep_url(string $ruta): string
{
    return function_exists('url')
        ? (string) url($ruta)
        : '../' . ltrim($ruta, '/');
}

function rep_tabla_existe(PDO $pdo, string $tabla): bool
{
    $q = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla'
    );
    $q->execute([':tabla' => $tabla]);
    return (int) $q->fetchColumn() > 0;
}

function rep_columna_existe(PDO $pdo, string $tabla, string $columna): bool
{
    $q = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla
           AND COLUMN_NAME = :columna'
    );
    $q->execute([
        ':tabla' => $tabla,
        ':columna' => $columna,
    ]);
    return (int) $q->fetchColumn() > 0;
}

function rep_tabla_historia(PDO $pdo): ?string
{
    if (rep_tabla_existe($pdo, 'historias_clinicas')) {
        return 'historias_clinicas';
    }

    if (rep_tabla_existe($pdo, 'consultas')) {
        return 'consultas';
    }

    return null;
}

function rep_fecha(mixed $fecha): string
{
    $v = trim((string) $fecha);

    if ($v === '') {
        return '—';
    }

    $d = DateTime::createFromFormat('Y-m-d', $v);

    return $d instanceof DateTime
        ? $d->format('d/m/Y')
        : $v;
}

function rep_hora(mixed $hora): string
{
    $v = trim((string) $hora);
    return $v === '' ? '—' : substr($v, 0, 5);
}

function rep_dinero(mixed $v): string
{
    return '$' . number_format((float) ($v ?? 0), 2, ',', '.');
}

function rep_rango(): array
{
    $desde = trim((string) ($_GET['desde'] ?? date('Y-m-01')));
    $hasta = trim((string) ($_GET['hasta'] ?? date('Y-m-d')));

    $validar = static function (string $f): ?string {
        $d = DateTime::createFromFormat('Y-m-d', $f);

        return $d instanceof DateTime && $d->format('Y-m-d') === $f
            ? $f
            : null;
    };

    $desde = $validar($desde) ?? date('Y-m-01');
    $hasta = $validar($hasta) ?? date('Y-m-d');

    if ($desde > $hasta) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    return [$desde, $hasta];
}

function rep_usuario(): string
{
    if (function_exists('current_user')) {
        $u = current_user();

        if (is_array($u)) {
            $n = trim((string) ($u['nombre'] ?? $u['usuario'] ?? ''));

            if ($n !== '') {
                return $n;
            }
        }
    }

    return trim((string) (
        $_SESSION['nombre']
        ?? $_SESSION['usuario']
        ?? 'Usuario'
    ));
}

function rep_csv(string $archivo, array $headers, array $rows): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');

    $out = fopen('php://output', 'wb');

    if ($out === false) {
        exit('No se pudo generar el archivo CSV.');
    }

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}
