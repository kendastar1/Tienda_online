<?php
$servidor = "localhost";
$usuario = "root";
$password = "";
$basedatos = "tienda_ropa";

try {
    $conexion = new PDO("mysql:host=$servidor;dbname=$basedatos", $usuario, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>