<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   CARGAR ARCHIVOS DEL SISTEMA
===================================================== */

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/config/crypto.php';
require_once $raiz . '/includes/auth.php';

/* =====================================================
   SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (function_exists('require_login')) {
    require_login();
}

/* =====================================================
   VALIDAR PDO
===================================================== */

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
            config/conexion.php debe crear una conexión PDO llamada $pdo.
        </div>'
    );
}

$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function repCliE(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function repCliUrl(string $ruta = ''): string
{
    if (function_exists('url')) {
        return (string) url($ruta);
    }

    $base = '/clinica_veterinaria_el_campo';
    $ruta = ltrim($ruta, '/');

    return $ruta === ''
        ? $base . '/'
        : $base . '/' . $ruta;
}

function repCliTablaExiste(
    PDO $pdo,
    string $tabla
): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :tabla'
    );

    $stmt->execute([
        ':tabla' => $tabla,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function repCliColumnaExiste(
    PDO $pdo,
    string $tabla,
    string $columna
): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :tabla
           AND column_name = :columna'
    );

    $stmt->execute([
        ':tabla' => $tabla,
        ':columna' => $columna,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function repCliVisible(
    mixed $valor,
    string $alternativa = 'No registrado'
): string {
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

function repCliContiene(
    string $texto,
    string $buscar
): bool {
    if ($buscar === '') {
        return true;
    }

    return function_exists('mb_stripos')
        ? mb_stripos(
            $texto,
            $buscar,
            0,
            'UTF-8'
        ) !== false
        : stripos($texto, $buscar) !== false;
}

function repCliUsuario(): string
{
    if (function_exists('current_user')) {
        $usuario = current_user();

        if (is_array($usuario)) {
            foreach (
                ['nombre', 'nombres', 'usuario', 'name']
                as $clave
            ) {
                $valor = trim(
                    (string) ($usuario[$clave] ?? '')
                );

                if ($valor !== '') {
                    return $valor;
                }
            }
        }
    }

    foreach (
        ['nombre', 'usuario', 'username']
        as $clave
    ) {
        $valor = trim(
            (string) ($_SESSION[$clave] ?? '')
        );

        if ($valor !== '') {
            return $valor;
        }
    }

    return 'Usuario del sistema';
}

/* =====================================================
   VARIABLES
===================================================== */

$buscar = trim(
    (string) ($_GET['buscar'] ?? '')
);

$clientes = [];
$error = '';

/* =====================================================
   CONSULTAR CLIENTES
===================================================== */

try {
    if (!repCliTablaExiste($pdo, 'clientes')) {
        throw new RuntimeException(
            'No se encontró la tabla clientes.'
        );
    }

    $campoCorreo = repCliColumnaExiste(
        $pdo,
        'clientes',
        'email'
    )
        ? 'email'
        : (
            repCliColumnaExiste(
                $pdo,
                'clientes',
                'correo'
            )
                ? 'correo'
                : null
        );

    $selectCorreo = $campoCorreo !== null
        ? "c.`{$campoCorreo}` AS correo"
        : "'' AS correo";

    $selectDireccion = repCliColumnaExiste(
        $pdo,
        'clientes',
        'direccion'
    )
        ? 'c.direccion AS direccion'
        : "'' AS direccion";

    $sql =
        "SELECT
            c.id,
            c.nombres,
            c.apellidos,
            c.cedula,
            c.telefono,
            {$selectCorreo},
            {$selectDireccion},
            (
                SELECT COUNT(*)
                FROM mascotas m
                WHERE m.cliente_id = c.id
            ) AS mascotas
         FROM clientes c
         ORDER BY c.id DESC";

    $filas = $pdo
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {
        try {
            $cliente = [
                'id' => (int) (
                    $fila['id'] ?? 0
                ),

                'nombres' => decrypt_personal(
                    $fila['nombres'] ?? null
                ),

                'apellidos' => decrypt_personal(
                    $fila['apellidos'] ?? null
                ),

                'cedula' => decrypt_personal(
                    $fila['cedula'] ?? null
                ),

                'telefono' => decrypt_personal(
                    $fila['telefono'] ?? null
                ),

                'correo' => decrypt_personal(
                    $fila['correo'] ?? null
                ),

                'direccion' => decrypt_personal(
                    $fila['direccion'] ?? null
                ),

                'mascotas' => (int) (
                    $fila['mascotas'] ?? 0
                ),
            ];
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Reporte clientes: error al descifrar ID ' .
                (int) ($fila['id'] ?? 0) .
                ': ' .
                $errorDescifrado->getMessage()
            );

            $cliente = [
                'id' => (int) (
                    $fila['id'] ?? 0
                ),
                'nombres' => 'Dato protegido',
                'apellidos' => '',
                'cedula' => '',
                'telefono' => '',
                'correo' => '',
                'direccion' => '',
                'mascotas' => (int) (
                    $fila['mascotas'] ?? 0
                ),
            ];
        }

        if ($buscar !== '') {
            $nombreCompleto = trim(
                $cliente['nombres'] .
                ' ' .
                $cliente['apellidos']
            );

            $campos = [
                $nombreCompleto,
                $cliente['nombres'],
                $cliente['apellidos'],
                $cliente['cedula'],
                $cliente['telefono'],
                $cliente['correo'],
                $cliente['direccion'],
            ];

            $coincide = false;

            foreach ($campos as $campo) {
                if (
                    repCliContiene(
                        (string) $campo,
                        $buscar
                    )
                ) {
                    $coincide = true;
                    break;
                }
            }

            if (!$coincide) {
                continue;
            }
        }

        $clientes[] = $cliente;
    }

} catch (Throwable $e) {
    error_log(
        'Reporte clientes: ' .
        $e->getMessage()
    );

    $error =
        'No se pudieron cargar los clientes. ' .
        'Revisa la tabla clientes, la conexión y la clave de cifrado.';
}

/* =====================================================
   EXPORTAR CSV
===================================================== */

if (
    isset($_GET['exportar']) &&
    $_GET['exportar'] === 'csv'
) {
    $archivo =
        'reporte_clientes_' .
        date('Y-m-d_H-i-s') .
        '.csv';

    header(
        'Content-Type: text/csv; charset=UTF-8'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $archivo .
        '"'
    );

    $salida = fopen(
        'php://output',
        'wb'
    );

    if ($salida === false) {
        exit;
    }

    fwrite(
        $salida,
        "\xEF\xBB\xBF"
    );

    fputcsv(
        $salida,
        [
            'ID',
            'Cliente',
            'Cédula',
            'Teléfono',
            'Correo',
            'Dirección',
            'Mascotas',
        ],
        ';'
    );

    foreach ($clientes as $cliente) {
        fputcsv(
            $salida,
            [
                $cliente['id'],

                trim(
                    $cliente['nombres'] .
                    ' ' .
                    $cliente['apellidos']
                ),

                $cliente['cedula'],
                $cliente['telefono'],
                $cliente['correo'],
                $cliente['direccion'],
                $cliente['mascotas'],
            ],
            ';'
        );
    }

    fclose($salida);
    exit;
}

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Reporte de clientes';
$activePage = 'reportes';

$headerFile =
    $raiz . '/includes/header.php';

$footerFile =
    $raiz . '/includes/footer.php';

if (is_file($headerFile)) {
    require_once $headerFile;
}
?>

<style>
.rep-clientes-page,
.rep-clientes-page * {
    box-sizing: border-box;
}

.rep-clientes-page {
    width: 100%;
    margin: 0 auto;
    padding: 10px 0 42px;
}

.rep-clientes-panel {
    overflow: hidden;
    border: 1px solid #dbe5f0;
    border-radius: 18px;
    background: #fff;
    box-shadow:
        0 15px 40px
        rgba(15, 35, 65, .08);
}

.rep-clientes-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 24px 28px;
    border-bottom: 1px solid #e2e8f0;
    background:
        linear-gradient(
            135deg,
            #fff,
            #f8fbff
        );
}

.rep-clientes-header h1 {
    margin: 0 0 7px;
    color: #08264f;
    font-size: 26px;
}

.rep-clientes-header p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.rep-clientes-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.rep-clientes-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 10px 15px;
    border: 0;
    border-radius: 10px;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}

.rep-btn-back {
    background: #eef2f7;
    color: #334155;
}

.rep-btn-print {
    background: #0f2a4f;
    color: #fff;
}

.rep-btn-csv {
    background: #059669;
    color: #fff;
}

.rep-btn-search {
    background: #2563eb;
    color: #fff;
}

.rep-btn-clear {
    background: #eef2f7;
    color: #475569;
}

.rep-clientes-toolbar {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 18px 26px;
    border-bottom: 1px solid #e8eef5;
    background: #fbfdff;
}

.rep-clientes-search-box {
    flex: 1;
    min-width: 0;
}

.rep-clientes-search-box label {
    display: block;
    margin-bottom: 7px;
    color: #475569;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
}

.rep-clientes-search {
    display: flex;
    gap: 10px;
}

.rep-clientes-search input {
    width: 100%;
    min-height: 44px;
    padding: 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    color: #0f172a;
    font: inherit;
    outline: none;
}

.rep-clientes-search input:focus {
    border-color: #2563eb;
    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, .12);
}

