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
   SESIÓN Y SEGURIDAD
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (function_exists('require_login')) {
    require_login();
}

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

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function repCitaE(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function repCitaUrl(string $ruta = ''): string
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

function repCitaTablaExiste(PDO $pdo, string $tabla): bool
{
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

function repCitaVisible(
    mixed $valor,
    string $alternativa = 'No registrado'
): string {
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

function repCitaContiene(
    string $texto,
    string $buscar
): bool {
    if ($buscar === '') {
        return true;
    }

    return function_exists('mb_stripos')
        ? mb_stripos($texto, $buscar, 0, 'UTF-8') !== false
        : stripos($texto, $buscar) !== false;
}

function repCitaFechaVisible(mixed $fecha): string
{
    $texto = trim((string) $fecha);

    if ($texto === '') {
        return '—';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $texto);

    return $objeto instanceof DateTime
        ? $objeto->format('d/m/Y')
        : $texto;
}

function repCitaUsuario(): string
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

function repCitaEstadoNormalizado(mixed $estado): string
{
    $valor = trim((string) $estado);

    if ($valor === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}

/* =====================================================
   FILTROS
===================================================== */

$desde = trim(
    (string) ($_GET['desde'] ?? date('Y-m-01'))
);

$hasta = trim(
    (string) ($_GET['hasta'] ?? date('Y-m-d'))
);

$buscar = trim(
    (string) ($_GET['buscar'] ?? '')
);

$estadoFiltro = trim(
    (string) ($_GET['estado'] ?? '')
);

$estadosPermitidos = [
    '',
    'Pendiente',
    'Confirmada',
    'Atendida',
    'Cancelada',
];

if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = '';
}

/* =====================================================
   VALIDAR FECHAS
===================================================== */

$fechaDesde = DateTime::createFromFormat('Y-m-d', $desde);
$fechaHasta = DateTime::createFromFormat('Y-m-d', $hasta);

if (
    !$fechaDesde instanceof DateTime ||
    $fechaDesde->format('Y-m-d') !== $desde
) {
    $desde = date('Y-m-01');
}

if (
    !$fechaHasta instanceof DateTime ||
    $fechaHasta->format('Y-m-d') !== $hasta
) {
    $hasta = date('Y-m-d');
}

if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

/* =====================================================
   CONSULTAR CITAS
===================================================== */

$citas = [];
$error = '';

try {
    foreach (['citas', 'mascotas', 'clientes'] as $tabla) {
        if (!repCitaTablaExiste($pdo, $tabla)) {
            throw new RuntimeException(
                'No se encontró la tabla ' . $tabla . '.'
            );
        }
    }

    $sql =
        'SELECT
            ci.id,
            ci.fecha,
            ci.hora,
            ci.motivo,
            ci.estado,
            m.id AS mascota_id,
            m.nombre AS mascota,
            m.especie,
            m.raza,
            c.id AS cliente_id,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula
         FROM citas ci
         INNER JOIN mascotas m
            ON m.id = ci.mascota_id
         INNER JOIN clientes c
            ON c.id = m.cliente_id
         WHERE ci.fecha BETWEEN :desde AND :hasta
         ORDER BY ci.fecha DESC, ci.hora DESC, ci.id DESC';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':desde' => $desde,
        ':hasta' => $hasta,
    ]);

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($filas as $fila) {
        try {
            $fila['cliente_nombres'] = decrypt_personal(
                $fila['cliente_nombres'] ?? null
            );

            $fila['cliente_apellidos'] = decrypt_personal(
                $fila['cliente_apellidos'] ?? null
            );

            $fila['cliente_cedula'] = decrypt_personal(
                $fila['cliente_cedula'] ?? null
            );
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Reporte citas: error descifrando cita ID ' .
                (int) ($fila['id'] ?? 0) .
                ': ' .
                $errorDescifrado->getMessage()
            );

            $fila['cliente_nombres'] = 'Dato protegido';
            $fila['cliente_apellidos'] = '';
            $fila['cliente_cedula'] = '';
        }

        /* -------------------------------------------------
           FILTRO POR ESTADO
        ------------------------------------------------- */

        if ($estadoFiltro !== '') {
            if (
                repCitaEstadoNormalizado($fila['estado'] ?? '') !==
                repCitaEstadoNormalizado($estadoFiltro)
            ) {
                continue;
            }
        }

        /* -------------------------------------------------
           BUSCADOR SOBRE DATOS YA DESCIFRADOS
        ------------------------------------------------- */

        if ($buscar !== '') {
            $propietario = trim(
                (string) ($fila['cliente_nombres'] ?? '') .
                ' ' .
                (string) ($fila['cliente_apellidos'] ?? '')
            );

            $campos = [
                $fila['mascota'] ?? '',
                $fila['especie'] ?? '',
                $fila['raza'] ?? '',
                $propietario,
                $fila['cliente_nombres'] ?? '',
                $fila['cliente_apellidos'] ?? '',
                $fila['cliente_cedula'] ?? '',
                $fila['motivo'] ?? '',
                $fila['estado'] ?? '',
            ];

            $coincide = false;

            foreach ($campos as $campo) {
                if (
                    repCitaContiene(
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

        $citas[] = $fila;
    }

} catch (Throwable $e) {
    error_log(
        'Reporte citas: ' .
        $e->getMessage()
    );

    $error =
        'No se pudieron cargar las citas. ' .
        'Revisa las tablas, la conexión y la clave de cifrado.';
}

/* =====================================================
   ESTADÍSTICAS
===================================================== */

$estadisticas = [
    'total' => count($citas),
    'pendientes' => 0,
    'confirmadas' => 0,
    'atendidas' => 0,
    'canceladas' => 0,
];

foreach ($citas as $cita) {
    $estado = repCitaEstadoNormalizado(
        $cita['estado'] ?? ''
    );

    if ($estado === 'pendiente') {
        $estadisticas['pendientes']++;
    } elseif ($estado === 'confirmada') {
        $estadisticas['confirmadas']++;
    } elseif ($estado === 'atendida') {
        $estadisticas['atendidas']++;
    } elseif ($estado === 'cancelada') {
        $estadisticas['canceladas']++;
    }
}

/* =====================================================
   EXPORTAR CSV
===================================================== */

if (
    isset($_GET['exportar']) &&
    $_GET['exportar'] === 'csv'
) {
    $archivo =
        'reporte_citas_' .
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
            'Fecha',
            'Hora',
            'Mascota',
            'Especie',
            'Raza',
            'Propietario',
            'Cédula',
            'Motivo',
            'Estado',
        ],
        ';'
    );

    foreach ($citas as $cita) {
        $propietario = trim(
            (string) ($cita['cliente_nombres'] ?? '') .
            ' ' .
            (string) ($cita['cliente_apellidos'] ?? '')
        );

        fputcsv(
            $salida,
            [
                $cita['id'] ?? '',
                repCitaFechaVisible($cita['fecha'] ?? ''),
                substr((string) ($cita['hora'] ?? ''), 0, 5),
                $cita['mascota'] ?? '',
                $cita['especie'] ?? '',
                $cita['raza'] ?? '',
                $propietario,
                $cita['cliente_cedula'] ?? '',
                $cita['motivo'] ?? '',
                $cita['estado'] ?? '',
            ],
            ';'
        );
    }

    fclose($salida);
    exit;
}

/* =====================================================
   URLs PARA EXPORTAR
===================================================== */

$parametrosCsv = [
    'exportar' => 'csv',
    'desde' => $desde,
    'hasta' => $hasta,
];

if ($buscar !== '') {
    $parametrosCsv['buscar'] = $buscar;
}

if ($estadoFiltro !== '') {
    $parametrosCsv['estado'] = $estadoFiltro;
}

$urlCsv =
    repCitaUrl('reportes/citas.php') .
    '?' .
    http_build_query($parametrosCsv);

/* =====================================================
   ENCABEZADO DEL SISTEMA
===================================================== */

$pageTitle = 'Reporte de citas';
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
    .rep-citas-page,
    .rep-citas-page * {
        box-sizing: border-box;
    }

    .rep-citas-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        margin: 0 auto;
        padding: 10px 0 42px;
    }

    .rep-citas-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(15, 35, 65, 0.08);
    }

    .rep-citas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f8fbff);
    }

    .rep-citas-header h1 {
        margin: 0 0 7px;
        color: #08264f;
        font-size: 26px;
    }

    .rep-citas-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .rep-citas-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .rep-citas-btn {
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
        transition: .2s ease;
    }

    .rep-citas-btn-back {
        background: #eef2f7;
        color: #334155;
    }

    .rep-citas-btn-print {
        background: #0f2a4f;
        color: #ffffff;
    }

    .rep-citas-btn-csv {
        background: #059669;
        color: #ffffff;
    }

    .rep-citas-btn-search {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 7px 17px rgba(37, 99, 235, .20);
    }

    .rep-citas-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 15px;
        padding: 22px 26px;
        background: #ffffff;
    }

    .rep-citas-stat {
        padding: 18px 20px;
        border: 1px solid #dbe5f0;
        border-radius: 14px;
        background: #f8fafc;
    }

    .rep-citas-stat span {
        display: block;
        margin-bottom: 8px;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .rep-citas-stat strong {
        color: #08264f;
        font-size: 27px;
    }

    .rep-citas-toolbar {
        padding: 18px 26px;
        border-top: 1px solid #eef2f7;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .rep-citas-search {
        display: grid;
        grid-template-columns:
            minmax(150px, .8fr)
            minmax(150px, .8fr)
            minmax(280px, 1.3fr)
            minmax(125px, .45fr);
        gap: 12px;
        align-items: end;
    }

    .rep-citas-field {
        display: grid;
        gap: 7px;
    }

    .rep-citas-field label {
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .rep-citas-field input,
    .rep-citas-field select {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        outline: none;
    }

    .rep-citas-field input:focus,
    .rep-citas-field select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .rep-citas-toolbar-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 11px;
    }

    .rep-citas-apply {
        min-width: 230px;
    }

    .rep-citas-count {
        color: #475569;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    .rep-citas-alert {
        margin: 20px 26px 0;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 13px;
        font-weight: 700;
    }

    .rep-citas-content {
        padding: 24px 26px 12px;
    }

    .rep-citas-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        scrollbar-width: thin;
    }

    .rep-citas-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: collapse;
    }

    .rep-citas-table th,
    .rep-citas-table td {
        padding: 13px 12px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 12.5px;
    }

    .rep-citas-table th {
        background: #f8fafc;
        color: #334155;
        font-size: 11.5px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .rep-citas-table tbody tr:hover {
        background: #f8fbff;
    }

    .rep-citas-primary {
        display: block;
        color: #0f2747;
        font-size: 13.5px;
        font-weight: 800;
    }

    .rep-citas-secondary {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 11px;
    }

    .rep-citas-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 27px;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .rep-citas-badge-pendiente {
        background: #fff7ed;
        color: #c2410c;
    }

    .rep-citas-badge-confirmada {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .rep-citas-badge-atendida {
        background: #f0fdf4;
        color: #166534;
    }

    .rep-citas-badge-cancelada {
        background: #fff1f2;
        color: #be123c;
    }

    .rep-citas-empty {
        padding: 50px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 13px;
        background: #fbfdff;
        text-align: center;
        color: #64748b;
    }

    .rep-citas-empty strong {
        display: block;
        margin-bottom: 8px;
        color: #0f2747;
        font-size: 20px;
    }

    .rep-citas-footer {
        padding: 18px 26px 22px;
        color: #64748b;
        font-size: 12px;
        text-align: right;
    }

    @media (max-width: 1050px) {
        .rep-citas-search {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .rep-citas-header {
            align-items: stretch;
            flex-direction: column;
        }

        .rep-citas-stats,
        .rep-citas-search {
            grid-template-columns: 1fr;
        }

        .rep-citas-toolbar-bottom {
            align-items: stretch;
            flex-direction: column;
        }

        .rep-citas-apply {
            width: 100%;
        }
    }

    @media print {
        .sidebar,
        #sidebar,
        .sidebar-overlay,
        .topbar,
        .rep-citas-actions,
        .rep-citas-toolbar {
            display: none !important;
        }

        .main-content,
        main {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .rep-citas-page {
            padding: 0 !important;
        }

        .rep-citas-panel {
            border: 0 !important;
            box-shadow: none !important;
        }

        .rep-citas-table-wrap {
            overflow: visible !important;
            border: 0 !important;
        }

        .rep-citas-table {
            min-width: 0 !important;
        }
    }
</style>

<div class="rep-citas-page">
<section class="rep-citas-panel">

    <header class="rep-citas-header">
        <div>
            <h1>📅 Reporte de citas</h1>

            <p>
                Del
                <?= repCitaE(repCitaFechaVisible($desde)) ?>
                al
                <?= repCitaE(repCitaFechaVisible($hasta)) ?>.
            </p>
        </div>

        <div class="rep-citas-actions">
            <a
                class="rep-citas-btn rep-citas-btn-back"
                href="<?= repCitaE(
                    repCitaUrl('reportes/index.php')
                ) ?>"
            >
                ← Reportes
            </a>

            <button
                class="rep-citas-btn rep-citas-btn-print"
                type="button"
                onclick="window.print()"
            >
                🖨️ Imprimir
            </button>

            <a
                class="rep-citas-btn rep-citas-btn-csv"
                href="<?= repCitaE($urlCsv) ?>"
            >
                ⬇️ CSV
            </a>
        </div>
    </header>

    <div class="rep-citas-stats">

        <article class="rep-citas-stat">
            <span>Total</span>
            <strong><?= (int) $estadisticas['total'] ?></strong>
        </article>

        <article class="rep-citas-stat">
            <span>Pendientes</span>
            <strong><?= (int) $estadisticas['pendientes'] ?></strong>
        </article>

        <article class="rep-citas-stat">
            <span>Confirmadas</span>
            <strong><?= (int) $estadisticas['confirmadas'] ?></strong>
        </article>

        <article class="rep-citas-stat">
            <span>Atendidas</span>
            <strong><?= (int) $estadisticas['atendidas'] ?></strong>
        </article>

    </div>

    <div class="rep-citas-toolbar">

        <form
            class="rep-citas-search"
            method="GET"
            action=""
        >

            <div class="rep-citas-field">
                <label for="desde">Desde</label>

                <input
                    type="date"
                    id="desde"
                    name="desde"
                    value="<?= repCitaE($desde) ?>"
                >
            </div>

            <div class="rep-citas-field">
                <label for="hasta">Hasta</label>

                <input
                    type="date"
                    id="hasta"
                    name="hasta"
                    value="<?= repCitaE($hasta) ?>"
                >
            </div>

            <div class="rep-citas-field">
                <label for="buscar">Buscar</label>

                <input
                    type="search"
                    id="buscar"
                    name="buscar"
                    value="<?= repCitaE($buscar) ?>"
                    placeholder="Mascota, propietario, cédula o motivo"
                >
            </div>

            <div class="rep-citas-field">
                <label for="estado">Estado</label>

                <select
                    id="estado"
                    name="estado"
                >
                    <option value="">
                        Todos
                    </option>

                    <?php foreach (
                        [
                            'Pendiente',
                            'Confirmada',
                            'Atendida',
                            'Cancelada',
                        ]
                        as $estado
                    ): ?>

                        <option
                            value="<?= repCitaE($estado) ?>"
                            <?= $estadoFiltro === $estado
                                ? 'selected'
                                : '' ?>
                        >
                            <?= repCitaE($estado) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rep-citas-toolbar-bottom">
                <button
                    class="
                        rep-citas-btn
                        rep-citas-btn-search
                        rep-citas-apply
                    "
                    type="submit"
                >
                    🔎 Aplicar
                </button>

                <div class="rep-citas-count">
                    <?= count($citas) ?>
                    cita<?= count($citas) === 1 ? '' : 's' ?>
                </div>
            </div>

        </form>

    </div>

    <?php if ($error !== ''): ?>
        <div class="rep-citas-alert">
            ⚠️ <?= repCitaE($error) ?>
        </div>
    <?php endif; ?>

    <div class="rep-citas-content">

        <?php if ($citas !== []): ?>

            <div class="rep-citas-table-wrap">

                <table class="rep-citas-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Propietario</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($citas as $cita): ?>

                            <?php
                            $propietario = trim(
                                (string) (
                                    $cita['cliente_nombres'] ?? ''
                                ) .
                                ' ' .
                                (string) (
                                    $cita['cliente_apellidos'] ?? ''
                                )
                            );

                            $estadoTexto = trim(
                                (string) (
                                    $cita['estado'] ?? ''
                                )
                            );

                            $estadoClase =
                                repCitaEstadoNormalizado(
                                    $estadoTexto
                                );

                            $estadoClase = in_array(
                                $estadoClase,
                                [
                                    'pendiente',
                                    'confirmada',
                                    'atendida',
                                    'cancelada',
                                ],
                                true
                            )
                                ? $estadoClase
                                : 'pendiente';
                            ?>

                            <tr>

                                <td>
                                    <?= repCitaE(
                                        repCitaFechaVisible(
                                            $cita['fecha'] ?? ''
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= repCitaE(
                                        substr(
                                            (string) (
                                                $cita['hora'] ?? ''
                                            ),
                                            0,
                                            5
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span class="rep-citas-primary">
                                        <?= repCitaE(
                                            repCitaVisible(
                                                $cita['mascota'] ?? '',
                                                'Sin mascota'
                                            )
                                        ) ?>
                                    </span>

                                    <span class="rep-citas-secondary">
                                        <?= repCitaE(
                                            trim(
                                                repCitaVisible(
                                                    $cita['especie'] ?? '',
                                                    ''
                                                ) .
                                                ' ' .
                                                repCitaVisible(
                                                    $cita['raza'] ?? '',
                                                    ''
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="rep-citas-primary">
                                        <?= repCitaE(
                                            repCitaVisible(
                                                $propietario,
                                                'Sin propietario'
                                            )
                                        ) ?>
                                    </span>

                                    <span class="rep-citas-secondary">
                                        Cédula:
                                        <?= repCitaE(
                                            repCitaVisible(
                                                $cita['cliente_cedula'] ?? '',
                                                'No registrada'
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= repCitaE(
                                        repCitaVisible(
                                            $cita['motivo'] ?? '',
                                            'Sin motivo'
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="
                                            rep-citas-badge
                                            rep-citas-badge-<?= repCitaE(
                                                $estadoClase
                                            ) ?>
                                        "
                                    >
                                        <?= repCitaE(
                                            repCitaVisible(
                                                $estadoTexto,
                                                'Sin estado'
                                            )
                                        ) ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="rep-citas-empty">
                <strong>No se encontraron citas</strong>

                No existen citas que coincidan con los filtros seleccionados.
            </div>

        <?php endif; ?>

    </div>

    <footer class="rep-citas-footer">
        Generado por
        <strong>
            <?= repCitaE(repCitaUsuario()) ?>
        </strong>
        ·
        <?= date('d/m/Y H:i') ?>
    </footer>

</section>
</div>

<?php
if (is_file($footerFile)) {
    require_once $footerFile;
}
?>