<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

date_default_timezone_set(
    'America/Guayaquil'
);

$usuarioActual = current_user();

$nombreUsuario = trim(
    (string) (
        $usuarioActual['nombre']
        ?? 'Usuario'
    )
);

if ($nombreUsuario === '') {
    $nombreUsuario = 'Usuario';
}

/*
|--------------------------------------------------------------------------
| NOMBRE PARA MOSTRAR EN EL DASHBOARD
|--------------------------------------------------------------------------
|
| Conservamos el nombre completo para la tarjeta del usuario y usamos
| únicamente el primer nombre en el saludo principal.
|
*/

if (function_exists('mb_convert_case')) {
    $nombreUsuarioMostrar = mb_convert_case(
        mb_strtolower($nombreUsuario, 'UTF-8'),
        MB_CASE_TITLE,
        'UTF-8'
    );
} else {
    $nombreUsuarioMostrar = ucwords(strtolower($nombreUsuario));
}

$partesNombre = preg_split('/\s+/u', trim($nombreUsuarioMostrar));
$primerNombreUsuario = $partesNombre[0] ?? 'Usuario';

if ($primerNombreUsuario === '') {
    $primerNombreUsuario = 'Usuario';
}

$rolActual = current_role();

$nombreRol = role_label(
    $rolActual
);

/*
|--------------------------------------------------------------------------
| IMAGEN SEGÚN EL ROL
|--------------------------------------------------------------------------
|
| El sistema busca automáticamente la imagen del rol en varias carpetas
| comunes. La opción recomendada es:
|
| assets/img/roles/administrador.png
| assets/img/roles/recepcionista.png
| assets/img/roles/medico_veterinario.png
|
*/

$rolClave = strtolower(
    trim((string) $rolActual)
);

$rolClave = str_replace(
    ['á', 'é', 'í', 'ó', 'ú'],
    ['a', 'e', 'i', 'o', 'u'],
    $rolClave
);

$nombresImagenRol = match ($rolClave) {
    'administrador',
    'admin' => [
        'administrador',
        'admin'
    ],

    'recepcionista',
    'recepcion' => [
        'recepcionista',
        'recepcion'
    ],

    'medico',
    'medico veterinario',
    'veterinario' => [
        'medico_veterinario',
        'medico-veterinario',
        'medico veterinario',
        'veterinario',
        'medico'
    ],

    default => [
        'usuario'
    ]
};

$carpetasImagenRol = [
    'assets/img/roles',
    'assets/img',
    'assets/imagenes',
    'assets/images',
    'imagenes',
    'img',
    'images'
];

$extensionesImagenRol = [
    'png',
    'jpg',
    'jpeg',
    'webp'
];

$imagenRol = '';
$imagenRolExiste = false;

foreach ($carpetasImagenRol as $carpetaImagenRol) {

    foreach ($nombresImagenRol as $nombreImagenRol) {

        foreach ($extensionesImagenRol as $extensionImagenRol) {

            $rutaCandidata =
                $carpetaImagenRol . '/' .
                $nombreImagenRol . '.' .
                $extensionImagenRol;

            $rutaFisicaCandidata = __DIR__
                . DIRECTORY_SEPARATOR
                . str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $rutaCandidata
                );

            if (is_file($rutaFisicaCandidata)) {
                $imagenRol = $rutaCandidata;
                $imagenRolExiste = true;
                break 3;
            }
        }
    }
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
| ICONOS SVG DEL DASHBOARD
|--------------------------------------------------------------------------
|
| Iconos integrados directamente en el archivo. No dependen de Internet,
| Font Awesome ni otras librerías externas.
|
*/

