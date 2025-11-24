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
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validar campos obligatorios
    if (empty($name) || empty($email) || empty($password)) {
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
    
    // Validar longitud de contraseña
    if (strlen($password) < 6) {
        echo json_encode([
            'status' => 'error',
            'message' => 'La contraseña debe tener al menos 6 caracteres'
        ]);
        exit;
    }
    
    try {
        // Verificar si el correo ya existe
        $check_sql = "SELECT id FROM clientes_activos WHERE correo = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Este correo electrónico ya está registrado'
            ]);
            exit;
        }
        $check_stmt->close();
        
        // Insertar nuevo cliente (contraseña en texto plano)
        $insert_sql = "INSERT INTO clientes_activos (nombre, correo, password, fecha_registro) VALUES (?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sss", $name, $email, $password);
        
        if ($insert_stmt->execute()) {
            // Obtener el ID del nuevo usuario insertado
            $new_user_id = $conn->insert_id;
            
            // Iniciar sesión automáticamente después del registro
            session_start();
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['logged_in'] = true;
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Cuenta creada exitosamente. Bienvenido/a!',
                'user_name' => $name,
                'user_email' => $email,
                'user_id' => $new_user_id
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error al crear la cuenta: ' . $insert_stmt->error
            ]);
        }
        
        $insert_stmt->close();
        
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