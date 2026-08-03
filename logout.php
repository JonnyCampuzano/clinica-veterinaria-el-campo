<?php
declare(strict_types=1);


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$_SESSION = [];


session_regenerate_id(true);


$_SESSION['flash'] = [
    'type' => 'success',
    'message' => 'Sesión cerrada correctamente.'
];


header('Location: login.php');
exit;
