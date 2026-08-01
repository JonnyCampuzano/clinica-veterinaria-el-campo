<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$usuarios = $pdo->query('SELECT id, nombre, email, rol, estado, created_at FROM usuarios ORDER BY id DESC')->fetchAll();

$pageTitle = 'Usuarios';
$activePage = 'usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <p>Administra las cuentas que pueden ingresar al sistema.</p>
    <a class="btn btn-primary" href="<?= e(url('usuarios/crear.php')) ?>">➕ Nuevo usuario</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Registro</th>
            <th>Acción</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><strong><?= e($usuario['nombre']) ?></strong></td>
                <td><?= e($usuario['email']) ?></td>
                <td><?= e($usuario['rol']) ?></td>
                <td><span class="badge badge-<?= $usuario['estado'] === 'Activo' ? 'success' : 'danger' ?>"><?= e($usuario['estado']) ?></span></td>
                <td><?= e(date('d/m/Y', strtotime($usuario['created_at']))) ?></td>
                <td><a class="btn btn-warning btn-sm" href="<?= e(url('usuarios/editar.php?id=' . $usuario['id'])) ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
