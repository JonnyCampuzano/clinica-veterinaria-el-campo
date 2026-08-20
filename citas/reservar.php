<?php
declare(strict_types=1);

$raiz = dirname(__DIR__);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/config/crypto.php';
require_once $raiz . '/includes/auth.php';

require_login();

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No existe una conexión PDO válida.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function reserva_e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function reserva_rol(mixed $rol): string
{
    $valor = trim((string) $rol);

    $valor = function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);

    return strtr($valor, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ñ' => 'n',
    ]);
}

/*
 * El cliente que inició sesión puede reservar.
 * También se permite abrirlo como administrador para pruebas.
 */
$rol = reserva_rol($_SESSION['rol'] ?? $_SESSION['role'] ?? '');

if (
    $rol !== '' &&
    !in_array($rol, ['cliente', 'usuario', 'paciente', 'administrador', 'admin'], true)
) {
    header('Location: ' . url('panel.php'));
    exit;
}

/* =====================================================
   ASEGURAR TABLA
===================================================== */

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS reservas_citas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NULL,
            nombre_cliente VARCHAR(512) NULL,
            correo_cliente VARCHAR(512) NULL,
            nombre_mascota VARCHAR(120) NOT NULL DEFAULT '',
            especie VARCHAR(80) NOT NULL DEFAULT '',
            fecha DATE NULL,
            hora TIME NULL,
            motivo TEXT NULL,
            estado ENUM('Pendiente','Confirmada','Cancelada')
                NOT NULL DEFAULT 'Pendiente',
            fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservas_fecha (fecha),
            INDEX idx_reservas_estado (estado),
            INDEX idx_reservas_usuario (usuario_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
} catch (Throwable $error) {
    error_log('Reserva pública: error creando tabla: ' . $error->getMessage());
}

/* =====================================================
   DATOS DEL USUARIO
===================================================== */

$usuarioId = (int) (
    $_SESSION['usuario_id']
    ?? $_SESSION['id_usuario']
    ?? $_SESSION['user_id']
    ?? 0
);

$nombreCliente = trim((string) (
    $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? ''
));

$correoCliente = trim((string) (
    $_SESSION['email']
    ?? $_SESSION['correo']
    ?? ''
));

/*
 * Si la sesión no contiene nombre/correo, intentamos obtenerlos
 * desde usuarios sin asumir si la columna se llama email o correo.
 */
if ($usuarioId > 0 && ($nombreCliente === '' || $correoCliente === '')) {
    try {
        $columnasUsuarios = $pdo->query(
            'SHOW COLUMNS FROM usuarios'
        )->fetchAll(PDO::FETCH_COLUMN);

        $colNombre = in_array('nombre_enc', $columnasUsuarios, true)
            ? 'nombre_enc'
            : (in_array('nombre', $columnasUsuarios, true) ? 'nombre' : null);

        $colCorreo = in_array('correo_enc', $columnasUsuarios, true)
            ? 'correo_enc'
            : (
                in_array('email', $columnasUsuarios, true)
                    ? 'email'
                    : (
                        in_array('correo', $columnasUsuarios, true)
                            ? 'correo'
                            : null
                    )
            );

        if ($colNombre !== null || $colCorreo !== null) {
            $select = ['id'];

            if ($colNombre !== null) {
                $select[] = $colNombre . ' AS nombre_usuario';
            }

            if ($colCorreo !== null) {
                $select[] = $colCorreo . ' AS correo_usuario';
            }

            $stmtUsuario = $pdo->prepare(
                'SELECT ' . implode(', ', $select) .
                ' FROM usuarios WHERE id = :id LIMIT 1'
            );

            $stmtUsuario->execute([':id' => $usuarioId]);

            $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

            if (is_array($usuario)) {
                if ($nombreCliente === '') {
                    $nombreCliente = decrypt_personal(
                        $usuario['nombre_usuario'] ?? null
                    );
                }

                if ($correoCliente === '') {
                    $correoCliente = decrypt_personal(
                        $usuario['correo_usuario'] ?? null
                    );
                }
            }
        }
    } catch (Throwable $error) {
        error_log(
            'Reserva pública: no se pudieron cargar datos del usuario: ' .
            $error->getMessage()
        );
    }
}

if ($nombreCliente === '') {
    $nombreCliente = 'Cliente';
}

/* =====================================================
   CSRF
===================================================== */

