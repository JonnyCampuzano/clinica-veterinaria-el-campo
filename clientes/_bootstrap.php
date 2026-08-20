<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/crypto.php';
if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('config/conexion.php debe crear $pdo.');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
