<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('citas/index.php');
}

verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Cita no válida.');
    redirect('citas/index.php');
}

$stmt = $pdo->prepare('DELETE FROM citas WHERE id = ?');
$stmt->execute([$id]);

flash('success', 'Cita eliminada correctamente.');
redirect('citas/index.php');
