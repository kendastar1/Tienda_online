<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tienda_ropa";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

try {
    $sql = "SELECT id, nombre, direccion, telefono, encargado FROM sucursales WHERE estado = 'activa'";
    $result = $conn->query($sql);
    
    $sucursales = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $sucursales[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'direccion' => $row['direccion'],
                'telefono' => $row['telefono'],
                'encargado' => $row['encargado']
            ];
        }
    }
    
    echo json_encode($sucursales);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>