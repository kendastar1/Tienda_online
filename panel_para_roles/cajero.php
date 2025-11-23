<?php
session_start();

// CONEXIÓN DIRECTA
$conn = new mysqli("localhost", "root", "", "tienda_ropa");

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Verificar si el usuario está logueado y es cajero (rol_id = 3)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 3) {
    header('Location: login.php');
    exit();
}

// Obtener sucursal del usuario
$sucursal_id = 1;
$usuario_id = $_SESSION['usuario_id'];

// Obtener productos de la base de datos
$productos = [];
$categorias = ['Todo'];

try {
    $query = "SELECT * FROM productos_stock WHERE estado = 'activo'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    }

    // Obtener categorías únicas de los productos
    $categorias_query = "SELECT DISTINCT categoria FROM productos_stock WHERE estado = 'activo' AND categoria IS NOT NULL";
    $categorias_result = $conn->query($categorias_query);
    
    if ($categorias_result && $categorias_result->num_rows > 0) {
        while($row = $categorias_result->fetch_assoc()) {
            $categorias[] = $row['categoria'];
        }
    }
} catch (Exception $e) {
    error_log("Error al obtener productos: " . $e->getMessage());
}

// Función para obtener la ruta correcta de la imagen
function obtenerRutaImagen($nombre_imagen) {
    if (empty($nombre_imagen)) {
        return "https://images.unsplash.com/photo-1523381210434-271e8be1f52b";
    }
    
    $ruta_base = "uploads/";
    if (file_exists($ruta_base . $nombre_imagen)) {
        return $ruta_base . $nombre_imagen;
    }
    
    return "https://images.unsplash.com/photo-1523381210434-271e8be1f52b";
}

// Función para registrar venta en la base de datos
function registrarVenta($conn, $venta_data) {
    try {
        $conn->begin_transaction();
        
        // Insertar en tabla ventas
        $stmt = $conn->prepare("INSERT INTO ventas (cliente_id, sucursal_id, usuario_id, subtotal, impuesto, total, metodo_pago, efectivo_recibido, cambio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Error preparando statement: " . $conn->error);
        }
        
        $stmt->bind_param("iiidddsdd", 
            $venta_data['cliente_id'],
            $venta_data['sucursal_id'],
            $venta_data['usuario_id'],
            $venta_data['subtotal'],
            $venta_data['impuesto'],
            $venta_data['total'],
            $venta_data['metodo_pago'],
            $venta_data['efectivo_recibido'],
            $venta_data['cambio']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar venta: " . $stmt->error);
        }
        
        $venta_id = $stmt->insert_id;
        $stmt->close();
        
        // Insertar detalles de venta y actualizar stock
        $detalle_stmt = $conn->prepare("INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        if (!$detalle_stmt) {
            throw new Exception("Error preparando statement de detalle: " . $conn->error);
        }
        
        $update_stock_stmt = $conn->prepare("UPDATE productos_stock SET cantidad = cantidad - ? WHERE id = ?");
        
        if (!$update_stock_stmt) {
            throw new Exception("Error preparando statement de stock: " . $conn->error);
        }
        
        foreach ($venta_data['detalles'] as $detalle) {
            // Insertar detalle
            $detalle_stmt->bind_param("iiidd", $venta_id, $detalle['producto_id'], $detalle['cantidad'], $detalle['precio_unitario'], $detalle['subtotal']);
            if (!$detalle_stmt->execute()) {
                throw new Exception("Error al registrar detalle de venta: " . $detalle_stmt->error);
            }
            
            // Actualizar stock
            $update_stock_stmt->bind_param("ii", $detalle['cantidad'], $detalle['producto_id']);
            if (!$update_stock_stmt->execute()) {
                throw new Exception("Error al actualizar stock: " . $update_stock_stmt->error);
            }
        }
        
        $detalle_stmt->close();
        $update_stock_stmt->close();
        
        // Registrar actividad
        $actividad_stmt = $conn->prepare("INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id) VALUES (?, 'Venta registrada', ?, 'venta', ?)");
        if ($actividad_stmt) {
            $descripcion = "Venta #" . $venta_id . " procesada exitosamente. Total: $" . $venta_data['total'];
            $actividad_stmt->bind_param("isi", $venta_data['usuario_id'], $descripcion, $venta_id);
            $actividad_stmt->execute();
            $actividad_stmt->close();
        }
        
        $conn->commit();
        return $venta_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en registrarVenta: " . $e->getMessage());
        return false;
    }
}

