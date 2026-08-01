<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

/*
|--------------------------------------------------------------------------
| INICIAR SESIÓN
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| PROTEGER EL PANEL
|--------------------------------------------------------------------------
*/

$idUsuario = $_SESSION['usuario_id']
    ?? $_SESSION['id_usuario']
    ?? null;

if (empty($idUsuario)) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$nombreUsuario = $_SESSION['nombre']
    ?? $_SESSION['usuario']
    ?? 'Usuario';

$rolUsuario = $_SESSION['rol']
    ?? 'Usuario';

/*
|--------------------------------------------------------------------------
| BUSCAR EL ARCHIVO PRINCIPAL DE CADA MÓDULO
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE LOS MÓDULOS
|--------------------------------------------------------------------------
*/

$modulos = [
    [
        'titulo' => 'Clientes',
        'icono' => '👥',
        'descripcion' => 'Registro y administración de propietarios.',
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
        'rutas' => [
            'usuarios/index.php',
            'usuarios/usuarios.php',
            'usuarios/listar_usuarios.php'
        ]
    ]
];

/*
|--------------------------------------------------------------------------
| ASIGNAR RUTAS DISPONIBLES
|--------------------------------------------------------------------------
*/

$modulosDisponibles = 0;

foreach ($modulos as $indice => $modulo) {
    $rutaEncontrada = encontrarRuta($modulo['rutas']);
    $modulos[$indice]['ruta'] = $rutaEncontrada;

    if ($rutaEncontrada !== null) {
        $modulosDisponibles++;
    }
}

