<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener categoría desde el parámetro GET
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

// Consulta para obtener productos por categoría
if (!empty($categoria)) {
    $sql = "SELECT * FROM productos_stock WHERE categoria = ? AND estado = 'activo' ORDER BY fecha_creacion DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $categoria);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM productos_stock WHERE estado = 'activo' ORDER BY fecha_creacion DESC";
    $result = $conn->query($sql);
}

$productos = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}

$conn->close();

echo json_encode($productos);
?>