// Procesar venta si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar_venta'])) {
    $venta_data = json_decode($_POST['venta_data'], true);
    
    if ($venta_data) {
        $venta_data['usuario_id'] = $usuario_id;
        $venta_data['sucursal_id'] = $sucursal_id;
        
        $venta_id = registrarVenta($conn, $venta_data);
        
        if ($venta_id) {
            echo json_encode(['success' => true, 'venta_id' => $venta_id, 'message' => 'Venta registrada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar la venta']);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Cajero - Sistema de Ventas</title>
    <link rel="stylesheet" href="css/stiloparacajero.css">
    <style>
        .carrito-toggle-btn {
            background: #000;
            color: white;
            border: none;
            padding: 8px 16px;
            font-size: 12px;
            cursor: pointer;
            border-radius: 0px;
            font-family: "Times New Roman", serif;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: auto;
        }

        .carrito-toggle-btn:hover {
            background: #333;
        }

        .carrito-badge {
            background: #d32f2f;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carrito-view {
            display: none;
            padding: 20px;
        }

        .carrito-view.active {
            display: block;
        }

        .products-view.active {
            display: grid;
        }

        .products-view {
            display: grid;
        }

        .view-hidden {
            display: none !important;
        }

        .carrito-totals {
            background: #f9f9f9;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #ddd;
        }

        .carrito-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-vaciar-carrito {
            background: #d32f2f;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-vaciar-carrito:hover {
            background: #b71c1c;
        }

        .carrito-empty {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .carrito-items-container {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .carrito-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }

        .carrito-item-imagen {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            overflow: hidden;
        }

        .carrito-item-info {
            flex: 1;
        }

        .carrito-item-nombre {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .carrito-item-precio {
            color: #666;
            font-size: 14px;
        }

        .carrito-item-subtotal {
            font-weight: bold;
            color: #333;
        }

        .carrito-item-eliminar {
            margin-left: auto;
        }

        .btn-eliminar {
            background: #ff4444;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-eliminar:hover {
            background: #cc0000;
        }
        
        .right-panel {
            position: relative;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .producto-actual-imagen {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.5s ease;
            filter: brightness(0.7);
        }
        
        .mensaje-sin-producto {
            text-align: center;
            color: #666;
            padding: 40px;
            max-width: 80%;
        }
        
        .mensaje-sin-producto h3 {
            font-size: 24px;
            margin-bottom: 15px;
            font-weight: normal;
        }
        
        .mensaje-sin-producto p {
            font-size: 16px;
            line-height: 1.5;
        }
        
        .producto-actual-info {
            position: absolute;
            bottom: 80px;
            left: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.8);
            padding: 15px;
            border-radius: 5px;
            backdrop-filter: blur(5px);
        }
        
        .producto-actual-nombre {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .producto-actual-precio {
            font-size: 16px;
            color: #333;
        }
        
        .card-info {
            position: absolute;
            bottom: 20px;
            left: 20px;
            width: 90%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            padding: 18px;
            border-radius: 0px;
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
        }
        
        .totals-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 14px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .total-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .total-label {
            font-weight: normal;
            color: white;
        }
        
        .total-value {
            font-weight: bold;
            color: white;
        }
        
        .line {
            width: 100%;
            height: 1px;
            background: rgba(255,255,255,0.3);
            margin: 10px 0;
        }
        
        .btn-comprar {
            margin-top: 10px;
            padding: 8px 18px;
            background: white;
            color: black;
            border: none;
            cursor: pointer;
            border-radius: 0px;
            font-size: 13px;
            float: right;
            font-family: "Times New Roman", serif;
            transition: all 0.3s ease;
        }
        
        .btn-comprar:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        @keyframes agregarProducto {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(0, 0, 0, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(0, 0, 0, 0);
            }
        }
        
        .producto-agregado {
            animation: agregarProducto 0.6s ease;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .imagen-nueva {
            animation: slideInRight 0.5s ease;
        }
        
        .notificacion-agregado {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }
        
        .notificacion-agregado.mostrar {
            transform: translateX(0);
        }
        
        .notificacion-icono {
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-weight: bold;
        }

        .product-tag {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            font-size: 10px;
            border-radius: 3px;
            z-index: 2;
        }

        .product-tag.red {
            background: #f44336;
        }

        /* Estilos del modal de pago */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        .total-pagar {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        .payment-methods {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }

        .payment-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .payment-btn.active {
            border-color: #3498db;
            background: #3498db;
            color: white;
        }

        .payment-btn:hover {
            border-color: #2980b9;
        }

        .payment-icon {
            width: 24px;
            height: 24px;
        }

        .efectivo-section, .cambio-section, .tarjeta-section, .qr-section {
            margin-bottom: 20px;
        }

        .efectivo-label, .cambio-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .efectivo-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }

        .cambio-value {
            padding: 12px;
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
        }

        .tarjeta-section, .qr-section {
            text-align: center;
            padding: 30px;
        }

        .terminal-icon, .qr-code {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tarjeta-message, .qr-message {
            color: #666;
            line-height: 1.5;
        }

        .loading-dots {
            color: #3498db;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancelar {
            flex: 1;
            padding: 12px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-cancelar:hover {
            background: #7f8c8d;
        }

        .btn-confirmar {
            flex: 1;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-confirmar:hover {
            background: #219a52;
        }

        .btn-confirmar:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="layout">
    
    <div class="left-panel">
        
        <div class="navbar">
            <div class="nav-left">
                <div class="title-big">Sistema de Punto de Venta</div>
                <div class="title-small">Tienda de Ropa</div>
            </div>

            <div class="search-box">
                <input type="text" placeholder=" " id="search-input">
                <label class="search-label" for="search-input">Buscar producto</label>
                <div class="icon">
                    <svg class="search-icon" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </div>

            <div class="nav-user">
                <span class="user-name"><?php echo $_SESSION['nombre'] ?? 'Cajero'; ?></span>
                <span class="user-role">Cajero</span>
            </div>
        </div>

        <div class="categories-container">
            <?php foreach($categorias as $index => $categoria): ?>
                <button class="category-btn <?php echo $index === 0 ? 'active' : ''; ?>" 
                        data-categoria="<?php echo htmlspecialchars($categoria); ?>">
                    <?php echo htmlspecialchars($categoria); ?>
                </button>
            <?php endforeach; ?>
            
            <button class="carrito-toggle-btn" id="carrito-toggle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Carrito
                <span class="carrito-badge" id="carrito-badge">0</span>
            </button>
        </div>

        <!-- Vista de Productos -->
        <div class="products-view active" id="products-view">
            <div class="products-grid" id="products-container">
                <?php if(count($productos) > 0): ?>
                    <?php foreach($productos as $producto): ?>
                        <div class="product-card" data-categoria="<?php echo htmlspecialchars($producto['categoria']); ?>">
                            <div style="position: relative;">
                                <?php if($producto['porcentaje_descuento'] > 0): ?>
                                    <span class="product-tag red">-<?php echo $producto['porcentaje_descuento']; ?>%</span>
                                <?php endif; ?>
                                
                                <?php
                                $imagen_src = "";
                                if(!empty($producto['imagen'])) {
                                    $rutas_posibles = [
                                        "uploads/" . $producto['imagen'],
                                        "../uploads/" . $producto['imagen'],
                                        "../../uploads/" . $producto['imagen'],
                                        "panel_para_roles/uploads/" . $producto['imagen'],
                                        "../panel_para_roles/uploads/" . $producto['imagen']
                                    ];
                                    
                                    foreach($rutas_posibles as $ruta) {
                                        if(file_exists($ruta)) {
                                            $imagen_src = $ruta;
                                            break;
                                        }
                                    }
                                    
                                    if(empty($imagen_src)) {
                                        $imagen_src = "https://images.unsplash.com/photo-1523381210434-271e8be1f52b";
                                    }
                                } else {
                                    $imagen_src = "https://images.unsplash.com/photo-1523381210434-271e8be1f52b";
                                }
                                ?>
                                
                                <img src="<?php echo $imagen_src; ?>" 
                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                     style="width: 100%; height: 200px; object-fit: cover;"
                                     class="producto-imagen"
                                     data-producto-id="<?php echo $producto['id']; ?>">
                            </div>
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($producto['categoria'] ?? 'Sin categoría'); ?></div>
                                <div class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                                <div class="product-price">
                                    <?php if($producto['descuento'] > 0): ?>
                                        <span style="text-decoration: line-through; color: #999; font-size: 12px;">
                                            $<?php echo number_format($producto['precio'], 2); ?>
                                        </span>
                                        <br>
                                        $<?php echo number_format($producto['precio_final'], 2); ?>
                                    <?php else: ?>
                                        $<?php echo number_format($producto['precio'], 2); ?>
                                    <?php endif; ?>
                                </div>
                                <button class="add-btn-card" 
                                        data-producto-id="<?php echo $producto['id']; ?>"
                                        data-producto-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        data-producto-precio="<?php echo $producto['precio_final']; ?>"
                                        data-producto-imagen="<?php echo htmlspecialchars($producto['imagen'] ?? ''); ?>"
                                        data-producto-stock="<?php echo $producto['cantidad']; ?>"
                                        <?php echo $producto['cantidad'] <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $producto['cantidad'] > 0 ? 'Agregar' : 'Agotado'; ?>
                                </button>
                                <?php if($producto['cantidad'] > 0): ?>
                                    <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                        Stock: <?php echo $producto['cantidad']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        No hay productos disponibles en este momento.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vista del Carrito -->
        <div class="carrito-view" id="carrito-view">
            <h3 style="margin-bottom: 20px; color: #333;">Carrito de Compras</h3>
            
            <div class="carrito-items-container" id="carrito-items">
                <div class="carrito-empty">El carrito está vacío</div>
            </div>
            
            <div class="carrito-totals">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Subtotal:</span>
                    <span id="carrito-subtotal">$0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>Impuesto (16%):</span>
                    <span id="carrito-impuesto">$0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 16px;">
                    <span>Total:</span>
                    <span id="carrito-total">$0.00</span>
                </div>
            </div>
            
            <div class="carrito-actions">
                <button class="btn-vaciar-carrito" onclick="vaciarCarrito()">Vaciar Carrito</button>
                <button class="btn-comprar" onclick="abrirModalPago()" id="btn-procesar-pago" disabled>Procesar Pago</button>
            </div>
        </div>

    </div>

    <div class="right-panel">
        <div class="mensaje-sin-producto" id="mensaje-sin-producto">
            <h3>¡Bienvenido al Sistema de Ventas!</h3>
            <p>Agrega productos al carrito para verlos aquí.<br>
            Selecciona productos de la lista a la izquierda para comenzar.</p>
        </div>
        
        <img src="" 
             alt="Producto actual" 
             class="producto-actual-imagen" 
             id="producto-actual-imagen" 
             style="display: none;">
        
        <div class="producto-actual-info" id="producto-actual-info" style="display: none;">
            <div class="producto-actual-nombre" id="producto-actual-nombre"></div>
            <div class="producto-actual-precio" id="producto-actual-precio"></div>
        </div>
        
        <div class="card-info">
            <div class="totals-row">
                <div class="total-item">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-value" id="subtotal">$0.00</span>
                </div>
                <div class="total-item">
                    <span class="total-label">Impuesto (16%):</span>
                    <span class="total-value" id="impuesto">$0.00</span>
                </div>
                <div class="total-item">
                    <span class="total-label">Total:</span>
                    <span class="total-value" id="total">$0.00</span>
                </div>
            </div>

            <div class="line"></div>

            <button class="btn-comprar" onclick="abrirModalPago()" id="btn-procesar-pago-sidebar" disabled>Procesar Pago</button>
        </div>
    </div>

</div>

<!-- Notificación de producto agregado -->
<div class="notificacion-agregado" id="notificacion-agregado">
    <div class="notificacion-icono">✓</div>
    <span id="notificacion-texto">Producto agregado al carrito</span>
</div>

<!-- Modal de Pago -->
<div class="modal-overlay" id="modalPago">
    <div class="modal-content">
        <div class="modal-title">Procesar Pago</div>
        
        <div class="total-pagar" id="totalPagarModal">$0.00</div>
        
        <div class="payment-methods">
            <button class="payment-btn active" onclick="seleccionarMetodo('efectivo')">
                <svg class="payment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                Efectivo
            </button>
            <button class="payment-btn" onclick="seleccionarMetodo('tarjeta')">
                <svg class="payment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                Tarjeta
            </button>
            <button class="payment-btn" onclick="seleccionarMetodo('qr')">
                <svg class="payment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <rect x="7" y="7" width="3" height="3"></rect>
                    <rect x="14" y="7" width="3" height="3"></rect>
                    <rect x="7" y="14" width="3" height="3"></rect>
                    <rect x="14" y="14" width="3" height="3"></rect>
                </svg>
                QR
            </button>
        </div>
        
        <div class="efectivo-section" id="seccionEfectivo">
            <label class="efectivo-label">Efectivo Recibido</label>
            <input type="number" class="efectivo-input" id="efectivoRecibido" placeholder="0.00" oninput="calcularCambio()">
        </div>
        
        <div class="cambio-section" id="seccionCambio">
            <label class="cambio-label">Cambio</label>
            <div class="cambio-value" id="cambioValue">$0.00</div>
        </div>
        
        <div class="tarjeta-section" id="seccionTarjeta" style="display: none;">
            <div class="terminal-icon"></div>
            <div class="tarjeta-message">
                Inserte o pase la tarjeta<br>
                <span class="loading-dots">Esperando terminal de pago</span>
            </div>
        </div>
        
        <div class="qr-section" id="seccionQR" style="display: none;">
            <div class="qr-code"></div>
            <div class="qr-message">Escanea el código QR para pagar</div>
        </div>
        
        <div class="modal-buttons">
            <button class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-confirmar" onclick="confirmarPago()" id="btn-confirmar-pago" disabled>Confirmar Pago</button>
        </div>
    </div>
</div>

<script>
    // Variables globales
    let totalAPagar = 0;
    let metodoPagoSeleccionado = 'efectivo';
    let carrito = [];
    let vistaActual = 'productos';

    // Toggle entre vistas
    document.getElementById('carrito-toggle').addEventListener('click', function() {
        toggleVistaCarrito();
    });

    function toggleVistaCarrito() {
        const productsView = document.getElementById('products-view');
        const carritoView = document.getElementById('carrito-view');
        
        if (vistaActual === 'productos') {
            productsView.classList.remove('active');
            productsView.classList.add('view-hidden');
            carritoView.classList.add('active');
            vistaActual = 'carrito';
        } else {
            carritoView.classList.remove('active');
            productsView.classList.remove('view-hidden');
            productsView.classList.add('active');
            vistaActual = 'productos';
        }
    }

    // Búsqueda y filtros
    document.getElementById('search-input').addEventListener('input', function() {
        const label = document.querySelector('.search-label');
        if (this.value.length > 0) {
            label.style.display = 'none';
        } else {
            label.style.display = 'block';
        }
        filtrarPorBusqueda(this.value.toLowerCase());
    });

    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('active');
            });
            this.classList.add('active');
            const categoria = this.getAttribute('data-categoria');
            filtrarProductos(categoria);
        });
    });

    function filtrarProductos(categoria) {
        const productos = document.querySelectorAll('.product-card');
        productos.forEach(producto => {
            if (categoria === 'Todo') {
                producto.style.display = 'block';
            } else {
                const productoCategoria = producto.getAttribute('data-categoria');
                if (productoCategoria === categoria) {
                    producto.style.display = 'block';
                } else {
                    producto.style.display = 'none';
                }
            }
        });
    }

    function filtrarPorBusqueda(termino) {
        const productos = document.querySelectorAll('.product-card');
        productos.forEach(producto => {
            const nombre = producto.querySelector('.product-title').textContent.toLowerCase();
            const categoria = producto.querySelector('.product-category').textContent.toLowerCase();
            if (nombre.includes(termino) || categoria.includes(termino)) {
                producto.style.display = 'block';
            } else {
                producto.style.display = 'none';
            }
        });
    }

    // Carrito functions
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-btn-card') && !e.target.disabled) {
            const productoId = e.target.getAttribute('data-producto-id');
            const productoNombre = e.target.getAttribute('data-producto-nombre');
            const productoPrecio = parseFloat(e.target.getAttribute('data-producto-precio'));
            const productoImagen = e.target.getAttribute('data-producto-imagen');
            const productoStock = parseInt(e.target.getAttribute('data-producto-stock'));
            
            const productoEnCarrito = carrito.find(item => item.id === productoId);
            const cantidadEnCarrito = productoEnCarrito ? productoEnCarrito.cantidad : 0;
            
            if (cantidadEnCarrito >= productoStock) {
                alert('No hay suficiente stock disponible para este producto');
                return;
            }
            
            const productoElement = e.target.closest('.product-card');
            const imagenElement = productoElement.querySelector('.producto-imagen');
            const imagenSrc = imagenElement.src;
            
            e.target.classList.add('producto-agregado');
            setTimeout(() => {
                e.target.classList.remove('producto-agregado');
            }, 600);
            
            agregarAlCarrito({
                id: productoId,
                nombre: productoNombre,
                precio: productoPrecio,
                imagen: productoImagen,
                imagenSrc: imagenSrc,
                stock: productoStock,
                cantidad: 1
            });
        }
        
        if (e.target.classList.contains('btn-eliminar')) {
            const productoId = e.target.getAttribute('data-producto-id');
            eliminarDelCarrito(productoId);
        }
    });

    function agregarAlCarrito(producto) {
        const productoExistente = carrito.find(item => item.id === producto.id);
        
        if (productoExistente) {
            if (productoExistente.cantidad >= producto.stock) {
                alert('No hay suficiente stock disponible para este producto');
                return;
            }
            productoExistente.cantidad += 1;
            productoExistente.subtotal = productoExistente.precio * productoExistente.cantidad;
        } else {
            producto.subtotal = producto.precio;
            carrito.push(producto);
        }
        
        actualizarCarrito();
        actualizarTotales();
        mostrarProductoEnPanelDerecho(producto);
        mostrarNotificacion(producto.nombre);
    }

    function eliminarDelCarrito(productoId) {
        carrito = carrito.filter(item => item.id !== productoId);
        actualizarCarrito();
        actualizarTotales();
        
        if (carrito.length === 0) {
            restaurarMensajeSinProducto();
        }
    }

    function vaciarCarrito() {
        if (carrito.length === 0) {
            alert('El carrito ya está vacío');
            return;
        }
        
        if (confirm('¿Estás seguro de que quieres vaciar el carrito?')) {
            carrito = [];
            actualizarCarrito();
            actualizarTotales();
            restaurarMensajeSinProducto();
        }
    }

    function actualizarCarrito() {
        const carritoItems = document.getElementById('carrito-items');
        const carritoBadge = document.getElementById('carrito-badge');
        const btnProcesar = document.getElementById('btn-procesar-pago');
        const btnProcesarSidebar = document.getElementById('btn-procesar-pago-sidebar');
        
        const totalItems = carrito.reduce((total, item) => total + item.cantidad, 0);
        carritoBadge.textContent = totalItems;
        
        if (carrito.length === 0) {
            carritoItems.innerHTML = '<div class="carrito-empty">El carrito está vacío</div>';
            btnProcesar.disabled = true;
            btnProcesarSidebar.disabled = true;
            return;
        }
        
        btnProcesar.disabled = false;
        btnProcesarSidebar.disabled = false;
        
        let html = '';
        carrito.forEach(item => {
            const rutaBase = 'uploads/';
            html += `
                <div class="carrito-item">
                    <div class="carrito-item-imagen">
                        ${item.imagen ? 
                            `<img src="${rutaBase}${item.imagen}" alt="${item.nombre}" 
                                  style="width: 100%; height: 100%; object-fit: cover;"
                                  onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b'">` : 
                            `<img src="https://images.unsplash.com/photo-1523381210434-271e8be1f52b" alt="${item.nombre}"
                                  style="width: 100%; height: 100%; object-fit: cover;">`
                        }
                    </div>
                    <div class="carrito-item-info">
                        <div class="carrito-item-nombre">${item.nombre}</div>
                        <div class="carrito-item-precio">$${item.precio.toFixed(2)} x ${item.cantidad}</div>
                        <div class="carrito-item-subtotal">$${item.subtotal.toFixed(2)}</div>
                    </div>
                    <div class="carrito-item-eliminar">
                        <button class="btn-eliminar" data-producto-id="${item.id}">×</button>
                    </div>
                </div>
            `;
        });
        
        carritoItems.innerHTML = html;
    }

    function actualizarTotales() {
        const subtotal = carrito.reduce((total, item) => total + item.subtotal, 0);
        const impuesto = subtotal * 0.16;
        const total = subtotal + impuesto;
        
        document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('impuesto').textContent = `$${impuesto.toFixed(2)}`;
        document.getElementById('total').textContent = `$${total.toFixed(2)}`;
        
        document.getElementById('carrito-subtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('carrito-impuesto').textContent = `$${impuesto.toFixed(2)}`;
        document.getElementById('carrito-total').textContent = `$${total.toFixed(2)}`;
        
        totalAPagar = total;
    }

    function mostrarProductoEnPanelDerecho(producto) {
        const mensajeSinProducto = document.getElementById('mensaje-sin-producto');
        const imagenProducto = document.getElementById('producto-actual-imagen');
        const infoProducto = document.getElementById('producto-actual-info');
        const nombreProducto = document.getElementById('producto-actual-nombre');
        const precioProducto = document.getElementById('producto-actual-precio');
        
        mensajeSinProducto.style.display = 'none';
        imagenProducto.src = producto.imagenSrc;
        imagenProducto.style.display = 'block';
        imagenProducto.classList.add('imagen-nueva');
        setTimeout(() => {
            imagenProducto.classList.remove('imagen-nueva');
        }, 500);
        
        nombreProducto.textContent = producto.nombre;
        precioProducto.textContent = `$${producto.precio.toFixed(2)}`;
        infoProducto.style.display = 'block';
    }

    function restaurarMensajeSinProducto() {
        const mensajeSinProducto = document.getElementById('mensaje-sin-producto');
        const imagenProducto = document.getElementById('producto-actual-imagen');
        const infoProducto = document.getElementById('producto-actual-info');
        
        mensajeSinProducto.style.display = 'flex';
        imagenProducto.style.display = 'none';
        infoProducto.style.display = 'none';
    }

    function mostrarNotificacion(nombreProducto) {
        const notificacion = document.getElementById('notificacion-agregado');
        const notificacionTexto = document.getElementById('notificacion-texto');
        
        notificacionTexto.textContent = `"${nombreProducto}" agregado al carrito`;
        notificacion.classList.add('mostrar');
        
        setTimeout(() => {
            notificacion.classList.remove('mostrar');
        }, 3000);
    }

    // Modal de pago functions
    function abrirModalPago() {
        if (carrito.length === 0) {
            alert('El carrito está vacío');
            return;
        }
        
        document.getElementById('totalPagarModal').textContent = `$${totalAPagar.toFixed(2)}`;
        document.getElementById('modalPago').style.display = 'flex';
        document.getElementById('efectivoRecibido').value = '';
        document.getElementById('cambioValue').textContent = '$0.00';
        document.getElementById('btn-confirmar-pago').disabled = true;
        seleccionarMetodo('efectivo');
    }

    function cerrarModal() {
        document.getElementById('modalPago').style.display = 'none';
    }

    function seleccionarMetodo(metodo) {
        metodoPagoSeleccionado = metodo;
        
        document.querySelectorAll('.payment-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        event.target.classList.add('active');
        
        document.getElementById('seccionEfectivo').style.display = 'none';
        document.getElementById('seccionCambio').style.display = 'none';
        document.getElementById('seccionTarjeta').style.display = 'none';
        document.getElementById('seccionQR').style.display = 'none';
        
        switch(metodo) {
            case 'efectivo':
                document.getElementById('seccionEfectivo').style.display = 'block';
                document.getElementById('seccionCambio').style.display = 'block';
                document.getElementById('btn-confirmar-pago').disabled = true;
                break;
            case 'tarjeta':
                document.getElementById('seccionTarjeta').style.display = 'block';
                document.getElementById('btn-confirmar-pago').disabled = false;
                break;
            case 'qr':
                document.getElementById('seccionQR').style.display = 'block';
                document.getElementById('btn-confirmar-pago').disabled = false;
                break;
        }
    }

    function calcularCambio() {
        if (metodoPagoSeleccionado === 'efectivo') {
            const efectivoRecibido = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
            const cambio = efectivoRecibido - totalAPagar;
            
            const cambioElement = document.getElementById('cambioValue');
            const btnConfirmar = document.getElementById('btn-confirmar-pago');
            
            if (cambio >= 0) {
                cambioElement.textContent = `$${cambio.toFixed(2)}`;
                btnConfirmar.disabled = false;
            } else {
                cambioElement.textContent = `-$${Math.abs(cambio).toFixed(2)}`;
                btnConfirmar.disabled = true;
            }
        }
    }

    function confirmarPago() {
        if (metodoPagoSeleccionado === 'efectivo') {
            const efectivoRecibido = parseFloat(document.getElementById('efectivoRecibido').value) || 0;
            if (efectivoRecibido < totalAPagar) {
                alert('El efectivo recibido es menor al total a pagar');
                return;
            }
        }
        
        const ventaData = {
            cliente_id: null,
            sucursal_id: <?php echo $sucursal_id; ?>,
            usuario_id: <?php echo $usuario_id; ?>,
            subtotal: carrito.reduce((total, item) => total + item.subtotal, 0),
            impuesto: carrito.reduce((total, item) => total + item.subtotal, 0) * 0.16,
            total: totalAPagar,
            metodo_pago: metodoPagoSeleccionado,
            efectivo_recibido: metodoPagoSeleccionado === 'efectivo' ? parseFloat(document.getElementById('efectivoRecibido').value) || 0 : null,
            cambio: metodoPagoSeleccionado === 'efectivo' ? (parseFloat(document.getElementById('efectivoRecibido').value) || 0) - totalAPagar : null,
            detalles: carrito.map(item => ({
                producto_id: parseInt(item.id),
                cantidad: parseInt(item.cantidad),
                precio_unitario: parseFloat(item.precio),
                subtotal: parseFloat(item.subtotal)
            }))
        };
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'procesar_venta=true&venta_data=' + encodeURIComponent(JSON.stringify(ventaData))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pago procesado exitosamente. Venta #' + data.venta_id + ' completada.');
                
                carrito = [];
                actualizarCarrito();
                actualizarTotales();
                cerrarModal();
                restaurarMensajeSinProducto();
                
                if (vistaActual === 'carrito') {
                    toggleVistaCarrito();
                }
                
                setTimeout(() => {
                    location.reload();
                }, 1000);
                
            } else {
                alert('Error al procesar la venta: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión al procesar la venta');
        });
    }

    document.getElementById('modalPago').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModal();
        }
    });
</script>

</body>
</html>