<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/config/crypto.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$fecha = trim((string) ($_GET['fecha'] ?? ''));

$flash = hc_tomar_flash();
$mensajeExito = '';
$mensajeError = '';

if (($flash['tipo'] ?? '') === 'success') {
    $mensajeExito = (string) ($flash['mensaje'] ?? '');
}

if (($flash['tipo'] ?? '') === 'error') {
    $mensajeError = (string) ($flash['mensaje'] ?? '');
}

$historias = [];
$estadisticas = [
    'total' => 0,
    'hoy' => 0,
    'mes' => 0,
    'controles' => 0,
];

try {
    $consultaEstadisticas = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            COALESCE(SUM(fecha = CURDATE()), 0) AS hoy,
            COALESCE(
                SUM(
                    YEAR(fecha) = YEAR(CURDATE())
                    AND MONTH(fecha) = MONTH(CURDATE())
                ),
                0
            ) AS mes,
            COALESCE(
                SUM(
                    proxima_cita IS NOT NULL
                    AND proxima_cita >= CURDATE()
                ),
                0
            ) AS controles
         FROM historias_clinicas'
    );

    $fila = $consultaEstadisticas->fetch(PDO::FETCH_ASSOC);

    if (is_array($fila)) {
        $estadisticas = [
            'total' => (int) ($fila['total'] ?? 0),
            'hoy' => (int) ($fila['hoy'] ?? 0),
            'mes' => (int) ($fila['mes'] ?? 0),
            'controles' => (int) ($fila['controles'] ?? 0),
        ];
    }
} catch (Throwable $error) {
    error_log(
        'Error cargando estadísticas de historia clínica: ' .
        $error->getMessage()
    );
}

try {
    /*
     * Los datos personales del propietario están cifrados en MySQL.
     * Por esa razón no hacemos LIKE sobre nombres, apellidos o cédula.
     * Se consultan los registros y luego se descifran/filtran en PHP.
     */
    $sql = '
        SELECT
            co.id,
            co.fecha,
            co.motivo,
            co.diagnostico,
            co.tratamiento,
            co.peso,
            co.temperatura,
            co.proxima_cita,
            m.nombre AS mascota_nombre,
            m.especie AS mascota_especie,
            m.raza AS mascota_raza,
            c.nombres AS cliente_nombres,
            c.apellidos AS cliente_apellidos,
            c.cedula AS cliente_cedula,
            u.nombre AS usuario_nombre
        FROM historias_clinicas co
        INNER JOIN mascotas m
            ON m.id = co.mascota_id
        INNER JOIN clientes c
            ON c.id = m.cliente_id
        LEFT JOIN usuarios u
            ON u.id = co.usuario_id
        WHERE 1 = 1
    ';

    $parametros = [];

    /*
     * La fecha no está cifrada, así que sí puede filtrarse directamente
     * en MySQL.
     */
    if ($fecha !== '') {
        $sql .= ' AND co.fecha = :fecha';
        $parametros[':fecha'] = $fecha;
    }

    $sql .= ' ORDER BY co.fecha DESC, co.id DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);

    $filasHistorias = $consulta->fetchAll(PDO::FETCH_ASSOC);
    $historias = [];

    foreach ($filasHistorias as $historia) {
        try {
            /*
             * Descifrar únicamente los datos personales del propietario.
             */
            $historia['cliente_nombres'] = decrypt_personal(
                $historia['cliente_nombres'] ?? null
            );

            $historia['cliente_apellidos'] = decrypt_personal(
                $historia['cliente_apellidos'] ?? null
            );

            $historia['cliente_cedula'] = decrypt_personal(
                $historia['cliente_cedula'] ?? null
            );
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Error al descifrar propietario de historia clínica ID ' .
                (int) ($historia['id'] ?? 0) .
                ': ' .
                $errorDescifrado->getMessage()
            );

            /*
             * Nunca mostramos el ciphertext enc:v1: al usuario.
             */
            $historia['cliente_nombres'] = 'Dato protegido';
            $historia['cliente_apellidos'] = '';
            $historia['cliente_cedula'] = '';
        }

        /*
         * Búsqueda después del descifrado.
         * Permite buscar por mascota, propietario, cédula,
         * motivo, diagnóstico, tratamiento y veterinario.
         */
        if ($buscar !== '') {
            $propietarioBusqueda = trim(
                (string) ($historia['cliente_nombres'] ?? '') .
                ' ' .
                (string) ($historia['cliente_apellidos'] ?? '')
            );

            $camposBusqueda = [
                $historia['mascota_nombre'] ?? '',
                $historia['mascota_especie'] ?? '',
                $historia['mascota_raza'] ?? '',
                $historia['cliente_nombres'] ?? '',
                $historia['cliente_apellidos'] ?? '',
                $propietarioBusqueda,
                $historia['cliente_cedula'] ?? '',
                $historia['motivo'] ?? '',
                $historia['diagnostico'] ?? '',
                $historia['tratamiento'] ?? '',
                $historia['usuario_nombre'] ?? '',
            ];

            $coincide = false;

            foreach ($camposBusqueda as $campo) {
                $campoTexto = (string) $campo;

                if (
                    function_exists('mb_stripos')
                        ? mb_stripos(
                            $campoTexto,
                            $buscar,
                            0,
                            'UTF-8'
                        ) !== false
                        : stripos($campoTexto, $buscar) !== false
                ) {
                    $coincide = true;
                    break;
                }
            }

            if (!$coincide) {
                continue;
            }
        }

        $historias[] = $historia;
    }
} catch (Throwable $error) {
    error_log(
        'Error cargando historias clínicas: ' .
        $error->getMessage()
    );

    $mensajeError =
        'No se pudieron cargar las historias clínicas. ' .
        'Revisa la tabla historias_clinicas, las relaciones y la clave de cifrado.';
}

