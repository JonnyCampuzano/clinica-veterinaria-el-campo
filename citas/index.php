<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL DEL PROYECTO
===================================================== */

$raiz = dirname(__DIR__);

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   CARGAR ARCHIVOS DEL SISTEMA
===================================================== */

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/* =====================================================
   PROTEGER LA PÁGINA
===================================================== */

require_login();

/* =====================================================
   COMPROBAR CONEXIÓN PDO
===================================================== */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            max-width:760px;
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

/* =====================================================
   FUNCIONES AUXILIARES
===================================================== */

function citaEscapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function citaValorVisible(
    mixed $valor,
    string $alternativa = 'No registrado'
): string {
    $texto = trim((string) $valor);

    return $texto !== ''
        ? $texto
        : $alternativa;
}

function citaFechaVisible(mixed $fecha): string
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

function citaHoraVisible(mixed $hora): string
{
    $valor = trim((string) $hora);

    if ($valor === '') {
        return 'No registrada';
    }

    $objeto = DateTime::createFromFormat('H:i:s', $valor);

    if (!$objeto instanceof DateTime) {
        $objeto = DateTime::createFromFormat('H:i', $valor);
    }

    return $objeto instanceof DateTime
        ? $objeto->format('H:i')
        : $valor;
}

function citaClaseEstado(mixed $estado): string
{
    return match (strtolower(trim((string) $estado))) {
        'confirmada', 'confirmado' => 'estado-confirmada',
        'atendida', 'atendido' => 'estado-atendida',
        'cancelada', 'cancelado' => 'estado-cancelada',
        default => 'estado-pendiente',
    };
}


function citaNormalizarRol(mixed $rol): string
{
    $valor = trim((string) $rol);

    if (function_exists('mb_strtolower')) {
        $valor = mb_strtolower($valor, 'UTF-8');
    } else {
        $valor = strtolower($valor);
    }

    return strtr($valor, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n',
    ]);
}

/**
 * Busca el primer archivo existente dentro del proyecto.
 */
function citaRutaDisponible(
    string $raizProyecto,
    array $rutas,
    string $rutaPredeterminada
): string {
    foreach ($rutas as $ruta) {
        $archivo = $raizProyecto
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $ruta
            );

        if (is_file($archivo)) {
            return $ruta;
        }
    }

    return $rutaPredeterminada;
}

/* =====================================================
   ROL ACTUAL Y SEGURIDAD DE SOLICITUDES WEB
===================================================== */

$rolActualNormalizado = citaNormalizarRol(
    $_SESSION['rol'] ?? $_SESSION['role'] ?? ''
);

/*
 * Un cliente público no puede entrar al módulo administrativo
 * aunque escriba la URL manualmente.
 */
if ($rolActualNormalizado === 'cliente') {
    header('Location: ' . url('citas/reservar.php'));
    exit;
}

$puedeGestionarSolicitudes = in_array(
    $rolActualNormalizado,
    ['administrador', 'admin', 'recepcionista'],
    true
);

if (
    !isset($_SESSION['csrf_citas']) ||
    !is_string($_SESSION['csrf_citas']) ||
    $_SESSION['csrf_citas'] === ''
) {
    $_SESSION['csrf_citas'] = bin2hex(random_bytes(32));
}

$csrfCitas = (string) $_SESSION['csrf_citas'];

$mensajeReservaExito = (string) (
    $_SESSION['mensaje_reserva_exito'] ?? ''
);

$mensajeReservaError = (string) (
    $_SESSION['mensaje_reserva_error'] ?? ''
);

unset(
    $_SESSION['mensaje_reserva_exito'],
    $_SESSION['mensaje_reserva_error']
);