$totalModulos = count($modulos);
$fechaActual = date('d/m/Y');
$horaActual = date('H:i');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Clínica Veterinaria El Campo</title>

    <style>
        :root {
            --sidebar: #0b1f3a;
            --sidebar-soft: #112a4d;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-soft: #eaf2ff;
            --success: #16a34a;
            --success-soft: #dcfce7;
            --warning: #d97706;
            --warning-soft: #fef3c7;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --surface: #ffffff;
            --background: #f4f7fb;
            --text: #15345b;
            --muted: #6b7d98;
            --border: #dce6f2;
            --shadow: 0 12px 30px rgba(15, 47, 92, 0.08);
            --radius: 16px;
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
            overflow-x: hidden;
            color: var(--text);
            background: var(--background);
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
            grid-template-columns: 280px minmax(0, 1fr);
        }

        /* =========================
           BARRA LATERAL
        ========================= */

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 18px;
            color: #dbe8f8;
            background:
                linear-gradient(
                    180deg,
                    var(--sidebar) 0%,
                    #08182e 100%
                );
            box-shadow: 8px 0 30px rgba(8, 24, 46, 0.13);
            z-index: 200;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 8px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.09);
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            flex: 0 0 50px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    #3b82f6,
                    #1d4ed8
                );
            font-size: 25px;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.28);
        }

        .brand-text strong {
            display: block;
            color: #ffffff;
            font-size: 15px;
            line-height: 1.25;
        }

        .brand-text span {
            display: block;
            margin-top: 4px;
            color: #90a8c5;
            font-size: 12px;
        }

        .sidebar-label {
            margin: 24px 10px 10px;
            color: #6f89aa;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: grid;
            gap: 7px;
        }

        .sidebar-link {
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: #bed0e6;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            transition:
                color 0.2s ease,
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #ffffff;
            background: rgba(37, 99, 235, 0.25);
            transform: translateX(3px);
        }

        .sidebar-link.disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .sidebar-link.disabled:hover {
            transform: none;
            color: #bed0e6;
            background: transparent;
        }

        .sidebar-icon {
            width: 28px;
            display: inline-flex;
            justify-content: center;
            font-size: 18px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 13px;
            padding: 11px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.06);
        }

        .sidebar-avatar {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #ffffff;
            background: #1d4ed8;
            font-weight: 900;
        }

        .sidebar-user strong {
            display: block;
            color: #ffffff;
            font-size: 13px;
        }

        .sidebar-user span {
            display: block;
            margin-top: 3px;
            color: #8fa6c2;
            font-size: 11px;
            text-transform: capitalize;
        }

        .logout-button {
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #ffffff;
            background: rgba(220, 38, 38, 0.16);
            border: 1px solid rgba(248, 113, 113, 0.22);
            border-radius: 10px;
            font-weight: 800;
            transition:
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .logout-button:hover {
            transform: translateY(-2px);
            background: rgba(220, 38, 38, 0.28);
        }

        /* =========================
           CONTENIDO PRINCIPAL
        ========================= */

        .main-content {
            min-width: 0;
            padding: 0 32px 45px;
        }

        .topbar {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .menu-button {
            display: none;
            width: 42px;
            height: 42px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            background: var(--surface);
            cursor: pointer;
        }

        .page-title h1 {
            color: var(--text);
            font-size: 24px;
            line-height: 1.2;
        }

        .page-title p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
        }

        .topbar-status {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 9px 13px;
            color: #166534;
            background: var(--success-soft);
            border: 1px solid #bbf7d0;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.12);
        }

        /* =========================
           BIENVENIDA
        ========================= */

        .welcome-card {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 30px;
            margin-bottom: 24px;
            padding: 34px;
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    #0f3a73 0%,
                    #2563eb 100%
                );
            border-radius: 20px;
            box-shadow: 0 18px 38px rgba(37, 99, 235, 0.22);
        }

        .welcome-card::after {
            content: "🐾";
            position: absolute;
            right: 35px;
            bottom: -48px;
            font-size: 180px;
            opacity: 0.07;
            transform: rotate(-17deg);
            pointer-events: none;
        }

        .welcome-content,
        .welcome-summary {
            position: relative;
            z-index: 2;
        }

        .welcome-label {
            display: inline-block;
            margin-bottom: 9px;
            color: #bfdbfe;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .welcome-card h2 {
            font-size: 31px;
            line-height: 1.2;
        }

        .welcome-card p {
            max-width: 720px;
            margin-top: 9px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.65;
        }

        .welcome-summary {
            min-width: 180px;
            padding: 18px 21px;
            text-align: center;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.23);
            border-radius: 16px;
            backdrop-filter: blur(8px);
        }

        .welcome-summary strong {
            display: block;
            font-size: 30px;
            line-height: 1;
        }

        .welcome-summary span {
            display: block;
            margin-top: 8px;
            color: rgba(255, 255, 255, 0.86);
            font-size: 13px;
        }

        /* =========================
           INDICADORES
        ========================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            min-height: 128px;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            flex: 0 0 52px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            font-size: 24px;
        }

        .stat-icon.blue {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .stat-icon.green {
            color: #15803d;
            background: #dcfce7;
        }

        .stat-icon.orange {
            color: #b45309;
            background: #fef3c7;
        }

        .stat-icon.purple {
            color: #6d28d9;
            background: #ede9fe;
        }

        .stat-card span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card strong {
            display: block;
            margin-top: 4px;
            color: var(--text);
            font-size: 21px;
            line-height: 1.2;
        }

        /* =========================
           MÓDULOS
        ========================= */

        .section-header {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 16px;
        }

        .section-header h2 {
            color: var(--text);
            font-size: 22px;
        }

        .section-header p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 14px;
        }

        .module-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 13px;
        }

        .module-row {
            position: relative;
            overflow: hidden;
            min-height: 112px;
            display: grid;
            grid-template-columns: 64px minmax(0, 1fr) auto;
            align-items: center;
            gap: 20px;
            padding: 20px 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition:
                transform 0.22s ease,
                border-color 0.22s ease,
                box-shadow 0.22s ease;
        }

        .module-row:hover {
            transform: translateX(5px);
            border-color: #93b4ef;
            border-left-color: var(--primary-dark);
            box-shadow: 0 16px 34px rgba(37, 99, 235, 0.13);
        }

        .module-row.disabled {
            opacity: 0.58;
            cursor: not-allowed;
            border-left-color: #94a3b8;
        }

        .module-row.disabled:hover {
            transform: none;
            border-color: var(--border);
            border-left-color: #94a3b8;
            box-shadow: var(--shadow);
        }

        .module-icon {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: var(--primary-soft);
            font-size: 29px;
        }

        .module-info h3 {
            color: var(--text);
            font-size: 18px;
        }

        .module-info p {
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .module-action {
            min-width: 145px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            color: #ffffff;
            background: var(--primary);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 850;
            white-space: nowrap;
            transition:
                background-color 0.2s ease,
                transform 0.2s ease;
        }

        .module-row:hover .module-action {
            transform: translateX(2px);
            background: var(--primary-dark);
        }

        .module-row.disabled .module-action {
            color: #475569;
            background: #e2e8f0;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .app-layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: fixed;
                left: -290px;
                width: 280px;
                transition: left 0.25s ease;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                padding-inline: 20px;
            }

            .menu-button {
                display: inline-grid;
                place-items: center;
            }

            .welcome-card {
                grid-template-columns: 1fr;
            }

            .welcome-summary {
                width: fit-content;
            }
        }

        @media (max-width: 650px) {
            .main-content {
                padding: 0 14px 35px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
                justify-content: center;
                gap: 12px;
                padding: 14px 0;
            }

            .topbar-status {
                align-self: stretch;
                justify-content: center;
            }

            .welcome-card {
                padding: 25px 22px;
            }

            .welcome-card h2 {
                font-size: 25px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .module-row {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }

            .module-action {
                width: 100%;
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

        <div class="sidebar-label">
            Navegación
        </div>

        <nav class="sidebar-nav">

            <a class="sidebar-link active" href="panel.php">
                <span class="sidebar-icon">▦</span>
                Dashboard
            </a>

            <?php foreach ($modulos as $modulo): ?>

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

                    <span
                        class="sidebar-link disabled"
                        title="Archivo principal no encontrado"
                    >
                        <span class="sidebar-icon">
                            <?= e($modulo['icono']) ?>
                        </span>

                        <?= e($modulo['titulo']) ?>
                    </span>

                <?php endif; ?>

            <?php endforeach; ?>

        </nav>

        <div class="sidebar-footer">

            <div class="sidebar-user">

                <div class="sidebar-avatar">
                    <?= e(strtoupper(substr($nombreUsuario, 0, 1))) ?>
                </div>

                <div>
                    <strong>
                        <?= e($nombreUsuario) ?>
                    </strong>

                    <span>
                        <?= e($rolUsuario) ?>
                    </span>
                </div>

            </div>

            <a
                class="logout-button"
                href="logout.php"
            >
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

                    <p>
                        Administración general del sistema veterinario
                    </p>
                </div>

            </div>

            <div class="topbar-status">
                <span class="status-dot"></span>
                Sistema operativo
            </div>

        </header>

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    PANEL DE CONTROL
                </span>

                <h2>
                    Bienvenido, <?= e($nombreUsuario) ?>
                </h2>

                <p>
                    Gestiona clientes, mascotas, citas, historias clínicas,
                    inventario y usuarios desde una sola interfaz.
                </p>

            </div>

            <div class="welcome-summary">
                <strong>
                    <?= e($modulosDisponibles) ?>/<?= e($totalModulos) ?>
                </strong>

                <span>
                    módulos disponibles
                </span>
            </div>

        </section>

        <section class="stats-grid">

            <article class="stat-card">
                <div class="stat-icon blue">▦</div>

                <div>
                    <span>Total de módulos</span>
                    <strong><?= e($totalModulos) ?></strong>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon green">✓</div>

                <div>
                    <span>Módulos disponibles</span>
                    <strong><?= e($modulosDisponibles) ?></strong>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon purple">👤</div>

                <div>
                    <span>Rol actual</span>
                    <strong><?= e(ucfirst($rolUsuario)) ?></strong>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon orange">🕐</div>

                <div>
                    <span>Fecha y hora</span>
                    <strong>
                        <?= e($fechaActual) ?> · <?= e($horaActual) ?>
                    </strong>
                </div>
            </article>

        </section>

       

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

            if (
                window.innerWidth <= 900 &&
                !clickDentroMenu
            ) {
                sidebar.classList.remove('open');
            }
        });
    }
</script>

</body>
</html>