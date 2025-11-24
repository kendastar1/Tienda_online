<?php
header('Content-Type: application/json');

// Configuración de la base de datos
$servername = "localhost";
$username = "root"; // Cambia por tu usuario
$password = ""; // Cambia por tu contraseña
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos no recibidos']);
    exit;
}

$nombre = $data['nombre'] ?? '';
$apellido = $data['apellido'] ?? '';
$correo = $data['correo'] ?? '';
$telefono = $data['telefono'] ?? '';
$direccion = $data['direccion'] ?? '';
$ciudad = $data['ciudad'] ?? '';
$metodo_pago = $data['metodo_pago'] ?? 'efectivo';
$carrito = $data['carrito'] ?? [];

if (empty($nombre) || empty($apellido) || empty($correo) || empty($carrito)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();

    // 1. Verificar si el cliente ya existe
    $stmt = $conn->prepare("SELECT id FROM clientes_activos WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Cliente existe, obtener su ID
        $cliente = $result->fetch_assoc();
        $cliente_id = $cliente['id'];
        
        // Actualizar datos del cliente
        $stmt = $conn->prepare("UPDATE clientes_activos SET nombre = ?, apellido = ?, telefono = ?, direccion = ?, ciudad = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre, $apellido, $telefono, $direccion, $ciudad, $cliente_id);
        $stmt->execute();
    } else {
        // Crear nuevo cliente
        $stmt = $conn->prepare("INSERT INTO clientes_activos (nombre, apellido, correo, telefono, direccion, ciudad, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $password_hash = password_hash('cliente_temp', PASSWORD_DEFAULT);
        $stmt->bind_param("sssssss", $nombre, $apellido, $correo, $telefono, $direccion, $ciudad, $password_hash);
        $stmt->execute();
        $cliente_id = $conn->insert_id;
    }

    // 2. Calcular totales
    $subtotal = 0;
    foreach ($carrito as $item) {
        $precio_final = floatval($item['precio_final']);
        $cantidad = intval($item['cantidad']);
        $subtotal += $precio_final * $cantidad;
    }
    
    $impuesto = $subtotal * 0.16; // 16% de impuesto
    $total = $subtotal + $impuesto;

    // 3. Crear venta
    $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, sucursal_id, usuario_id, subtotal, impuesto, total, metodo_pago, estado) VALUES (?, 1, 1, ?, ?, ?, ?, 'completada')");
    $stmt->bind_param("iddds", $cliente_id, $subtotal, $impuesto, $total, $metodo_pago);
    $stmt->execute();
    $venta_id = $conn->insert_id;

    // 4. Crear detalles de venta y actualizar stock
    foreach ($carrito as $item) {
        $producto_id = intval($item['id']);
        $cantidad = intval($item['cantidad']);
        $precio_unitario = floatval($item['precio_final']);
        $subtotal_item = $precio_unitario * $cantidad;

        // Insertar detalle de venta
        $stmt = $conn->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio_unitario, $subtotal_item);
        $stmt->execute();

        // Actualizar stock
        $stmt = $conn->prepare("UPDATE productos_stock SET cantidad = cantidad - ? WHERE id = ?");
        $stmt->bind_param("ii", $cantidad, $producto_id);
        $stmt->execute();
    }

    // 5. Registrar actividad
    $accion = "Venta registrada";
    $descripcion = "Venta #$venta_id procesada exitosamente. Total: $$total";
    $stmt = $conn->prepare("INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id) VALUES (1, ?, ?, 'venta', ?)");
    $stmt->bind_param("ssi", $accion, $descripcion, $venta_id);
    $stmt->execute();

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Compra realizada exitosamente', 
        'venta_id' => $venta_id
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>