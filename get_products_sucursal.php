<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

try {
    // Consulta para obtener información de productos con sucursal
    $sql = "SELECT ps.id, ps.nombre, ps.sucursal_id, s.nombre as sucursal_nombre 
            FROM productos_stock ps 
            LEFT JOIN sucursales s ON ps.sucursal_id = s.id 
            WHERE ps.estado = 'activo'";
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $productos = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $productos[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'sucursal_id' => (int)$row['sucursal_id'],
                'sucursal_nombre' => $row['sucursal_nombre'] ?: 'Sucursal ' . $row['sucursal_id']
            ];
        }
    }

    echo json_encode($productos);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>