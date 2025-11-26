<?php
// get_sucursales.php
header('Content-Type: application/json');

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Consulta para obtener sucursales activas
$sql = "SELECT id, nombre, direccion FROM sucursales WHERE estado = 'activa'";
$result = $conn->query($sql);

$sucursales = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $sucursales[] = $row;
    }
}

$conn->close();

echo json_encode($sucursales);
?>