.rep-clientes-count {
    min-width: 78px;
    padding-bottom: 11px;
    color: #475569;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}

.rep-clientes-alert {
    margin: 20px 26px 0;
    padding: 13px 16px;
    border: 1px solid #fecaca;
    border-radius: 11px;
    background: #fff1f2;
    color: #b91c1c;
    font-size: 13px;
    font-weight: 700;
}

.rep-clientes-content {
    padding: 24px 26px 12px;
}

.rep-clientes-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.rep-clientes-table {
    width: 100%;
    min-width: 1220px;
    border-collapse: collapse;
}

.rep-clientes-table th,
.rep-clientes-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #e8eef5;
    text-align: left;
    vertical-align: middle;
    font-size: 12.5px;
}

.rep-clientes-table th {
    background: #f8fafc;
    color: #334155;
    font-size: 11.5px;
    font-weight: 900;
    text-transform: uppercase;
}

.rep-clientes-table tbody tr:hover {
    background: #f8fbff;
}

.rep-cliente-nombre strong {
    display: block;
    color: #0f2747;
    font-size: 13.5px;
    font-weight: 800;
}

.rep-cliente-nombre small {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 11px;
}

.rep-clientes-mascotas {
    display: inline-flex;
    min-width: 28px;
    min-height: 28px;
    align-items: center;
    justify-content: center;
    padding: 4px 8px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 900;
}

