<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| PROTEGER EL DASHBOARD MEDIANTE PERMISOS
|--------------------------------------------------------------------------
*/

require_permission('dashboard.ver');

date_default_timezone_set('America/Guayaquil');

/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO AUTENTICADO
|--------------------------------------------------------------------------
*/

$usuarioActual = current_user();

$nombreUsuario = trim(
    (string) ($usuarioActual['nombre'] ?? '')
);

if ($nombreUsuario === '') {
    $nombreUsuario = 'Usuario';
}

$rolActual = current_role();
$nombreRol = role_label($rolActual);

if (trim($nombreRol) === '') {
    $nombreRol = 'Usuario';
}

/*
|--------------------------------------------------------------------------
| FUNCIONES DE APOYO
|--------------------------------------------------------------------------
*/

if (!function_exists('encontrarRuta')) {
    function encontrarRuta(array $rutas): ?string
    {
        foreach ($rutas as $ruta) {
            $rutaCompleta = __DIR__
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $ruta);

            if (is_file($rutaCompleta)) {
                return $ruta;
            }
        }

        return null;
    }
}

if (!function_exists('inicialUsuario')) {
    function inicialUsuario(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return 'U';
        }

        if (function_exists('mb_substr')) {
            return strtoupper(mb_substr($nombre, 0, 1, 'UTF-8'));
        }

        return strtoupper(substr($nombre, 0, 1));
    }
}

/*
|--------------------------------------------------------------------------
| SALUDO DINÁMICO
|--------------------------------------------------------------------------
*/

$horaActualNumero = (int) date('H');

$saludo = match (true) {
    $horaActualNumero >= 5 && $horaActualNumero < 12 => 'Buenos días',
    $horaActualNumero >= 12 && $horaActualNumero < 19 => 'Buenas tardes',
    default => 'Buenas noches'
};

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE LOS MÓDULOS Y SUS PERMISOS
|--------------------------------------------------------------------------
*/

