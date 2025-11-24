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
    $password = $_POST['password'] ?? '';
    
    // Validar campos obligatorios
    if (empty($email) || empty($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Todos los campos son obligatorios'
        ]);
        exit;
    }
    
    try {
        // Buscar usuario por email
        $sql = "SELECT id, nombre, correo, password FROM clientes_activos WHERE correo = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verificar contraseña (texto plano)
            if ($password === $user['password']) {
                // Iniciar sesión
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_email'] = $user['correo'];
                $_SESSION['logged_in'] = true;
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Inicio de sesión exitoso',
                    'user_name' => $user['nombre'],
                    'user_email' => $user['correo'],
                    'user_id' => $user['id']
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Contraseña incorrecta'
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No existe una cuenta con este correo electrónico'
            ]);
        }
        
        $stmt->close();
        
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