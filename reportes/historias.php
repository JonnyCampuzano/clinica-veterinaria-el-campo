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

function repHistE(mixed $valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function repHistUrl(string $ruta = ''): string
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

function repHistTablaExiste(PDO $pdo, string $tabla): bool
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

function repHistColumnaExiste(
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

function repHistVisible(
    mixed $valor,
    string $alternativa = 'No registrado'
): string {
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

function repHistContiene(string $texto, string $buscar): bool
{
    if ($buscar === '') {
        return true;
    }

    return function_exists('mb_stripos')
        ? mb_stripos($texto, $buscar, 0, 'UTF-8') !== false
        : stripos($texto, $buscar) !== false;
}

function repHistFechaVisible(mixed $fecha): string
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

function repHistUsuarioActual(): string
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
   CONSULTAR HISTORIAS CLÍNICAS
===================================================== */

$historias = [];
$error = '';

try {
    foreach (
        ['historias_clinicas', 'mascotas', 'clientes']
        as $tabla
    ) {
        if (!repHistTablaExiste($pdo, $tabla)) {
            throw new RuntimeException(
                'No se encontró la tabla ' . $tabla . '.'
            );
        }
    }

    $tieneUsuarios =
        repHistTablaExiste($pdo, 'usuarios') &&
        repHistColumnaExiste(
            $pdo,
            'historias_clinicas',
            'usuario_id'
        );

    $selectVeterinario = $tieneUsuarios
        ? 'u.nombre AS veterinario'
        : "'' AS veterinario";

    $joinUsuario = $tieneUsuarios
        ? 'LEFT JOIN usuarios u ON u.id = h.usuario_id'
        : '';

    $selectProxima = repHistColumnaExiste(
        $pdo,
        'historias_clinicas',
        'proxima_cita'
    )
        ? 'h.proxima_cita'
        : 'NULL AS proxima_cita';

    $selectPeso = repHistColumnaExiste(
        $pdo,
        'historias_clinicas',
        'peso'
    )
        ? 'h.peso'
        : 'NULL AS peso';

    $selectTemperatura = repHistColumnaExiste(
        $pdo,
        'historias_clinicas',
        'temperatura'
    )
        ? 'h.temperatura'
        : 'NULL AS temperatura';

    $sql =
        "SELECT
            h.id,
            h.fecha,
            h.motivo,
            h.diagnostico,
            h.tratamiento,
            {$selectPeso},
            {$selectTemperatura},
            {$selectProxima},
            m.id AS mascota_id,
            m.nombre AS mascota,
            m.especie,
            m.raza,
            c.id AS cliente_id,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula,
            {$selectVeterinario}
         FROM historias_clinicas h
         INNER JOIN mascotas m
            ON m.id = h.mascota_id
         INNER JOIN clientes c
            ON c.id = m.cliente_id
         {$joinUsuario}
         WHERE h.fecha BETWEEN :desde AND :hasta
         ORDER BY h.fecha DESC, h.id DESC";

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

            /*
             * Si el nombre del usuario/veterinario también está cifrado,
             * se descifra; si está en texto plano, decrypt_personal()
             * conserva el valor.
             */
            $fila['veterinario'] = decrypt_personal(
                $fila['veterinario'] ?? null
            );
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Reporte historias: error descifrando historia ID ' .
                (int) ($fila['id'] ?? 0) .
                ': ' .
                $errorDescifrado->getMessage()
            );

            $fila['cliente_nombres'] = 'Dato protegido';
            $fila['cliente_apellidos'] = '';
            $fila['cliente_cedula'] = '';
            $fila['veterinario'] =
                trim((string) ($fila['veterinario'] ?? ''));
        }

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
                $fila['diagnostico'] ?? '',
                $fila['tratamiento'] ?? '',
                $fila['veterinario'] ?? '',
            ];

            $coincide = false;

            foreach ($campos as $campo) {
                if (
                    repHistContiene(
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

        $historias[] = $fila;
    }

} catch (Throwable $e) {
    error_log(
        'Reporte historias clínicas: ' .
        $e->getMessage()
    );

    $error =
        'No se pudieron cargar las historias clínicas. ' .
        'Revisa la tabla historias_clinicas, las relaciones y la clave de cifrado.';
}

/* =====================================================
   EXPORTAR CSV
===================================================== */

if (
    isset($_GET['exportar']) &&
    $_GET['exportar'] === 'csv'
) {
    $archivo =
        'reporte_historias_clinicas_' .
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
            'Mascota',
            'Especie',
            'Raza',
            'Propietario',
            'Cédula',
            'Motivo',
            'Diagnóstico',
            'Tratamiento',
            'Peso (kg)',
            'Temperatura (°C)',
            'Veterinario',
            'Próximo control',
        ],
        ';'
    );

    foreach ($historias as $historia) {
        $propietario = trim(
            (string) (
                $historia['cliente_nombres'] ?? ''
            ) .
            ' ' .
            (string) (
                $historia['cliente_apellidos'] ?? ''
            )
        );

        fputcsv(
            $salida,
            [
                $historia['id'] ?? '',
                repHistFechaVisible(
                    $historia['fecha'] ?? ''
                ),
                $historia['mascota'] ?? '',
                $historia['especie'] ?? '',
                $historia['raza'] ?? '',
                $propietario,
                $historia['cliente_cedula'] ?? '',
                $historia['motivo'] ?? '',
                $historia['diagnostico'] ?? '',
                $historia['tratamiento'] ?? '',
                $historia['peso'] ?? '',
                $historia['temperatura'] ?? '',
                $historia['veterinario'] ?? '',
                repHistFechaVisible(
                    $historia['proxima_cita'] ?? ''
                ),
            ],
            ';'
        );
    }

    fclose($salida);
    exit;
}

