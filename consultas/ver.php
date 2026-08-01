<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Consulta no válida.');
    redirect('consultas/index.php');
}

$stmt = $pdo->prepare(
    "SELECT co.*, m.nombre AS mascota, m.especie, m.raza, m.alergias,
            CONCAT(c.nombres, ' ', c.apellidos) AS cliente,
            c.telefono, u.nombre AS veterinario
     FROM consultas co
     INNER JOIN mascotas m ON m.id = co.mascota_id
     INNER JOIN clientes c ON c.id = m.cliente_id
     INNER JOIN usuarios u ON u.id = co.usuario_id
     WHERE co.id = ?"
);
$stmt->execute([$id]);
$consulta = $stmt->fetch();

if (!$consulta) {
    flash('error', 'La consulta no existe.');
    redirect('consultas/index.php');
}

$pageTitle = 'Detalle de consulta';
$activePage = 'consultas';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <p>Registro clínico de <?= e($consulta['mascota']) ?></p>
    <a class="btn btn-secondary" href="<?= e(url('consultas/index.php')) ?>">← Volver</a>
</div>

<div class="card form-card">
    <div class="detail-grid">
        <div class="detail-item"><strong>Fecha</strong><span><?= e(date('d/m/Y', strtotime($consulta['fecha']))) ?></span></div>
        <div class="detail-item"><strong>Veterinario</strong><span><?= e($consulta['veterinario']) ?></span></div>
        <div class="detail-item"><strong>Mascota</strong><span><?= e($consulta['mascota'] . ' · ' . $consulta['especie']) ?></span></div>
        <div class="detail-item"><strong>Propietario</strong><span><?= e($consulta['cliente'] . ' · ' . $consulta['telefono']) ?></span></div>
        <div class="detail-item"><strong>Raza</strong><span><?= e($consulta['raza'] ?: 'No registrada') ?></span></div>
        <div class="detail-item"><strong>Alergias</strong><span><?= e($consulta['alergias'] ?: 'No registradas') ?></span></div>
        <div class="detail-item"><strong>Peso</strong><span><?= $consulta['peso'] !== null ? e($consulta['peso']) . ' kg' : 'No registrado' ?></span></div>
        <div class="detail-item"><strong>Temperatura</strong><span><?= $consulta['temperatura'] !== null ? e($consulta['temperatura']) . ' °C' : 'No registrada' ?></span></div>
        <div class="detail-item full" style="grid-column:1/-1"><strong>Motivo</strong><span><?= nl2br(e($consulta['motivo'])) ?></span></div>
        <div class="detail-item full" style="grid-column:1/-1"><strong>Diagnóstico</strong><span><?= nl2br(e($consulta['diagnostico'])) ?></span></div>
        <div class="detail-item full" style="grid-column:1/-1"><strong>Tratamiento e indicaciones</strong><span><?= nl2br(e($consulta['tratamiento'])) ?></span></div>
        <div class="detail-item"><strong>Próxima cita</strong><span><?= $consulta['proxima_cita'] ? e(date('d/m/Y', strtotime($consulta['proxima_cita']))) : 'No programada' ?></span></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
