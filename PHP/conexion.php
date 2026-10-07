<?php
$host = "localhost";
$port = "8889";      // MAMP usa 8889
$db   = "saludusm";
$user = "root";
$pass = "root";      // MAMP usa root

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>