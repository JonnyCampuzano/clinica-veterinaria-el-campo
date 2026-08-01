<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('clientes/index.php');
}

verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Cliente no válido.');
    redirect('clientes/index.php');
}

$stmt = $pdo->prepare('DELETE FROM clientes WHERE id = ?');
$stmt->execute([$id]);

flash('success', 'Cliente eliminado correctamente.');
redirect('clientes/index.php');
