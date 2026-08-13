<?php
declare(strict_types=1);

/* =====================================================
   RUTA PRINCIPAL DEL PROYECTO
===================================================== */

$projectRoot = dirname(__DIR__);

/* =====================================================
   CARGAR CONFIGURACIÓN Y FUNCIONES
===================================================== */

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/includes/funciones.php';

/* =====================================================
   INICIAR SESIÓN
===================================================== */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =====================================================
   CONFIGURACIÓN DE RESPALDO
===================================================== */

if (!defined('APP_NAME')) {
    define(
        'APP_NAME',
        'Clínica Veterinaria El Campo'
    );
}

if (!defined('APP_URL')) {
    define(
        'APP_URL',
        '/clinica_veterinaria_el_campo'
    );
}

/* =====================================================
   FUNCIÓN PARA ESCAPAR HTML
===================================================== */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =====================================================
   FUNCIÓN PARA GENERAR URL
===================================================== */

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(
            (string) APP_URL,
            '/'
        );

        $path = ltrim(
            $path,
            '/'
        );

        if ($path === '') {
            return $baseUrl;
        }

        return $baseUrl . '/' . $path;
    }
}

/* =====================================================
   OBTENER USUARIO ACTUAL
===================================================== */

if (!function_exists('current_user')) {
    function current_user(): array
    {
        return [
            'id' => $_SESSION['usuario_id']
                ?? $_SESSION['id_usuario']
                ?? $_SESSION['id']
                ?? 0,

            'nombre' => $_SESSION['nombre']
                ?? $_SESSION['usuario']
                ?? 'Usuario',

            'email' => $_SESSION['email']
                ?? $_SESSION['correo']
                ?? '',

            'rol' => $_SESSION['rol']
                ?? 'Usuario'
        ];
    }
}

/* =====================================================
   COMPROBAR ADMINISTRADOR
===================================================== */

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $rol = strtolower(
            trim(
                (string) ($_SESSION['rol'] ?? '')
            )
        );

        return in_array(
            $rol,
            [
                'admin',
                'administrador'
            ],
            true
        );
    }
}

/* =====================================================
   OBTENER MENSAJE TEMPORAL
===================================================== */

if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        if (
            !isset($_SESSION['flash']) ||
            !is_array($_SESSION['flash'])
        ) {
            return null;
        }

        $flash = $_SESSION['flash'];

        unset($_SESSION['flash']);

        $type = $flash['type']
            ?? $flash['tipo']
            ?? 'info';

        $message = $flash['message']
            ?? $flash['mensaje']
            ?? '';

        if (trim((string) $message) === '') {
            return null;
        }

        return [
            'type' => (string) $type,
            'message' => (string) $message
        ];
    }
}

/* =====================================================
   COMPROBAR AUTENTICACIÓN
===================================================== */

$usuarioAutenticado =
    !empty($_SESSION['usuario_id']) ||
    !empty($_SESSION['id_usuario']) ||
    !empty($_SESSION['usuario']);

if (!$usuarioAutenticado) {
    header(
        'Location: ' . url('login.php')
    );

    exit;
}

/* =====================================================
   INFORMACIÓN DE LA PÁGINA
===================================================== */

$pageTitle = $pageTitle ?? 'Panel';
$activePage = $activePage ?? '';

$user = current_user();
$flashMessage = get_flash();

$nombreUsuario = trim(
    (string) ($user['nombre'] ?? 'Usuario')
);

if ($nombreUsuario === '') {
    $nombreUsuario = 'Usuario';
}

/* =====================================================
   LETRA INICIAL DEL USUARIO
===================================================== */

if (function_exists('mb_substr')) {
    $userInitial = mb_strtoupper(
        mb_substr(
            $nombreUsuario,
            0,
            1,
            'UTF-8'
        ),
        'UTF-8'
    );
} else {
    $userInitial = strtoupper(
        substr(
            $nombreUsuario,
            0,
            1
        )
    );
}

/* =====================================================
   VALIDAR TIPO DE MENSAJE
===================================================== */