if (!function_exists('dashboard_icon')) {
    function dashboard_icon(string $nombre, int $tamano = 20): string
    {
        $iconos = [
            'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'paw' => '<circle cx="5.5" cy="8.5" r="2.2"/><circle cx="10" cy="5.5" r="2.2"/><circle cx="14.5" cy="5.5" r="2.2"/><circle cx="19" cy="8.5" r="2.2"/><path d="M12.25 12.2c-3.2 0-6.1 2.4-6.1 5.1 0 2 1.6 3.2 3.4 3.2 1 0 1.8-.4 2.7-.9.9.5 1.7.9 2.7.9 1.8 0 3.4-1.2 3.4-3.2 0-2.7-2.9-5.1-6.1-5.1Z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="3" y1="11" x2="21" y2="11"/><path d="M8 15h.01M12 15h.01M16 15h.01M8 18h.01M12 18h.01"/>',
            'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1.5"/><path d="M9 9h6M9 13h6M9 17h4"/>',
            'package' => '<path d="m21 8-9 5-9-5"/><path d="M3 8l9-5 9 5v8l-9 5-9-5Z"/><path d="M12 13v8"/><path d="m7.5 5.5 9 5"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 16v-5M12 16V7M17 16V4"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><circle cx="12" cy="9" r="2"/><path d="M8.8 15c.8-1.7 2-2.5 3.2-2.5s2.4.8 3.2 2.5"/>',
            'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-6"/>',
            'check' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
            'clinic' => '<path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 13h6"/><path d="M12 10v6"/><path d="M8 21v-3h8v3"/>',
            'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
            'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15" r="1"/>',
            'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        ];

        $contenido = $iconos[$nombre] ?? $iconos['home'];

        return '<svg class="ui-icon" width="' . $tamano . '" height="' . $tamano . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $contenido . '</svg>';
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
        'icono' => 'users',
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
        'icono' => 'paw',
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
        'icono' => 'calendar',
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
        'icono' => 'clipboard',
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
        'icono' => 'package',
        'descripcion' => 'Control de productos, medicamentos y existencias.',
        'permiso' => 'inventario.ver',
        'rutas' => [
            'inventario/index.php',
            'inventario/inventario.php',
            'inventario/productos.php'
        ]
    ],


    [
        'titulo' => 'Reportes',
        'icono' => 'chart',
        'descripcion' => 'Estadísticas e informes del sistema veterinario.',
        'permiso' => 'reportes.ver',
        'rutas' => [
            'reportes/index.php'
        ]
    ],

    [
        'titulo' => 'Usuarios',
        'icono' => 'shield',
        'descripcion' => 'Administración de usuarios, roles y permisos.',
        'permiso' => 'usuarios.ver',
        'rutas' => [
            'usuarios/index.php',
            'usuarios/usuarios.php',
            'usuarios/listar_usuarios.php'
        ]
    ],
    
];

/*
|--------------------------------------------------------------------------
| APLICAR LOS PERMISOS DEL ROL ACTUAL
|--------------------------------------------------------------------------
*/

$modulosDisponibles = 0;
$modulosSinPermiso = 0;

foreach ($modulos as $indice => $modulo) {
    $permitido = can($modulo['permiso']);
    $rutaEncontrada = encontrarRuta($modulo['rutas']);
    $archivoDisponible = $rutaEncontrada !== null;

    $modulos[$indice]['permitido'] = $permitido;
    $modulos[$indice]['archivo_disponible'] = $archivoDisponible;
    $modulos[$indice]['ruta'] = ($permitido && $archivoDisponible)
        ? $rutaEncontrada
        : null;

    if ($permitido && $archivoDisponible) {
        $modulosDisponibles++;
    }

    if (!$permitido) {
        $modulosSinPermiso++;
    }
}

$totalModulos = count($modulos);
$modulosNoDisponibles = $totalModulos - $modulosDisponibles;

$fechaActual = date('d/m/Y');
$horaActual = date('H:i');
$anioActual = date('Y');
$inicial = inicialUsuario($nombreUsuarioMostrar);
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
            overflow-x: hidden;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 25%),
                linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        ::selection {
            color: #0f172a;
            background: #bfdbfe;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, 0.28);
            outline-offset: 3px;
        }

        .app-layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
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
            padding: 20px 16px;
            color: #cbd5e1;
            background: linear-gradient(
                180deg,
                #0b1f3a 0%,
                #0f3564 54%,
                #0b4d8f 100%
            );
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.28) transparent;
            box-shadow: 10px 0 30px rgba(2, 6, 23, 0.12);
            z-index: 100;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.24);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            display: grid;
            place-items: center;
            border-radius: 15px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.32);
        }

        .ui-icon {
            display: block;
            flex: 0 0 auto;
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
            gap: 7px;
        }

        .sidebar-link,
        .sidebar-link-disabled {
            position: relative;
            min-height: 50px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 8px 10px;
            border-radius: 13px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 0.01em;
            transition:
                transform 0.2s ease,
                background-color 0.2s ease,
                color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .sidebar-link {
            color: #cbd5e1;
        }

        .sidebar-link:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(2px);
        }

        .sidebar-link.active {
            color: #ffffff;
            background: rgba(37, 99, 235, 0.34);
            box-shadow: inset 3px 0 0 #60a5fa;
        }

        .sidebar-link-disabled {
            color: #71829a;
            cursor: not-allowed;
            opacity: 0.72;
        }

        .sidebar-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 10px;
            color: #93c5fd;
            background: rgba(255, 255, 255, 0.06);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .sidebar-link:hover .sidebar-icon,
        .sidebar-link.active .sidebar-icon {
            color: #ffffff;
            background: rgba(96, 165, 250, 0.18);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }

        .logout-button {
            min-height: 48px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 10px;
            border-radius: 13px;
            color: #dbeafe;
            font-size: 13.5px;
            font-weight: 700;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .logout-button:hover {
            color: #ffffff;
            background: rgba(239, 68, 68, 0.14);
        }

        .logout-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 10px;
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.10);
        }

        /* =========================
           IMAGEN DEL USUARIO SEGÚN ROL
           Se muestra en el bloque derecho del Dashboard
        ========================= */

        .hero-role-card {
            min-height: 194px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 14px 16px;
            text-align: center;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.24);
            backdrop-filter: blur(10px);
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.12);
        }

        .hero-role-image {
            width: 108px;
            height: 118px;
            display: block;
            object-fit: cover;
            object-position: center top;
            border-radius: 16px;
            border: 3px solid rgba(255, 255, 255, 0.96);
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.20);
        }

        .hero-role-fallback {
            width: 108px;
            height: 118px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            border: 3px solid rgba(255, 255, 255, 0.96);
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.20);
            font-size: 36px;
            font-weight: 900;
        }

        .hero-role-name {
            margin-top: 2px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
        }

        .hero-role-title {
            color: #dbeafe;
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.2;
        }

        .hero-role-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
            padding: 5px 9px;
            border-radius: 999px;
            color: #dcfce7;
            background: rgba(22, 163, 74, 0.18);
            border: 1px solid rgba(187, 247, 208, 0.24);
            font-size: 11px;
            font-weight: 800;
        }

        .hero-role-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #86efac;
            box-shadow: 0 0 0 3px rgba(134, 239, 172, 0.12);
        }

        /* =========================
           MAIN
        ========================= */

        .main-content {
            width: 100%;
            max-width: 1600px;
            min-width: 0;
            margin: 0 auto;
            padding: 24px 28px 36px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
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
            color: var(--text);
            font-size: clamp(25px, 2vw, 29px);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.025em;
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
            grid-template-columns: minmax(0, 1fr) 260px;
            align-items: center;
            gap: 22px;
            margin-bottom: 22px;
            padding: 24px 26px;
            border-radius: 24px;
            color: #fff;
            background: linear-gradient(135deg, #123f86 0%, #2563eb 58%, #3b82f6 100%);
            box-shadow: 0 16px 34px rgba(37, 99, 235, 0.20);
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
            margin-bottom: 8px;
            color: #dbeafe;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .hero h2 {
            font-size: clamp(28px, 2.2vw, 33px);
            line-height: 1.12;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .hero-wave {
            display: inline-block;
            margin-left: 4px;
            font-size: 0.86em;
            vertical-align: 0.04em;
        }

        .hero p {
            max-width: 730px;
            color: rgba(255, 255, 255, 0.90);
            line-height: 1.6;
            font-size: 14px;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 11px;
            font-weight: 800;
            font-size: 13px;
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
            gap: 14px;
            margin-bottom: 26px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 104px;
            padding: 16px 17px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.055);
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: #dbeafe;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .stat-card::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            opacity: 0.85;
        }

        .stat-card.stat-blue::after {
            background: linear-gradient(90deg, #2563eb, #93c5fd);
        }

        .stat-card.stat-green::after {
            background: linear-gradient(90deg, #16a34a, #86efac);
        }

        .stat-card.stat-violet::after {
            background: linear-gradient(90deg, #7c3aed, #c4b5fd);
        }

        .stat-card.stat-orange::after {
            background: linear-gradient(90deg, #d97706, #fcd34d);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            flex: 0 0 48px;
        }

        .stat-icon svg {
            width: 22px;
            height: 22px;
        }

        .stat-icon.blue {
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #dbeafe;
        }

        .stat-icon.green {
            color: #15803d;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
        }

        .stat-icon.violet {
            color: #6d28d9;
            background: #f5f3ff;
            border: 1px solid #ede9fe;
        }

        .stat-icon.orange {
            color: #b45309;
            background: #fffbeb;
            border: 1px solid #fef3c7;
        }

        .stat-info {
            min-width: 0;
        }

        .stat-info small {
            display: block;
            color: #64748b;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.055em;
            text-transform: uppercase;
        }

        .stat-info strong {
            display: block;
            margin-top: 4px;
            color: #0f172a;
            font-size: 24px;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .stat-info strong.stat-value-text {
            font-size: 16px;
            letter-spacing: 0;
        }

        .stat-info strong.stat-value-date {
            font-size: 14px;
            line-height: 1.3;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .stat-info span {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.35;
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
            gap: 16px;
            margin-bottom: 28px;
        }

        .module-card {
            --module-accent: #2563eb;
            --module-soft: #eff6ff;
            --module-border: #dbeafe;

            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 196px;
            padding: 19px;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #e5e7eb;
            border-radius: 19px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            transition:
                transform 0.22s ease,
                box-shadow 0.22s ease,
                border-color 0.22s ease;
        }

        .module-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--module-accent);
            opacity: 0.9;
        }

        .module-card:nth-child(2) {
            --module-accent: #16a34a;
            --module-soft: #f0fdf4;
            --module-border: #dcfce7;
        }

        .module-card:nth-child(3) {
            --module-accent: #7c3aed;
            --module-soft: #f5f3ff;
            --module-border: #ede9fe;
        }

        .module-card:nth-child(4) {
            --module-accent: #0891b2;
            --module-soft: #ecfeff;
            --module-border: #cffafe;
        }

        .module-card:nth-child(5) {
            --module-accent: #d97706;
            --module-soft: #fffbeb;
            --module-border: #fef3c7;
        }

        .module-card:nth-child(6) {
            --module-accent: #4f46e5;
            --module-soft: #eef2ff;
            --module-border: #e0e7ff;
        }

        .module-card:nth-child(7) {
            --module-accent: #475569;
            --module-soft: #f8fafc;
            --module-border: #e2e8f0;
        }

        .module-card:hover {
            transform: translateY(-3px);
            border-color: var(--module-border);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.09);
        }

        .module-card.disabled {
            opacity: 0.7;
            filter: saturate(0.75);
        }

        .module-card.disabled:hover {
            transform: none;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .module-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 13px;
        }

        .module-badge {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: var(--module-accent);
            background: var(--module-soft);
            border: 1px solid var(--module-border);
            transition: transform 0.2s ease;
        }

        .module-card:hover .module-badge {
            transform: scale(1.04);
        }

        .module-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }

        .module-status-dot {
            width: 6px;
            height: 6px;
            flex: 0 0 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-active {
            color: #15803d;
            background: #f0fdf4;
            border-color: #dcfce7;
        }

        .status-disabled {
            color: #a16207;
            background: #fffbeb;
            border-color: #fef3c7;
        }

        .module-card h4 {
            margin-bottom: 6px;
            color: var(--text);
            font-size: 18px;
            line-height: 1.3;
        }

        .module-card p {
            margin-bottom: 15px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .module-actions {
            margin-top: auto;
        }

        .module-button,
        .module-button-disabled {
            width: 100%;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 800;
        }

        .module-button {
            color: #ffffff;
            background: var(--module-accent);
            box-shadow: 0 6px 14px color-mix(in srgb, var(--module-accent) 18%, transparent);
            transition:
                filter 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
        }

        .module-button .ui-icon {
            transition: transform 0.2s ease;
        }

        .module-button:hover {
            filter: brightness(0.94);
            transform: translateY(-1px);
            box-shadow: 0 8px 18px color-mix(in srgb, var(--module-accent) 23%, transparent);
        }

        .module-button:hover .ui-icon {
            transform: translateX(3px);
        }

        .module-button-disabled {
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            cursor: not-allowed;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            gap: 18px;
            align-items: stretch;
        }

        .info-card {
            position: relative;
            overflow: hidden;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow-sm);
        }

        .info-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }

        .info-card.quick-card::before {
            background: linear-gradient(90deg, #16a34a, #4ade80);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .info-card-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .info-card-heading-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 42px;
            border-radius: 13px;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #dbeafe;
        }

        .quick-card .info-card-heading-icon {
            color: #15803d;
            background: #f0fdf4;
            border-color: #dcfce7;
        }

        .info-card-heading h3 {
            color: var(--text);
            font-size: 19px;
            line-height: 1.2;
        }

        .info-card-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
        }

        .info-card-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            padding: 7px 10px;
            border-radius: 999px;
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .info-card-pill .dot {
            width: 7px;
            height: 7px;
        }

        .summary-list,
        .quick-list {
            display: grid;
            gap: 10px;
        }

        .summary-item {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            min-height: 62px;
            padding: 10px 12px;
            background: #fbfdff;
            border: 1px solid #e8eef7;
            border-radius: 15px;
            transition:
                transform 0.18s ease,
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }

        .summary-item:hover {
            transform: translateY(-1px);
            border-color: #dbeafe;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.045);
        }

        .summary-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #dbeafe;
        }

        .summary-icon.green {
            color: #15803d;
            background: #f0fdf4;
            border-color: #dcfce7;
        }

        .summary-icon.violet {
            color: #6d28d9;
            background: #f5f3ff;
            border-color: #ede9fe;
        }

        .summary-icon.orange {
            color: #b45309;
            background: #fffbeb;
            border-color: #fef3c7;
        }

        .summary-icon.slate {
            color: #475569;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .summary-copy {
            min-width: 0;
        }

        .summary-copy span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.3;
        }

        .summary-copy small {
            display: block;
            margin-top: 2px;
            color: #94a3b8;
            font-size: 11px;
            line-height: 1.3;
        }

        .summary-value {
            max-width: 230px;
            color: var(--text);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.35;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .quick-link {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr) 24px;
            align-items: center;
            gap: 12px;
            min-height: 64px;
            padding: 10px 12px;
            color: var(--text);
            background: #fbfdff;
            border: 1px solid #e8eef7;
            border-radius: 15px;
            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background-color 0.2s ease;
        }

        .quick-link:hover {
            transform: translateX(3px);
            border-color: #bfdbfe;
            background: #ffffff;
            box-shadow: 0 9px 20px rgba(15, 23, 42, 0.055);
        }

        .quick-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            transition: transform 0.2s ease;
        }

        .quick-link:hover .quick-icon {
            transform: scale(1.04);
        }

        .quick-icon.green {
            color: #15803d;
            background: #f0fdf4;
            border-color: #dcfce7;
        }

        .quick-icon.violet {
            color: #6d28d9;
            background: #f5f3ff;
            border-color: #ede9fe;
        }

        .quick-icon.orange {
            color: #b45309;
            background: #fffbeb;
            border-color: #fef3c7;
        }

        .quick-icon.cyan {
            color: #0e7490;
            background: #ecfeff;
            border-color: #cffafe;
        }

        .quick-copy {
            min-width: 0;
        }

        .quick-copy strong {
            display: block;
            color: var(--text);
            font-size: 13px;
            font-weight: 850;
            line-height: 1.3;
        }

        .quick-copy span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.35;
        }

        .quick-arrow {
            display: grid;
            place-items: center;
            color: #94a3b8;
            transition:
                color 0.2s ease,
                transform 0.2s ease;
        }

        .quick-link:hover .quick-arrow {
            color: var(--primary);
            transform: translateX(2px);
        }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            display: none;
            background: rgba(15, 23, 42, 0.46);
            backdrop-filter: blur(2px);
            z-index: 90;
        }

        .sidebar-overlay.visible {
            display: block;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
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
                left: -285px;
                width: 270px;
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
                max-width: none;
                padding: 20px 18px 32px;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .hero-side {
                grid-template-columns: 1fr;
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

            .info-card-header {
                align-items: flex-start;
            }

            .info-card-pill {
                display: none;
            }

            .summary-item {
                grid-template-columns: 40px minmax(0, 1fr);
            }

            .summary-value {
                grid-column: 2;
                max-width: none;
                margin-top: -5px;
                text-align: left;
            }
        }

        @media (max-width: 520px) {
            .main-content {
                padding: 16px 12px 28px;
            }

            .topbar {
                gap: 12px;
                margin-bottom: 16px;
                padding-bottom: 13px;
            }

            .topbar-badges {
                width: 100%;
                gap: 8px;
            }

            .badge {
                padding: 8px 10px;
                font-size: 11.5px;
            }

            .hero {
                gap: 16px;
                margin-bottom: 18px;
                padding: 20px 16px;
                border-radius: 19px;
            }

            .hero h2 {
                font-size: 25px;
            }

            .hero p {
                font-size: 13px;
            }

            .hero-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .btn {
                width: 100%;
            }

            .hero-role-card {
                min-height: 0;
            }

            .stats-grid {
                gap: 11px;
            }

            .stat-card {
                min-height: 94px;
                padding: 14px;
                border-radius: 16px;
            }

            .module-grid {
                gap: 13px;
            }

            .module-card,
            .info-card {
                border-radius: 17px;
            }

            .module-card {
                min-height: 0;
                padding: 17px;
            }

            .info-card {
                padding: 17px;
            }

            .quick-link {
                grid-template-columns: 40px minmax(0, 1fr) 20px;
                gap: 10px;
            }

            .quick-icon {
                width: 38px;
                height: 38px;
            }

            .footer-note {
                margin-top: 20px;
                font-size: 11.5px;
            }
        }
    </style>
</head>

<body>

<div class="app-layout">

    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <div class="brand-logo"><?= dashboard_icon('paw', 24) ?></div>

            <div class="brand-text">
                <strong>Clínica Veterinaria</strong>
                <span>El Campo</span>
            </div>
        </div>

        <div class="nav-title">
            Menú principal
        </div>

        <nav class="sidebar-nav">

            <a class="sidebar-link active" href="<?= e(url('panel.php')) ?>">
                <span class="sidebar-icon"><?= dashboard_icon('home', 19) ?></span>
                Dashboard
            </a>

            <?php foreach ($modulos as $modulo): ?>

                <?php if (!$modulo['permitido']) continue; ?>

                <?php if ($modulo['ruta'] !== null): ?>

                    <a
                        class="sidebar-link"
                        href="<?= e(url($modulo['ruta'])) ?>"
                    >
                        <span class="sidebar-icon">
                            <?= dashboard_icon($modulo['icono'], 19) ?>
                        </span>

                        <?= e($modulo['titulo']) ?>
                    </a>

                <?php else: ?>

                    <span class="sidebar-link-disabled">
                        <span class="sidebar-icon">
                            <?= dashboard_icon($modulo['icono'], 19) ?>
                        </span>

                        <?= e($modulo['titulo']) ?>
                    </span>

                <?php endif; ?>

            <?php endforeach; ?>

        </nav>

        <div class="sidebar-footer">

            <a class="logout-button" href="<?= e(url('logout.php')) ?>">
                <span class="logout-icon"><?= dashboard_icon('logout', 19) ?></span>
                <span>Cerrar sesión</span>
            </a>

        </div>

    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="menu-button"
                    id="menuButton"
                    type="button"
                    aria-label="Abrir menú"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    <svg class="ui-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
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
                    <?= dashboard_icon('calendar', 17) ?> <?= e($fechaActual) ?> · <?= e($horaActual) ?>
                </div>

            </div>

        </header>

        <section class="hero">

            <div class="hero-content">

                <span class="hero-label">
                    Panel de control
                </span>

                <h2>
                    <?= e($saludo) ?>, <?= e($primerNombreUsuario) ?>
                    <span class="hero-wave" aria-hidden="true">👋</span>
                </h2>

                <p>
                    Administra clientes, mascotas, citas, historias clínicas e inventario
                    desde un solo lugar, de forma rápida y organizada.
                </p>

                <div class="hero-actions">

                    <?php if ($modulos[0]['ruta'] !== null): ?>
                        <a
                            href="<?= e(url($modulos[0]['ruta'])) ?>"
                            class="btn btn-primary"
                        >
                            <?= dashboard_icon('users', 18) ?> Clientes
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[2]['ruta'] !== null): ?>
                        <a
                            href="<?= e(url($modulos[2]['ruta'])) ?>"
                            class="btn btn-outline"
                        >
                            <?= dashboard_icon('calendar', 18) ?> Ver citas
                        </a>
                    <?php endif; ?>

                </div>

            </div>

            <div class="hero-side">

                <div class="hero-role-card">

                    <?php if ($imagenRolExiste): ?>

                        <img
                            class="hero-role-image"
                            src="<?= e(url($imagenRol)) ?>"
                            alt="<?= e('Imagen del rol ' . $nombreRol) ?>"
                        >

                    <?php else: ?>

                        <div
                            class="hero-role-fallback"
                            title="Agrega la imagen correspondiente en assets/img/roles/"
                        >
                            <?= e($inicial) ?>
                        </div>

                    <?php endif; ?>

                    <div class="hero-role-name">
                        <?= e($nombreUsuarioMostrar) ?>
                    </div>

                    <div class="hero-role-title">
                        <?= e($nombreRol) ?>
                    </div>

                    <div class="hero-role-status">
                        <span class="hero-role-status-dot"></span>
                        Activo
                    </div>

                </div>

            </div>

        </section>

        <section class="stats-grid">

            <article class="stat-card stat-blue">
                <div class="stat-icon blue"><?= dashboard_icon('package', 22) ?></div>

                <div class="stat-info">
                    <small>Total de módulos</small>
                    <strong><?= e((string) $totalModulos) ?></strong>
                    <span>Áreas registradas</span>
                </div>
            </article>

            <article class="stat-card stat-green">
                <div class="stat-icon green"><?= dashboard_icon('check', 22) ?></div>

                <div class="stat-info">
                    <small>Módulos permitidos</small>
                    <strong><?= e((string) $modulosDisponibles) ?></strong>
                    <span>Disponibles para tu rol</span>
                </div>
            </article>

            <article class="stat-card stat-violet">
                <div class="stat-icon violet"><?= dashboard_icon('user', 22) ?></div>

                <div class="stat-info">
                    <small>Rol actual</small>
                    <strong class="stat-value-text"><?= e($nombreRol) ?></strong>
                    <span>Perfil de acceso activo</span>
                </div>
            </article>

            <article class="stat-card stat-orange">
                <div class="stat-icon orange"><?= dashboard_icon('clock', 22) ?></div>

                <div class="stat-info">
                    <small>Fecha y hora</small>
                    <strong class="stat-value-date"><?= e($fechaActual) ?> · <?= e($horaActual) ?></strong>
                    <span>Hora local del sistema</span>
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
                                <?= dashboard_icon($modulo['icono'], 27) ?>
                            </div>

                            <?php if ($modulo['ruta'] !== null): ?>
                                <span class="module-status status-active">
                                    <span class="module-status-dot"></span>
                                    Disponible
                                </span>
                            <?php else: ?>
                                <span class="module-status status-disabled">
                                    <span class="module-status-dot"></span>
                                    No disponible
                                </span>
                            <?php endif; ?>
                        </div>

                        <h4><?= e($modulo['titulo']) ?></h4>

                        <p><?= e($modulo['descripcion']) ?></p>

                        <div class="module-actions">

                            <?php if ($modulo['ruta'] !== null): ?>
                                <a
                                    href="<?= e(url($modulo['ruta'])) ?>"
                                    class="module-button"
                                >
                                    Abrir módulo
                                    <?= dashboard_icon('arrow-right', 17) ?>
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

                <div class="info-card-header">
                    <div class="info-card-heading">
                        <div class="info-card-heading-icon">
                            <?= dashboard_icon('clinic', 20) ?>
                        </div>

                        <div>
                            <h3>Resumen del sistema</h3>
                            <p>Datos principales de tu sesión y nivel de acceso.</p>
                        </div>
                    </div>

                    <span class="info-card-pill">
                        <span class="dot"></span>
                        Sesión activa
                    </span>
                </div>

                <div class="summary-list">

                    <div class="summary-item">
                        <div class="summary-icon">
                            <?= dashboard_icon('clinic', 18) ?>
                        </div>

                        <div class="summary-copy">
                            <span>Nombre del sistema</span>
                            <small>Aplicación administrativa</small>
                        </div>

                        <strong class="summary-value">
                            Clínica Veterinaria El Campo
                        </strong>
                    </div>

                    <div class="summary-item">
                        <div class="summary-icon green">
                            <?= dashboard_icon('user', 18) ?>
                        </div>

                        <div class="summary-copy">
                            <span>Usuario activo</span>
                            <small>Sesión iniciada actualmente</small>
                        </div>

                        <strong class="summary-value">
                            <?= e($nombreUsuarioMostrar) ?>
                        </strong>
                    </div>

                    <div class="summary-item">
                        <div class="summary-icon violet">
                            <?= dashboard_icon('shield', 18) ?>
                        </div>

                        <div class="summary-copy">
                            <span>Rol asignado</span>
                            <small>Nivel de acceso del usuario</small>
                        </div>

                        <strong class="summary-value">
                            <?= e($nombreRol) ?>
                        </strong>
                    </div>

                    <div class="summary-item">
                        <div class="summary-icon orange">
                            <?= dashboard_icon('layers', 18) ?>
                        </div>

                        <div class="summary-copy">
                            <span>Módulos permitidos</span>
                            <small>Disponibles para tu rol</small>
                        </div>

                        <strong class="summary-value">
                            <?= e((string) $modulosDisponibles) ?> de <?= e((string) $totalModulos) ?>
                        </strong>
                    </div>

                    <div class="summary-item">
                        <div class="summary-icon slate">
                            <?= dashboard_icon('lock', 18) ?>
                        </div>

                        <div class="summary-copy">
                            <span>Módulos sin permiso</span>
                            <small>Restringidos para tu rol actual</small>
                        </div>

                        <strong class="summary-value">
                            <?= e((string) $modulosSinPermiso) ?>
                        </strong>
                    </div>

                </div>

            </div>

            <div class="info-card quick-card">

                <div class="info-card-header">
                    <div class="info-card-heading">
                        <div class="info-card-heading-icon">
                            <?= dashboard_icon('arrow-right', 20) ?>
                        </div>

                        <div>
                            <h3>Acciones rápidas</h3>
                            <p>Accesos directos a las tareas más utilizadas.</p>
                        </div>
                    </div>

                    <span class="info-card-pill">
                        <?= e((string) $modulosDisponibles) ?> accesos
                    </span>
                </div>

                <div class="quick-list">

                    <?php if ($modulos[0]['ruta'] !== null): ?>
                        <a href="<?= e(url($modulos[0]['ruta'])) ?>" class="quick-link">
                            <span class="quick-icon">
                                <?= dashboard_icon('users', 19) ?>
                            </span>

                            <span class="quick-copy">
                                <strong>Administrar clientes</strong>
                                <span>Registrar y consultar propietarios.</span>
                            </span>

                            <span class="quick-arrow">
                                <?= dashboard_icon('arrow-right', 17) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[1]['ruta'] !== null): ?>
                        <a href="<?= e(url($modulos[1]['ruta'])) ?>" class="quick-link">
                            <span class="quick-icon green">
                                <?= dashboard_icon('paw', 19) ?>
                            </span>

                            <span class="quick-copy">
                                <strong>Revisar mascotas</strong>
                                <span>Consultar fichas y datos de mascotas.</span>
                            </span>

                            <span class="quick-arrow">
                                <?= dashboard_icon('arrow-right', 17) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[2]['ruta'] !== null): ?>
                        <a href="<?= e(url($modulos[2]['ruta'])) ?>" class="quick-link">
                            <span class="quick-icon violet">
                                <?= dashboard_icon('calendar', 19) ?>
                            </span>

                            <span class="quick-copy">
                                <strong>Consultar citas</strong>
                                <span>Revisar la agenda veterinaria.</span>
                            </span>

                            <span class="quick-arrow">
                                <?= dashboard_icon('arrow-right', 17) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if ($modulos[4]['ruta'] !== null): ?>
                        <a href="<?= e(url($modulos[4]['ruta'])) ?>" class="quick-link">
                            <span class="quick-icon orange">
                                <?= dashboard_icon('package', 19) ?>
                            </span>

                            <span class="quick-copy">
                                <strong>Ver inventario</strong>
                                <span>Controlar productos y existencias.</span>
                            </span>

                            <span class="quick-arrow">
                                <?= dashboard_icon('arrow-right', 17) ?>
                            </span>
                        </a>
                    <?php endif; ?>

                    <?php if (isset($modulos[5]) && $modulos[5]['ruta'] !== null): ?>
                        <a href="<?= e(url($modulos[5]['ruta'])) ?>" class="quick-link">
                            <span class="quick-icon cyan">
                                <?= dashboard_icon('chart', 19) ?>
                            </span>

                            <span class="quick-copy">
                                <strong>Consultar reportes</strong>
                                <span>Visualizar estadísticas e informes.</span>
                            </span>

                            <span class="quick-arrow">
                                <?= dashboard_icon('arrow-right', 17) ?>
                            </span>
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
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function setSidebarState(open) {
        if (!sidebar || !menuButton) return;

        sidebar.classList.toggle('open', open);
        menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuButton.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');

        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('visible', open);
            sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    }

    if (menuButton && sidebar) {
        menuButton.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('open');
            setSidebarState(!isOpen);
        });

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                setSidebarState(false);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setSidebarState(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 980) {
                setSidebarState(false);
            }
        });

        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 980) {
                    setSidebarState(false);
                }
            });
        });
    }
</script>

</body>
</html>