$modulos = [
    [
        'titulo' => 'Clientes',
        'icono' => '👥',
        'descripcion' => 'Registro y administración de propietarios.',
        'permiso' => 'clientes.ver',
        'rutas' => [
            'clientes/index.php',
            'clientes/clientes.php',
            'clientes/listar_clientes.php'
        ]
    ],
    [
        'titulo' => 'Mascotas',
        'icono' => '🐾',
        'descripcion' => 'Registro y seguimiento de mascotas.',
        'permiso' => 'mascotas.ver',
        'rutas' => [
            'mascotas/index.php',
            'mascotas/mascotas.php',
            'mascotas/listar_mascotas.php'
        ]
    ],
    [
        'titulo' => 'Citas',
        'icono' => '📅',
        'descripcion' => 'Agenda y administración de citas veterinarias.',
        'permiso' => 'citas.ver',
        'rutas' => [
            'citas/index.php',
            'citas/citas.php',
            'citas/listar_citas.php'
        ]
    ],
    [
        'titulo' => 'Historia Clínica',
        'icono' => '📋',
        'descripcion' => 'Consultas, diagnósticos y tratamientos.',
        'permiso' => 'historias.ver',
        'rutas' => [
            'consultas/index.php',
            'consultas/consultas.php',
            'consultas/historias.php',
            'consultas/historia_clinica.php',
            'historias_clinicas/index.php',
            'historias_clinicas/historias.php'
        ]
    ],
    [
        'titulo' => 'Inventario',
        'icono' => '📦',
        'descripcion' => 'Control de productos, medicamentos y existencias.',
        'permiso' => 'inventario.ver',
        'rutas' => [
            'inventario/index.php',
            'inventario/inventario.php',
            'inventario/productos.php'
        ]
    ],
    [
        'titulo' => 'Usuarios',
        'icono' => '🔐',
        'descripcion' => 'Administración de usuarios, roles y permisos.',
        'permiso' => 'usuarios.ver',
        'rutas' => [
            'usuarios/index.php',
            'usuarios/usuarios.php',
            'usuarios/listar_usuarios.php'
        ]
    ],
    [
        'titulo' => 'Reportes',
        'icono' => '📊',
        'descripcion' => 'Estadísticas e informes del sistema veterinario.',
        'permiso' => 'reportes.ver',
        'rutas' => [
            'reportes/index.php'
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| APLICAR LOS PERMISOS DEL ROL ACTUAL
|--------------------------------------------------------------------------
*/

$modulosDisponibles = 0;
foreach ($modulos as $indice => $modulo) {
    $permitido = can($modulo['permiso']);
    $rutaEncontrada = encontrarRuta($modulo['rutas']);

    $modulos[$indice]['permitido'] = $permitido;
    $modulos[$indice]['archivo_disponible'] = $rutaEncontrada !== null;
    $modulos[$indice]['ruta'] = $permitido ? $rutaEncontrada : null;

    if ($permitido) {
        $modulosDisponibles++;
    }

}

$totalModulos = count($modulos);
$modulosNoDisponibles = $totalModulos - $modulosDisponibles;

$fechaActual = date('d/m/Y');
$horaActual = date('H:i');
$anioActual = date('Y');
$inicial = inicialUsuario($nombreUsuario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Panel Principal | Clínica Veterinaria El Campo</title>

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-soft: #dbeafe;

            --success: #16a34a;
            --success-soft: #dcfce7;

            --warning: #d97706;
            --warning-soft: #fef3c7;

            --danger: #dc2626;
            --danger-soft: #fee2e2;

            --violet: #7c3aed;
            --violet-soft: #ede9fe;

            --sidebar: #0f172a;
            --sidebar-soft: #1e293b;

            --bg: #f1f5f9;
            --surface: #ffffff;
            --surface-soft: #f8fafc;

            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;

            --shadow-sm: 0 8px 20px rgba(15, 23, 42, 0.05);
            --shadow-md: 0 16px 40px rgba(15, 23, 42, 0.08);

            --radius-lg: 22px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        .app-layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 22px 18px;
            color: #cbd5e1;
            background: linear-gradient(
                180deg,
                var(--sidebar) 0%,
                #111827 100%
            );
            box-shadow: 10px 0 30px rgba(2, 6, 23, 0.12);
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            font-size: 26px;
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.35);
        }

        .brand-text strong {
            display: block;
            color: #fff;
            font-size: 15px;
            line-height: 1.3;
        }

        .brand-text span {
            display: block;
            margin-top: 3px;
            color: #94a3b8;
            font-size: 12px;
        }

        .nav-title {
            margin: 22px 10px 10px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: grid;
            gap: 8px;
        }

        .sidebar-link,
        .sidebar-link-disabled {
            min-height: 48px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            transition:
                transform 0.2s ease,
                background-color 0.2s ease,
                color 0.2s ease;
        }

        .sidebar-link {
            color: #cbd5e1;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #ffffff;
            background: rgba(37, 99, 235, 0.22);
            transform: translateX(3px);
        }

        .sidebar-link-disabled {
            color: #64748b;
            cursor: not-allowed;
            opacity: 0.65;
        }

        .sidebar-icon {
            width: 26px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-size: 18px;
            font-weight: 900;
            flex: 0 0 46px;
        }

        .user-card strong {
            display: block;
            color: #fff;
            font-size: 14px;
        }

        .user-card span {
            display: block;
            margin-top: 4px;
            color: #94a3b8;
            font-size: 12px;
        }

        .logout-button {
            width: 100%;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(220, 38, 38, 0.14);
            border: 1px solid rgba(248, 113, 113, 0.25);
            color: #fff;
            border-radius: 12px;
            font-weight: 800;
            transition:
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .logout-button:hover {
            background: rgba(220, 38, 38, 0.24);
            transform: translateY(-2px);
        }

        /* =========================
           MAIN
        ========================= */

        .main-content {
            min-width: 0;
            padding: 26px 28px 40px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .menu-button {
            display: none;
            width: 44px;
            height: 44px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }

        .page-title h1 {
            font-size: 30px;
            line-height: 1.15;
            color: var(--text);
        }

        .page-title p {
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
        }

        .topbar-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-success {
            color: #166534;
            background: var(--success-soft);
            border: 1px solid #bbf7d0;
        }

        .badge-primary {
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: currentColor;
        }

        /* =========================
           HERO
        ========================= */

        .hero {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 24px;
            margin-bottom: 24px;
            padding: 30px;
            border-radius: 26px;
            color: #fff;
            background: linear-gradient(135deg, #0f3d88 0%, #2563eb 55%, #3b82f6 100%);
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.22);
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -70px;
            bottom: -70px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero::before {
            content: "";
            position: absolute;
            right: 90px;
            top: 50px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-content,
        .hero-side {
            position: relative;
            z-index: 2;
        }

        .hero-label {
            display: inline-block;
            margin-bottom: 10px;
            color: #dbeafe;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .hero h2 {
            font-size: 36px;
            line-height: 1.15;
            margin-bottom: 10px;
        }

        .hero p {
            max-width: 760px;
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.7;
            font-size: 15px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .btn {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 14px;
            transition:
                transform 0.2s ease,
                background-color 0.2s ease;
        }

        .btn-primary {
            color: #0f172a;
            background: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: #eff6ff;
        }

        .btn-outline {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .btn-outline:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.2);
        }

        .hero-side {
            display: grid;
            gap: 14px;
            align-content: start;
        }

        .side-card {
            padding: 20px 18px;
            text-align: center;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
        }

        .side-card strong {
            display: block;
            font-size: 30px;
            line-height: 1;
            margin-bottom: 8px;
        }

        .side-card span {
            display: block;
            color: rgba(255, 255, 255, 0.88);
            font-size: 13px;
        }

        /* =========================
           STATS
        ========================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            min-height: 122px;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            font-size: 25px;
            flex: 0 0 56px;
        }

        .stat-icon.blue {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .stat-icon.green {
            color: #15803d;
            background: #dcfce7;
        }

        .stat-icon.violet {
            color: #6d28d9;
            background: #ede9fe;
        }

        .stat-icon.orange {
            color: #b45309;
            background: #fef3c7;
        }

        .stat-info small {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .stat-info strong {
            display: block;
            margin-top: 6px;
            color: #0f172a;
            font-size: 17px;
            line-height: 1.3;
        }

        .stat-info span {
            display: block;
            margin-top: 5px;
            color: #64748b;
            font-size: 13px;
        }

        /* =========================
           SECTIONS
        ========================= */

        .section-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .section-header h3 {
            color: var(--text);
            font-size: 22px;
        }

        .section-header p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 14px;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .module-card {
            display: flex;
            flex-direction: column;
            min-height: 220px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow-sm);
            transition:
                transform 0.22s ease,
                box-shadow 0.22s ease,
                border-color 0.22s ease;
        }

        .module-card:hover {
            transform: translateY(-4px);
            border-color: #bfdbfe;
            box-shadow: var(--shadow-md);
        }

        .module-card.disabled {
            opacity: 0.72;
        }

        .module-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }

        .module-badge {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: #eff6ff;
            font-size: 28px;
        }

        .module-status {
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-active {
            color: #166534;
            background: #dcfce7;
        }

        .status-disabled {
            color: #92400e;
            background: #fef3c7;
        }

        .module-card h4 {
            font-size: 19px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .module-card p {
            color: var(--muted);
            line-height: 1.65;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .module-actions {
            margin-top: auto;
        }

        .module-button,
        .module-button-disabled {
            width: 100%;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
        }

        .module-button {
            color: #fff;
            background: var(--primary);
            transition:
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .module-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .module-button-disabled {
            color: #64748b;
            background: #e2e8f0;
            cursor: not-allowed;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 18px;
        }

        .info-card {
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow-sm);
        }

        .summary-list,
        .quick-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .summary-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 14px;
        }

        .summary-item span {
            color: var(--muted);
            font-size: 14px;
        }

        .summary-item strong {
            color: var(--text);
            font-size: 14px;
            text-align: right;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 16px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 14px;
            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                background-color 0.2s ease;
        }

        .quick-link:hover {
            transform: translateX(4px);
            border-color: #bfdbfe;
            background: #f8fbff;
        }

        .quick-number {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #1d4ed8;
            background: #dbeafe;
            font-size: 13px;
            font-weight: 900;
            flex: 0 0 30px;
        }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 980px) {
            .app-layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                left: -300px;
                width: 290px;
                transition: left 0.25s ease;
            }

            .sidebar.open {
                left: 0;
            }

            .menu-button {
                display: inline-grid;
                place-items: center;
            }

            .main-content {
                padding: 22px 18px 36px;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .hero-side {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .hero {
                padding: 24px 20px;
            }

            .hero h2 {
                font-size: 28px;
            }

            .hero-side {
                grid-template-columns: 1fr;
            }

            .stats-grid,
            .module-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<div class="app-layout">

    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <div class="brand-logo">🐾</div>

            <div class="brand-text">
                <strong>Clínica Veterinaria</strong>
                <span>El Campo</span>
            </div>
        </div>

        <div class="nav-title">
            Menú principal
        </div>

        <nav class="sidebar-nav">

            <a class="sidebar-link active" href="panel.php">
                <span class="sidebar-icon">🏠</span>
                Dashboard
            </a>

            <?php foreach ($modulos as $modulo): ?>

                <?php if (!$modulo['permitido']) continue; ?>

                <?php if ($modulo['ruta'] !== null): ?>

                    <a
                        class="sidebar-link"
                        href="<?= e($modulo['ruta']) ?>"
                    >
                        <span class="sidebar-icon">
                            <?= e($modulo['icono']) ?>
                        </span>

                        <?= e($modulo['titulo']) ?>
                    </a>

                <?php else: ?>

                    <span class="sidebar-link-disabled">
                        <span class="sidebar-icon">
                            <?= e($modulo['icono']) ?>
                        </span>

                        <?= e($modulo['titulo']) ?>
                    </span>

                <?php endif; ?>

            <?php endforeach; ?>

        </nav>

        <div class="sidebar-footer">

            <div class="user-card">
                <div class="user-avatar">
                    <?= e($inicial) ?>
                </div>

                <div>
                    <strong><?= e($nombreUsuario) ?></strong>
                    <span><?= e($nombreRol) ?></span>
                </div>
            </div>

            <a class="logout-button" href="logout.php">
                🚪 Cerrar sesión
            </a>

        </div>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="menu-button"
                    id="menuButton"
                    type="button"
                    aria-label="Abrir menú"
                >
                    ☰
                </button>

                <div class="page-title">
                    <h1>Dashboard</h1>
                    <p>Administración general del sistema veterinario</p>
                </div>

            </div>

            <div class="topbar-badges">

                <div class="badge badge-success">
                    <span class="dot"></span>
                    Sistema operativo
                </div>

                <div class="badge badge-primary">
                    📅 <?= e($fechaActual) ?> · <?= e($horaActual) ?>
                </div>

            </div>

        </header>

        <section class="hero">

            <div class="hero-content">

                <span class="hero-label">
                    Panel de control
                </span>

                <h2>
                    <?= e($saludo) ?>, <?= e($nombreUsuario) ?>
                </h2>

                <p>
                    Gestiona clientes, mascotas, citas, historias clínicas,
                    inventario, usuarios y reportes desde una sola interfaz profesional,
                    rápida y organizada.
                </p>

                <div class="hero-actions">

                    <?php if ($modulos[0]['ruta'] !== null): ?>
                        <a
                            href="<?= e($modulos[0]['ruta']) ?>"
                            class="btn btn-primary"
                        >
                            👥 Ir a Clientes
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[2]['ruta'] !== null): ?>
                        <a
                            href="<?= e($modulos[2]['ruta']) ?>"
                            class="btn btn-outline"
                        >
                            📅 Ver Citas
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <div class="hero-side">

                <div class="side-card">
                    <strong><?= e((string) $modulosDisponibles) ?>/<?= e((string) $totalModulos) ?></strong>
                    <span>Módulos permitidos para tu rol</span>
                </div>

                <div class="side-card">
                    <strong><?= e($nombreRol) ?></strong>
                    <span>Rol actual</span>
                </div>

            </div>

        </section>

        <section class="stats-grid">

            <article class="stat-card">
                <div class="stat-icon blue">📦</div>

                <div class="stat-info">
                    <small>Total de módulos</small>
                    <strong><?= e((string) $totalModulos) ?></strong>
                    <span>Áreas registradas en el sistema</span>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon green">✅</div>

                <div class="stat-info">
                    <small>Módulos permitidos</small>
                    <strong><?= e((string) $modulosDisponibles) ?></strong>
                    <span>Según los permisos de tu rol</span>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon violet">👤</div>

                <div class="stat-info">
                    <small>Rol actual</small>
                    <strong><?= e($nombreRol) ?></strong>
                    <span>Permisos asignados al usuario</span>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon orange">🕐</div>

                <div class="stat-info">
                    <small>Fecha y hora</small>
                    <strong><?= e($fechaActual) ?> · <?= e($horaActual) ?></strong>
                    <span>Última actualización visual</span>
                </div>
            </article>

        </section>

        <section>

            <div class="section-header">
                <div>
                    <h3>Módulos principales</h3>
                    <p>Accede rápidamente a las funciones más importantes del sistema.</p>
                </div>
            </div>

            <div class="module-grid">

                <?php foreach ($modulos as $modulo): ?>
                    <?php if (!$modulo['permitido']) continue; ?>

                    <article class="module-card <?= $modulo['ruta'] === null ? 'disabled' : '' ?>">

                        <div class="module-top">
                            <div class="module-badge">
                                <?= e($modulo['icono']) ?>
                            </div>

                            <?php if ($modulo['ruta'] !== null): ?>
                                <span class="module-status status-active">
                                    Disponible
                                </span>
                            <?php else: ?>
                                <span class="module-status status-disabled">
                                    No disponible
                                </span>
                            <?php endif; ?>
                        </div>

                        <h4><?= e($modulo['titulo']) ?></h4>

                        <p><?= e($modulo['descripcion']) ?></p>

                        <div class="module-actions">

                            <?php if ($modulo['ruta'] !== null): ?>
                                <a
                                    href="<?= e($modulo['ruta']) ?>"
                                    class="module-button"
                                >
                                    Abrir módulo →
                                </a>
                            <?php else: ?>
                                <span class="module-button-disabled">
                                    Módulo sin archivo de entrada
                                </span>
                            <?php endif; ?>

                        </div>

                    </article>
                <?php endforeach; ?>

            </div>

        </section>

        <section class="bottom-grid">

            <div class="info-card">

                <div class="section-header">
                    <div>
                        <h3>Resumen del sistema</h3>
                        <p>Información general de la sesión actual.</p>
                    </div>
                </div>

                <div class="summary-list">

                    <div class="summary-item">
                        <span>Nombre del sistema</span>
                        <strong>Clínica Veterinaria El Campo</strong>
                    </div>

                    <div class="summary-item">
                        <span>Usuario activo</span>
                        <strong><?= e($nombreUsuario) ?></strong>
                    </div>

                    <div class="summary-item">
                        <span>Rol asignado</span>
                        <strong><?= e($nombreRol) ?></strong>
                    </div>

                    <div class="summary-item">
                        <span>Módulos permitidos para tu rol</span>
                        <strong><?= e((string) $modulosDisponibles) ?> de <?= e((string) $totalModulos) ?></strong>
                    </div>

                    <div class="summary-item">
                        <span>Módulos sin permiso</span>
                        <strong><?= e((string) $modulosNoDisponibles) ?></strong>
                    </div>

                </div>

            </div>

            <div class="info-card">

                <div class="section-header">
                    <div>
                        <h3>Acciones rápidas</h3>
                        <p>Atajos para trabajar más rápido.</p>
                    </div>
                </div>

                <div class="quick-list">

                    <?php if ($modulos[0]['ruta'] !== null): ?>
                        <a href="<?= e($modulos[0]['ruta']) ?>" class="quick-link">
                            <span class="quick-number">1</span>
                            Administrar clientes
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[1]['ruta'] !== null): ?>
                        <a href="<?= e($modulos[1]['ruta']) ?>" class="quick-link">
                            <span class="quick-number">2</span>
                            Revisar mascotas
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[2]['ruta'] !== null): ?>
                        <a href="<?= e($modulos[2]['ruta']) ?>" class="quick-link">
                            <span class="quick-number">3</span>
                            Consultar citas
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[4]['ruta'] !== null): ?>
                        <a href="<?= e($modulos[4]['ruta']) ?>" class="quick-link">
                            <span class="quick-number">4</span>
                            Ver inventario
                        </a>
                    <?php endif; ?>

                    <?php if (isset($modulos[6]) && $modulos[6]['ruta'] !== null): ?>
                        <a href="<?= e($modulos[6]['ruta']) ?>" class="quick-link">
                            <span class="quick-number">5</span>
                            Consultar reportes
                        </a>
                    <?php endif; ?>

                </div>

            </div>

        </section>

        <p class="footer-note">
            © <?= e((string) $anioActual) ?> Clínica Veterinaria El Campo · Panel administrativo
        </p>

    </main>

</div>

<script>
    const menuButton = document.getElementById('menuButton');
    const sidebar = document.getElementById('sidebar');

    if (menuButton && sidebar) {
        menuButton.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (event) {
            const clickDentroMenu =
                sidebar.contains(event.target) ||
                menuButton.contains(event.target);

            if (window.innerWidth <= 980 && !clickDentroMenu) {
                sidebar.classList.remove('open');
            }
        });
    }
</script>

</body>
</html>