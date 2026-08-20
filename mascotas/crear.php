<?php
declare(strict_types=1);

$raiz = dirname(__DIR__);

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/config/crypto.php';
require_once $raiz . '/includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| PROTEGER LA PÁGINA
|--------------------------------------------------------------------------
*/

if (function_exists('require_login')) {
    require_login();
} elseif (
    empty($_SESSION['usuario_id']) &&
    empty($_SESSION['id_usuario']) &&
    empty($_SESSION['usuario'])
) {
    redirect('login.php');
}

/*
|--------------------------------------------------------------------------
| VERIFICAR CONEXIÓN
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit(
        'Error: config/conexion.php debe crear una conexión PDO llamada $pdo.'
    );
}

/*
|--------------------------------------------------------------------------
| VALORES DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$datos = [
    'cliente_id' => 0,
    'nombre' => '',
    'especie' => '',
    'raza' => '',
    'sexo' => '',
    'fecha_nacimiento' => '',
    'peso' => '',
    'color' => '',
    'alergias' => '',
    'observaciones' => '',
];

$errores = [];
$sexosPermitidos = ['Macho', 'Hembra'];

/*
|--------------------------------------------------------------------------
| CARGAR CLIENTES
|--------------------------------------------------------------------------
*/

try {
    $consultaClientes = $pdo->query(
        'SELECT
            id,
            nombres,
            apellidos,
            cedula
         FROM clientes
         ORDER BY id ASC'
    );

    $clientesCifrados = $consultaClientes->fetchAll(
        PDO::FETCH_ASSOC
    );

    $clientes = [];

    foreach ($clientesCifrados as $cliente) {
        try {
            $cliente['nombres'] = decrypt_personal(
                $cliente['nombres'] ?? null
            );

            $cliente['apellidos'] = decrypt_personal(
                $cliente['apellidos'] ?? null
            );

            $cliente['cedula'] = decrypt_personal(
                $cliente['cedula'] ?? null
            );

            $clientes[] = $cliente;
        } catch (Throwable $errorDescifrado) {
            error_log(
                'Error al descifrar cliente ID ' .
                (int) ($cliente['id'] ?? 0) .
                ' al registrar mascota: ' .
                $errorDescifrado->getMessage()
            );
        }
    }

    /*
     * Los nombres están cifrados en MySQL, así que el orden
     * alfabético debe hacerse después de descifrarlos.
     */
    usort(
        $clientes,
        static function (array $a, array $b): int {
            $nombreA = trim(
                (string) ($a['nombres'] ?? '') .
                ' ' .
                (string) ($a['apellidos'] ?? '')
            );

            $nombreB = trim(
                (string) ($b['nombres'] ?? '') .
                ' ' .
                (string) ($b['apellidos'] ?? '')
            );

            return strcasecmp($nombreA, $nombreB);
        }
    );
} catch (Throwable $e) {
    error_log('Error cargando clientes: ' . $e->getMessage());
    $clientes = [];
    $errores[] =
        'No se pudieron cargar los clientes registrados. ' .
        'Revisa la clave de cifrado y la conexión.';
}