.rep-clientes-empty {
    padding: 50px 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 13px;
    background: #fbfdff;
    text-align: center;
    color: #64748b;
}

.rep-clientes-empty strong {
    display: block;
    margin-bottom: 8px;
    color: #0f2747;
    font-size: 20px;
}

.rep-clientes-footer {
    padding: 18px 26px 22px;
    color: #64748b;
    font-size: 12px;
    text-align: right;
}

@media (max-width: 850px) {
    .rep-clientes-header,
    .rep-clientes-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .rep-clientes-actions,
    .rep-clientes-search {
        flex-wrap: wrap;
    }

    .rep-clientes-search input {
        flex-basis: 100%;
    }
}

@media print {
    .sidebar,
    #sidebar,
    .sidebar-overlay,
    .topbar,
    .rep-clientes-actions,
    .rep-clientes-toolbar {
        display: none !important;
    }

    .main-content,
    main {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .rep-clientes-panel {
        border: 0 !important;
        box-shadow: none !important;
    }

    .rep-clientes-table {
        min-width: 0 !important;
    }
}
</style>

<div class="rep-clientes-page">
<section class="rep-clientes-panel">

<header class="rep-clientes-header">

    <div>
        <h1>👥 Reporte de clientes</h1>
        <p>
            Propietarios y mascotas registradas.
        </p>
    </div>

    <div class="rep-clientes-actions">

        <a
            class="rep-clientes-btn rep-btn-back"
            href="<?= repCliE(
                repCliUrl(
                    'reportes/index.php'
                )
            ) ?>"
        >
            ← Reportes
        </a>

        <button
            class="rep-clientes-btn rep-btn-print"
            type="button"
            onclick="window.print()"
        >
            🖨️ Imprimir
        </button>

        <a
            class="rep-clientes-btn rep-btn-csv"
            href="<?= repCliE(
                repCliUrl(
                    'reportes/clientes.php?exportar=csv' .
                    (
                        $buscar !== ''
                            ? '&buscar=' .
                              rawurlencode($buscar)
                            : ''
                    )
                )
            ) ?>"
        >
            ⬇️ CSV
        </a>

    </div>
