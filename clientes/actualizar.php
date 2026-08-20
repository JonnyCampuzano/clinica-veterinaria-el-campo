<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

$id=(int)($_POST['id']??0);
$nombres=trim($_POST['nombres']??'');
$apellidos=trim($_POST['apellidos']??'');
$cedula=normalize_cedula($_POST['cedula']??'');
$telefono=trim($_POST['telefono']??'');
$email=normalize_email($_POST['email']??'');
$direccion=trim($_POST['direccion']??'');

if($id<=0 || $nombres==='' || $apellidos==='' || $telefono==='') exit('Datos inválidos.');
if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) exit('Email inválido.');

$stmt=$pdo->prepare(
"UPDATE clientes SET
 nombres=:nombres, apellidos=:apellidos, cedula=:cedula, cedula_hash=:cedula_hash,
 telefono=:telefono, email=:email, email_hash=:email_hash, direccion=:direccion
 WHERE id=:id"
);

$stmt->execute([
':nombres'=>encrypt_personal($nombres),
':apellidos'=>encrypt_personal($apellidos),
':cedula'=>encrypt_personal($cedula),
':cedula_hash'=>$cedula!==''?cedula_index($cedula):null,
':telefono'=>encrypt_personal($telefono),
':email'=>encrypt_personal($email),
':email_hash'=>$email!==''?email_index($email):null,
':direccion'=>encrypt_personal($direccion),
':id'=>$id
]);

header('Location: index.php?ok=actualizado');
exit;