/*
|--------------------------------------------------------------------------
| PROCESAR FORMULARIO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(
        (string) ($_POST['csrf_token'] ?? '')
    );

    $datos = [
        'cliente_id' => (int) ($_POST['cliente_id'] ?? 0),
        'nombre' => trim((string) ($_POST['nombre'] ?? '')),
        'especie' => trim((string) ($_POST['especie'] ?? '')),
        'raza' => trim((string) ($_POST['raza'] ?? '')),
        'sexo' => trim((string) ($_POST['sexo'] ?? '')),
        'fecha_nacimiento' => trim((string) ($_POST['fecha_nacimiento'] ?? '')),
        'peso' => trim((string) ($_POST['peso'] ?? '')),
        'color' => trim((string) ($_POST['color'] ?? '')),
        'alergias' => trim((string) ($_POST['alergias'] ?? '')),
        'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
    ];

    /* Validaciones obligatorias */
    if ($datos['cliente_id'] <= 0) {
        $errores[] = 'Selecciona el propietario de la mascota.';
    }

    if ($datos['nombre'] === '') {
        $errores[] = 'Ingresa el nombre de la mascota.';
    } elseif (mb_strlen($datos['nombre']) > 100) {
        $errores[] = 'El nombre no puede superar los 100 caracteres.';
    }

    if ($datos['especie'] === '') {
        $errores[] = 'Ingresa la especie de la mascota.';
    } elseif (mb_strlen($datos['especie']) > 60) {
        $errores[] = 'La especie no puede superar los 60 caracteres.';
    }

    if (!in_array($datos['sexo'], $sexosPermitidos, true)) {
        $errores[] = 'Selecciona un sexo válido.';
    }

    /* Validar cliente existente */
    if ($datos['cliente_id'] > 0) {
        $verificarCliente = $pdo->prepare(
            'SELECT id
             FROM clientes
             WHERE id = :id
             LIMIT 1'
        );

        $verificarCliente->execute([
            ':id' => $datos['cliente_id'],
        ]);

        if (!$verificarCliente->fetchColumn()) {
            $errores[] = 'El cliente seleccionado no existe.';
        }
    }

    /* Validar fecha */
    $fechaNacimiento = null;

    if ($datos['fecha_nacimiento'] !== '') {
        $fechaObjeto = DateTime::createFromFormat(
            'Y-m-d',
            $datos['fecha_nacimiento']
        );

        $fechaValida = $fechaObjeto instanceof DateTime
            && $fechaObjeto->format('Y-m-d') === $datos['fecha_nacimiento'];

        if (!$fechaValida) {
            $errores[] = 'La fecha de nacimiento no es válida.';
        } elseif ($datos['fecha_nacimiento'] > date('Y-m-d')) {
            $errores[] = 'La fecha de nacimiento no puede ser futura.';
        } else {
            $fechaNacimiento = $datos['fecha_nacimiento'];
        }
    }

    /* Validar peso */
    $peso = null;

    if ($datos['peso'] !== '') {
        $pesoNormalizado = str_replace(',', '.', $datos['peso']);

        if (!is_numeric($pesoNormalizado)) {
            $errores[] = 'El peso debe ser un número válido.';
        } else {
            $peso = (float) $pesoNormalizado;

            if ($peso < 0 || $peso > 9999.99) {
                $errores[] = 'El peso ingresado está fuera del rango permitido.';
            }
        }
    }

    /* Validar longitudes opcionales */
    if (mb_strlen($datos['raza']) > 100) {
        $errores[] = 'La raza no puede superar los 100 caracteres.';
    }

    if (mb_strlen($datos['color']) > 80) {
        $errores[] = 'El color no puede superar los 80 caracteres.';
    }

    /* Guardar */
    if ($errores === []) {
        try {
            $insertar = $pdo->prepare(
                'INSERT INTO mascotas
                    (
                        cliente_id,
                        nombre,
                        especie,
                        raza,
                        sexo,
                        fecha_nacimiento,
                        peso,
                        color,
                        alergias,
                        observaciones
                    )
                 VALUES
                    (
                        :cliente_id,
                        :nombre,
                        :especie,
                        :raza,
                        :sexo,
                        :fecha_nacimiento,
                        :peso,
                        :color,
                        :alergias,
                        :observaciones
                    )'
            );

            $insertar->execute([
                ':cliente_id' => $datos['cliente_id'],
                ':nombre' => $datos['nombre'],
                ':especie' => $datos['especie'],
                ':raza' => $datos['raza'] !== '' ? $datos['raza'] : null,
                ':sexo' => $datos['sexo'],
                ':fecha_nacimiento' => $fechaNacimiento,
                ':peso' => $peso,
                ':color' => $datos['color'] !== '' ? $datos['color'] : null,
                ':alergias' => $datos['alergias'] !== '' ? $datos['alergias'] : null,
                ':observaciones' => $datos['observaciones'] !== ''
                    ? $datos['observaciones']
                    : null,
            ]);

            regenerate_csrf();

            redirect('mascotas/index.php?msg=creada');
        } catch (PDOException $e) {
            error_log('Error registrando mascota: ' . $e->getMessage());
            $errores[] = 'No se pudo registrar la mascota. Revisa los datos e inténtalo nuevamente.';
        }
    }
}

