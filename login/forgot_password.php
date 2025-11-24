<?php
header('Content-Type: application/json');

// Configuración de la base de datos
$servername = "localhost";
$username = "root"; // Cambia por tu usuario de MySQL
$password = ""; // Cambia por tu contraseña de MySQL
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'status' => 'error',
        'message' => 'Error de conexión a la base de datos: ' . $conn->connect_error
    ]));
}

// Establecer charset
$conn->set_charset("utf8");

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validar campos obligatorios
    if (empty($email) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Todos los campos son obligatorios'
        ]);
        exit;
    }
    
    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'El formato del correo electrónico no es válido'
        ]);
        exit;
    }
    
    // Validar que las contraseñas coincidan
    if ($newPassword !== $confirmPassword) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Las contraseñas no coinciden'
        ]);
        exit;
    }
    
    // Validar longitud de contraseña
    if (strlen($newPassword) < 6) {
        echo json_encode([
            'status' => 'error',
            'message' => 'La contraseña debe tener al menos 6 caracteres'
        ]);
        exit;
    }
    
    try {
        // Verificar si el correo existe
        $check_sql = "SELECT id, nombre FROM clientes_activos WHERE correo = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'No existe una cuenta con este correo electrónico'
            ]);
            exit;
        }
        
        $user = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // Actualizar contraseña (texto plano)
        $update_sql = "UPDATE clientes_activos SET password = ? WHERE correo = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $newPassword, $email);
        
        if ($update_stmt->execute()) {
            // Iniciar sesión automáticamente después de restablecer contraseña
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_email'] = $email;
            $_SESSION['logged_in'] = true;
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente. Bienvenido/a de nuevo!',
                'user_name' => $user['nombre'],
                'user_email' => $email,
                'user_id' => $user['id']
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al actualizar la contraseña: ' . $update_stmt->error
            ]);
        }
        
        $update_stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error en el servidor: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método no permitido'
    ]);
}

// Cerrar conexión
$conn->close();
?>