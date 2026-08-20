<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: crear.php');
    exit;
}

$nombres = trim($_POST['nombres'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$cedula = normalize_cedula($_POST['cedula'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = normalize_email($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($nombres === '' || $apellidos === '' || $telefono === '') exit('Datos obligatorios incompletos.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) exit('Email inválido.');

if ($cedula !== '') {
    $q = $pdo->prepare("SELECT id FROM clientes WHERE cedula_hash=:h LIMIT 1");
    $q->execute([':h'=>cedula_index($cedula)]);
    if ($q->fetch()) exit('Ya existe un cliente con esa cédula.');
}

if ($email !== '') {
    $q = $pdo->prepare("SELECT id FROM clientes WHERE email_hash=:h LIMIT 1");
    $q->execute([':h'=>email_index($email)]);
    if ($q->fetch()) exit('Ya existe un cliente con ese email.');
}

$stmt = $pdo->prepare(
    "INSERT INTO clientes
     (nombres,apellidos,cedula,cedula_hash,telefono,email,email_hash,direccion)
     VALUES
     (:nombres,:apellidos,:cedula,:cedula_hash,:telefono,:email,:email_hash,:direccion)"
);

$stmt->execute([
    ':nombres'=>encrypt_personal($nombres),
    ':apellidos'=>encrypt_personal($apellidos),
    ':cedula'=>encrypt_personal($cedula),
    ':cedula_hash'=>$cedula !== '' ? cedula_index($cedula) : null,
    ':telefono'=>encrypt_personal($telefono),
    ':email'=>encrypt_personal($email),
    ':email_hash'=>$email !== '' ? email_index($email) : null,
    ':direccion'=>encrypt_personal($direccion),
]);

header('Location: index.php?ok=creado');
exit;
