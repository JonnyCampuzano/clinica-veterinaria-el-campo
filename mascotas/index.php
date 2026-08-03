<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

/*
|--------------------------------------------------------------------------
| BUSCADOR
|--------------------------------------------------------------------------
*/

$buscar = trim(
    (string) ($_GET['buscar'] ?? '')
);

/*
|--------------------------------------------------------------------------
| CONSULTA DE MASCOTAS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        m.*,
        CONCAT(c.nombres, ' ', c.apellidos) AS cliente
    FROM mascotas AS m
    INNER JOIN clientes AS c
        ON c.id = m.cliente_id
";

$params = [];

if ($buscar !== '') {
    $sql .= "
        WHERE
            m.nombre LIKE ?
            OR m.especie LIKE ?
            OR m.raza LIKE ?
            OR c.nombres LIKE ?
            OR c.apellidos LIKE ?
    ";

    $termino = '%' . $buscar . '%';

    $params = [
        $termino,
        $termino,
        $termino,
        $termino,
        $termino,
    ];
}

$sql .= ' ORDER BY m.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$mascotas = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE LA PÁGINA
|--------------------------------------------------------------------------
*/

$pageTitle = 'Mascotas';
$activePage = 'mascotas';

require __DIR__ . '/../includes/header.php';
?>

<!-- ================================================================
     ACCIONES SUPERIORES
================================================================ -->

<div class="page-actions">

    <form
        class="search-bar"
        method="get"
        action="<?= e(url('mascotas/index.php')) ?>"
    >
        <input
            type="search"
            name="buscar"
            value="<?= e($buscar) ?>"
            placeholder="Buscar mascota o propietario..."
            autocomplete="off"
        >

        <button
            class="btn btn-secondary"
            type="submit"
        >
            🔍 Buscar
        </button>

        <?php if ($buscar !== ''): ?>
            <a
                class="btn btn-light"
                href="<?= e(url('mascotas/index.php')) ?>"
            >
                Limpiar
            </a>
        <?php endif; ?>
    </form>

    <a
        class="btn btn-primary"
        href="<?= e(url('mascotas/crear.php')) ?>"
    >
        ➕ Nueva mascota
    </a>

</div>

<!-- ================================================================
     TABLA DE MASCOTAS
================================================================ -->

<div class="table-wrapper">

    <table>

        <thead>
            <tr>
                <th>Mascota</th>
                <th>Propietario</th>
                <th>Especie</th>
                <th>Raza</th>
                <th>Sexo</th>
                <th>Peso</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

        <?php if (!empty($mascotas)): ?>

            <?php foreach ($mascotas as $mascota): ?>

                <tr>

                    <td>
                        <strong>
                            <?= e((string) $mascota['nombre']) ?>
                        </strong>
                    </td>

                    <td>
                        <?= e((string) $mascota['cliente']) ?>
                    </td>

                    <td>
                        <?= e((string) $mascota['especie']) ?>
                    </td>

                    <td>
                        <?= !empty($mascota['raza'])
                            ? e((string) $mascota['raza'])
                            : '—'
                        ?>
                    </td>

                    <td>
                        <?= !empty($mascota['sexo'])
                            ? e((string) $mascota['sexo'])
                            : '—'
                        ?>
                    </td>

                    <td>
                        <?php if (
                            isset($mascota['peso']) &&
                            $mascota['peso'] !== null &&
                            $mascota['peso'] !== ''
                        ): ?>
                            <?= e((string) $mascota['peso']) ?> kg
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td>

                        <div class="actions">

                            <!-- CONSULTA -->

                            <a
                                class="btn btn-secondary btn-sm"
                                href="<?= e(
                                    url(
                                        'consultas/crear.php?mascota_id='
                                        . (int) $mascota['id']
                                    )
                                ) ?>"
                            >
                                🩺 Consulta
                            </a>

                            <!-- EDITAR -->

                            <a
                                class="btn btn-warning btn-sm"
                                href="<?= e(
                                    url(
                                        'mascotas/editar.php?id='
                                        . (int) $mascota['id']
                                    )
                                ) ?>"
                            >
                                ✏️ Editar
                            </a>

                            <!-- ELIMINAR -->

                            <form
                                class="inline-form"
                                method="post"
                                action="<?= e(url('mascotas/eliminar.php')) ?>"
                                onsubmit="return confirm('¿Estás seguro de eliminar esta mascota? También podrían eliminarse sus citas y consultas relacionadas. Esta acción no se puede deshacer.');"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= e(
                                        (string) $mascota['id']
                                    ) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm"
                                >
                                    🗑️ Eliminar
                                </button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">
                    <div class="empty-state">

                        <?php if ($buscar !== ''): ?>

                            No se encontraron mascotas relacionadas con:

                            <strong>
                                “<?= e($buscar) ?>”
                            </strong>

                        <?php else: ?>

                            No existen mascotas registradas.

                        <?php endif; ?>

                    </div>
                </td>
            </tr>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>