if (
    empty($_SESSION['csrf_reservar_cita']) ||
    !is_string($_SESSION['csrf_reservar_cita'])
) {
    $_SESSION['csrf_reservar_cita'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['csrf_reservar_cita'];

/* =====================================================
   FORMULARIO
===================================================== */

$datos = [
    'nombre_mascota' => '',
    'especie' => '',
    'fecha' => date('Y-m-d'),
    'hora' => '',
    'motivo' => '',
];

$errores = [];
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = [
        'nombre_mascota' => trim((string) ($_POST['nombre_mascota'] ?? '')),
        'especie' => trim((string) ($_POST['especie'] ?? '')),
        'fecha' => trim((string) ($_POST['fecha'] ?? '')),
        'hora' => trim((string) ($_POST['hora'] ?? '')),
        'motivo' => trim((string) ($_POST['motivo'] ?? '')),
    ];

    $csrfRecibido = (string) ($_POST['csrf_token'] ?? '');

    if (
        $csrfRecibido === '' ||
        !hash_equals($csrf, $csrfRecibido)
    ) {
        $errores[] = 'La sesión del formulario expiró. Recarga la página.';
    }

    if ($datos['nombre_mascota'] === '') {
        $errores[] = 'Ingresa el nombre de la mascota.';
    }

    if ($datos['especie'] === '') {
        $errores[] = 'Ingresa la especie de la mascota.';
    }

    $fechaObj = DateTime::createFromFormat('Y-m-d', $datos['fecha']);

    if (
        !$fechaObj instanceof DateTime ||
        $fechaObj->format('Y-m-d') !== $datos['fecha']
    ) {
        $errores[] = 'Selecciona una fecha válida.';
    } elseif ($datos['fecha'] < date('Y-m-d')) {
        $errores[] = 'La fecha no puede ser anterior a hoy.';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $datos['hora'])) {
        $errores[] = 'Selecciona una hora válida.';
    }

    if ($datos['motivo'] === '') {
        $errores[] = 'Describe brevemente el motivo de la cita.';
    }

    if ($errores === []) {
        try {
            /*
             * Evitar reservas idénticas repetidas del mismo usuario
             * en la misma fecha/hora.
             */
            $duplicada = $pdo->prepare(
                'SELECT id
                 FROM reservas_citas
                 WHERE usuario_id <=> :usuario_id
                   AND fecha = :fecha
                   AND hora = :hora
                   AND estado = "Pendiente"
                 LIMIT 1'
            );

            $duplicada->execute([
                ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                ':fecha' => $datos['fecha'],
                ':hora' => $datos['hora'],
            ]);

            if ($duplicada->fetchColumn()) {
                $errores[] =
                    'Ya tienes una solicitud pendiente para esa fecha y hora.';
            } else {
                $insertar = $pdo->prepare(
                    'INSERT INTO reservas_citas (
                        usuario_id,
                        nombre_cliente,
                        correo_cliente,
                        nombre_mascota,
                        especie,
                        fecha,
                        hora,
                        motivo,
                        estado
                    ) VALUES (
                        :usuario_id,
                        :nombre_cliente,
                        :correo_cliente,
                        :nombre_mascota,
                        :especie,
                        :fecha,
                        :hora,
                        :motivo,
                        "Pendiente"
                    )'
                );

                $insertar->execute([
                    ':usuario_id' =>
                        $usuarioId > 0 ? $usuarioId : null,
                    ':nombre_cliente' =>
                        encrypt_personal($nombreCliente),
                    ':correo_cliente' =>
                        encrypt_personal($correoCliente),
                    ':nombre_mascota' =>
                        $datos['nombre_mascota'],
                    ':especie' =>
                        $datos['especie'],
                    ':fecha' =>
                        $datos['fecha'],
                    ':hora' =>
                        $datos['hora'],
                    ':motivo' =>
                        $datos['motivo'],
                ]);

                $exito =
                    'Tu solicitud de cita fue enviada correctamente. ' .
                    'La clínica debe confirmarla.';

                $datos = [
                    'nombre_mascota' => '',
                    'especie' => '',
                    'fecha' => date('Y-m-d'),
                    'hora' => '',
                    'motivo' => '',
                ];

                $_SESSION['csrf_reservar_cita'] =
                    bin2hex(random_bytes(32));

                $csrf = (string) $_SESSION['csrf_reservar_cita'];
            }
        } catch (Throwable $error) {
            error_log(
                'Reserva pública: error guardando solicitud: ' .
                $error->getMessage()
            );

            $errores[] =
                'No se pudo guardar la solicitud. ' .
                'Verifica la estructura de reservas_citas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservar cita | Clínica Veterinaria El Campo</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#15233b}
.top{background:#fff;border-bottom:1px solid #dbe5f0;padding:18px 24px}
.top-inner{max-width:1000px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:15px}
.brand{font-weight:800;color:#08264f;font-size:19px}
.back{padding:10px 14px;border:1px solid #cbd5e1;border-radius:10px;text-decoration:none;color:#334155;font-weight:700}
.wrap{max-width:900px;margin:34px auto;padding:0 18px}
.card{overflow:hidden;background:#fff;border:1px solid #dbe5f0;border-radius:18px;box-shadow:0 14px 38px rgba(15,35,65,.08)}
.header{padding:25px 28px;background:linear-gradient(135deg,#fff,#f7fbff);border-bottom:1px solid #e2e8f0}
.header h1{margin:0 0 7px;color:#08264f;font-size:26px}
.header p{margin:0;color:#64748b}
.body{padding:28px}
.info{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px}
.info div{padding:13px 15px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:11px}
.info span{display:block;font-size:11px;text-transform:uppercase;font-weight:800;color:#64748b;margin-bottom:5px}
.alert{padding:13px 15px;border-radius:10px;margin-bottom:20px;font-weight:700;font-size:14px}
.error{background:#fff1f2;border:1px solid #fecaca;color:#b91c1c}
.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.field{display:flex;flex-direction:column;gap:8px}
.full{grid-column:1/-1}
label{font-weight:800;font-size:14px}
input,textarea{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;outline:none}
input:focus,textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
textarea{min-height:110px;resize:vertical}
.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:28px;padding-top:22px;border-top:1px solid #e2e8f0}
.btn{border:0;border-radius:10px;padding:11px 18px;font:inherit;font-weight:800;cursor:pointer;text-decoration:none}
.primary{background:#2563eb;color:#fff;box-shadow:0 8px 18px rgba(37,99,235,.22)}
.secondary{background:#e9eef5;color:#334155}
.note{margin-top:20px;padding:13px 15px;border-radius:11px;background:#eff6ff;color:#1e40af;font-size:13px;line-height:1.45}
@media(max-width:680px){.grid,.info{grid-template-columns:1fr}.full{grid-column:auto}.actions{flex-direction:column-reverse}.btn{width:100%;text-align:center}}
</style>
</head>
<body>
<div class="top">
    <div class="top-inner">
        <div class="brand">🐾 Clínica Veterinaria El Campo</div>
        <a class="back" href="<?= reserva_e(url('public/index.php')) ?>">← Página principal</a>
    </div>
</div>

<div class="wrap">
<section class="card">
<header class="header">
    <h1>📅 Reservar una cita</h1>
    <p>Envía tu solicitud y el personal de la clínica podrá confirmarla.</p>
</header>

<div class="body">
    <?php if ($exito !== ''): ?>
        <div class="alert success">✅ <?= reserva_e($exito) ?></div>
    <?php endif; ?>

    <?php if ($errores !== []): ?>
        <div class="alert error">
            <?php foreach ($errores as $error): ?>
                <div>⚠️ <?= reserva_e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="info">
        <div>
            <span>Cliente</span>
            <strong><?= reserva_e($nombreCliente) ?></strong>
        </div>
        <div>
            <span>Correo</span>
            <strong><?= reserva_e($correoCliente !== '' ? $correoCliente : 'No registrado') ?></strong>
        </div>
    </div>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= reserva_e($csrf) ?>">

        <div class="grid">
            <div class="field">
                <label for="nombre_mascota">Nombre de la mascota *</label>
                <input
                    id="nombre_mascota"
                    name="nombre_mascota"
                    maxlength="120"
                    value="<?= reserva_e($datos['nombre_mascota']) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="especie">Especie *</label>
                <input
                    id="especie"
                    name="especie"
                    maxlength="80"
                    placeholder="Ej. Perro, gato"
                    value="<?= reserva_e($datos['especie']) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="fecha">Fecha *</label>
                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= reserva_e($datos['fecha']) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="hora">Hora *</label>
                <input
                    type="time"
                    id="hora"
                    name="hora"
                    value="<?= reserva_e($datos['hora']) ?>"
                    required
                >
            </div>

            <div class="field full">
                <label for="motivo">Motivo *</label>
                <textarea
                    id="motivo"
                    name="motivo"
                    maxlength="1000"
                    placeholder="Ej. Vacunación, revisión general..."
                    required
                ><?= reserva_e($datos['motivo']) ?></textarea>
            </div>
        </div>

        <div class="note">
            🔐 El nombre y correo del cliente se guardan cifrados.
            La solicitud quedará con estado <strong>Pendiente</strong>
            hasta que la clínica la gestione.
        </div>

        <div class="actions">
            <a class="btn secondary" href="<?= reserva_e(url('public/index.php')) ?>">
                Cancelar
            </a>
            <button class="btn primary" type="submit">
                📥 Enviar solicitud
            </button>
        </div>
    </form>
</div>
</section>
</div>
</body>
</html>
