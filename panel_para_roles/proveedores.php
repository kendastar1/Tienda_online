<?php
// proveedores.php
session_start();
require_once 'conexion.php';

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Manejo de endpoints AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Obtener detalles de un pedido
    if ($_GET['action'] === 'get_order' && isset($_GET['id'])) {
        $id = (int) $_GET['id'];

        $stmt = $conexion->prepare("
            SELECT p.*, 
                   c.nombre as cliente_nombre,
                   c.correo as cliente_correo,
                   c.telefono as cliente_telefono
            FROM pedidos p
            LEFT JOIN clientes_activos c ON p.cliente_id = c.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
            exit;
        }

        $stmt2 = $conexion->prepare("
            SELECT dp.*, ps.nombre AS producto_nombre, ps.precio
            FROM detalle_pedidos dp
            LEFT JOIN productos_stock ps ON dp.producto_id = ps.id
            WHERE dp.pedido_id = :id
        ");
        $stmt2->execute([':id' => $id]);
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        exit;
    }

    // Actualizar pedido
    if ($_GET['action'] === 'update_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload || !isset($payload['id'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        $id = (int)$payload['id'];
        $nuevo_estado = isset($payload['estado']) ? $payload['estado'] : null;

        try {
            if ($nuevo_estado !== null) {
                $stmt = $conexion->prepare("UPDATE pedidos SET estado = :estado WHERE id = :id");
                $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
                
                // Registrar actividad
                $stmtAct = $conexion->prepare("
                    INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id, fecha_registro)
                    VALUES (:usuario_id, 'Pedido actualizado', 'Estado del pedido cambiado a: ' || :estado, 'pedido', :referencia_id, NOW())
                ");
                $stmtAct->execute([
                    ':usuario_id' => $_SESSION['usuario_id'],
                    ':estado' => $nuevo_estado,
                    ':referencia_id' => $id
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Pedido actualizado']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        exit;
    }

    // Obtener productos para envío
    if ($_GET['action'] === 'get_products') {
        $stmt = $conexion->prepare("
            SELECT ps.id, ps.nombre, ps.cantidad, ps.precio, ps.precio_final, c.nombre as categoria_nombre
            FROM productos_stock ps
            LEFT JOIN categorias c ON ps.categoria_id = c.id
            WHERE ps.estado = 'activo'
            ORDER BY ps.nombre
        ");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'products' => $products]);
        exit;
    }

    // Obtener facturas (usando ventas como facturas)
    if ($_GET['action'] === 'get_invoices') {
        $stmt = $conexion->prepare("
            SELECT v.*, 
                   c.nombre as cliente_nombre,
                   s.nombre as sucursal_nombre,
                   u.nombre as vendedor_nombre
            FROM ventas v
            LEFT JOIN clientes_activos c ON v.cliente_id = c.id
            LEFT JOIN sucursales s ON v.sucursal_id = s.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            ORDER BY v.fecha_venta DESC
            LIMIT 50
        ");
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'invoices' => $invoices]);
        exit;
    }

    // Obtener stock bajo - VERSIÓN MEJORADA CON DEBUG
    if ($_GET['action'] === 'get_low_stock') {
        try {
            // Primero, verifiquemos qué productos tenemos
            $debugStmt = $conexion->prepare("
                SELECT COUNT(*) as total, 
                       SUM(CASE WHEN cantidad <= 10 THEN 1 ELSE 0 END) as bajos,
                       SUM(CASE WHEN cantidad <= 3 THEN 1 ELSE 0 END) as criticos
                FROM productos_stock 
                WHERE estado = 'activo'
            ");
            $debugStmt->execute();
            $debugInfo = $debugStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conexion->prepare("
                SELECT 
                    ps.id, 
                    ps.nombre, 
                    ps.cantidad, 
                    ps.precio, 
                    ps.precio_final,
                    COALESCE(c.nombre, 'Sin categoría') as categoria_nombre,
                    COALESCE(s.nombre, 'Sin sucursal') as sucursal_nombre,
                    CASE 
                        WHEN ps.cantidad <= 3 THEN 'crítico'
                        WHEN ps.cantidad <= 10 THEN 'bajo' 
                        ELSE 'normal'
                    END as nivel_stock
                FROM productos_stock ps
                LEFT JOIN categorias c ON ps.categoria_id = c.id
                LEFT JOIN sucursales s ON ps.sucursal_id = s.id
                WHERE ps.cantidad <= 10 AND ps.estado = 'activo'
                ORDER BY ps.cantidad ASC
            ");
            $stmt->execute();
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'lowStock' => $lowStock,
                'debug' => $debugInfo
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error en la consulta: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Portal de Proveedores - Gestión de Pedidos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .topbar {
            background: linear-gradient(135deg, var(--primary-color), #1a2530);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .topbar h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .summary-card {
            border-radius: 12px;
            background: white;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            height: 100%;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .summary-card .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .summary-card.pending .card-icon {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .summary-card.transit .card-icon {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .summary-card.delivered .card-icon {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .summary-card.alert .card-icon {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .summary-card h3 {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .summary-card p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .tab-nav {
            background: white;
            border-radius: 12px;
            padding: 10px;
            margin-top: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .nav-pills .nav-link {
            border-radius: 8px;
            padding: 10px 20px;
            margin-right: 10px;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .card-orders {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        
        .order-row {
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .order-row:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        
        .badge-status {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .badge-pending {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .badge-transit {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .badge-delivered {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .badge-cancelled {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .order-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .action-btn {
            min-width: 160px;
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 15px;
        }
        
        .low-stock {
            color: var(--danger-color);
            font-weight: 600;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.05);
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        @media (max-width: 767px) {
            .order-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .summary-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-box-seam me-3" style="font-size: 1.8rem;"></i>
                    <div>
                        <h4>Portal de Proveedores</h4>
                        <small class="text-light">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></small>
                    </div>
                </div>
                <div>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-4">
        <!-- Summary cards -->
        <div class="row g-4 mb-4">
            <?php
            // Contar pedidos por estado - USANDO PEDIDOS
            $counts = [
                'pendiente' => 0,
                'procesado' => 0,
                'completado' => 0,
                'cancelado' => 0
            ];
            
            $stmt = $conexion->query("SELECT estado, COUNT(*) as c FROM pedidos GROUP BY estado");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = strtolower($r['estado']);
                $counts[$key] = (int)$r['c'];
            }

            // Alertas stock bajo (cantidad <= 10)
            $stmt2 = $conexion->prepare("SELECT COUNT(*) AS low FROM productos_stock WHERE cantidad <= 10 AND estado = 'activo'");
            $stmt2->execute();
            $low = (int)$stmt2->fetch(PDO::FETCH_ASSOC)['low'];
            ?>
            <div class="col-md-3">
                <div class="summary-card pending">
                    <div class="card-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3><?php echo $counts['pendiente'] ?? 0; ?></h3>
                    <p>Pedidos Pendientes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card transit">
                    <div class="card-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h3><?php echo $counts['procesado'] ?? 0; ?></h3>
                    <p>En Proceso</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card delivered">
                    <div class="card-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3><?php echo $counts['completado'] ?? 0; ?></h3>
                    <p>Completados</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card alert">
                    <div class="card-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h3><?php echo $low; ?></h3>
                    <p>Alertas de Stock</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-nav mb-4">
            <ul class="nav nav-pills" id="mainTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#" data-tab="orders">
                        <i class="bi bi-clipboard-check me-1"></i> Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="products">
                        <i class="bi bi-box-seam me-1"></i> Enviar Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-tab="invoices">
                        <i class="bi bi-receipt me-1"></i> Facturas
                    </a>
                </li>
                
            </ul>
        </div>

        <!-- Orders Tab -->
        <div class="tab-content" id="ordersTab">
            <div class="card-orders">
                <h5 class="mb-3 d-flex align-items-center">
                    <i class="bi bi-clipboard-check me-2"></i> Gestionar Pedidos
                </h5>

                <?php
                // Obtener pedidos (más recientes primero) - USANDO PEDIDOS
                $stmt = $conexion->prepare("
                    SELECT p.*, 
                           c.nombre as cliente_nombre,
                           c.correo as cliente_correo
                    FROM pedidos p
                    LEFT JOIN clientes_activos c ON p.cliente_id = c.id
                    ORDER BY p.fecha_pedido DESC
                    LIMIT 50
                ");
                $stmt->execute();
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!$orders) {
                    echo '<div class="alert alert-light text-center py-4">No hay pedidos para mostrar.</div>';
                } else {
                    foreach ($orders as $o) {
                        // badge mapping para pedidos
                        $estado = strtolower($o['estado']);
                        $badge_class = 'badge-pending';
                        $badge_text = 'PENDIENTE';
                        
                        if ($estado === 'procesado') {
                            $badge_class = 'badge-transit';
                            $badge_text = 'EN PROCESO';
                        } elseif ($estado === 'completado') {
                            $badge_class = 'badge-delivered';
                            $badge_text = 'COMPLETADO';
                        } elseif ($estado === 'cancelado') {
                            $badge_class = 'badge-cancelled';
                            $badge_text = 'CANCELADO';
                        }

                        // Mostrar pedido
                        ?>
                        <div class="order-row">
                            <div style="flex:1 1 auto;">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <strong>Pedido Cliente #<?php echo str_pad($o['id'], 3, "0", STR_PAD_LEFT); ?></strong>
                                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                </div>
                                <div class="order-meta mb-2">
                                    <span><i class="bi bi-calendar me-1"></i> <?php echo htmlspecialchars($o['fecha_pedido']); ?></span>
                                    <div class="mt-2">
                                        <strong><i class="bi bi-person me-1"></i> Cliente:</strong> <?php echo htmlspecialchars($o['cliente_nombre'] ?? 'No especificado'); ?> |
                                        <strong><i class="bi bi-envelope me-1"></i> Email:</strong> <?php echo htmlspecialchars($o['cliente_correo'] ?? 'No especificado'); ?>
                                    </div>
                                    <div class="mt-2">
                                        <strong><i class="bi bi-currency-dollar me-1"></i> Total: $<?php echo number_format($o['total'],2); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div style="width:220px; text-align:right;">
                                <!-- acciones: marcar en proceso / confirmar entrega / ver detalles -->
                                <?php if ($estado === 'pendiente'): ?>
                                    <button class="btn btn-primary btn-sm action-btn mb-2 btn-mark-trans" data-id="<?php echo $o['id']; ?>">
                                        <i class="bi bi-gear me-1"></i> En Proceso
                                    </button>
                                <?php elseif ($estado === 'procesado'): ?>
                                    <button class="btn btn-success btn-sm action-btn mb-2 btn-confirm-delivery" data-id="<?php echo $o['id']; ?>">
                                        <i class="bi bi-check-lg me-1"></i> Completar
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm action-btn mb-2" disabled>
                                        <i class="bi bi-check2-all me-1"></i> Sin acciones
                                    </button>
                                <?php endif; ?>

                                <button class="btn btn-outline-primary btn-sm action-btn btn-view" data-id="<?php echo $o['id']; ?>">
                                    <i class="bi bi-eye me-1"></i> Ver Detalles
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>

        <!-- Products Tab -->
        <div class="tab-content d-none" id="productsTab">
            <div class="card-orders">
                <h5 class="mb-3 d-flex align-items-center">
                    <i class="bi bi-box-seam me-2"></i> Enviar Productos
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargarán con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Invoices Tab -->
        <div class="tab-content d-none" id="invoicesTab">
            <div class="card-orders">
                <h5 class="mb-3 d-flex align-items-center">
                    <i class="bi bi-receipt me-2"></i> Facturas
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="invoicesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Sucursal</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargarán con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Tab -->
        <div class="tab-content d-none" id="lowStockTab">
            <div class="card-orders">
                <h5 class="mb-3 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle me-2"></i> Stock Bajo
                </h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="lowStockTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los datos se cargarán con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para ver/editar pedido -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i> Ver Detalles del Pedido</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="orderForm">
                <input type="hidden" name="id" id="order_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado</label>
                        <select id="order_estado" name="estado" class="form-select">
                            <option value="pendiente">Pendiente</option>
                            <option value="procesado">En Proceso</option>
                            <option value="completado">Completado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Información del Cliente</label>
                        <div class="form-control bg-light">
                            <small id="cliente_info">Cargando...</small>
                        </div>
                    </div>
                </div>

                <h6 class="mt-4 mb-3">Items del Pedido</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="text-end mt-3">
                    <strong>Total: $<span id="modal_total">0.00</span></strong>
                </div>
            </form>
          </div>
          <div class="modal-footer">
            <div id="modalAlert" class="me-auto text-start"></div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="button" class="btn btn-primary" id="saveOrderBtn">Guardar cambios</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap & Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Helper to open modal
    const orderModalEl = document.getElementById('orderModal');
    const orderModal = new bootstrap.Modal(orderModalEl);
    
    // Tab management
    document.querySelectorAll('#mainTabs .nav-link').forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Update active tab
            document.querySelectorAll('#mainTabs .nav-link').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // Show corresponding content
            const tabName = tab.getAttribute('data-tab');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('d-none'));
            document.getElementById(tabName + 'Tab').classList.remove('d-none');
            
            // Load data for the tab if needed
            if (tabName === 'products') {
                loadProducts();
            } else if (tabName === 'invoices') {
                loadInvoices();
            } else if (tabName === 'low-stock') {
                loadLowStock();
            }
        });
    });

    // Click ver detalles
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = btn.dataset.id;
            fetchOrder(id);
        });
    });

    // Mark as en proceso
    document.querySelectorAll('.btn-mark-trans').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            await updateOrderStatus(id, 'procesado');
        });
    });

    // Confirm delivery
    document.querySelectorAll('.btn-confirm-delivery').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            await updateOrderStatus(id, 'completado');
        });
    });

    async function updateOrderStatus(id, status) {
        try {
            const res = await fetch('proveedores.php?action=update_order', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, estado: status})
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            console.error(err);
            alert('Error al actualizar estado');
        }
    }

    // Fetch order details and open modal
    async function fetchOrder(id) {
        try {
            const res = await fetch('proveedores.php?action=get_order&id=' + encodeURIComponent(id));
            const data = await res.json();
            if (!data.success) {
                alert(data.message || 'Error al obtener pedido');
                return;
            }
            const order = data.order;
            const items = data.items;

            document.getElementById('order_id').value = order.id;
            document.getElementById('order_estado').value = order.estado || 'pendiente';
            
            // Mostrar información del cliente
            const clienteInfo = `
                ${order.cliente_nombre || 'No especificado'} | 
                ${order.cliente_correo || 'No especificado'} | 
                ${order.cliente_telefono || 'No especificado'}
            `;
            document.getElementById('cliente_info').textContent = clienteInfo;

            // fill items table
            const tbody = document.querySelector('#itemsTable tbody');
            tbody.innerHTML = '';
            let total = 0;
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay items en este pedido</td></tr>';
            } else {
                items.forEach(it => {
                    const subtotal = parseFloat(it.subtotal) || (parseFloat(it.cantidad) * parseFloat(it.precio_unitario));
                    total += subtotal;
                    const tr = document.createElement('tr');

                    tr.innerHTML = `
                        <td>${it.producto_nombre || ('ID ' + it.producto_id)}</td>
                        <td>${it.cantidad}</td>
                        <td>$${parseFloat(it.precio_unitario).toFixed(2)}</td>
                        <td>$${subtotal.toFixed(2)}</td>
                    `;

                    tbody.appendChild(tr);
                });
            }
            
            document.getElementById('modal_total').textContent = total.toFixed(2);

            // show modal
            orderModal.show();
        } catch (err) {
            console.error(err);
            alert('Error al cargar pedido');
        }
    }

    // Guardar cambios
    document.getElementById('saveOrderBtn').addEventListener('click', async () => {
        const id = document.getElementById('order_id').value;
        const estado = document.getElementById('order_estado').value;

        // send to server
        try {
            document.getElementById('modalAlert').innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Guardando...';
            const res = await fetch('proveedores.php?action=update_order', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id, estado: estado })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('modalAlert').innerHTML = '<div class="text-success">Guardado correctamente. Recargando...</div>';
                setTimeout(() => location.reload(), 900);
            } else {
                document.getElementById('modalAlert').innerHTML = '<div class="text-danger">Error: ' + (data.message || '') + '</div>';
            }
        } catch (err) {
            console.error(err);
            document.getElementById('modalAlert').innerHTML = '<div class="text-danger">Error al guardar</div>';
        }
    });

    // Load products for shipping
    async function loadProducts() {
        try {
            const res = await fetch('proveedores.php?action=get_products');
            const data = await res.json();
            
            if (!data.success) {
                console.error('Error loading products:', data.message);
                return;
            }
            
            const tbody = document.querySelector('#productsTable tbody');
            tbody.innerHTML = '';
            
            if (data.products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay productos disponibles</td></tr>';
                return;
            }
            
            data.products.forEach(product => {
                const tr = document.createElement('tr');
                
                tr.innerHTML = `
                    <td>${product.id}</td>
                    <td>${product.nombre}</td>
                    <td>${product.categoria_nombre || 'N/A'}</td>
                    <td>${product.cantidad}</td>
                    <td>$${parseFloat(product.precio_final || product.precio).toFixed(2)}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="shipProduct(${product.id})">
                            <i class="bi bi-send me-1"></i> Enviar
                        </button>
                    </td>
                `;
                
                tbody.appendChild(tr);
            });
        } catch (err) {
            console.error('Error loading products:', err);
        }
    }

    // Load invoices
    async function loadInvoices() {
        try {
            const res = await fetch('proveedores.php?action=get_invoices');
            const data = await res.json();
            
            if (!data.success) {
                console.error('Error loading invoices:', data.message);
                return;
            }
            
            const tbody = document.querySelector('#invoicesTable tbody');
            tbody.innerHTML = '';
            
            if (data.invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay facturas disponibles</td></tr>';
                return;
            }
            
            data.invoices.forEach(invoice => {
                const tr = document.createElement('tr');
                
                tr.innerHTML = `
                    <td>${invoice.id}</td>
                    <td>${invoice.fecha_venta}</td>
                    <td>${invoice.cliente_nombre || 'No especificado'}</td>
                    <td>${invoice.sucursal_nombre || 'No especificado'}</td>
                    <td>$${parseFloat(invoice.total).toFixed(2)}</td>
                    <td><span class="badge bg-success">COMPLETADA</span></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download me-1"></i> Descargar
                        </button>
                    </td>
                `;
                
                tbody.appendChild(tr);
            });
        } catch (err) {
            console.error('Error loading invoices:', err);
        }
    }

    // Load low stock - VERSIÓN MEJORADA CON DEBUG
    async function loadLowStock() {
        try {
            console.log('Cargando stock bajo...');
            const res = await fetch('proveedores.php?action=get_low_stock');
            const data = await res.json();
            
            console.log('Respuesta del servidor:', data);
            
            if (!data.success) {
                console.error('Error loading low stock:', data.message);
                document.querySelector('#lowStockTable tbody').innerHTML = 
                    '<tr><td colspan="6" class="text-center text-danger">Error al cargar el stock bajo: ' + data.message + '</td></tr>';
                return;
            }
            
            const tbody = document.querySelector('#lowStockTable tbody');
            tbody.innerHTML = '';
            
            // Debug info
            console.log('Productos con stock bajo:', data.lowStock);
            console.log('Total productos:', data.lowStock.length);
            console.log('Debug info:', data.debug);
            
            if (data.lowStock.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            No hay productos con stock bajo
                            <br>
                            <small class="text-muted">Todos los productos tienen stock suficiente (>10 unidades)</small>
                        </td>
                    </tr>
                `;
                return;
            }
            
            data.lowStock.forEach(product => {
                const tr = document.createElement('tr');
                
                // Determinar clase y badge según el nivel de stock
                let stockClass = '';
                let badge = '';
                let badgeClass = '';
                
                if (product.nivel_stock === 'crítico') {
                    stockClass = 'low-stock';
                    badge = 'CRÍTICO';
                    badgeClass = 'bg-danger';
                } else if (product.nivel_stock === 'bajo') {
                    stockClass = 'text-warning';
                    badge = 'BAJO';
                    badgeClass = 'bg-warning';
                } else {
                    stockClass = 'text-info';
                    badge = 'NORMAL';
                    badgeClass = 'bg-info';
                }
                
                tr.innerHTML = `
                    <td>${product.id}</td>
                    <td>
                        <strong>${product.nombre}</strong>
                        ${product.nivel_stock === 'crítico' ? '<i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>' : ''}
                    </td>
                    <td>${product.categoria_nombre}</td>
                    <td class="${stockClass} fw-bold fs-6">
                        ${product.cantidad} unidades
                    </td>
                    <td>$${parseFloat(product.precio_final || product.precio).toFixed(2)}</td>
                    <td>
                        <span class="badge ${badgeClass}">${badge}</span>
                    </td>
                `;
                
                tbody.appendChild(tr);
            });
            
            // Actualizar el contador en la tarjeta
            document.querySelector('.summary-card.alert h3').textContent = data.lowStock.length;
            
        } catch (err) {
            console.error('Error loading low stock:', err);
            document.querySelector('#lowStockTable tbody').innerHTML = 
                '<tr><td colspan="6" class="text-center text-danger">Error de conexión al cargar los datos</td></tr>';
        }
    }

    // Ship product function
    function shipProduct(productId) {
        alert(`Función de envío para producto ID: ${productId}. Esta función se implementaría según los requisitos específicos.`);
        // Aquí se implementaría la lógica para enviar productos
    }

    // Cargar datos iniciales si es necesario
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si estamos en la pestaña de stock bajo por defecto
        if (document.querySelector('#mainTabs .nav-link.active').getAttribute('data-tab') === 'low-stock') {
            loadLowStock();
        }
    });
    </script>
</body>
</html>