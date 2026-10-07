<?php
require "conexion.php";
$filas = $pdo->query("SELECT nombre_centro, comuna FROM centro_medico")->fetchAll(PDO::FETCH_ASSOC);
foreach ($filas as $f) {
    echo $f["nombre_centro"] . " - " . $f["comuna"] . "<br>";
}
?>