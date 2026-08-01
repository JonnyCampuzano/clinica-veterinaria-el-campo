<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('inventario/index.php');
}

verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Producto no válido.');
    redirect('inventario/index.php');
}

$stmt = $pdo->prepare('DELETE FROM inventario WHERE id = ?');
$stmt->execute([$id]);

flash('success', 'Producto eliminado correctamente.');
redirect('inventario/index.php');