/* =====================================================
   CONFIRMAR O CANCELAR SOLICITUD DE CLIENTE
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion_reserva'])
) {
    if (!$puedeGestionarSolicitudes) {
        $_SESSION['mensaje_reserva_error'] =
            'No tienes permiso para confirmar o cancelar solicitudes.';

        header('Location: ' . url('citas/index.php'));
        exit;
    }

    $csrfRecibido = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrfRecibido === '' ||
        !hash_equals($csrfCitas, $csrfRecibido)
    ) {
        $_SESSION['mensaje_reserva_error'] =
            'La solicitud de seguridad no es válida. Inténtalo nuevamente.';

        header('Location: ' . url('citas/index.php'));
        exit;
    }

    $idReserva = (int) ($_POST['reserva_id'] ?? 0);
    $accionReserva = strtolower(
        trim((string) ($_POST['accion_reserva'] ?? ''))
    );

    $nuevoEstadoReserva = match ($accionReserva) {
        'confirmar' => 'Confirmada',
        'cancelar' => 'Cancelada',
        default => '',
    };

    if ($idReserva <= 0 || $nuevoEstadoReserva === '') {
        $_SESSION['mensaje_reserva_error'] =
            'La solicitud seleccionada no es válida.';

        header('Location: ' . url('citas/index.php'));
        exit;
    }

    try {
        $actualizarReserva = $pdo->prepare(
            'UPDATE reservas_citas
             SET estado = :estado
             WHERE id = :id
               AND estado = "Pendiente"'
        );

        $actualizarReserva->execute([
            ':estado' => $nuevoEstadoReserva,
            ':id' => $idReserva,
        ]);

        if ($actualizarReserva->rowCount() > 0) {
            $_SESSION['mensaje_reserva_exito'] =
                $nuevoEstadoReserva === 'Confirmada'
                    ? 'La solicitud del cliente fue confirmada correctamente.'
                    : 'La solicitud del cliente fue cancelada correctamente.';
        } else {
            $_SESSION['mensaje_reserva_error'] =
                'La solicitud no existe o ya había sido procesada.';
        }
    } catch (Throwable $error) {
        error_log(
            'Error al cambiar estado de reserva web: ' .
            $error->getMessage()
        );

        $_SESSION['mensaje_reserva_error'] =
            'No fue posible actualizar la solicitud del cliente.';
    }

    header('Location: ' . url('citas/index.php'));
    exit;
}

/* =====================================================
   RUTAS DE LOS BOTONES
===================================================== */

$rutaCrear = citaRutaDisponible(
    $raiz,
    [
        'citas/crear.php',
        'citas/nueva.php',
        'citas/nueva_cita.php',
    ],
    'citas/crear.php'
);

$rutaEditarBase = citaRutaDisponible(
    $raiz,
    [
        'citas/editar.php',
        'citas/editar_cita.php',
    ],
    'citas/editar.php'
);

$rutaEliminarBase = citaRutaDisponible(
    $raiz,
    [
        'citas/eliminar.php',
        'citas/eliminar_cita.php',
    ],
    'citas/eliminar.php'
);

/* =====================================================
   FILTROS Y MENSAJES
===================================================== */

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$estadoFiltro = trim((string) ($_GET['estado'] ?? ''));
$mensajeCodigo = trim((string) ($_GET['msg'] ?? ''));
$errorCodigo = trim((string) ($_GET['error'] ?? ''));

$estadosPermitidos = [
    'Pendiente',
    'Confirmada',
    'Atendida',
    'Cancelada',
];

if (
    $estadoFiltro !== '' &&
    !in_array($estadoFiltro, $estadosPermitidos, true)
) {
    $estadoFiltro = '';
}

$mensajesExito = [
    'creada' => 'Cita registrada correctamente.',
    'actualizada' => 'Cita actualizada correctamente.',
    'eliminada' => 'Cita eliminada correctamente.',
];

$mensajesError = [
    'id_invalido' => 'El identificador de la cita no es válido.',
    'no_encontrada' => 'La cita solicitada no existe.',
    'no_eliminada' => 'No se pudo eliminar la cita.',
];

$mensajeExito = $mensajesExito[$mensajeCodigo] ?? '';
$mensajeError = $mensajesError[$errorCodigo] ?? '';

