<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Panel';
$activePage = $activePage ?? '';
$user = current_user();
$flashMessage = get_flash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestión para la Clínica Veterinaria El Campo">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('panel.php')) ?>">
            <span class="brand-logo">🐾</span>
            <span>
                <strong>Clínica Veterinaria</strong>
                <small>El Campo</small>
            </span>
        </a>

        <nav class="nav-menu" aria-label="Menú principal">
            <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('panel.php')) ?>">
                <span>🏠</span> Panel
            </a>
            <a class="<?= $activePage === 'clientes' ? 'active' : '' ?>" href="<?= e(url('clientes/index.php')) ?>">
                <span>👥</span> Clientes
            </a>
            <a class="<?= $activePage === 'mascotas' ? 'active' : '' ?>" href="<?= e(url('mascotas/index.php')) ?>">
                <span>🐕</span> Mascotas
            </a>
            <a class="<?= $activePage === 'citas' ? 'active' : '' ?>" href="<?= e(url('citas/index.php')) ?>">
                <span>📅</span> Citas
            </a>
            <a class="<?= $activePage === 'consultas' ? 'active' : '' ?>" href="<?= e(url('consultas/index.php')) ?>">
                <span>🩺</span> Historia clínica
            </a>
            <a class="<?= $activePage === 'inventario' ? 'active' : '' ?>" href="<?= e(url('inventario/index.php')) ?>">
                <span>📦</span> Inventario
            </a>
            <?php if (is_admin()): ?>
                <a class="<?= $activePage === 'usuarios' ? 'active' : '' ?>" href="<?= e(url('usuarios/index.php')) ?>">
                    <span>🔐</span> Usuarios
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <span><?= e($user['rol']) ?></span>
            <a href="<?= e(url('logout.php')) ?>">Cerrar sesión</a>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="menu-button" id="menuButton" type="button" aria-label="Abrir menú">☰</button>
            <div>
                <h1><?= e($pageTitle) ?></h1>
                <p>Gestión veterinaria integral</p>
            </div>
            <div class="user-chip">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['nombre'], 0, 1))) ?></span>
                <div>
                    <strong><?= e($user['nombre']) ?></strong>
                    <small><?= e($user['email']) ?></small>
                </div>
            </div>
        </header>

        <section class="content">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= e($flashMessage['type']) ?>">
                    <?= e($flashMessage['message']) ?>
                </div>
            <?php endif; ?>
