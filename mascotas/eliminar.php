<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('mascotas/index.php');
}

verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Mascota no válida.');
    redirect('mascotas/index.php');
}

$stmt = $pdo->prepare('DELETE FROM mascotas WHERE id = ?');
$stmt->execute([$id]);

flash('success', 'Mascota eliminada correctamente.');
redirect('mascotas/index.php');