$citas = [];

/* =====================================================
   ESTADÍSTICAS
===================================================== */

$estadisticas = [
    'total' => 0,
    'hoy' => 0,
    'proximas' => 0,
    'pendientes' => 0,
];

try {
    $consultaEstadisticas = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN fecha = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS hoy,
            SUM(
                CASE
                    WHEN fecha > CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS proximas,
            SUM(
                CASE
                    WHEN estado = "Pendiente"
                    THEN 1
                    ELSE 0
                END
            ) AS pendientes
         FROM citas'
    );

    $filaEstadisticas = $consultaEstadisticas->fetch(
        PDO::FETCH_ASSOC
    );

    if (is_array($filaEstadisticas)) {
        $estadisticas = [
            'total' => (int) ($filaEstadisticas['total'] ?? 0),
            'hoy' => (int) ($filaEstadisticas['hoy'] ?? 0),
            'proximas' => (int) ($filaEstadisticas['proximas'] ?? 0),
            'pendientes' => (int) (
                $filaEstadisticas['pendientes'] ?? 0
            ),
        ];
    }
} catch (Throwable $error) {
    error_log(
        'Error al consultar estadísticas de citas: ' .
        $error->getMessage()
    );
}

/* =====================================================
   CONSULTAR CITAS
===================================================== */

