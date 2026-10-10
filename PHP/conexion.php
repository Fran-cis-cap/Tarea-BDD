<?php
// ============================================================
// CONEXIÓN A LA BASE DE DATOS
// Ajustar según el servidor:
//   MAMP : puerto 8889, usuario root, contraseña root
//   XAMPP: 
// ============================================================
date_default_timezone_set('America/Santiago');

$host = "localhost";
$port = "8889";
$db   = "saludusm";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    // Misma zona horaria en PHP y en MySQL, para que NOW() de los triggers y
    // procedimientos coincida con las fechas que calcula PHP.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}