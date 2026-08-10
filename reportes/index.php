<?php
declare(strict_types=1);



$raiz = dirname(__DIR__);

require_once $raiz . '/config/app.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| INICIAR SESIÓN Y COMPROBAR PERMISO
|--------------------------------------------------------------------------
*/

if (function_exists('require_login')) {
    require_login();
}

if (function_exists('require_permission')) {
    require_permission('reportes.ver');
}


/*
|--------------------------------------------------------------------------
| FUNCIONES AUXILIARES
|--------------------------------------------------------------------------
*/

if (!function_exists('rep_e')) {
    function rep_e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


if (!function_exists('rep_url')) {
    function rep_url(string $ruta = ''): string
    {
        $ruta = ltrim(trim($ruta), '/');

        // Corrige automáticamente cualquier ruta antigua en singular.
        if ($ruta === 'reporte' || $ruta === 'reporte/') {
            $ruta = 'reportes/index.php';
        } elseif (str_starts_with($ruta, 'reporte/')) {
            $ruta = 'reportes/' . substr($ruta, strlen('reporte/'));
        }

        if (function_exists('url')) {
            return url($ruta);
        }

        $base = defined('APP_URL')
            ? rtrim((string) APP_URL, '/')
            : '/clinica_veterinaria_el_campo';

        if ($ruta === '') {
            return $base . '/';
        }

        return $base . '/' . $ruta;
    }
}


if (!function_exists('rep_usuario')) {
    function rep_usuario(): string
    {
        /*
        |--------------------------------------------------------------------------
        | Intentar obtener usuario desde current_user()
        |--------------------------------------------------------------------------
        */

        if (function_exists('current_user')) {

            $usuario = current_user();

            $nombre = trim(
                (string) (
                    $usuario['nombre']
                    ?? $usuario['nombres']
                    ?? $usuario['usuario']
                    ?? ''
                )
            );

            if ($nombre !== '') {
                return $nombre;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Intentar obtener usuario desde la sesión
        |--------------------------------------------------------------------------
        */

        $nombreSesion = trim(
            (string) (
                $_SESSION['nombre']
                ?? $_SESSION['nombres']
                ?? $_SESSION['usuario']
                ?? ''
            )
        );

        if ($nombreSesion !== '') {
            return $nombreSesion;
        }

        return 'Usuario';
    }
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
|
| Inicializamos todo en cero para evitar errores si alguna consulta todavía
| no está conectada.
|
*/

$stats = [
    'clientes'    => 0,
    'mascotas'    => 0,
    'citas_hoy'   => 0,
    'historias'   => 0,
    'citas'       => 0,
    'inventario'  => 0,
    'stock_bajo'  => 0,
    'usuarios'    => 0,
];

$error = '';


/*
|--------------------------------------------------------------------------
| INTENTAR CARGAR ESTADÍSTICAS DE LA BASE DE DATOS
|--------------------------------------------------------------------------
|
| Este bloque solamente se ejecuta si tu proyecto ya tiene una variable
| PDO llamada $pdo.
|
*/

if (isset($pdo) && $pdo instanceof PDO) {

    /*
    |--------------------------------------------------------------------------
    | CLIENTES
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM clientes"
        );

        $stats['clientes'] = (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0 si la tabla no existe.
    }


    /*
    |--------------------------------------------------------------------------
    | MASCOTAS
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM mascotas"
        );

        $stats['mascotas'] = (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL DE CITAS
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM citas"
        );

        $stats['citas'] = (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }


    /*
    |--------------------------------------------------------------------------
    | CITAS DE HOY
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "
            SELECT COUNT(*)
            FROM citas
            WHERE DATE(fecha) = CURDATE()
            "
        );

        $stats['citas_hoy'] = (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }


    /*
    |--------------------------------------------------------------------------
    | HISTORIAS CLÍNICAS
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM historias_clinicas"
        );

        $stats['historias'] = (int) $consulta->fetchColumn();

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Si tu tabla se llama historia_clinica
        |--------------------------------------------------------------------------
        */

        try {

            $consulta = $pdo->query(
                "SELECT COUNT(*) FROM historia_clinica"
            );

            $stats['historias'] =
                (int) $consulta->fetchColumn();

        } catch (Throwable $e2) {
            // Se mantiene en 0.
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM inventario"
        );

        $stats['inventario'] =
            (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }


    /*
    |--------------------------------------------------------------------------
    | STOCK BAJO
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "
            SELECT COUNT(*)
            FROM inventario
            WHERE stock <= stock_minimo
            "
        );

        $stats['stock_bajo'] =
            (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }


    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */

    try {

        $consulta = $pdo->query(
            "SELECT COUNT(*) FROM usuarios"
        );

        $stats['usuarios'] =
            (int) $consulta->fetchColumn();

    } catch (Throwable $e) {
        // Se mantiene en 0.
    }
}


/*
|--------------------------------------------------------------------------
| ENCABEZADO GENERAL DEL SISTEMA
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/includes/header.php';
?>


<style>

/*
|--------------------------------------------------------------------------
| CONTENEDOR PRINCIPAL
|--------------------------------------------------------------------------
*/

.rep-page {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 25px;
    box-sizing: border-box;
}


/*
|--------------------------------------------------------------------------
| ENCABEZADO DE REPORTES
|--------------------------------------------------------------------------
*/

.rep-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    padding: 25px 30px;
    margin-bottom: 25px;

    background: #ffffff;
    border: 1px solid #dce5f1;
    border-radius: 18px;

    box-shadow:
        0 8px 25px rgba(15, 23, 42, 0.06);
}


.rep-header h1 {
    margin: 0 0 8px 0;
    font-size: 30px;
    color: #102a4c;
}


.rep-header p {
    margin: 0;
    color: #64748b;
    font-size: 15px;
}


/*
|--------------------------------------------------------------------------
| BOTONES
|--------------------------------------------------------------------------
*/

.rep-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}


.rep-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 42px;
    padding: 10px 18px;

    text-decoration: none;

    border-radius: 10px;

    font-size: 14px;
    font-weight: 600;

    transition: 0.2s ease;
}


.rep-btn-secondary {
    color: #1e5c96;
    background: #edf5ff;
    border: 1px solid #cbdff5;
}


.rep-btn-secondary:hover {
    transform: translateY(-1px);
    background: #dfedff;
}


/*
|--------------------------------------------------------------------------
| ALERTAS
|--------------------------------------------------------------------------
*/

.rep-alert {
    margin-bottom: 20px;
    padding: 14px 18px;

    color: #92400e;
    background: #fff7ed;

    border: 1px solid #fed7aa;
    border-radius: 12px;
}


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

.rep-stats {
    display: grid;

    grid-template-columns:
        repeat(4, minmax(150px, 1fr));

    gap: 15px;

    margin-bottom: 25px;
}


.rep-stat {
    padding: 20px;

    background: #ffffff;

    border: 1px solid #dce5f1;
    border-radius: 16px;

    box-shadow:
        0 5px 18px rgba(15, 23, 42, 0.04);
}


.rep-stat span {
    display: block;

    margin-bottom: 8px;

    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}


.rep-stat strong {
    display: block;

    color: #102a4c;

    font-size: 30px;
    line-height: 1;
}


/*
|--------------------------------------------------------------------------
| MÓDULOS DE REPORTES
|--------------------------------------------------------------------------
*/

.rep-modules {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(250px, 1fr));

    gap: 18px;
}


