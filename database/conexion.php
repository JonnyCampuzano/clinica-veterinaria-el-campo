<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli(
        'localhost',
        'root',
        '',
        'clinica_veterinaria_el_campo'
    );

    $conexion->set_charset('utf8mb4');

} catch (mysqli_sql_exception $error) {
    error_log(
        'Error de conexión: ' . $error->getMessage()
    );

    exit('No se pudo conectar con la base de datos.');
}