if ($flashMessage !== null) {
    $allowedFlashTypes = [
        'success',
        'error',
        'warning',
        'info',
        'exito',
        'advertencia'
    ];

    if (
        !in_array(
            $flashMessage['type'],
            $allowedFlashTypes,
            true
        )
    ) {
        $flashMessage['type'] = 'info';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Sistema de gestión para la Clínica Veterinaria El Campo"
    >

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_NAME) ?>
    </title>

    <!-- CSS PRINCIPAL DEL SISTEMA -->
    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

</head>

<body>

<div class="app-shell">

    <!-- ==============================
         BARRA LATERAL
    =============================== -->

    <aside
        class="sidebar"
        id="sidebar"
    >

        <a
            class="brand"
            href="<?= e(url('panel.php')) ?>"
        >

            <span class="brand-logo">
                🐾
            </span>

            <span class="brand-text">

                <strong>
                    Clínica Veterinaria
                </strong>

                <small>
                    El Campo
                </small>

            </span>

        </a>

        <!-- MENÚ PRINCIPAL -->

        <nav
    class="nav-menu"
    aria-label="Menú principal"
>

    <!-- DASHBOARD -->

    <?php if (can('dashboard.ver')): ?>

        <a
            class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"
            href="<?= e(url('panel.php')) ?>"
        >
            <span class="nav-icon">🏠</span>
            <span>Panel</span>
        </a>

    <?php endif; ?>


    <!-- CLIENTES -->

    <?php if (can('clientes.ver')): ?>

        <a
            class="<?= $activePage === 'clientes' ? 'active' : '' ?>"
            href="<?= e(url('clientes/index.php')) ?>"
        >
            <span class="nav-icon">👥</span>
            <span>Clientes</span>
        </a>

    <?php endif; ?>


    <!-- MASCOTAS -->

    <?php if (can('mascotas.ver')): ?>

        <a
            class="<?= $activePage === 'mascotas' ? 'active' : '' ?>"
            href="<?= e(url('mascotas/index.php')) ?>"
        >
            <span class="nav-icon">🐾</span>
            <span>Mascotas</span>
        </a>

    <?php endif; ?>


    <!-- CITAS -->

    <?php if (can('citas.ver')): ?>

        <a
            class="<?= $activePage === 'citas' ? 'active' : '' ?>"
            href="<?= e(url('citas/index.php')) ?>"
        >
            <span class="nav-icon">📅</span>
            <span>Citas</span>
        </a>

    <?php endif; ?>


    <!-- HISTORIA CLÍNICA -->

    <?php if (can('historias.ver')): ?>

        <a
            class="<?= $activePage === 'consultas' || $activePage === 'historias' ? 'active' : '' ?>"
            href="<?= e(url('consultas/index.php')) ?>"
        >
            <span class="nav-icon">🩺</span>
            <span>Historia clínica</span>
        </a>

    <?php endif; ?>


    <!-- INVENTARIO -->

    <?php if (can('inventario.ver')): ?>

        <a
            class="<?= $activePage === 'inventario' ? 'active' : '' ?>"
            href="<?= e(url('inventario/index.php')) ?>"
        >
            <span class="nav-icon">📦</span>
            <span>Inventario</span>
        </a>

    <?php endif; ?>


    <!-- REPORTES -->

    <?php if (can('reportes.ver')): ?>

        <a
            class="<?= $activePage === 'reportes' ? 'active' : '' ?>"
            href="<?= e(url('reportes/index.php')) ?>"
        >
            <span class="nav-icon">📊</span>
            <span>Reportes</span>
        </a>

    <?php endif; ?>


    <!-- USUARIOS -->

    <?php if (can('usuarios.ver')): ?>

        <a
            class="<?= $activePage === 'usuarios' ? 'active' : '' ?>"
            href="<?= e(url('usuarios/index.php')) ?>"
        >
            <span class="nav-icon">🔐</span>
            <span>Usuarios</span>
        </a>

    <?php endif; ?>


    <!-- CONFIGURACIÓN -->

    <?php if (can('configuracion.ver')): ?>

        <a
            class="<?= $activePage === 'configuracion' ? 'active' : '' ?>"
            href="<?= e(url('configuracion/index.php')) ?>"
        >
            <span class="nav-icon">⚙️</span>
            <span>Configuración</span>
        </a>

    <?php endif; ?>

</nav>

        <!-- PARTE INFERIOR DEL MENÚ -->

        <div class="sidebar-footer">

            <span class="user-role">
                <?= e($user['rol'] ?? 'Usuario') ?>
            </span>

            <a
                class="logout-link"
                href="<?= e(url('logout.php')) ?>"
            >
                ↩ Cerrar sesión
            </a>

        </div>

    </aside>

    <!-- ==============================
         CONTENIDO PRINCIPAL
    =============================== -->

    <main class="main-area">

        <header class="topbar">

            <button
                class="menu-button"
                id="menuButton"
                type="button"
                aria-label="Abrir menú"
            >
                ☰
            </button>

            <div class="topbar-title">

                <h1>
                    <?= e($pageTitle) ?>
                </h1>

                <p>
                    Gestión veterinaria integral
                </p>

            </div>

            <div class="user-chip">

                <span class="avatar">
                    <?= e($userInitial) ?>
                </span>

                <div class="user-information">

                    <strong>
                        <?= e($nombreUsuario) ?>
                    </strong>

                    <small>
                        <?= e($user['email'] ?? '') ?>
                    </small>

                </div>

            </div>

        </header>

        <section class="content">

            <?php if ($flashMessage !== null): ?>

                <div
                    class="alert alert-<?= e($flashMessage['type']) ?>"
                    role="alert"
                >
                    <?= e($flashMessage['message']) ?>
                </div>

            <?php endif; ?>