.rep-module {
    display: flex;
    align-items: center;

    gap: 18px;

    padding: 24px;

    color: inherit;
    text-decoration: none;

    background: #ffffff;

    border: 1px solid #dce5f1;
    border-radius: 18px;

    box-shadow:
        0 7px 22px rgba(15, 23, 42, 0.05);

    transition: 0.2s ease;
}


.rep-module:hover {
    transform: translateY(-3px);

    border-color: #8ebcf1;

    box-shadow:
        0 10px 30px rgba(15, 23, 42, 0.10);
}


.rep-module-icon {
    width: 58px;
    height: 58px;

    flex: 0 0 58px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 28px;

    background: #edf5ff;

    border-radius: 15px;
}


.rep-module h2 {
    margin: 0 0 7px 0;

    color: #14375f;

    font-size: 18px;
}


.rep-module p {
    margin: 0;

    color: #64748b;

    font-size: 14px;
    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| PIE DE REPORTES
|--------------------------------------------------------------------------
*/

.rep-footer-note {
    margin-top: 25px;
    padding: 16px;

    text-align: center;

    color: #718096;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .rep-stats {
        grid-template-columns:
            repeat(2, minmax(140px, 1fr));
    }

}


@media (max-width: 750px) {

    .rep-page {
        padding: 15px;
    }

    .rep-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .rep-modules {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 500px) {

    .rep-stats {
        grid-template-columns: 1fr;
    }

}

</style>


<section class="rep-page">


    <!-- ==============================================================
         ENCABEZADO
    ============================================================== -->

    <header class="rep-header">

        <div>

            <h1>
                📊 Reportes
            </h1>

            <p>
                Generación y consulta de reportes de la
                Clínica Veterinaria El Campo.
            </p>

        </div>


        <div class="rep-actions">

            <a
                class="rep-btn rep-btn-secondary"
                href="<?= rep_e(rep_url('panel.php')) ?>"
            >
                ← Dashboard
            </a>

        </div>

    </header>


    <!-- ==============================================================
         ERRORES
    ============================================================== -->

    <?php if ($error !== ''): ?>

        <div class="rep-alert">

            ⚠️ <?= rep_e($error) ?>

        </div>

    <?php endif; ?>


    <!-- ==============================================================
         ESTADÍSTICAS
    ============================================================== -->

    <div class="rep-stats">

        <?php

        $tarjetas = [

            'Clientes'
                => $stats['clientes'],

            'Mascotas'
                => $stats['mascotas'],

            'Citas hoy'
                => $stats['citas_hoy'],

            'Historias clínicas'
                => $stats['historias'],

            'Total citas'
                => $stats['citas'],

            'Productos inventario'
                => $stats['inventario'],

            'Stock bajo'
                => $stats['stock_bajo'],

            'Usuarios'
                => $stats['usuarios'],

        ];

        ?>


        <?php foreach ($tarjetas as $titulo => $valor): ?>

            <article class="rep-stat">

                <span>
                    <?= rep_e($titulo) ?>
                </span>

                <strong>
                    <?= (int) $valor ?>
                </strong>

            </article>

        <?php endforeach; ?>

    </div>


    <!-- ==============================================================
         MÓDULOS
    ============================================================== -->

    <div class="rep-modules">


        <!-- REPORTE DE CLIENTES -->

        <a
            class="rep-module"
            href="<?= rep_e(
                rep_url('reportes/clientes.php')
            ) ?>"
        >

            <div class="rep-module-icon">
                👥
            </div>

            <div>

                <h2>
                    Reporte de clientes
                </h2>

                <p>
                    Propietarios, contacto y mascotas asociadas.
                </p>

            </div>

        </a>


        <!-- REPORTE DE CITAS -->

        <a
            class="rep-module"
            href="<?= rep_e(
                rep_url('reportes/citas.php')
            ) ?>"
        >

            <div class="rep-module-icon">
                📅
            </div>

            <div>

                <h2>
                    Reporte de citas
                </h2>

                <p>
                    Agenda por fechas, estado,
                    mascota y propietario.
                </p>

            </div>

        </a>


        <!-- REPORTE DE HISTORIAS CLÍNICAS -->

        <a
            class="rep-module"
            href="<?= rep_e(
                rep_url('reportes/historias.php')
            ) ?>"
        >

            <div class="rep-module-icon">
                📋
            </div>

            <div>

                <h2>
                    Historias clínicas
                </h2>

                <p>
                    Diagnósticos, tratamientos
                    y profesional responsable.
                </p>

            </div>

        </a>


        <!-- REPORTE DE INVENTARIO -->

        <a
            class="rep-module"
            href="<?= rep_e(
                rep_url('reportes/inventario.php')
            ) ?>"
        >

            <div class="rep-module-icon">
                📦
            </div>

            <div>

                <h2>
                    Reporte de inventario
                </h2>

                <p>
                    Stock, mínimos, precios y vencimientos.
                </p>

            </div>

        </a>

    </div>


    <!-- ==============================================================
         PIE DEL MÓDULO
    ============================================================== -->

    <div class="rep-footer-note">

        Generado por

        <strong>
            <?= rep_e(rep_usuario()) ?>
        </strong>

        ·

        <?= date('d/m/Y H:i') ?>

    </div>

</section>


<?php

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__) . '/includes/footer.php';

?>