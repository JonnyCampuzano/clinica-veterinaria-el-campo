<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN GENERAL DEL SISTEMA
|--------------------------------------------------------------------------
| Archivo:
| C:\xamp1\htdocs\clinica_veterinaria_el_campo\configuracion\index.php
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__);

require_once $raiz . '/config/app.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| CONTROL DE ACCESO
|--------------------------------------------------------------------------
*/

require_permission('configuracion.ver');

/*
|--------------------------------------------------------------------------
| COMPROBAR CONEXIÓN PDO
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        '<div style="
            max-width:750px;
            margin:50px auto;
            padding:25px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fff1f2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error de conexión</strong><br><br>
            No se encontró una conexión PDO válida en
            <code>config/conexion.php</code>.
        </div>'
    );
}

/*
|--------------------------------------------------------------------------
| CREAR TABLA AUTOMÁTICAMENTE
|--------------------------------------------------------------------------
| No necesitas crearla manualmente desde phpMyAdmin.
|--------------------------------------------------------------------------
*/

try {

    $pdo->exec(
        "
        CREATE TABLE IF NOT EXISTS configuracion_sistema (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,

            nombre_clinica VARCHAR(150) NOT NULL,

            ciudad VARCHAR(150) NOT NULL DEFAULT '',

            direccion VARCHAR(255) NOT NULL DEFAULT '',

            telefono VARCHAR(30) NOT NULL DEFAULT '',

            correo VARCHAR(150) NOT NULL DEFAULT '',

            horario VARCHAR(150) NOT NULL DEFAULT '',

            descripcion VARCHAR(255) NOT NULL DEFAULT '',

            actualizado_en TIMESTAMP
                NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
        "
    );

} catch (Throwable $e) {

    error_log(
        'Error creando configuracion_sistema: ' .
        $e->getMessage()
    );

    exit(
        '<div style="
            max-width:750px;
            margin:50px auto;
            padding:25px;
            border:1px solid #fecaca;
            border-radius:12px;
            background:#fff1f2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error:</strong><br><br>
            No fue posible crear la tabla de configuración.
        </div>'
    );
}

/*
|--------------------------------------------------------------------------
| CREAR CONFIGURACIÓN INICIAL
|--------------------------------------------------------------------------
*/

try {

    $insertarInicial = $pdo->prepare(
        "
        INSERT IGNORE INTO configuracion_sistema
        (
            id,
            nombre_clinica,
            ciudad,
            direccion,
            telefono,
            correo,
            horario,
            descripcion
        )
        VALUES
        (
            1,
            :nombre_clinica,
            :ciudad,
            :direccion,
            :telefono,
            :correo,
            :horario,
            :descripcion
        )
        "
    );

    $insertarInicial->execute([
        'nombre_clinica' =>
            'Clínica Veterinaria El Campo',

        'ciudad' =>
            'Nobol, Guayas – Ecuador',

        'direccion' =>
            'Nobol, Guayas – Ecuador',

        'telefono' =>
            '0990000000',

        'correo' =>
            'elcampo@veterinaria.ec',

        'horario' =>
            'Lun – Vie: 08h00 – 17h00',

        'descripcion' =>
            'Salud y bienestar para tus mascotas.'
    ]);

} catch (Throwable $e) {

    error_log(
        'Error insertando configuración inicial: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_configuracion'])) {

    $_SESSION['csrf_configuracion'] =
        bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| PROCESAR FORMULARIO
|--------------------------------------------------------------------------
*/

$mensajeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Verificar permiso para modificar
    |--------------------------------------------------------------------------
    */

    require_permission('configuracion.editar');

    /*
    |--------------------------------------------------------------------------
    | Verificar token
    |--------------------------------------------------------------------------
    */

    $tokenFormulario = (string) (
        $_POST['csrf_token'] ?? ''
    );

    $tokenSesion = (string) (
        $_SESSION['csrf_configuracion'] ?? ''
    );

    if (
        $tokenFormulario === '' ||
        $tokenSesion === '' ||
        !hash_equals(
            $tokenSesion,
            $tokenFormulario
        )
    ) {

        $mensajeError =
            'La sesión del formulario expiró. Recarga la página.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Recibir datos
        |--------------------------------------------------------------------------
        */

        $nombreClinica = trim(
            (string) ($_POST['nombre_clinica'] ?? '')
        );

        $ciudad = trim(
            (string) ($_POST['ciudad'] ?? '')
        );

        $direccion = trim(
            (string) ($_POST['direccion'] ?? '')
        );

        $telefono = trim(
            (string) ($_POST['telefono'] ?? '')
        );

        $correo = trim(
            (string) ($_POST['correo'] ?? '')
        );

        $horario = trim(
            (string) ($_POST['horario'] ?? '')
        );

        $descripcion = trim(
            (string) ($_POST['descripcion'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDACIONES
        |--------------------------------------------------------------------------
        */

        if ($nombreClinica === '') {

            $mensajeError =
                'Ingrese el nombre de la clínica.';

        } elseif ($ciudad === '') {

            $mensajeError =
                'Ingrese la ciudad o ubicación.';

        } elseif ($correo === '') {

            $mensajeError =
                'Ingrese el correo electrónico.';

        } elseif (
            !filter_var(
                $correo,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $mensajeError =
                'El correo electrónico no es válido.';

        } elseif (strlen($nombreClinica) > 150) {

            $mensajeError =
                'El nombre de la clínica es demasiado largo.';

        } elseif (strlen($correo) > 150) {

            $mensajeError =
                'El correo electrónico es demasiado largo.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | GUARDAR CAMBIOS
            |--------------------------------------------------------------------------
            */

            try {

                $actualizar = $pdo->prepare(
                    "
                    UPDATE configuracion_sistema

                    SET
                        nombre_clinica = :nombre_clinica,
                        ciudad = :ciudad,
                        direccion = :direccion,
                        telefono = :telefono,
                        correo = :correo,
                        horario = :horario,
                        descripcion = :descripcion

                    WHERE id = 1
                    "
                );

                $actualizar->execute([
                    'nombre_clinica' =>
                        $nombreClinica,

                    'ciudad' =>
                        $ciudad,

                    'direccion' =>
                        $direccion,

                    'telefono' =>
                        $telefono,

                    'correo' =>
                        $correo,

                    'horario' =>
                        $horario,

                    'descripcion' =>
                        $descripcion
                ]);

                /*
                |--------------------------------------------------------------------------
                | Nuevo token
                |--------------------------------------------------------------------------
                */

                $_SESSION['csrf_configuracion'] =
                    bin2hex(random_bytes(32));

                /*
                |--------------------------------------------------------------------------
                | Redireccionar
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ' .
                    url(
                        'configuracion/index.php?guardado=1'
                    )
                );

                exit;

            } catch (Throwable $e) {

                error_log(
                    'Error guardando configuración: ' .
                    $e->getMessage()
                );

                $mensajeError =
                    'No fue posible guardar los cambios.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CONSULTAR CONFIGURACIÓN ACTUAL
|--------------------------------------------------------------------------
*/

try {

    $consulta = $pdo->query(
        "
        SELECT
            id,
            nombre_clinica,
            ciudad,
            direccion,
            telefono,
            correo,
            horario,
            descripcion,
            actualizado_en

        FROM configuracion_sistema

        WHERE id = 1

        LIMIT 1
        "
    );

    $configuracion =
        $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$configuracion) {

        throw new RuntimeException(
            'No existe la configuración.'
        );
    }

} catch (Throwable $e) {

    error_log(
        'Error leyendo configuración: ' .
        $e->getMessage()
    );

    exit(
        'No fue posible cargar la configuración.'
    );
}

/*
|--------------------------------------------------------------------------
| USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

$usuario = current_user();

$nombreUsuario = trim(
    (string) (
        $usuario['nombre']
        ?? 'Usuario'
    )
);

if ($nombreUsuario === '') {
    $nombreUsuario = 'Usuario';
}

$correoUsuario = trim(
    (string) (
        $usuario['correo']
        ?? $usuario['email']
        ?? ''
    )
);

$rolUsuario = function_exists('role_label')
    ? role_label()
    : ucfirst(current_role());

$inicial = function_exists('mb_substr')
    ? mb_strtoupper(
        mb_substr(
            $nombreUsuario,
            0,
            1,
            'UTF-8'
        ),
        'UTF-8'
    )
    : strtoupper(
        substr(
            $nombreUsuario,
            0,
            1
        )
    );

$guardado =
    isset($_GET['guardado']) &&
    $_GET['guardado'] === '1';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Configuración | Clínica Veterinaria El Campo
    </title>

    <!-- CSS GENERAL DEL SISTEMA -->
    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <style>

        /* =====================================================
           CONFIGURACIÓN
        ===================================================== */

        .config-wrapper {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
        }

        .config-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 24px;
        }

        .config-header h2 {
            margin: 0 0 7px;

            color: #183153;
            font-size: 24px;
        }

        .config-header p {
            margin: 0;

            color: #64748b;
            font-size: 14px;
        }

        .config-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            padding: 8px 13px;

            border-radius: 20px;

            background: #dcfce7;
            color: #166534;

            font-size: 13px;
            font-weight: 700;
        }

        .config-grid {
            display: grid;

            grid-template-columns:
                minmax(0, 2fr)
                minmax(260px, 0.8fr);

            gap: 22px;

            align-items: start;
        }

        .config-card {
            background: #ffffff;

            border: 1px solid #dbe5f0;
            border-radius: 14px;

            box-shadow:
                0 3px 12px
                rgba(15, 23, 42, 0.04);

            overflow: hidden;
        }

        .config-card-header {
            padding: 19px 22px;

            border-bottom: 1px solid #e5edf5;

            background: #fbfdff;
        }

        .config-card-header h3 {
            margin: 0 0 5px;

            color: #17345b;
            font-size: 18px;
        }

        .config-card-header p {
            margin: 0;

            color: #64748b;
            font-size: 13px;
        }

        .config-card-body {
            padding: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #334155;

            font-size: 13px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;

            min-height: 44px;

            padding: 10px 12px;

            border: 1px solid #cbd5e1;
            border-radius: 8px;

            outline: none;

            background: #ffffff;
            color: #172033;

            font-family: inherit;
            font-size: 14px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-control:focus {
            border-color: #3b82f6;

            box-shadow:
                0 0 0 3px
                rgba(59, 130, 246, 0.12);
        }

        .config-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;

            margin-top: 22px;

            padding-top: 20px;

            border-top: 1px solid #edf2f7;
        }

        .config-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            min-height: 42px;

            padding: 9px 17px;

            border: 0;
            border-radius: 8px;

            cursor: pointer;

            text-decoration: none;

            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
        }

        .config-btn-primary {
            background: #3b82f6;
            color: #ffffff;
        }

        .config-btn-primary:hover {
            background: #2563eb;
        }

        .config-btn-secondary {
            background: #eef4fb;
            color: #334155;
        }

        .config-btn-secondary:hover {
            background: #e2e8f0;
        }

        .config-alert {
            margin-bottom: 20px;

            padding: 13px 16px;

            border-radius: 9px;

            font-size: 14px;
            font-weight: 600;
        }

        .config-alert-success {
            border: 1px solid #bbf7d0;

            background: #f0fdf4;
            color: #166534;
        }

        .config-alert-error {
            border: 1px solid #fecaca;

            background: #fef2f2;
            color: #991b1b;
        }

        .info-list {
            display: flex;
            flex-direction: column;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            gap: 15px;

            padding: 13px 0;

            border-bottom: 1px solid #eef2f7;
        }

        .info-item:last-child {
            border-bottom: 0;
        }

        .info-item span {
            color: #64748b;
            font-size: 13px;
        }

        .info-item strong {
            color: #1e293b;

            font-size: 13px;
            text-align: right;
        }

        .security-box {
            margin-top: 18px;

            padding: 15px;

            border-radius: 10px;

            background: #eff6ff;
            color: #1e40af;

            font-size: 13px;
            line-height: 1.55;
        }

        .footer-config {
            margin-top: 30px;

            padding-bottom: 18px;

            color: #64748b;

            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 900px) {

            .config-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .config-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .config-actions {
                flex-direction: column;
            }

            .config-btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="app-shell">

    <!-- =================================================
         MENÚ LATERAL
    ================================================== -->

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

        <nav
            class="nav-menu"
            aria-label="Menú principal"
        >

            <?php if (can('dashboard.ver')): ?>

                <a href="<?= e(url('panel.php')) ?>">
                    <span class="nav-icon">🏠</span>
                    <span>Panel</span>
                </a>

            <?php endif; ?>


            <?php if (can('clientes.ver')): ?>

                <a
                    href="<?= e(
                        url('clientes/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">👥</span>
                    <span>Clientes</span>
                </a>

            <?php endif; ?>


            <?php if (can('mascotas.ver')): ?>

                <a
                    href="<?= e(
                        url('mascotas/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">🐾</span>
                    <span>Mascotas</span>
                </a>

            <?php endif; ?>


            <?php if (can('citas.ver')): ?>

                <a
                    href="<?= e(
                        url('citas/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">📅</span>
                    <span>Citas</span>
                </a>

            <?php endif; ?>


            <?php if (can('historias.ver')): ?>

                <a
                    href="<?= e(
                        url('consultas/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">🩺</span>
                    <span>Historia clínica</span>
                </a>

            <?php endif; ?>


            <?php if (can('inventario.ver')): ?>

                <a
                    href="<?= e(
                        url('inventario/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">📦</span>
                    <span>Inventario</span>
                </a>

            <?php endif; ?>


            <?php if (can('reportes.ver')): ?>

                <a
                    href="<?= e(
                        url('reportes/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">📊</span>
                    <span>Reportes</span>
                </a>

            <?php endif; ?>


            <?php if (can('usuarios.ver')): ?>

                <a
                    href="<?= e(
                        url('usuarios/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">🔐</span>
                    <span>Usuarios</span>
                </a>

            <?php endif; ?>


            <?php if (can('configuracion.ver')): ?>

                <a
                    class="active"
                    href="<?= e(
                        url('configuracion/index.php')
                    ) ?>"
                >
                    <span class="nav-icon">⚙️</span>
                    <span>Configuración</span>
                </a>

            <?php endif; ?>

        </nav>


        <div class="sidebar-footer">

            <span class="user-role">
                <?= e($rolUsuario) ?>
            </span>

            <a
                class="logout-link"
                href="<?= e(url('logout.php')) ?>"
            >
                ↩ Cerrar sesión
            </a>

        </div>

    </aside>


    <!-- =================================================
         CONTENIDO
    ================================================== -->

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
                    Configuración
                </h1>

                <p>
                    Gestión veterinaria integral
                </p>

            </div>

            <div class="user-chip">

                <span class="avatar">
                    <?= e($inicial) ?>
                </span>

                <div class="user-information">

                    <strong>
                        <?= e($nombreUsuario) ?>
                    </strong>

                    <small>
                        <?= e($correoUsuario) ?>
                    </small>

                </div>

            </div>

        </header>


        <section class="content">

            <div class="config-wrapper">

                <!-- ENCABEZADO -->

                <div class="config-header">

                    <div>

                        <h2>
                            ⚙️ Configuración general
                        </h2>

                        <p>
                            Administre los datos principales
                            de la clínica veterinaria.
                        </p>

                    </div>

                    <span class="config-badge">
                        ● Sistema activo
                    </span>

                </div>


                <!-- MENSAJE CORRECTO -->

                <?php if ($guardado): ?>

                    <div
                        class="
                            config-alert
                            config-alert-success
                        "
                    >
                        ✅ Configuración guardada
                        correctamente.
                    </div>

                <?php endif; ?>


                <!-- MENSAJE ERROR -->

                <?php if ($mensajeError !== ''): ?>

                    <div
                        class="
                            config-alert
                            config-alert-error
                        "
                    >
                        ⚠️ <?= e($mensajeError) ?>
                    </div>

                <?php endif; ?>


                <div class="config-grid">

                    <!-- ======================================
                         FORMULARIO
                    ======================================= -->

                    <section class="config-card">

                        <div class="config-card-header">

                            <h3>
                                🏥 Datos de la clínica
                            </h3>

                            <p>
                                Esta información queda almacenada
                                en la base de datos.
                            </p>

                        </div>

                        <div class="config-card-body">

                            <form
                                method="POST"
                                action="<?= e(
                                    url(
                                        'configuracion/index.php'
                                    )
                                ) ?>"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(
                                        $_SESSION[
                                            'csrf_configuracion'
                                        ]
                                    ) ?>"
                                >

                                <div class="form-grid">

                                    <!-- NOMBRE -->

                                    <div
                                        class="
                                            form-group
                                            full
                                        "
                                    >

                                        <label
                                            for="nombre_clinica"
                                        >
                                            Nombre de la clínica
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            id="nombre_clinica"
                                            name="nombre_clinica"
                                            maxlength="150"
                                            value="<?= e(
                                                $configuracion[
                                                    'nombre_clinica'
                                                ]
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <!-- CIUDAD -->

                                    <div class="form-group">

                                        <label for="ciudad">
                                            Ciudad / ubicación
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            id="ciudad"
                                            name="ciudad"
                                            maxlength="150"
                                            value="<?= e(
                                                $configuracion[
                                                    'ciudad'
                                                ]
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <!-- TELÉFONO -->

                                    <div class="form-group">

                                        <label for="telefono">
                                            Teléfono
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            id="telefono"
                                            name="telefono"
                                            maxlength="30"
                                            value="<?= e(
                                                $configuracion[
                                                    'telefono'
                                                ]
                                            ) ?>"
                                        >

                                    </div>


                                    <!-- CORREO -->

                                    <div class="form-group">

                                        <label for="correo">
                                            Correo electrónico
                                        </label>

                                        <input
                                            class="form-control"
                                            type="email"
                                            id="correo"
                                            name="correo"
                                            maxlength="150"
                                            value="<?= e(
                                                $configuracion[
                                                    'correo'
                                                ]
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <!-- HORARIO -->

                                    <div class="form-group">

                                        <label for="horario">
                                            Horario de atención
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            id="horario"
                                            name="horario"
                                            maxlength="150"
                                            value="<?= e(
                                                $configuracion[
                                                    'horario'
                                                ]
                                            ) ?>"
                                        >

                                    </div>


                                    <!-- DIRECCIÓN -->

                                    <div
                                        class="
                                            form-group
                                            full
                                        "
                                    >

                                        <label for="direccion">
                                            Dirección
                                        </label>

                                        <input
                                            class="form-control"
                                            type="text"
                                            id="direccion"
                                            name="direccion"
                                            maxlength="255"
                                            value="<?= e(
                                                $configuracion[
                                                    'direccion'
                                                ]
                                            ) ?>"
                                        >

                                    </div>


                                    <!-- DESCRIPCIÓN -->

                                    <div
                                        class="
                                            form-group
                                            full
                                        "
                                    >

                                        <label for="descripcion">
                                            Descripción
                                        </label>

                                        <textarea
                                            class="form-control"
                                            id="descripcion"
                                            name="descripcion"
                                            maxlength="255"
                                        ><?= e(
                                            $configuracion[
                                                'descripcion'
                                            ]
                                        ) ?></textarea>

                                    </div>

                                </div>


                                <!-- BOTONES -->

                                <div class="config-actions">

                                    <button
                                        class="
                                            config-btn
                                            config-btn-secondary
                                        "
                                        type="reset"
                                    >
                                        ↺ Cancelar cambios
                                    </button>

                                    <button
                                        class="
                                            config-btn
                                            config-btn-primary
                                        "
                                        type="submit"
                                    >
                                        💾 Guardar cambios
                                    </button>

                                </div>

                            </form>

                        </div>

                    </section>


                    <!-- ======================================
                         INFORMACIÓN
                    ======================================= -->

                    <aside>

                        <section class="config-card">

                            <div class="config-card-header">

                                <h3>
                                    📋 Estado del sistema
                                </h3>

                                <p>
                                    Información de configuración.
                                </p>

                            </div>

                            <div class="config-card-body">

                                <div class="info-list">

                                    <div class="info-item">

                                        <span>
                                            Base de datos
                                        </span>

                                        <strong>
                                            Conectada ✅
                                        </strong>

                                    </div>


                                    <div class="info-item">

                                        <span>
                                            Usuario
                                        </span>

                                        <strong>
                                            <?= e(
                                                $nombreUsuario
                                            ) ?>
                                        </strong>

                                    </div>


                                    <div class="info-item">

                                        <span>
                                            Rol
                                        </span>

                                        <strong>
                                            <?= e(
                                                $rolUsuario
                                            ) ?>
                                        </strong>

                                    </div>


                                    <div class="info-item">

                                        <span>
                                            Última modificación
                                        </span>

                                        <strong>
                                            <?= e(
                                                $configuracion[
                                                    'actualizado_en'
                                                ]
                                            ) ?>
                                        </strong>

                                    </div>

                                </div>


                                <div class="security-box">

                                    🔐 <strong>Seguridad:</strong>

                                    este módulo está protegido
                                    mediante permisos y solamente
                                    los usuarios autorizados pueden
                                    modificar la configuración.

                                </div>

                            </div>

                        </section>

                    </aside>

                </div>


                <p class="footer-config">

                    © <?= e(date('Y')) ?>
                    Clínica Veterinaria El Campo ·
                    Configuración del sistema

                </p>

            </div>

        </section>

    </main>

</div>


<script>

    const menuButton =
        document.getElementById('menuButton');

    const sidebar =
        document.getElementById('sidebar');

    if (menuButton && sidebar) {

        menuButton.addEventListener(
            'click',
            function () {

                sidebar.classList.toggle('open');

            }
        );

        document.addEventListener(
            'click',
            function (event) {

                const dentroDelMenu =
                    sidebar.contains(event.target) ||
                    menuButton.contains(event.target);

                if (
                    window.innerWidth <= 900 &&
                    !dentroDelMenu
                ) {

                    sidebar.classList.remove('open');

                }

            }
        );

    }

</script>

</body>
</html>