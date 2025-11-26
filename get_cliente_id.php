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
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email no proporcionado']);
    exit;
}

$email = $conn->real_escape_string($input['email']);

try {
    // Buscar cliente por email
    $sql = "SELECT id, nombre, correo FROM clientes_activos WHERE correo = ? AND estado = 'activo'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cliente = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'cliente_id' => (int)$cliente['id'],
            'nombre' => $cliente['nombre'],
            'email' => $cliente['correo']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Cliente no encontrado con ese email'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>