$pageTitle = 'Historias clínicas';
$activePage = 'consultas';

require_once $raiz . '/includes/header.php';
require_once __DIR__ . '/_styles.php';
?>

<div class="hc-page">
    <section class="hc-panel">
        <header class="hc-header">
            <div>
                <h1>📋 Historias clínicas</h1>
                <p>
                    Consulta diagnósticos, tratamientos y controles
                    registrados para cada mascota.
                </p>
            </div>

            <a
                class="hc-btn hc-btn-primary"
                href="<?= hc_e(hc_url('consultas/crear.php')) ?>"
            >
                ＋ Nueva historia clínica
            </a>
        </header>

        <div class="hc-stats">
            <article class="hc-stat">
                <span>Total registros</span>
                <strong><?= $estadisticas['total'] ?></strong>
            </article>

            <article class="hc-stat">
                <span>Registrados hoy</span>
                <strong><?= $estadisticas['hoy'] ?></strong>
            </article>

            <article class="hc-stat">
                <span>Este mes</span>
                <strong><?= $estadisticas['mes'] ?></strong>
            </article>

            <article class="hc-stat">
                <span>Próximos controles</span>
                <strong><?= $estadisticas['controles'] ?></strong>
            </article>
        </div>

        <div class="hc-toolbar">
            <form method="GET" class="hc-search">
                <input
                    type="search"
                    name="buscar"
                    value="<?= hc_e($buscar) ?>"
                    placeholder="Buscar mascota, cliente, diagnóstico o tratamiento"
                >

                <input
                    type="date"
                    name="fecha"
                    value="<?= hc_e($fecha) ?>"
                >

                <button
                    type="submit"
                    class="hc-btn hc-btn-primary"
                >
                    🔎 Buscar
                </button>

                <?php if ($buscar !== '' || $fecha !== ''): ?>
                    <a
                        class="hc-btn hc-btn-secondary"
                        href="<?= hc_e(hc_url('consultas/index.php')) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>

            <span class="hc-count">
                <?= count($historias) ?>
                registro<?= count($historias) === 1 ? '' : 's' ?>
            </span>
        </div>

        <?php if ($mensajeExito !== ''): ?>
            <div class="hc-alert hc-alert-success">
                ✅ <?= hc_e($mensajeExito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensajeError !== ''): ?>
            <div class="hc-alert hc-alert-error">
                ⚠️ <?= hc_e($mensajeError) ?>
            </div>
        <?php endif; ?>

        <div class="hc-content">
            <?php if ($historias !== []): ?>
                <div class="hc-table-wrapper">
                    <table class="hc-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Mascota</th>
                                <th>Propietario</th>
                                <th>Motivo</th>
                                <th>Diagnóstico</th>
                                <th>Veterinario</th>
                                <th>Próximo control</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($historias as $historia): ?>
                                <?php
                                $propietario = trim(
                                    (string) ($historia['cliente_nombres'] ?? '') .
                                    ' ' .
                                    (string) ($historia['cliente_apellidos'] ?? '')
                                );
                                ?>
                                <tr>
                                    <td>
                                        <span class="hc-primary-text">
                                            <?= hc_e(
                                                hc_fecha_visible(
                                                    $historia['fecha'] ?? ''
                                                )
                                            ) ?>
                                        </span>
                                        <span class="hc-secondary-text">
                                            ID #<?= (int) ($historia['id'] ?? 0) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="hc-primary-text">
                                            <?= hc_e(
                                                $historia['mascota_nombre']
                                                ?? 'Mascota'
                                            ) ?>
                                        </span>
                                        <span class="hc-secondary-text">
                                            <?= hc_e(
                                                trim(
                                                    (string) (
                                                        $historia[
                                                            'mascota_especie'
                                                        ] ?? ''
                                                    ) .
                                                    ' ' .
                                                    (string) (
                                                        $historia[
                                                            'mascota_raza'
                                                        ] ?? ''
                                                    )
                                                )
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="hc-primary-text">
                                            <?= hc_e(
                                                $propietario !== ''
                                                    ? $propietario
                                                    : 'No registrado'
                                            ) ?>
                                        </span>
                                        <span class="hc-secondary-text">
                                            Cédula:
                                            <?= hc_e(
                                                $historia['cliente_cedula']
                                                ?? 'No registrada'
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= hc_e(
                                            mb_strimwidth(
                                                (string) (
                                                    $historia['motivo'] ?? ''
                                                ),
                                                0,
                                                70,
                                                '…',
                                                'UTF-8'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= hc_e(
                                            mb_strimwidth(
                                                (string) (
                                                    $historia[
                                                        'diagnostico'
                                                    ] ?? ''
                                                ),
                                                0,
                                                75,
                                                '…',
                                                'UTF-8'
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= hc_e(
                                            $historia['usuario_nombre']
                                            ?? 'No registrado'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= hc_e(
                                            hc_fecha_visible(
                                                $historia[
                                                    'proxima_cita'
                                                ] ?? ''
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <div class="hc-actions">
                                            <a
                                                class="hc-action hc-action-view"
                                                href="<?= hc_e(
                                                    hc_url(
                                                        'consultas/ver.php?id=' .
                                                        (int) (
                                                            $historia['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                👁 Ver
                                            </a>

                                            <a
                                                class="hc-action hc-action-edit"
                                                href="<?= hc_e(
                                                    hc_url(
                                                        'consultas/editar.php?id=' .
                                                        (int) (
                                                            $historia['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                ✏️ Editar
                                            </a>

                                            <a
                                                class="hc-action hc-action-delete"
                                                href="<?= hc_e(
                                                    hc_url(
                                                        'consultas/eliminar.php?id=' .
                                                        (int) (
                                                            $historia['id']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>"
                                            >
                                                🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="hc-empty">
                    <span>🩺</span>

                    <h2>No hay historias clínicas</h2>

                    <p>
                        Registra la primera atención médica de una mascota.
                    </p>

                    <a
                        class="hc-btn hc-btn-primary"
                        href="<?= hc_e(hc_url('consultas/crear.php')) ?>"
                    >
                        ＋ Nueva historia clínica
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php
require_once $raiz . '/includes/footer.php';
?>