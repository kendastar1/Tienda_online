<?php
header('Content-Type: application/json');
session_start();

// Configuración de la base de datos
$servername = "localhost";
$username = "root"; // Cambiar por tu usuario
$password = ""; // Cambiar por tu contraseña
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener datos del pago
$data = json_decode(file_get_contents('php://input'), true);

$cliente_id = isset($data['cliente_id']) ? $data['cliente_id'] : null;
$sucursal_id = isset($data['sucursal_id']) ? $data['sucursal_id'] : 1; // Sucursal por defecto
$usuario_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 2; // Usuario por defecto si no hay sesión
$metodo_pago = $data['metodo_pago'];
$efectivo_recibido = $data['efectivo_recibido'];
$carrito = $data['carrito'];

// Calcular totales
$subtotal = 0;
foreach ($carrito as $item) {
    $subtotal += $item['precio'] * $item['cantidad'];
}

$impuesto = $subtotal * 0.16; // 16% de impuesto
$total = $subtotal + $impuesto;
$cambio = $efectivo_recibido - $total;

try {
    // Iniciar transacción
    $conn->begin_transaction();

    // 1. Insertar venta en la tabla ventas
    $stmt_venta = $conn->prepare("INSERT INTO ventas (cliente_id, sucursal_id, usuario_id, subtotal, impuesto, total, metodo_pago, efectivo_recibido, cambio, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completada')");
    $stmt_venta->bind_param("iiiddddd", $cliente_id, $sucursal_id, $usuario_id, $subtotal, $impuesto, $total, $metodo_pago, $efectivo_recibido, $cambio);
    $stmt_venta->execute();
    $venta_id = $conn->insert_id;

    // 2. Insertar detalles de venta y actualizar stock
    $stmt_detalle = $conn->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmt_update_stock = $conn->prepare("UPDATE productos_stock SET cantidad = cantidad - ? WHERE id = ?");

    foreach ($carrito as $item) {
        $producto_id = $item['id'];
        $cantidad = $item['cantidad'];
        $precio_unitario = $item['precio'];
        $subtotal_item = $precio_unitario * $cantidad;

        // Insertar detalle
        $stmt_detalle->bind_param("iiidd", $venta_id, $producto_id, $cantidad, $precio_unitario, $subtotal_item);
        $stmt_detalle->execute();

        // Actualizar stock
        $stmt_update_stock->bind_param("ii", $cantidad, $producto_id);
        $stmt_update_stock->execute();
    }

    // 3. Registrar actividad
    $accion = "Venta registrada";
    $descripcion = "Venta #$venta_id procesada exitosamente. Total: $$total";
    
    $stmt_actividad = $conn->prepare("INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id) VALUES (?, ?, ?, 'venta', ?)");
    $stmt_actividad->bind_param("issi", $usuario_id, $accion, $descripcion, $venta_id);
    $stmt_actividad->execute();

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'venta_id' => $venta_id,
        'total' => $total,
        'message' => 'Pago procesado exitosamente'
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el pago: ' . $e->getMessage()
    ]);
}

$conn->close();
?>