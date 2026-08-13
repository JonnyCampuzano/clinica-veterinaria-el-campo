<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| RUTA PRINCIPAL DEL PROYECTO
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__);

/*
|--------------------------------------------------------------------------
| CARGAR ARCHIVOS DEL SISTEMA
|--------------------------------------------------------------------------
*/

require_once $raiz . '/config/app.php';
require_once $raiz . '/includes/funciones.php';
require_once $raiz . '/config/conexion.php';
require_once $raiz . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| PROTECCIÓN REAL DE LA URL
|--------------------------------------------------------------------------
| Solo los roles con permiso usuarios.editar pueden modificar usuarios.
|--------------------------------------------------------------------------
*/

require_permission('usuarios.editar');

/*
|--------------------------------------------------------------------------
| VALIDAR CONEXIÓN
|--------------------------------------------------------------------------
*/

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('No se encontró una conexión PDO válida.');
}

/*
|--------------------------------------------------------------------------
| OBTENER ID
|--------------------------------------------------------------------------
*/

$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (int) ($_POST['id'] ?? 0)
    : (
        filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        ) ?: 0
    );

if ($id <= 0) {
    flash('error', 'Usuario no válido.');
    redirect('usuarios/index.php');
}

/*
|--------------------------------------------------------------------------
| USUARIO ACTUAL
|--------------------------------------------------------------------------
*/

$usuarioActual = current_user();

$idUsuarioActual = (int) (
    $usuarioActual['id']
    ?? $usuarioActual['usuario_id']
    ?? $usuarioActual['id_usuario']
    ?? $usuarioActual['user_id']
    ?? 0
);

/*
|--------------------------------------------------------------------------
| BUSCAR USUARIO A EDITAR
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT id, nombre, email, rol, estado
     FROM usuarios
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$id]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($usuario)) {
    flash('error', 'El usuario no existe.');
    redirect('usuarios/index.php');
}

/*
|--------------------------------------------------------------------------
| VALORES PERMITIDOS
|--------------------------------------------------------------------------
*/

$rolesPermitidos = [
    'Administrador',
    'Medico',
    'Recepcionista',
    'Cliente',
];

$estadosPermitidos = [
    'Activo',
    'Inactivo',
];

$error = '';

