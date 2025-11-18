<?php
include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['name'] ?? '');
    $correo = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validaciones
    if (empty($nombre) || empty($correo) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        exit;
    }
    
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'El correo electrónico no es válido']);
        exit;
    }
    
    try {
        // Verificar si el correo ya existe en clientes
        $sql = "SELECT id FROM clientes WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$correo]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Este correo ya está registrado como cliente']);
            exit;
        }
        
        $sql = "INSERT INTO clientes (nombre, correo, password) VALUES (?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        
        if ($stmt->execute([$nombre, $correo, $password])) {
            echo json_encode(['status' => 'success', 'message' => 'Cliente registrado correctamente. Redirigiendo...']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al registrar el cliente']);
        }
        
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>