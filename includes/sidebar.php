<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_login();

$usuarioActual = current_user();
$rolActual = current_role();

$nombreUsuario = $usuarioActual['nombre'];

if ($nombreUsuario === '') {
    $nombreUsuario = 'Usuario';
}

$primeraLetra = strtoupper(
    substr($nombreUsuario, 0, 1)
);

$rutaActual = str_replace(
    '\\',
    '/',
    (string) ($_SERVER['REQUEST_URI'] ?? '')
);

$escapar = static function (string $texto): string {
    return htmlspecialchars(
        $texto,
        ENT_QUOTES,
        'UTF-8'
    );
};

$claseActiva = static function (
    string $ruta
) use ($rutaActual): string {
    return str_contains($rutaActual, $ruta)
        ? 'active'
        : '';
};
?>

<aside class="sidebar">

    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            🐾
        </div>

        <div>
            <strong>Clínica Veterinaria</strong>
            <span>El Campo</span>
        </div>
    </div>

    <div class="sidebar-separator"></div>

    <p class="sidebar-title">
        MENÚ PRINCIPAL
    </p>

    <nav class="sidebar-menu">

        <!-- DASHBOARD -->
        <a
            href="<?= $escapar(auth_url('panel.php')) ?>"
            class="<?= $claseActiva('panel.php') ?>"
        >
            <span class="menu-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <!-- CLIENTES -->
        <?php if (can('clientes.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/clientes/index.php')
                ) ?>"
                class="<?= $claseActiva('/clientes/') ?>"
            >
                <span class="menu-icon">👥</span>
                <span>Clientes</span>
            </a>
        <?php endif; ?>

        <!-- MASCOTAS -->
        <?php if (can('mascotas.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/mascotas/index.php')
                ) ?>"
                class="<?= $claseActiva('/mascotas/') ?>"
            >
                <span class="menu-icon">🐾</span>
                <span>Mascotas</span>
            </a>
        <?php endif; ?>

        <!-- CITAS -->
        <?php if (can('citas.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/citas/index.php')
                ) ?>"
                class="<?= $claseActiva('/citas/') ?>"
            >
                <span class="menu-icon">🗓️</span>
                <span>Citas</span>
            </a>
        <?php endif; ?>

        <!-- HISTORIA CLÍNICA -->
        <?php if (can('historias.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/historias/index.php')
                ) ?>"
                class="<?= $claseActiva('/historias/') ?>"
            >
                <span class="menu-icon">📋</span>
                <span>Historia Clínica</span>
            </a>
        <?php endif; ?>

        <!-- INVENTARIO -->
        <?php if (can('inventario.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/inventario/index.php')
                ) ?>"
                class="<?= $claseActiva('/inventario/') ?>"
            >
                <span class="menu-icon">📦</span>
                <span>Inventario</span>
            </a>
        <?php endif; ?>

        <!-- reporte -->
         <?php if (can('reportes.ver')): ?>
            <a 
                 href="<?= url('modulos/reportes/index.php') ?>" class="menu-item">
                <span>📊</span>
                <span>Reportes</span>
            </a>
        <?php endif; ?>

        <!-- USUARIOS -->
        <?php if (can('usuarios.ver')): ?>
            <a
                href="<?= $escapar(
                    auth_url('modulos/usuarios/index.php')
                ) ?>"
                class="<?= $claseActiva('/usuarios/') ?>"
            >
                <span class="menu-icon">🔐</span>
                <span>Usuarios</span>
            </a>

    
        <?php endif; ?>

    </nav>

    <div class="sidebar-bottom">

        <div class="sidebar-user">
            <div class="user-avatar">
                <?= $escapar($primeraLetra) ?>
            </div>

            <div>
                <strong>
                    <?= $escapar($nombreUsuario) ?>
                </strong>

                <span>
                    <?= $escapar(role_label($rolActual)) ?>
                </span>
            </div>
        </div>

        <a
            class="logout-button"
            href="<?= $escapar(auth_url('logout.php')) ?>"
        >
            🚪 Cerrar sesión
        </a>

    </div>

</aside>