/* =====================================================
   URL CSV
===================================================== */

$parametrosCsv = [
    'exportar' => 'csv',
    'desde' => $desde,
    'hasta' => $hasta,
];

if ($buscar !== '') {
    $parametrosCsv['buscar'] = $buscar;
}

$urlCsv =
    repHistUrl('reportes/historias.php') .
    '?' .
    http_build_query($parametrosCsv);

/* =====================================================
   ENCABEZADO DEL SISTEMA
===================================================== */

$pageTitle = 'Reporte de historias clínicas';
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
    .rep-historias-page,
    .rep-historias-page * {
        box-sizing: border-box;
    }

    .rep-historias-page {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        margin: 0 auto;
        padding: 10px 0 42px;
    }

    .rep-historias-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 15px 40px rgba(15, 35, 65, 0.08);
    }

    .rep-historias-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 24px 28px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f8fbff);
    }

    .rep-historias-header h1 {
        margin: 0 0 7px;
        color: #08264f;
        font-size: 26px;
    }

    .rep-historias-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .rep-historias-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .rep-historias-btn {
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

    .rep-historias-btn-back {
        background: #eef2f7;
        color: #334155;
    }

    .rep-historias-btn-print {
        background: #0f2a4f;
        color: #ffffff;
    }

    .rep-historias-btn-csv {
        background: #059669;
        color: #ffffff;
    }

    .rep-historias-btn-search {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 7px 17px rgba(37, 99, 235, .20);
    }

    .rep-historias-toolbar {
        padding: 18px 26px;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .rep-historias-search {
        display: grid;
        grid-template-columns:
            minmax(150px, .8fr)
            minmax(150px, .8fr)
            minmax(320px, 1.5fr)
            auto;
        gap: 12px;
        align-items: end;
    }

    .rep-historias-field {
        display: grid;
        gap: 7px;
    }

    .rep-historias-field label {
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .rep-historias-field input {
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

    .rep-historias-field input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    }

    .rep-historias-filter-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rep-historias-count {
        color: #475569;
        font-size: 13px;
        font-weight: 800;
        white-space: nowrap;
    }

    .rep-historias-alert {
        margin: 20px 26px 0;
        padding: 13px 16px;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 13px;
        font-weight: 700;
    }

    .rep-historias-content {
        padding: 24px 26px 12px;
    }

    .rep-historias-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        scrollbar-width: thin;
    }

    .rep-historias-table {
        width: 100%;
        min-width: 1500px;
        border-collapse: collapse;
    }

    .rep-historias-table th,
    .rep-historias-table td {
        padding: 13px 12px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: top;
        font-size: 12.5px;
        line-height: 1.45;
    }

    .rep-historias-table th {
        background: #f8fafc;
        color: #334155;
        font-size: 11.5px;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rep-historias-table tbody tr:hover {
        background: #f8fbff;
    }

    .rep-hist-primary {
        display: block;
        color: #0f2747;
        font-size: 13.5px;
        font-weight: 800;
    }

    .rep-hist-secondary {
        display: block;
        margin-top: 4px;
        color: #64748b;
        font-size: 11px;
    }

    .rep-hist-text {
        max-width: 300px;
        color: #334155;
        white-space: normal;
    }

    .rep-historias-empty {
        padding: 50px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 13px;
        background: #fbfdff;
        text-align: center;
        color: #64748b;
    }

    .rep-historias-empty strong {
        display: block;
        margin-bottom: 8px;
        color: #0f2747;
        font-size: 20px;
    }

    .rep-historias-footer {
        padding: 18px 26px 22px;
        color: #64748b;
        font-size: 12px;
        text-align: right;
    }

    @media (max-width: 1050px) {
        .rep-historias-search {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .rep-historias-header {
            align-items: stretch;
            flex-direction: column;
        }

        .rep-historias-search {
            grid-template-columns: 1fr;
        }

        .rep-historias-filter-actions {
            align-items: stretch;
            flex-direction: column;
        }
    }

    @media print {
        .sidebar,
        #sidebar,
        .sidebar-overlay,
        .topbar,
        .rep-historias-actions,
        .rep-historias-toolbar {
            display: none !important;
        }

        .main-content,
        main {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .rep-historias-page {
            padding: 0 !important;
        }

        .rep-historias-panel {
            border: 0 !important;
            box-shadow: none !important;
        }

        .rep-historias-table-wrap {
            overflow: visible !important;
            border: 0 !important;
        }

        .rep-historias-table {
            min-width: 0 !important;
            font-size: 9px !important;
        }
    }
</style>

<div class="rep-historias-page">
<section class="rep-historias-panel">

    <header class="rep-historias-header">

        <div>
            <h1>📋 Reporte de historias clínicas</h1>

            <p>
                Del
                <?= repHistE(repHistFechaVisible($desde)) ?>
                al
                <?= repHistE(repHistFechaVisible($hasta)) ?>.
            </p>
        </div>

        <div class="rep-historias-actions">

            <a
                class="rep-historias-btn rep-historias-btn-back"
                href="<?= repHistE(
                    repHistUrl('reportes/index.php')
                ) ?>"
            >
                ← Reportes
            </a>

            <button
                class="rep-historias-btn rep-historias-btn-print"
                type="button"
                onclick="window.print()"
            >
                🖨️ Imprimir
            </button>

            <a
                class="rep-historias-btn rep-historias-btn-csv"
                href="<?= repHistE($urlCsv) ?>"
            >
                ⬇️ CSV
            </a>

        </div>
    </header>

    <div class="rep-historias-toolbar">

        <form
            class="rep-historias-search"
            method="GET"
            action=""
        >

            <div class="rep-historias-field">
                <label for="desde">Desde</label>

                <input
                    type="date"
                    id="desde"
                    name="desde"
                    value="<?= repHistE($desde) ?>"
                >
            </div>

            <div class="rep-historias-field">
                <label for="hasta">Hasta</label>

                <input
                    type="date"
                    id="hasta"
                    name="hasta"
                    value="<?= repHistE($hasta) ?>"
                >
            </div>

            <div class="rep-historias-field">
                <label for="buscar">Buscar</label>

                <input
                    type="search"
                    id="buscar"
                    name="buscar"
                    value="<?= repHistE($buscar) ?>"
                    placeholder="Mascota, propietario, cédula, diagnóstico o veterinario"
                >
            </div>

            <div class="rep-historias-filter-actions">
                <button
                    class="
                        rep-historias-btn
                        rep-historias-btn-search
                    "
                    type="submit"
                >
                    🔎 Aplicar
                </button>

                <span class="rep-historias-count">
                    <?= count($historias) ?>
                    registro<?= count($historias) === 1 ? '' : 's' ?>
                </span>
            </div>

        </form>

    </div>

    <?php if ($error !== ''): ?>
        <div class="rep-historias-alert">
            ⚠️ <?= repHistE($error) ?>
        </div>
    <?php endif; ?>

    <div class="rep-historias-content">

        <?php if ($historias !== []): ?>

            <div class="rep-historias-table-wrap">

                <table class="rep-historias-table">

                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Mascota</th>
                            <th>Propietario</th>
                            <th>Motivo</th>
                            <th>Diagnóstico</th>
                            <th>Tratamiento</th>
                            <th>Veterinario</th>
                            <th>Próximo control</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($historias as $historia): ?>

                            <?php
                            $propietario = trim(
                                (string) (
                                    $historia['cliente_nombres'] ?? ''
                                ) .
                                ' ' .
                                (string) (
                                    $historia['cliente_apellidos'] ?? ''
                                )
                            );

                            $especieRaza = trim(
                                repHistVisible(
                                    $historia['especie'] ?? '',
                                    ''
                                ) .
                                ' ' .
                                repHistVisible(
                                    $historia['raza'] ?? '',
                                    ''
                                )
                            );
                            ?>

                            <tr>

                                <td>
                                    <span class="rep-hist-primary">
                                        <?= repHistE(
                                            repHistFechaVisible(
                                                $historia['fecha'] ?? ''
                                            )
                                        ) ?>
                                    </span>

                                    <span class="rep-hist-secondary">
                                        ID #<?= (int) (
                                            $historia['id'] ?? 0
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="rep-hist-primary">
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia['mascota'] ?? '',
                                                'Sin mascota'
                                            )
                                        ) ?>
                                    </span>

                                    <span class="rep-hist-secondary">
                                        <?= repHistE(
                                            repHistVisible(
                                                $especieRaza,
                                                'Sin especie/raza'
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="rep-hist-primary">
                                        <?= repHistE(
                                            repHistVisible(
                                                $propietario,
                                                'Sin propietario'
                                            )
                                        ) ?>
                                    </span>

                                    <span class="rep-hist-secondary">
                                        Cédula:
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia[
                                                    'cliente_cedula'
                                                ] ?? '',
                                                'No registrada'
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="rep-hist-text">
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia['motivo'] ?? '',
                                                'Sin motivo'
                                            )
                                        ) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="rep-hist-text">
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia['diagnostico'] ?? '',
                                                'Sin diagnóstico'
                                            )
                                        ) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="rep-hist-text">
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia['tratamiento'] ?? '',
                                                'Sin tratamiento'
                                            )
                                        ) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="rep-hist-primary">
                                        <?= repHistE(
                                            repHistVisible(
                                                $historia['veterinario'] ?? '',
                                                'No registrado'
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= repHistE(
                                        repHistFechaVisible(
                                            $historia['proxima_cita'] ?? ''
                                        )
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php else: ?>

            <div class="rep-historias-empty">
                <strong>No se encontraron historias clínicas</strong>

                No existen registros que coincidan con los filtros seleccionados.
            </div>

        <?php endif; ?>

    </div>

    <footer class="rep-historias-footer">
        Generado por
        <strong>
            <?= repHistE(repHistUsuarioActual()) ?>
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