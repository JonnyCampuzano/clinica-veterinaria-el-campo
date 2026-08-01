<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function require_login(): void
{
    if (empty($_SESSION['usuario_id'])) {
        flash('error', 'Debes iniciar sesión para ingresar al sistema.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (($_SESSION['usuario_rol'] ?? '') !== 'Administrador') {
        flash('error', 'No tienes permiso para acceder a esta sección.');
        redirect('panel.php');
    }
}

function current_user(): array
{
    return [
        'id' => (int) ($_SESSION['usuario_id'] ?? 0),
        'nombre' => $_SESSION['usuario_nombre'] ?? 'Usuario',
        'email' => $_SESSION['usuario_email'] ?? '',
        'rol' => $_SESSION['usuario_rol'] ?? ''
    ];
}

function is_admin(): bool
{
    return ($_SESSION['usuario_rol'] ?? '') === 'Administrador';
}
