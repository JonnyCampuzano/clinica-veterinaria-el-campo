<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN DE LA BASE DE DATOS
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
} catch (PDOException $error) {
    exit(
        'No fue posible conectarse con la base de datos: ' .
        $error->getMessage()
    );
}