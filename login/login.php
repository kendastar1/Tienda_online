<?php
include 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validaciones
    if (empty($correo) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos son obligatorios']);
        exit;
    }
    
    try {
        // Buscar cliente por correo
        $sql = "SELECT id, nombre, password FROM clientes WHERE correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$correo]);
        
        if ($stmt->rowCount() == 1) {
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ⚠️ COMPARACIÓN DIRECTA DE CONTRASEÑA EN TEXTO PLANO
            if ($password === $cliente['password']) {
                // Iniciar sesión
                session_start();
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nombre'] = $cliente['nombre'];
                $_SESSION['logged_in'] = true;
                
                echo json_encode(['status' => 'success', 'message' => 'Inicio de sesión exitoso. Bienvenido de vuelta!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
        }
        
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>