$pageTitle = 'Registrar mascota';
$activePage = 'mascotas';

require_once $raiz . '/includes/header.php';
?>

<style>
    .pet-form-page {
        max-width: 1050px;
        margin: 0 auto;
    }

    .pet-page-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 22px;
    }

    .pet-page-head h1 {
        margin: 0;
        color: #0f2747;
        font-size: 28px;
    }

    .pet-page-head p {
        margin: 7px 0 0;
        color: #64748b;
    }

    .pet-back {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 43px;
        padding: 10px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        color: #334155;
        background: #ffffff;
        text-decoration: none;
        font-weight: 700;
    }

    .pet-form-card {
        padding: 28px;
        border: 1px solid #dce6f2;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 14px 35px rgba(15, 47, 92, 0.08);
    }

    .pet-alert {
        margin-bottom: 22px;
        padding: 15px 18px;
        border: 1px solid #fecaca;
        border-radius: 12px;
        color: #991b1b;
        background: #fef2f2;
    }

    .pet-alert strong {
        display: block;
        margin-bottom: 8px;
    }

    .pet-alert ul {
        margin: 0;
        padding-left: 20px;
    }

    .pet-empty {
        margin-bottom: 22px;
        padding: 17px;
        border: 1px solid #fde68a;
        border-radius: 12px;
        color: #854d0e;
        background: #fffbeb;
    }

    .pet-empty a {
        color: #1d4ed8;
        font-weight: 800;
    }

    .pet-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
    }

    .pet-field {
        display: grid;
        gap: 8px;
    }

    .pet-field.full {
        grid-column: 1 / -1;
    }

    .pet-field label {
        color: #263b58;
        font-size: 14px;
        font-weight: 800;
    }

    .pet-field input,
    .pet-field select,
    .pet-field textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 11px;
        outline: none;
        background: #ffffff;
        color: #0f172a;
        font: inherit;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .pet-field input,
    .pet-field select {
        min-height: 48px;
        padding: 0 14px;
    }

    .pet-field textarea {
        min-height: 110px;
        padding: 13px 14px;
        resize: vertical;
    }

    .pet-field input:focus,
    .pet-field select:focus,
    .pet-field textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }

    .pet-help {
        color: #64748b;
        font-size: 12px;
    }

    .pet-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px solid #e2e8f0;
    }

    .pet-button {
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 11px 19px;
        border: 0;
        border-radius: 11px;
        cursor: pointer;
        text-decoration: none;
        font: inherit;
        font-weight: 800;
    }

    .pet-button.cancel {
        color: #334155;
        background: #e2e8f0;
    }

    .pet-button.save {
        color: #ffffff;
        background: #2563eb;
    }

    .pet-button.save:hover {
        background: #1d4ed8;
    }

    .pet-button:disabled {
        cursor: not-allowed;
        opacity: .55;
    }

    @media (max-width: 760px) {
        .pet-page-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .pet-form-card {
            padding: 20px;
        }

        .pet-form-grid {
            grid-template-columns: 1fr;
        }

        .pet-field.full {
            grid-column: auto;
        }

        .pet-form-actions {
            flex-direction: column-reverse;
        }

        .pet-button {
            width: 100%;
        }
    }
</style>

