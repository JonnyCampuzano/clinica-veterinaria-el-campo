<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexion.php';

require_login();
require_permission('reservas.ver_propias');

$usuarioActual = current_user();
$usuarioId = $usuarioActual['id'];
$nombreUsuario = $usuarioActual['nombre'] !== '' ? $usuarioActual['nombre'] : 'Usuario';

$mensaje = '';
$error = '';

$estadosEditables = ['pendiente', 'confirmada'];

/*
|--------------------------------------------------------------------------
| PROCESAR ACCIONES (crear, editar, cancelar)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = (string) ($_POST['accion'] ?? '');

    try {

        if ($accion === 'crear' && can('reservas.crear')) {

            $nombreMascota = trim((string) ($_POST['nombre_mascota'] ?? ''));
            $especie = trim((string) ($_POST['especie'] ?? ''));
            $fecha = trim((string) ($_POST['fecha'] ?? ''));
            $hora = trim((string) ($_POST['hora'] ?? ''));
            $motivo = trim((string) ($_POST['motivo'] ?? ''));

            if ($nombreMascota === '' || $especie === '' || $fecha === '' || $hora === '') {

                $error = 'Completa todos los campos obligatorios.';

            } else {

                $sql = "
                    INSERT INTO reservas_citas
                        (usuario_id, nombre_mascota, especie, fecha, hora, motivo, estado, fecha_registro)
                    VALUES
                        (:usuario_id, :nombre_mascota, :especie, :fecha, :hora, :motivo, 'pendiente', NOW())
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':usuario_id' => $usuarioId,
                    ':nombre_mascota' => $nombreMascota,
                    ':especie' => $especie,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':motivo' => $motivo
                ]);

                $mensaje = 'Reserva creada correctamente.';
            }

        } elseif ($accion === 'editar' && can('reservas.editar_propias')) {

            $id = (int) ($_POST['id'] ?? 0);
            $nombreMascota = trim((string) ($_POST['nombre_mascota'] ?? ''));
            $especie = trim((string) ($_POST['especie'] ?? ''));
            $fecha = trim((string) ($_POST['fecha'] ?? ''));
            $hora = trim((string) ($_POST['hora'] ?? ''));
            $motivo = trim((string) ($_POST['motivo'] ?? ''));

            if ($id <= 0 || $nombreMascota === '' || $especie === '' || $fecha === '' || $hora === '') {

                $error = 'Completa todos los campos obligatorios.';

            } else {

                // Solo permite editar reservas propias que sigan pendientes o confirmadas
                $sql = "
                    UPDATE reservas_citas
                    SET nombre_mascota = :nombre_mascota,
                        especie = :especie,
                        fecha = :fecha,
                        hora = :hora,
                        motivo = :motivo
                    WHERE id = :id
                      AND usuario_id = :usuario_id
                      AND estado IN ('pendiente', 'confirmada')
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':nombre_mascota' => $nombreMascota,
                    ':especie' => $especie,
                    ':fecha' => $fecha,
                    ':hora' => $hora,
                    ':motivo' => $motivo,
                    ':id' => $id,
                    ':usuario_id' => $usuarioId
                ]);

                if ($stmt->rowCount() > 0) {
                    $mensaje = 'Reserva actualizada correctamente.';
                } else {
                    $error = 'No se pudo editar esta reserva (ya no está pendiente/confirmada o no te pertenece).';
                }
            }

        } elseif ($accion === 'cancelar' && can('reservas.cancelar_propias')) {

            $id = (int) ($_POST['id'] ?? 0);

            $sql = "
                UPDATE reservas_citas
                SET estado = 'cancelada'
                WHERE id = :id
                  AND usuario_id = :usuario_id
                  AND estado IN ('pendiente', 'confirmada')
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':id' => $id,
                ':usuario_id' => $usuarioId
            ]);

            if ($stmt->rowCount() > 0) {
                $mensaje = 'Reserva cancelada.';
            } else {
                $error = 'No se pudo cancelar esta reserva.';
            }

        } else {

            $error = 'Acción no válida.';
        }

    } catch (PDOException $e) {

        $error = 'Ocurrió un error al procesar tu solicitud.';
    }
}

/*
|--------------------------------------------------------------------------
| OBTENER RESERVAS DEL USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id, nombre_mascota, especie, fecha, hora, motivo, estado, fecha_registro
    FROM reservas_citas
    WHERE usuario_id = :usuario_id
    ORDER BY fecha DESC, hora DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':usuario_id' => $usuarioId]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$etiquetasEstado = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'completada' => 'Completada'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas | Clínica Veterinaria El Campo</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f7faff;
            color: #0f172a;
            padding: 30px 20px;
        }

        .contenedor {
            max-width: 900px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .topbar h1 { font-size: 26px; }

        .topbar a {
            color: #147539;
            font-weight: 700;
            text-decoration: none;
        }

        .aviso {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .aviso.exito {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }

        .aviso.error {
            background: #fff1f1;
            border: 1px solid #ffb9b9;
            color: #c42020;
        }

        .tarjeta {
            background: #fff;
            border: 1px solid #dce7f5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 12px 30px rgba(40, 78, 130, 0.08);
        }

        .tarjeta h2 { margin-bottom: 16px; font-size: 19px; }

        .grid-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .grid-form .full { grid-column: 1 / -1; }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 14px;
        }

        input, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #bfd0ea;
            border-radius: 8px;
            font-size: 14px;
        }

        textarea { resize: vertical; min-height: 60px; }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #2f7d4a;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            margin-top: 14px;
        }

        .btn:hover { background: #27693e; }

        .btn-cancelar {
            background: #dc2626;
        }

        .btn-cancelar:hover { background: #b91c1c; }

        .btn-secundario {
            background: #2563eb;
        }

        .btn-secundario:hover { background: #1d4ed8; }

        .reserva {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 14px;
        }

        .reserva-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .reserva-top strong { font-size: 16px; }

        .estado {
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .estado-pendiente { background: #fef3c7; color: #92400e; }
        .estado-confirmada { background: #dbeafe; color: #1d4ed8; }
        .estado-cancelada { background: #fee2e2; color: #991b1b; }
        .estado-completada { background: #dcfce7; color: #166534; }

        .reserva p { color: #475569; font-size: 14px; margin-bottom: 4px; }

        .reserva-acciones {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .form-editar {
            display: none;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
        }

        .form-editar.visible { display: block; }

        .vacio {
            color: #64748b;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="contenedor">

    <div class="topbar">
        <h1>🐾 Mis Reservas</h1>
        <a href="<?= e(url('logout.php')) ?>">🚪 Cerrar sesión</a>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="aviso exito"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="aviso error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (can('reservas.crear')): ?>
    <div class="tarjeta">
        <h2>Nueva reserva</h2>

        <form method="POST">
            <input type="hidden" name="accion" value="crear">

            <div class="grid-form">
                <div>
                    <label>Nombre de la mascota</label>
                    <input type="text" name="nombre_mascota" required>
                </div>

                <div>
                    <label>Especie</label>
                    <input type="text" name="especie" placeholder="Perro, gato..." required>
                </div>

                <div>
                    <label>Fecha</label>
                    <input type="date" name="fecha" required>
                </div>

                <div>
                    <label>Hora</label>
                    <input type="time" name="hora" required>
                </div>

                <div class="full">
                    <label>Motivo</label>
                    <textarea name="motivo" placeholder="Ej: control anual, vacuna, dolor..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn">Reservar cita</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="tarjeta">
        <h2>Historial de reservas</h2>

        <?php if (empty($reservas)): ?>

            <p class="vacio">Aún no tienes reservas registradas.</p>

        <?php else: ?>

            <?php foreach ($reservas as $reserva): ?>

                <?php
                $editable = in_array($reserva['estado'], $estadosEditables, true);
                $idReserva = (int) $reserva['id'];
                ?>

                <div class="reserva">

                    <div class="reserva-top">
                        <strong><?= e($reserva['nombre_mascota']) ?> (<?= e($reserva['especie']) ?>)</strong>
                        <span class="estado estado-<?= e($reserva['estado']) ?>">
                            <?= e($etiquetasEstado[$reserva['estado']] ?? $reserva['estado']) ?>
                        </span>
                    </div>

                    <p>📅 <?= e($reserva['fecha']) ?> · 🕒 <?= e($reserva['hora']) ?></p>

                    <?php if (!empty($reserva['motivo'])): ?>
                        <p>📝 <?= e($reserva['motivo']) ?></p>
                    <?php endif; ?>

                    <?php if ($editable && (can('reservas.editar_propias') || can('reservas.cancelar_propias'))): ?>

                        <div class="reserva-acciones">

                            <?php if (can('reservas.editar_propias')): ?>
                                <button
                                    type="button"
                                    class="btn btn-secundario"
                                    onclick="document.getElementById('editar-<?= e((string) $idReserva) ?>').classList.toggle('visible')"
                                >
                                    ✏️ Editar
                                </button>
                            <?php endif; ?>

                            <?php if (can('reservas.cancelar_propias')): ?>
                                <form method="POST" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                    <input type="hidden" name="accion" value="cancelar">
                                    <input type="hidden" name="id" value="<?= e((string) $idReserva) ?>">
                                    <button type="submit" class="btn btn-cancelar">✖ Cancelar</button>
                                </form>
                            <?php endif; ?>

                        </div>

                        <?php if (can('reservas.editar_propias')): ?>
                        <div class="form-editar" id="editar-<?= e((string) $idReserva) ?>">

                            <form method="POST">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="id" value="<?= e((string) $idReserva) ?>">

                                <div class="grid-form">
                                    <div>
                                        <label>Nombre de la mascota</label>
                                        <input type="text" name="nombre_mascota" value="<?= e($reserva['nombre_mascota']) ?>" required>
                                    </div>

                                    <div>
                                        <label>Especie</label>
                                        <input type="text" name="especie" value="<?= e($reserva['especie']) ?>" required>
                                    </div>

                                    <div>
                                        <label>Fecha</label>
                                        <input type="date" name="fecha" value="<?= e($reserva['fecha']) ?>" required>
                                    </div>

                                    <div>
                                        <label>Hora</label>
                                        <input type="time" name="hora" value="<?= e($reserva['hora']) ?>" required>
                                    </div>

                                    <div class="full">
                                        <label>Motivo</label>
                                        <textarea name="motivo"><?= e((string) $reserva['motivo']) ?></textarea>
                                    </div>
                                </div>

                                <button type="submit" class="btn">Guardar cambios</button>
                            </form>

                        </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
