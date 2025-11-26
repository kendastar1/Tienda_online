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
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]);
    exit;
}

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o vacíos']);
    exit;
}

// Validar datos requeridos
if (!isset($input['productos']) || !is_array($input['productos']) || empty($input['productos'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay productos en el carrito']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // VALIDACIÓN Y MANEJO DEL CLIENTE_ID
    $cliente_id = null;
    if (isset($input['cliente_id']) && $input['cliente_id'] !== null && $input['cliente_id'] !== '') {
        $cliente_id_input = intval($input['cliente_id']);
        
        // Verificar que el cliente_id existe en la base de datos y está activo
        $stmt_cliente = $conn->prepare("SELECT id FROM clientes_activos WHERE id = ? AND estado = 'activo'");
        $stmt_cliente->bind_param("i", $cliente_id_input);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        
        if ($result_cliente->num_rows > 0) {
            $cliente_id = $cliente_id_input;
            error_log("Cliente ID válido encontrado: " . $cliente_id);
        } else {
            error_log("Cliente ID no encontrado o inactivo: " . $cliente_id_input);
            $cliente_id = null; // No lanzar error, solo usar null
        }
        $stmt_cliente->close();
    }
    
    // Determinar la sucursal principal para la venta
    $sucursales = [];
    $productos_sucursales = [];
    
    foreach ($input['productos'] as $producto) {
        if (!isset($producto['id']) || !isset($producto['cantidad'])) {
            throw new Exception("Datos de producto incompletos");
        }
        
        // Verificar stock disponible
        $stmt_stock = $conn->prepare("SELECT cantidad, sucursal_id, nombre FROM productos_stock WHERE id = ? AND estado = 'activo'");
        $stmt_stock->bind_param("i", $producto['id']);
        $stmt_stock->execute();
        $result_stock = $stmt_stock->get_result();
        
        if ($result_stock->num_rows === 0) {
            throw new Exception("Producto no encontrado o inactivo: ID " . $producto['id']);
        }
        
        $producto_info = $result_stock->fetch_assoc();
        
        // Verificar stock suficiente
        if ($producto_info['cantidad'] < $producto['cantidad']) {
            throw new Exception("Stock insuficiente para: " . $producto_info['nombre'] . ". Disponible: " . $producto_info['cantidad']);
        }
        
        $sucursales[] = $producto_info['sucursal_id'];
        $productos_sucursales[] = [
            'producto_id' => $producto['id'],
            'sucursal_id' => $producto_info['sucursal_id'],
            'nombre' => $producto_info['nombre']
        ];
        
        $stmt_stock->close();
    }
    
    // Determinar sucursal para la venta
    $sucursal_venta = 1; // Por defecto
    $sucursales_unicas = array_unique($sucursales);
    
    if (count($sucursales_unicas) === 1) {
        $sucursal_venta = $sucursales_unicas[0]; // Todos los productos son de la misma sucursal
    }
    
    // Obtener nombre de la sucursal
    $stmt_sucursal = $conn->prepare("SELECT nombre FROM sucursales WHERE id = ?");
    $stmt_sucursal->bind_param("i", $sucursal_venta);
    $stmt_sucursal->execute();
    $result_sucursal = $stmt_sucursal->get_result();
    $sucursal_nombre = "Sucursal Principal";
    if ($row_sucursal = $result_sucursal->fetch_assoc()) {
        $sucursal_nombre = $row_sucursal['nombre'];
    }
    $stmt_sucursal->close();
    
    // Validar y preparar datos para la venta
    $usuario_id = isset($input['usuario_id']) && $input['usuario_id'] ? $input['usuario_id'] : 1;
    $subtotal = floatval($input['subtotal']);
    $impuesto = floatval($input['impuesto']);
    $total = floatval($input['total']);
    $metodo_pago = isset($input['metodo_pago']) ? $input['metodo_pago'] : 'tarjeta';
    
    // Insertar en la tabla ventas
    $stmt = $conn->prepare("
        INSERT INTO ventas (
            cliente_id, pedido_id, sucursal_id, usuario_id, 
            subtotal, impuesto, total, metodo_pago, 
            efectivo_recibido, cambio, estado, fecha_venta
        ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NULL, NULL, 'completada', NOW())
    ");
    
    if (!$stmt) {
        throw new Exception("Error al preparar consulta de venta: " . $conn->error);
    }
    
    // Si cliente_id es null, la base de datos lo manejará como NULL
    $stmt->bind_param(
        "iiiddds",
        $cliente_id,
        $sucursal_venta,
        $usuario_id,
        $subtotal,
        $impuesto,
        $total,
        $metodo_pago
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error al insertar venta: " . $stmt->error);
    }
    
    $venta_id = $conn->insert_id;
    $stmt->close();
    
    // Insertar detalles de la venta y actualizar stock
    $stmt_detalle = $conn->prepare("
        INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt_update = $conn->prepare("
        UPDATE productos_stock 
        SET cantidad = cantidad - ? 
        WHERE id = ? AND estado = 'activo'
    ");
    
    foreach ($input['productos'] as $producto) {
        $producto_id = intval($producto['id']);
        $cantidad = intval($producto['cantidad']);
        $precio_unitario = floatval($producto['precio_final']);
        $subtotal_producto = $precio_unitario * $cantidad;
        
        // Insertar detalle de venta
        $stmt_detalle->bind_param(
            "iiidd",
            $venta_id,
            $producto_id,
            $cantidad,
            $precio_unitario,
            $subtotal_producto
        );
        
        if (!$stmt_detalle->execute()) {
            throw new Exception("Error al insertar detalle de venta: " . $stmt_detalle->error);
        }
        
        // Actualizar stock del producto
        $stmt_update->bind_param("ii", $cantidad, $producto_id);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar stock: " . $stmt_update->error);
        }
        
        // Verificar que se actualizó correctamente
        if ($stmt_update->affected_rows === 0) {
            throw new Exception("No se pudo actualizar el stock del producto ID: " . $producto_id);
        }
    }
    
    $stmt_detalle->close();
    $stmt_update->close();
    
    // Registrar actividad
    $accion = "Venta registrada";
    $cliente_info = $cliente_id ? " para cliente ID: $cliente_id" : " (cliente no registrado)";
    $descripcion = "Venta #$venta_id procesada exitosamente en $sucursal_nombre$cliente_info. Total: $" . number_format($total, 2);
    
    $stmt_actividad = $conn->prepare("
        INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id, fecha_registro)
        VALUES (?, ?, ?, 'venta', ?, NOW())
    ");
    
    $stmt_actividad->bind_param("issi", $usuario_id, $accion, $descripcion, $venta_id);
    
    if (!$stmt_actividad->execute()) {
        // No lanzar excepción por error en actividad, solo loggear
        error_log("Error al registrar actividad: " . $stmt_actividad->error);
    }
    
    $stmt_actividad->close();
    
    // Confirmar transacción
    $conn->commit();
    
    // Obtener información del cliente para la respuesta
    $cliente_info_response = null;
    if ($cliente_id) {
        $stmt_cliente_info = $conn->prepare("SELECT nombre, correo FROM clientes_activos WHERE id = ?");
        $stmt_cliente_info->bind_param("i", $cliente_id);
        $stmt_cliente_info->execute();
        $result_cliente_info = $stmt_cliente_info->get_result();
        
        if ($cliente_data = $result_cliente_info->fetch_assoc()) {
            $cliente_info_response = [
                'nombre' => $cliente_data['nombre'],
                'email' => $cliente_data['correo']
            ];
        }
        $stmt_cliente_info->close();
    }
    
    // Preparar respuesta con información detallada
    $response = [
        'success' => true,
        'venta_id' => $venta_id,
        'sucursal_venta' => $sucursal_venta,
        'sucursal_nombre' => $sucursal_nombre,
        'productos_sucursales' => $sucursales_unicas,
        'total_venta' => $total,
        'cliente_id' => $cliente_id,
        'cliente_info' => $cliente_info_response,
        'message' => 'Venta registrada exitosamente'
    ];
    
    // Información adicional sobre la entrega
    if (count($sucursales_unicas) > 1) {
        $response['nota_entrega'] = "Tu pedido contiene productos de diferentes sucursales. Te contactaremos para coordinar la entrega.";
        $response['tipo_entrega'] = "multiple_sucursales";
        
        // Agregar detalles de sucursales involucradas
        $sucursales_info = [];
        foreach ($sucursales_unicas as $sucursal_id) {
            $stmt_suc = $conn->prepare("SELECT nombre FROM sucursales WHERE id = ?");
            $stmt_suc->bind_param("i", $sucursal_id);
            $stmt_suc->execute();
            $result_suc = $stmt_suc->get_result();
            if ($row_suc = $result_suc->fetch_assoc()) {
                $sucursales_info[] = $row_suc['nombre'];
            }
            $stmt_suc->close();
        }
        $response['sucursales_involucradas'] = $sucursales_info;
    } else {
        $response['nota_entrega'] = "Puedes recoger tu pedido en $sucursal_nombre";
        $response['tipo_entrega'] = "sucursal_unica";
        $response['punto_recogida'] = $sucursal_nombre;
    }
    
    // Log para debugging
    error_log("Venta procesada - ID: $venta_id, Cliente ID: " . ($cliente_id ?: 'NULL') . ", Total: $total");
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_type' => 'payment_processing_error'
    ]);
    
    // Log del error
    error_log("Error en process_payment: " . $e->getMessage());
} finally {
    // Cerrar conexión
    $conn->close();
}
?>