<div class="pet-form-page">

    <div class="pet-page-head">
        <div>
            <h1>Registrar nueva mascota</h1>
            <p>Completa la información médica y general de la mascota.</p>
        </div>

        <a href="<?= e(url('mascotas/index.php')) ?>" class="pet-back">
            ← Volver a mascotas
        </a>
    </div>

    <section class="pet-form-card">

        <?php if ($errores !== []): ?>
            <div class="pet-alert">
                <strong>No se pudo guardar la mascota:</strong>

                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($clientes === []): ?>
            <div class="pet-empty">
                Primero debes registrar al menos un cliente.
                <a href="<?= e(url('clientes/crear.php')) ?>">
                    Registrar cliente
                </a>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

            <?= csrf_field() ?>

            <div class="pet-form-grid">

                <div class="pet-field full">
                    <label for="cliente_id">Propietario *</label>

                    <select
                        id="cliente_id"
                        name="cliente_id"
                        required
                        <?= $clientes === [] ? 'disabled' : '' ?>
                    >
                        <option value="">Selecciona un cliente</option>

                        <?php foreach ($clientes as $cliente): ?>
                            <?php
                            $nombreCliente = trim(
                                (string) $cliente['nombres'] . ' ' .
                                (string) $cliente['apellidos']
                            );

                            $textoCliente = $nombreCliente;

                            if (!empty($cliente['cedula'])) {
                                $textoCliente .= ' · Cédula: ' . $cliente['cedula'];
                            }
                            ?>

                            <option
                                value="<?= e((string) $cliente['id']) ?>"
                                <?= $datos['cliente_id'] === (int) $cliente['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($textoCliente) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pet-field">
                    <label for="nombre">Nombre de la mascota *</label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        value="<?= e($datos['nombre']) ?>"
                        maxlength="100"
                        placeholder="Ejemplo: Max"
                        required
                    >
                </div>

                <div class="pet-field">
                    <label for="especie">Especie *</label>
                    <input
                        type="text"
                        id="especie"
                        name="especie"
                        value="<?= e($datos['especie']) ?>"
                        maxlength="60"
                        placeholder="Ejemplo: Perro, gato"
                        required
                    >
                </div>

                <div class="pet-field">
                    <label for="raza">Raza</label>
                    <input
                        type="text"
                        id="raza"
                        name="raza"
                        value="<?= e($datos['raza']) ?>"
                        maxlength="100"
                        placeholder="Ejemplo: Labrador"
                    >
                </div>

                <div class="pet-field">
                    <label for="sexo">Sexo *</label>
                    <select id="sexo" name="sexo" required>
                        <option value="">Selecciona</option>
                        <option value="Macho" <?= $datos['sexo'] === 'Macho' ? 'selected' : '' ?>>
                            Macho
                        </option>
                        <option value="Hembra" <?= $datos['sexo'] === 'Hembra' ? 'selected' : '' ?>>
                            Hembra
                        </option>
                    </select>
                </div>

                <div class="pet-field">
                    <label for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input
                        type="date"
                        id="fecha_nacimiento"
                        name="fecha_nacimiento"
                        value="<?= e($datos['fecha_nacimiento']) ?>"
                        max="<?= e(date('Y-m-d')) ?>"
                    >
                </div>

                <div class="pet-field">
                    <label for="peso">Peso en kilogramos</label>
                    <input
                        type="number"
                        id="peso"
                        name="peso"
                        value="<?= e($datos['peso']) ?>"
                        min="0"
                        max="9999.99"
                        step="0.01"
                        placeholder="Ejemplo: 12.50"
                    >
                    <small class="pet-help">Usa punto para los decimales.</small>
                </div>

                <div class="pet-field">
                    <label for="color">Color</label>
                    <input
                        type="text"
                        id="color"
                        name="color"
                        value="<?= e($datos['color']) ?>"
                        maxlength="80"
                        placeholder="Ejemplo: Café y blanco"
                    >
                </div>

                <div class="pet-field full">
                    <label for="alergias">Alergias</label>
                    <textarea
                        id="alergias"
                        name="alergias"
                        placeholder="Escribe las alergias conocidas o deja vacío"
                    ><?= e($datos['alergias']) ?></textarea>
                </div>

                <div class="pet-field full">
                    <label for="observaciones">Observaciones</label>
                    <textarea
                        id="observaciones"
                        name="observaciones"
                        placeholder="Información adicional sobre la mascota"
                    ><?= e($datos['observaciones']) ?></textarea>
                </div>

            </div>

            <div class="pet-form-actions">
                <a
                    href="<?= e(url('mascotas/index.php')) ?>"
                    class="pet-button cancel"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="pet-button save"
                    <?= $clientes === [] ? 'disabled' : '' ?>
                >
                    💾 Guardar mascota
                </button>
            </div>

        </form>

    </section>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>