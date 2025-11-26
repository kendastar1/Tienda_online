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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['productos']) || !is_array($input['productos'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

try {
    $productos_sin_stock = [];
    $productos_con_stock = [];
    
    foreach ($input['productos'] as $producto) {
        if (!isset($producto['id']) || !isset($producto['cantidad'])) {
            continue;
        }
        
        $stmt = $conn->prepare("
            SELECT id, nombre, cantidad, sucursal_id, precio_final 
            FROM productos_stock 
            WHERE id = ? AND estado = 'activo'
        ");
        $stmt->bind_param("i", $producto['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $producto_info = $result->fetch_assoc();
            
            if ($producto_info['cantidad'] < $producto['cantidad']) {
                $productos_sin_stock[] = [
                    'id' => $producto_info['id'],
                    'nombre' => $producto_info['nombre'],
                    'stock_disponible' => $producto_info['cantidad'],
                    'cantidad_solicitada' => $producto['cantidad'],
                    'sucursal_id' => $producto_info['sucursal_id']
                ];
            } else {
                $productos_con_stock[] = [
                    'id' => $producto_info['id'],
                    'nombre' => $producto_info['nombre'],
                    'stock_disponible' => $producto_info['cantidad'],
                    'sucursal_id' => $producto_info['sucursal_id'],
                    'precio_final' => $producto_info['precio_final']
                ];
            }
        } else {
            $productos_sin_stock[] = [
                'id' => $producto['id'],
                'nombre' => 'Producto no encontrado',
                'stock_disponible' => 0,
                'cantidad_solicitada' => $producto['cantidad'],
                'sucursal_id' => null
            ];
        }
        
        $stmt->close();
    }
    
    echo json_encode([
        'success' => empty($productos_sin_stock),
        'productos_con_stock' => $productos_con_stock,
        'productos_sin_stock' => $productos_sin_stock,
        'tiene_stock_suficiente' => empty($productos_sin_stock)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>