/*
|--------------------------------------------------------------------------
| PROCESAR ACTUALIZACIÓN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $nombre = trim(
        (string) ($_POST['nombre'] ?? '')
    );

    $email = strtolower(
        trim(
            (string) ($_POST['email'] ?? '')
        )
    );

    $password = (string) (
        $_POST['password'] ?? ''
    );

    $rol = trim(
        (string) ($_POST['rol'] ?? '')
    );

    $estado = trim(
        (string) ($_POST['estado'] ?? '')
    );

    if (
        $nombre === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error =
            'Ingresa un nombre y un correo válido.';

    } elseif (
        !in_array(
            $rol,
            $rolesPermitidos,
            true
        )
    ) {
        $error =
            'El rol seleccionado no es válido.';

    } elseif (
        !in_array(
            $estado,
            $estadosPermitidos,
            true
        )
    ) {
        $error =
            'El estado seleccionado no es válido.';

    } elseif (
        $id === $idUsuarioActual
        && $estado === 'Inactivo'
    ) {
        $error =
            'No puedes desactivar tu propia cuenta.';

    } elseif (
        $password !== ''
        && strlen($password) < 8
    ) {
        $error =
            'La nueva contraseña debe tener al menos 8 caracteres.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | EVITAR CORREO DUPLICADO
            |--------------------------------------------------------------------------
            */

            $verificarCorreo = $pdo->prepare(
                'SELECT id
                 FROM usuarios
                 WHERE email = ?
                   AND id <> ?
                 LIMIT 1'
            );

            $verificarCorreo->execute([
                $email,
                $id,
            ]);

            if ($verificarCorreo->fetchColumn()) {
                $error =
                    'El correo ya pertenece a otro usuario.';
            } else {

                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR
                |--------------------------------------------------------------------------
                */

                if ($password !== '') {

                    $update = $pdo->prepare(
                        'UPDATE usuarios
                         SET nombre = ?,
                             email = ?,
                             password = ?,
                             rol = ?,
                             estado = ?
                         WHERE id = ?
                         LIMIT 1'
                    );

                    $update->execute([
                        $nombre,
                        $email,
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        $rol,
                        $estado,
                        $id,
                    ]);

                } else {

                    $update = $pdo->prepare(
                        'UPDATE usuarios
                         SET nombre = ?,
                             email = ?,
                             rol = ?,
                             estado = ?
                         WHERE id = ?
                         LIMIT 1'
                    );

                    $update->execute([
                        $nombre,
                        $email,
                        $rol,
                        $estado,
                        $id,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | SI EDITA SU PROPIA CUENTA, ACTUALIZAR SESIÓN
                |--------------------------------------------------------------------------
                */

                if ($id === $idUsuarioActual) {

                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['usuario'] = $nombre;

                    $_SESSION['correo'] = $email;
                    $_SESSION['email'] = $email;

                    /*
                    | Mantener el rol interno normalizado en sesión.
                    */
                    $rolSesion = match ($rol) {
                        'Administrador' => 'administrador',
                        'Medico' => 'medico',
                        'Recepcionista' => 'recepcionista',
                        'Cliente' => 'cliente',
                        default => strtolower($rol),
                    };

                    $_SESSION['rol'] = $rolSesion;

                    /*
                    | Claves de compatibilidad.
                    */
                    $_SESSION['usuario_nombre'] = $nombre;
                    $_SESSION['usuario_email'] = $email;
                    $_SESSION['usuario_rol'] = $rolSesion;
                }

                flash(
                    'success',
                    'Usuario actualizado correctamente.'
                );

                redirect('usuarios/index.php');
            }

        } catch (PDOException $exception) {

            error_log(
                'Error actualizando usuario: ' .
                $exception->getMessage()
            );

            $error =
                $exception->getCode() === '23000'
                    ? 'El correo ya pertenece a otro usuario.'
                    : 'No se pudo actualizar el usuario.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| VALORES DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$values = array_merge(
    $usuario,
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? $_POST
        : []
);

$pageTitle = 'Editar usuario';
$activePage = 'usuarios';

require_once $raiz . '/includes/header.php';
?>

<div class="card form-card">

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form
        class="form-grid"
        method="POST"
        autocomplete="off"
    >

        <?= csrf_field() ?>

        <input
            type="hidden"
            name="id"
            value="<?= (int) $id ?>"
        >

        <div class="form-group">
            <label for="nombre">
                Nombre completo *
            </label>

            <input
                id="nombre"
                name="nombre"
                type="text"
                maxlength="120"
                value="<?= e(
                    (string) ($values['nombre'] ?? '')
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">
                Correo *
            </label>

            <input
                id="email"
                name="email"
                type="email"
                maxlength="150"
                value="<?= e(
                    (string) ($values['email'] ?? '')
                ) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">
                Nueva contraseña
            </label>

            <input
                id="password"
                name="password"
                type="password"
                minlength="8"
                placeholder="Déjala vacía para conservarla"
                autocomplete="new-password"
            >
        </div>

        <div class="form-group">
            <label for="rol">
                Rol *
            </label>

            <select
                id="rol"
                name="rol"
                required
            >
                <?php foreach ($rolesPermitidos as $opcion): ?>

                    <option
                        value="<?= e($opcion) ?>"
                        <?= (
                            (string) ($values['rol'] ?? '')
                            === $opcion
                        ) ? 'selected' : '' ?>
                    >
                        <?= e($opcion) ?>
                    </option>

                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="estado">
                Estado *
            </label>

            <select
                id="estado"
                name="estado"
                required
            >
                <?php foreach ($estadosPermitidos as $opcionEstado): ?>

                    <option
                        value="<?= e($opcionEstado) ?>"
                        <?= (
                            (string) ($values['estado'] ?? '')
                            === $opcionEstado
                        ) ? 'selected' : '' ?>
                    >
                        <?= e($opcionEstado) ?>
                    </option>

                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">

            <a
                class="btn btn-secondary"
                href="<?= e(
                    url('usuarios/index.php')
                ) ?>"
            >
                Cancelar
            </a>

            <button
                class="btn btn-primary"
                type="submit"
            >
                💾 Actualizar usuario
            </button>

        </div>

    </form>

</div>

<?php
require_once $raiz . '/includes/footer.php';
?>