<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BOOTSTRAP DEL MÓDULO DE INVENTARIO
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

require_login();

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
            El archivo <code>config/conexion.php</code> debe crear
            una conexión PDO llamada <code>$pdo</code>.
        </div>'
    );
}

function inv_e(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function inv_url(string $ruta): string
{
    if (function_exists('url')) {
        return (string) url($ruta);
    }

    return '../' . ltrim($ruta, '/');
}

function inv_redirigir(string $ruta): never
{
    header('Location: ' . inv_url($ruta));
    exit;
}

function inv_flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash_inventario'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje,
    ];
}

function inv_tomar_flash(): array
{
    $flash = $_SESSION['flash_inventario'] ?? [];
    unset($_SESSION['flash_inventario']);

    return is_array($flash) ? $flash : [];
}

function inv_csrf_token(string $clave): string
{
    if (empty($_SESSION[$clave])) {
        $_SESSION[$clave] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[$clave];
}

function inv_csrf_valido(string $clave, string $token): bool
{
    $guardado = (string) ($_SESSION[$clave] ?? '');

    return $guardado !== ''
        && $token !== ''
        && hash_equals($guardado, $token);
}

function inv_regenerar_csrf(string $clave): void
{
    $_SESSION[$clave] = bin2hex(random_bytes(32));
}

function inv_usuario_id(): ?int
{
    if (function_exists('current_user')) {
        $usuario = current_user();

        if (is_array($usuario)) {
            foreach (
                ['id', 'usuario_id', 'id_usuario', 'user_id']
                as $clave
            ) {
                $id = (int) ($usuario[$clave] ?? 0);

                if ($id > 0) {
                    return $id;
                }
            }
        }
    }

    foreach (
        ['usuario_id', 'id_usuario', 'user_id']
        as $clave
    ) {
        $id = (int) ($_SESSION[$clave] ?? 0);

        if ($id > 0) {
            return $id;
        }
    }

    return null;
}

function inv_columnas(PDO $pdo): array
{
    static $columnas = null;

    if (is_array($columnas)) {
        return $columnas;
    }

    $columnas = [];

    $consulta = $pdo->query('SHOW COLUMNS FROM inventario');

    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $nombre = (string) ($fila['Field'] ?? '');

        if ($nombre !== '') {
            $columnas[$nombre] = $fila;
        }
    }

    return $columnas;
}

function inv_tiene_columna(
    PDO $pdo,
    string $columna
): bool {
    return array_key_exists(
        $columna,
        inv_columnas($pdo)
    );
}

function inv_tabla_existe(
    PDO $pdo,
    string $tabla
): bool {
    $consulta = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :tabla'
    );

    $consulta->execute([
        ':tabla' => $tabla,
    ]);

    return (int) $consulta->fetchColumn() > 0;
}

function inv_columna_precio_venta(PDO $pdo): ?string
{
    if (inv_tiene_columna($pdo, 'precio_venta')) {
        return 'precio_venta';
    }

    if (inv_tiene_columna($pdo, 'precio')) {
        return 'precio';
    }

    return null;
}

function inv_columna_fecha_registro(PDO $pdo): ?string
{
    foreach (
        ['fecha_registro', 'created_at']
        as $columna
    ) {
        if (inv_tiene_columna($pdo, $columna)) {
            return $columna;
        }
    }

    return null;
}

function inv_estado_segun_stock(
    int $stock,
    string $estadoSolicitado
): string {
    if ($estadoSolicitado === 'inactivo') {
        return 'inactivo';
    }

    return $stock <= 0
        ? 'agotado'
        : 'disponible';
}

function inv_decimal(
    mixed $valor,
    string $campo,
    array &$errores
): float {
    $texto = trim((string) $valor);

    if ($texto === '') {
        return 0.0;
    }

    $normalizado = str_replace(',', '.', $texto);

    if (!is_numeric($normalizado)) {
        $errores[] =
            $campo . ' debe ser un número válido.';

        return 0.0;
    }

    $numero = (float) $normalizado;

    if ($numero < 0 || $numero > 99999999.99) {
        $errores[] =
            $campo . ' está fuera del rango permitido.';

        return 0.0;
    }

    return $numero;
}

function inv_entero_no_negativo(
    mixed $valor,
    string $campo,
    array &$errores
): int {
    $texto = trim((string) $valor);

    if (
        $texto === ''
        || !preg_match('/^\d+$/', $texto)
    ) {
        $errores[] =
            $campo . ' debe ser un número entero mayor o igual a cero.';

        return 0;
    }

    $numero = (int) $texto;

    if ($numero < 0) {
        $errores[] =
            $campo . ' no puede ser negativo.';

        return 0;
    }

    return $numero;
}

function inv_fecha_visible(mixed $fecha): string
{
    $valor = trim((string) $fecha);

    if ($valor === '') {
        return 'No registrada';
    }

    $objeto = DateTime::createFromFormat(
        'Y-m-d',
        substr($valor, 0, 10)
    );

    return $objeto instanceof DateTime
        ? $objeto->format('d/m/Y')
        : $valor;
}

function inv_dinero(mixed $valor): string
{
    if ($valor === null || $valor === '') {
        return '$0,00';
    }

    return '$' . number_format(
        (float) $valor,
        2,
        ',',
        '.'
    );
}

function inv_registrar_movimiento(
    PDO $pdo,
    int $productoId,
    int $stockAnterior,
    int $stockNuevo,
    string $motivo
): void {
    if (!inv_tabla_existe($pdo, 'movimientos_inventario')) {
        return;
    }

    if ($stockAnterior === $stockNuevo) {
        return;
    }

    $tipo = $stockNuevo > $stockAnterior
        ? 'entrada'
        : 'salida';

    $cantidad = abs($stockNuevo - $stockAnterior);

    $columnas = [];

    $consultaColumnas = $pdo->query(
        'SHOW COLUMNS FROM movimientos_inventario'
    );

    foreach (
        $consultaColumnas->fetchAll(PDO::FETCH_ASSOC)
        as $fila
    ) {
        $nombre = (string) ($fila['Field'] ?? '');

        if ($nombre !== '') {
            $columnas[$nombre] = true;
        }
    }

    $campos = [
        'producto_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_nuevo',
        'motivo',
    ];

    $valores = [
        ':producto_id' => $productoId,
        ':tipo' => $tipo,
        ':cantidad' => $cantidad,
        ':stock_anterior' => $stockAnterior,
        ':stock_nuevo' => $stockNuevo,
        ':motivo' => $motivo,
    ];

    if (isset($columnas['usuario_id'])) {
        $campos[] = 'usuario_id';
        $valores[':usuario_id'] = inv_usuario_id();
    }

    foreach ($campos as $campo) {
        if (!isset($columnas[$campo])) {
            return;
        }
    }

    $marcadores = array_map(
        static fn (string $campo): string =>
            ':' . $campo,
        $campos
    );

    $sql =
        'INSERT INTO movimientos_inventario (' .
        implode(', ', $campos) .
        ') VALUES (' .
        implode(', ', $marcadores) .
        ')';

    $insertar = $pdo->prepare($sql);
    $insertar->execute($valores);
}