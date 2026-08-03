<?php
declare(strict_types=1);

/* =====================================================
   DATOS DE LA BASE DE DATOS
===================================================== */

$host = 'localhost';
$puerto = '3306';
$baseDatos = 'clinica_veterinaria_el_campo';
$usuario = 'root';
$contrasena = '';

/* =====================================================
   CONEXIÓN PDO
===================================================== */

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4",
        $usuario,
        $contrasena,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $error) {
    error_log(
        'Error de conexión PDO: ' .
        $error->getMessage()
    );

    exit(
        '<div style="
            margin:40px;
            padding:20px;
            border:1px solid #fca5a5;
            border-radius:12px;
            background:#fee2e2;
            color:#991b1b;
            font-family:Arial,sans-serif;
        ">
            <strong>Error de conexión</strong><br><br>
            No se pudo conectar con la base de datos
            <code>clinica_veterinaria_el_campo</code>.<br><br>
            Verifica que MySQL esté iniciado en XAMPP.
        </div>'
    );
}

/* =====================================================
   CONEXIÓN MYSQLI DE COMPATIBILIDAD
===================================================== */

/*
 * Esta conexión se conserva porque otros módulos
 * antiguos podrían estar usando la variable $conexion.
 */

$conexion = null;

try {
    mysqli_report(
        MYSQLI_REPORT_ERROR |
        MYSQLI_REPORT_STRICT
    );

    $conexion = new mysqli(
        $host,
        $usuario,
        $contrasena,
        $baseDatos,
        (int) $puerto
    );

    $conexion->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    error_log(
        'Error de conexión MySQLi: ' .
        $error->getMessage()
    );

    $conexion = null;
}