</header>

<div class="rep-clientes-toolbar">

    <div class="rep-clientes-search-box">

        <label for="buscarCliente">
            Buscar
        </label>

        <form
            class="rep-clientes-search"
            method="GET"
        >

            <input
                id="buscarCliente"
                type="search"
                name="buscar"
                value="<?= repCliE($buscar) ?>"
                placeholder="
                    Nombre, cédula, teléfono,
                    correo o dirección
                "
            >

            <button
                class="
                    rep-clientes-btn
                    rep-btn-search
                "
                type="submit"
            >
                🔎 Buscar
            </button>

            <?php if ($buscar !== ''): ?>

                <a
                    class="
                        rep-clientes-btn
                        rep-btn-clear
                    "
                    href="<?= repCliE(
                        repCliUrl(
                            'reportes/clientes.php'
                        )
                    ) ?>"
                >
                    Limpiar
                </a>

            <?php endif; ?>

        </form>
    </div>

    <div class="rep-clientes-count">
        <?= count($clientes) ?>
        cliente<?= count($clientes) === 1
            ? ''
            : 's' ?>
    </div>

</div>

<?php if ($error !== ''): ?>

    <div class="rep-clientes-alert">
        ⚠️ <?= repCliE($error) ?>
    </div>

<?php endif; ?>

<div class="rep-clientes-content">

<?php if ($clientes !== []): ?>

<div class="rep-clientes-table-wrap">

<table class="rep-clientes-table">

<thead>
<tr>
    <th>Cliente</th>
    <th>Cédula</th>
    <th>Teléfono</th>
    <th>Correo</th>
    <th>Dirección</th>
    <th>Mascotas</th>
</tr>
</thead>

<tbody>

<?php foreach ($clientes as $cliente): ?>

<?php
$nombreCompleto = trim(
    $cliente['nombres'] .
    ' ' .
    $cliente['apellidos']
);
?>

<tr>

<td>
    <div class="rep-cliente-nombre">

        <strong>
            <?= repCliE(
                repCliVisible(
                    $nombreCompleto,
                    'Sin nombre'
                )
            ) ?>
        </strong>

        <small>
            ID #<?= (int) $cliente['id'] ?>
        </small>

    </div>
</td>

<td>
    <?= repCliE(
        repCliVisible(
            $cliente['cedula'],
            'No registrada'
        )
    ) ?>
</td>

<td>
    <?= repCliE(
        repCliVisible(
            $cliente['telefono']
        )
    ) ?>
</td>

<td>
    <?= repCliE(
        repCliVisible(
            $cliente['correo']
        )
    ) ?>
</td>

<td>
    <?= repCliE(
        repCliVisible(
            $cliente['direccion']
        )
    ) ?>
</td>

<td>
    <span class="rep-clientes-mascotas">
        <?= (int) $cliente['mascotas'] ?>
    </span>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

<?php else: ?>

<div class="rep-clientes-empty">

<?php if ($buscar !== ''): ?>

    <strong>
        No se encontraron clientes
    </strong>

    No existen resultados para
    “<?= repCliE($buscar) ?>”.

<?php else: ?>

    <strong>
        No hay clientes registrados
    </strong>

    Todavía no existen clientes para mostrar.

<?php endif; ?>

</div>

<?php endif; ?>

</div>

<footer class="rep-clientes-footer">

    Generado por
    <strong>
        <?= repCliE(
            repCliUsuario()
        ) ?>
    </strong>

    · <?= date('d/m/Y H:i') ?>

</footer>

</section>
</div>

<?php
if (is_file($footerFile)) {
    require_once $footerFile;
}
?>