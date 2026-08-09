<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONEXIÓN A MYSQL
|--------------------------------------------------------------------------
*/

$host = 'localhost';
$baseDatos = 'clinica_veterinaria';
$usuario = 'root';
$contrasena = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$baseDatos};charset={$charset}";

$opciones = [

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES =>
        false
];

try {

    $pdo = new PDO(
        $dsn,
        $usuario,
        $contrasena,
        $opciones
    );

} catch (PDOException $e) {

    exit(
        'No fue posible conectarse con la base de datos: '
        . $e->getMessage()
    );
}