try {
    $sql = '
        SELECT
            ci.id,
            ci.mascota_id,
            ci.fecha,
            ci.hora,
            ci.motivo,
            ci.estado,
            ci.created_at,

            m.nombre AS mascota_nombre,
            m.especie AS mascota_especie,
            m.raza AS mascota_raza,

            cl.id AS cliente_id,
            cl.nombres AS cliente_nombres,
            cl.apellidos AS cliente_apellidos,
            cl.cedula AS cliente_cedula,
            cl.telefono AS cliente_telefono

        FROM citas AS ci

        INNER JOIN mascotas AS m
            ON m.id = ci.mascota_id

        LEFT JOIN clientes AS cl
            ON cl.id = m.cliente_id
    ';

    $condiciones = [];
    $parametros = [];

    if ($buscar !== '') {
        $condiciones[] = '
            (
                m.nombre LIKE :buscar_mascota
                OR m.especie LIKE :buscar_especie
                OR COALESCE(m.raza, \'\') LIKE :buscar_raza
                OR COALESCE(cl.nombres, \'\') LIKE :buscar_nombres
                OR COALESCE(cl.apellidos, \'\') LIKE :buscar_apellidos
                OR COALESCE(cl.cedula, \'\') LIKE :buscar_cedula
                OR COALESCE(ci.motivo, \'\') LIKE :buscar_motivo
            )
        ';

        $termino = '%' . $buscar . '%';

        $parametros[':buscar_mascota'] = $termino;
        $parametros[':buscar_especie'] = $termino;
        $parametros[':buscar_raza'] = $termino;
        $parametros[':buscar_nombres'] = $termino;
        $parametros[':buscar_apellidos'] = $termino;
        $parametros[':buscar_cedula'] = $termino;
        $parametros[':buscar_motivo'] = $termino;
    }

    if ($estadoFiltro !== '') {
        $condiciones[] = 'ci.estado = :estado';
        $parametros[':estado'] = $estadoFiltro;
    }

    if ($condiciones !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }

    $sql .= '
        ORDER BY
            CASE
                WHEN ci.fecha >= CURDATE() THEN 0
                ELSE 1
            END,
            ci.fecha ASC,
            ci.hora ASC,
            ci.id DESC
    ';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $citas = $consulta->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log(
        'Error al consultar citas: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar las citas. ' .
        'Error: ' .
        $error->getMessage();
}

/* =====================================================
   SOLICITUDES DE CITAS REALIZADAS POR CLIENTES
===================================================== */

$solicitudesClientes = [];
$tablaReservasDisponible = false;

try {
    $comprobarTabla = $pdo->query(
        "SHOW TABLES LIKE 'reservas_citas'"
    );

    $tablaReservasDisponible =
        $comprobarTabla->fetchColumn() !== false;

    if ($tablaReservasDisponible) {
        $sqlSolicitudes = '
    SELECT
        rc.id,
        rc.usuario_id,
        u.nombre AS nombre_cliente,
        u.email AS correo_cliente,
        rc.nombre_mascota,
        rc.especie,
        rc.fecha,
        rc.hora,
        rc.motivo,
        rc.estado,
        rc.fecha_registro
    FROM reservas_citas rc
    LEFT JOIN usuarios u
        ON u.id = rc.usuario_id
    ORDER BY
        CASE
            WHEN rc.estado = "Pendiente" THEN 0
            WHEN rc.estado = "Confirmada" THEN 1
            ELSE 2
        END,
        CASE
            WHEN rc.fecha >= CURDATE() THEN 0
            ELSE 1
        END,
        rc.fecha ASC,
        rc.hora ASC,
        rc.id DESC
';

        $consultaSolicitudes = $pdo->query($sqlSolicitudes);

        $solicitudesClientes = $consultaSolicitudes->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
} catch (Throwable $error) {
    error_log(
        'Error al consultar solicitudes web: ' .
        $error->getMessage()
    );

    $mensajeReservaError =
        'No se pudieron cargar las solicitudes de citas de clientes.';
}

$totalSolicitudes = count($solicitudesClientes);

$solicitudesPendientes = count(
    array_filter(
        $solicitudesClientes,
        static fn(array $reserva): bool =>
            strtolower(trim((string) ($reserva['estado'] ?? '')))
            === 'pendiente'
    )
);

/* =====================================================
   ENCABEZADO
===================================================== */

$pageTitle = 'Citas';
$activePage = 'citas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .citas-page {
        width: min(1220px, 100%);
        margin: 0 auto;
        padding-bottom: 42px;
    }

    .citas-panel {
        overflow: hidden;
        border: 1px solid #dbe5f0;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 14px 38px rgba(15, 35, 65, 0.08);
    }

    .citas-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 24px 26px;
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #ffffff, #f7fbff);
    }

    .citas-header h1 {
        margin: 0 0 6px;
        color: #08264f;
        font-size: 25px;
    }

    .citas-header p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .citas-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 16px;
        border: 0;
        border-radius: 10px;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .citas-btn-primary {
        background: #2563eb;
        color: #ffffff;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }

    .citas-btn-primary:hover {
        background: #1d4ed8;
    }

    .citas-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        padding: 20px 26px;
        border-bottom: 1px solid #e8eef5;
        background: #fbfdff;
    }

    .citas-stat {
        padding: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        background: #ffffff;
    }

    .citas-stat span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .citas-stat strong {
        color: #0f2747;
        font-size: 25px;
    }

    .citas-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 26px;
        border-bottom: 1px solid #e8eef5;
        background: #ffffff;
    }

    .citas-search {
        display: flex;
        flex: 1;
        gap: 10px;
    }

    .citas-search input,
    .citas-search select {
        min-height: 42px;
        padding: 10px 13px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        outline: none;
    }

    .citas-search input {
        width: 100%;
    }

    .citas-search input:focus,
    .citas-search select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .citas-search button {
        background: #0f766e;
        color: #ffffff;
    }

    .citas-clear {
        background: #e9eef5;
        color: #334155;
    }

    .citas-count {
        white-space: nowrap;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .citas-alert {
        margin: 20px 26px 0;
        padding: 13px 16px;
        border-radius: 11px;
        font-size: 14px;
        font-weight: 700;
    }

    .citas-alert-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .citas-alert-error {
        border: 1px solid #fecaca;
        background: #fff1f2;
        color: #b91c1c;
    }

    .citas-content {
        padding: 24px 26px 28px;
    }

    .citas-table-wrapper {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
    }

    .citas-table {
        width: 100%;
        min-width: 1120px;
        border-collapse: collapse;
    }

    .citas-table th,
    .citas-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #e8eef5;
        text-align: left;
        vertical-align: middle;
        font-size: 13px;
    }

    .citas-table th {
        background: #f8fafc;
        color: #334155;
        font-weight: 800;
    }

    .citas-table tbody tr:hover {
        background: #f8fbff;
    }

    .citas-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .cita-fecha {
        white-space: nowrap;
    }

    .cita-fecha strong {
        display: block;
        color: #0f2747;
    }

    .cita-fecha small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .cita-mascota strong,
    .cita-cliente strong {
        display: block;
        color: #1e293b;
    }

    .cita-mascota small,
    .cita-cliente small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .cita-motivo {
        min-width: 210px;
        max-width: 320px;
        color: #475569;
        line-height: 1.45;
    }

    .cita-estado {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .estado-pendiente {
        background: #fff7ed;
        color: #c2410c;
    }

    .estado-confirmada {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .estado-atendida {
        background: #f0fdf4;
        color: #15803d;
    }

    .estado-cancelada {
        background: #fff1f2;
        color: #be123c;
    }

    .cita-actions {
        display: flex;
        gap: 8px;
        white-space: nowrap;
    }

    .cita-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }

    .cita-action-edit {
        background: #fff7ed;
        color: #c2410c;
    }

    .cita-action-delete {
        background: #fff1f2;
        color: #be123c;
    }

    .citas-empty {
        padding: 46px 20px;
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        text-align: center;
        background: #fbfdff;
    }

    .citas-empty span {
        display: block;
        margin-bottom: 12px;
        font-size: 38px;
    }

    .citas-empty h2 {
        margin: 0 0 8px;
        color: #0f2747;
        font-size: 20px;
    }

    .citas-empty p {
        margin: 0 0 18px;
        color: #64748b;
    }

    @media (max-width: 900px) {
        .citas-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .citas-header,
        .citas-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .citas-btn-primary {
            width: 100%;
        }

        .citas-search {
            flex-wrap: wrap;
        }

        .citas-search input {
            flex-basis: 100%;
        }

        .citas-search select,
        .citas-search button,
        .citas-clear {
            flex: 1;
        }

        .citas-count {
            text-align: right;
        }
    }

    @media (max-width: 600px) {
        .citas-stats {
            grid-template-columns: 1fr;
        }

        .citas-header,
        .citas-toolbar,
        .citas-content,
        .citas-stats {
            padding-left: 18px;
            padding-right: 18px;
        }

        .citas-alert {
            margin-left: 18px;
            margin-right: 18px;
        }
    }

    /* =====================================================
       SOLICITUDES WEB DE CLIENTES
    ===================================================== */

    .reservas-panel {
        margin-top: 24px;
    }

    .reservas-header-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 5px 10px;
        margin-left: 8px;
        border-radius: 999px;
        background: #fff7ed;
        color: #c2410c;
        font-size: 12px;
        font-weight: 800;
        vertical-align: middle;
    }

    .reserva-cliente strong,
    .reserva-mascota strong {
        display: block;
        color: #1e293b;
    }

    .reserva-cliente small,
    .reserva-mascota small {
        display: block;
        margin-top: 3px;
        color: #64748b;
    }

    .reserva-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        white-space: nowrap;
    }

    .reserva-actions form {
        margin: 0;
    }

    .reserva-action {
        min-height: 34px;
        padding: 7px 10px;
        border: 0;
        border-radius: 8px;
        font: inherit;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
    }

    .reserva-confirmar {
        background: #f0fdf4;
        color: #15803d;
    }

    .reserva-confirmar:hover {
        background: #dcfce7;
    }

    .reserva-cancelar {
        background: #fff1f2;
        color: #be123c;
    }

    .reserva-cancelar:hover {
        background: #ffe4e6;
    }

    .reserva-solo-lectura {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .reserva-origen {
        display: inline-flex;
        align-items: center;
        padding: 5px 9px;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 11px;
        font-weight: 800;
    }

</style>

<div class="citas-page">
    <section class="citas-panel">

        <header class="citas-header">
            <div>
                <h1>📅 Citas veterinarias</h1>
                <p>
                    Consulta y administra la agenda de atención.
                </p>
            </div>

            <a
                class="citas-btn citas-btn-primary"
                href="<?= citaEscapar(url($rutaCrear)) ?>"
            >
                ＋ Registrar cita
            </a>
        </header>

        <div class="citas-stats">
            <article class="citas-stat">
                <span>Total de citas</span>
                <strong><?= $estadisticas['total'] ?></strong>
            </article>

            <article class="citas-stat">
                <span>Citas para hoy</span>
                <strong><?= $estadisticas['hoy'] ?></strong>
            </article>

            <article class="citas-stat">
                <span>Próximas citas</span>
                <strong><?= $estadisticas['proximas'] ?></strong>
            </article>

            <article class="citas-stat">
                <span>Citas pendientes</span>
                <strong><?= $estadisticas['pendientes'] ?></strong>
            </article>
        </div>

        <div class="citas-toolbar">
            <form
                class="citas-search"
                method="GET"
                action=""
            >
                <input
                    type="search"
                    name="buscar"
                    value="<?= citaEscapar($buscar) ?>"
                    placeholder="Buscar mascota, propietario, cédula o motivo"
                    aria-label="Buscar citas"
                >

                <select
                    name="estado"
                    aria-label="Filtrar por estado"
                >
                    <option value="">Todos los estados</option>

                    <?php foreach ($estadosPermitidos as $estado): ?>
                        <option
                            value="<?= citaEscapar($estado) ?>"
                            <?= $estadoFiltro === $estado
                                ? 'selected'
                                : '' ?>
                        >
                            <?= citaEscapar($estado) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button
                    class="citas-btn"
                    type="submit"
                >
                    🔎 Buscar
                </button>

                <?php if (
                    $buscar !== '' ||
                    $estadoFiltro !== ''
                ): ?>
                    <a
                        class="citas-btn citas-clear"
                        href="<?= citaEscapar(
                            url('citas/index.php')
                        ) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>

            <div class="citas-count">
                <?= count($citas) ?>
                cita<?= count($citas) === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if ($mensajeExito !== ''): ?>
            <div
                class="citas-alert citas-alert-success"
                role="alert"
            >
                ✅ <?= citaEscapar($mensajeExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div
                class="citas-alert citas-alert-error"
                role="alert"
            >
                ⚠️ <?= citaEscapar($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="citas-content">
            <?php if ($citas !== []): ?>

                <div class="citas-table-wrapper">
                    <table class="citas-table">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Mascota</th>
                                <th>Propietario</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
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

                                $especieRaza = trim(
                                    (string) (
                                        $cita['mascota_especie'] ?? ''
                                    ) .
                                    ' · ' .
                                    (string) (
                                        $cita['mascota_raza'] ?? ''
                                    ),
                                    " \t\n\r\0\x0B·"
                                );

                                $idCita = (int) (
                                    $cita['id'] ?? 0
                                );

                                $rutaEditar = $rutaEditarBase .
                                    '?id=' .
                                    $idCita;

                                $rutaEliminar = $rutaEliminarBase .
                                    '?id=' .
                                    $idCita;
                                ?>

                                <tr>
                                    <td>
                                        <div class="cita-fecha">
                                            <strong>
                                                <?= citaEscapar(
                                                    citaFechaVisible(
                                                        $cita['fecha'] ?? ''
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                🕐
                                                <?= citaEscapar(
                                                    citaHoraVisible(
                                                        $cita['hora'] ?? ''
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cita-mascota">
                                            <strong>
                                                🐾
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $cita[
                                                            'mascota_nombre'
                                                        ] ?? ''
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $especieRaza,
                                                        'Sin especie o raza'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cita-cliente">
                                            <strong>
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $propietario,
                                                        'Sin propietario'
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                Cédula:
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $cita[
                                                            'cliente_cedula'
                                                        ] ?? '',
                                                        'No registrada'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cita-motivo">
                                            <?= citaEscapar(
                                                citaValorVisible(
                                                    $cita['motivo'] ?? '',
                                                    'Sin motivo registrado'
                                                )
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span
                                            class="cita-estado <?= citaEscapar(
                                                citaClaseEstado(
                                                    $cita['estado'] ?? ''
                                                )
                                            ) ?>"
                                        >
                                            <?= citaEscapar(
                                                citaValorVisible(
                                                    $cita['estado'] ?? '',
                                                    'Pendiente'
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="cita-actions">
                                            <a
                                                class="
                                                    cita-action
                                                    cita-action-edit
                                                "
                                                href="<?= citaEscapar(
                                                    url($rutaEditar)
                                                ) ?>"
                                            >
                                                ✏️ Editar
                                            </a>

                                            <a
                                                class="
                                                    cita-action
                                                    cita-action-delete
                                                "
                                                href="<?= citaEscapar(
                                                    url($rutaEliminar)
                                                ) ?>"
                                                onclick="
                                                    return confirm(
                                                        '¿Seguro que deseas eliminar esta cita?'
                                                    );
                                                "
                                            >
                                                🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="citas-empty">
                    <span>📅</span>

                    <?php if (
                        $buscar !== '' ||
                        $estadoFiltro !== ''
                    ): ?>
                        <h2>No se encontraron resultados</h2>

                        <p>
                            No existen citas que coincidan con los filtros.
                        </p>

                        <a
                            class="citas-btn citas-clear"
                            href="<?= citaEscapar(
                                url('citas/index.php')
                            ) ?>"
                        >
                            Mostrar todas
                        </a>
                    <?php else: ?>
                        <h2>Todavía no hay citas registradas</h2>

                        <p>
                            Registra la primera cita para comenzar.
                        </p>

                        <a
                            class="citas-btn citas-btn-primary"
                            href="<?= citaEscapar(url($rutaCrear)) ?>"
                        >
                            ＋ Registrar cita
                        </a>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
    </section>

    <section class="citas-panel reservas-panel">

        <header class="citas-header">
            <div>
                <h1>
                    📥 Solicitudes de citas de clientes

                    <?php if ($solicitudesPendientes > 0): ?>
                        <span class="reservas-header-badge">
                            <?= $solicitudesPendientes ?>
                            pendiente<?= $solicitudesPendientes === 1 ? '' : 's' ?>
                        </span>
                    <?php endif; ?>
                </h1>

                <p>
                    Solicitudes enviadas desde el registro público de clientes.
                    Administrador y Recepcionista pueden confirmarlas o cancelarlas.
                </p>
            </div>

            <div class="citas-count">
                <?= $totalSolicitudes ?>
                solicitud<?= $totalSolicitudes === 1 ? '' : 'es' ?>
            </div>
        </header>

        <?php if ($mensajeReservaExito !== ''): ?>
            <div
                class="citas-alert citas-alert-success"
                role="alert"
            >
                ✅ <?= citaEscapar($mensajeReservaExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeReservaError !== ''): ?>
            <div
                class="citas-alert citas-alert-error"
                role="alert"
            >
                ⚠️ <?= citaEscapar($mensajeReservaError) ?>
            </div>
        <?php endif; ?>

        <div class="citas-content">

            <?php if (!$tablaReservasDisponible): ?>

                <div class="citas-empty">
                    <span>📥</span>

                    <h2>Todavía no existe la tabla de reservas</h2>

                    <p>
                        La tabla <strong>reservas_citas</strong> se creará
                        cuando se utilice el formulario público de reservas.
                    </p>
                </div>

            <?php elseif ($solicitudesClientes === []): ?>

                <div class="citas-empty">
                    <span>🐾</span>

                    <h2>No hay solicitudes de clientes</h2>

                    <p>
                        Cuando un cliente solicite una cita desde la página
                        pública, aparecerá aquí.
                    </p>
                </div>

            <?php else: ?>

                <div class="citas-table-wrapper">

                    <table class="citas-table">

                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Cliente</th>
                                <th>Mascota</th>
                                <th>Motivo</th>
                                <th>Origen</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($solicitudesClientes as $reserva): ?>
                                <?php
                                $idReserva = (int) (
                                    $reserva['id'] ?? 0
                                );

                                $estadoReserva = trim(
                                    (string) (
                                        $reserva['estado'] ?? 'Pendiente'
                                    )
                                );

                                $esPendiente =
                                    strtolower($estadoReserva) === 'pendiente';
                                ?>

                                <tr>

                                    <td>
                                        <div class="cita-fecha">
                                            <strong>
                                                <?= citaEscapar(
                                                    citaFechaVisible(
                                                        $reserva['fecha'] ?? ''
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                🕐
                                                <?= citaEscapar(
                                                    citaHoraVisible(
                                                        $reserva['hora'] ?? ''
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="reserva-cliente">
                                            <strong>
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $reserva[
                                                            'nombre_cliente'
                                                        ] ?? '',
                                                        'Cliente'
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $reserva[
                                                            'correo_cliente'
                                                        ] ?? '',
                                                        'Sin correo'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="reserva-mascota">
                                            <strong>
                                                🐾
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $reserva[
                                                            'nombre_mascota'
                                                        ] ?? '',
                                                        'Sin nombre'
                                                    )
                                                ) ?>
                                            </strong>

                                            <small>
                                                <?= citaEscapar(
                                                    citaValorVisible(
                                                        $reserva['especie'] ?? '',
                                                        'Sin especie'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="cita-motivo">
                                            <?= citaEscapar(
                                                citaValorVisible(
                                                    $reserva['motivo'] ?? '',
                                                    'Sin motivo registrado'
                                                )
                                            ) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="reserva-origen">
                                            🌐 Web pública
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="cita-estado <?= citaEscapar(
                                                citaClaseEstado($estadoReserva)
                                            ) ?>"
                                        >
                                            <?= citaEscapar(
                                                citaValorVisible(
                                                    $estadoReserva,
                                                    'Pendiente'
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if (
                                            $esPendiente &&
                                            $puedeGestionarSolicitudes
                                        ): ?>

                                            <div class="reserva-actions">

                                                <form
                                                    method="POST"
                                                    action=""
                                                    onsubmit="
                                                        return confirm(
                                                            '¿Confirmar esta cita solicitada por el cliente?'
                                                        );
                                                    "
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= citaEscapar(
                                                            $csrfCitas
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="reserva_id"
                                                        value="<?= $idReserva ?>"
                                                    >

                                                    <button
                                                        class="
                                                            reserva-action
                                                            reserva-confirmar
                                                        "
                                                        type="submit"
                                                        name="accion_reserva"
                                                        value="confirmar"
                                                    >
                                                        ✅ Confirmar
                                                    </button>
                                                </form>

                                                <form
                                                    method="POST"
                                                    action=""
                                                    onsubmit="
                                                        return confirm(
                                                            '¿Cancelar esta solicitud de cita?'
                                                        );
                                                    "
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= citaEscapar(
                                                            $csrfCitas
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="reserva_id"
                                                        value="<?= $idReserva ?>"
                                                    >

                                                    <button
                                                        class="
                                                            reserva-action
                                                            reserva-cancelar
                                                        "
                                                        type="submit"
                                                        name="accion_reserva"
                                                        value="cancelar"
                                                    >
                                                        ❌ Cancelar
                                                    </button>
                                                </form>

                                            </div>

                                        <?php elseif ($esPendiente): ?>

                                            <span class="reserva-solo-lectura">
                                                Solo lectura
                                            </span>

                                        <?php else: ?>

                                            <span class="reserva-solo-lectura">
                                                <?= strtolower($estadoReserva)
                                                    === 'confirmada'
                                                    ? '✅ Procesada'
                                                    : '❌ Procesada' ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>