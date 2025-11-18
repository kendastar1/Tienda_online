<?php
session_start();

// Conexión a la base de datos
$host = 'localhost';
$dbname = 'tienda_ropa';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// OBTENER DATOS REALES DEL USUARIO DESDE LA BASE DE DATOS
try {
    if (isset($_SESSION['usuario_id'])) {
        $usuario_id = $_SESSION['usuario_id'];
        
        $sqlUsuario = "SELECT u.id, u.nombre, u.correo, u.rol_id, r.nombre as rol_nombre 
                       FROM usuarios u 
                       LEFT JOIN roles r ON u.rol_id = r.id 
                       WHERE u.id = ?";
        $stmtUsuario = $pdo->prepare($sqlUsuario);
        $stmtUsuario->execute([$usuario_id]);
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['rol_id'] = $usuario['rol_id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol_nombre'];
        } else {
            header('Location: login.php');
            exit();
        }
    } else {
        header('Location: login.php');
        exit();
    }
    
} catch(PDOException $e) {
    header('Location: login.php');
    exit();
}

// Función para registrar actividades
function registrarActividad($pdo, $usuario_id, $accion, $descripcion, $tipo = 'sistema', $referencia_id = null) {
    try {
        $sql = "INSERT INTO actividades (usuario_id, accion, descripcion, tipo, referencia_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $accion, $descripcion, $tipo, $referencia_id]);
        return true;
    } catch(PDOException $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
        return false;
    }
}

// Función para procesar imágenes
function procesarImagen($archivo) {
    $directorio = 'uploads/';
    
    // Crear directorio si no existe
    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }
    
    // Validar tipo de archivo
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $tipoArchivo = $archivo['type'];
    
    if (!in_array($tipoArchivo, $tiposPermitidos)) {
        $_SESSION['error'] = "Solo se permiten archivos JPG, PNG, GIF y WEBP";
        return false;
    }
    
    // Validar tamaño (máximo 2MB)
    if ($archivo['size'] > 2 * 1024 * 1024) {
        $_SESSION['error'] = "La imagen no debe pesar más de 2MB";
        return false;
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaCompleta = $directorio . $nombreArchivo;
    
    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return $nombreArchivo;
    } else {
        $_SESSION['error'] = "Error al subir la imagen";
        return false;
    }
}

// Categorías predefinidas para productos
$categorias = [
    'camisas',
    'pantalones',
    'zapatos',
    'accesorios',
    'vestidos',
    'chaquetas',
    'ropa_interior',
    'deportiva',
    'trajes',
    'faldas'
];

// Procesar operaciones de inventario (crear, actualizar, eliminar productos)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inventario_action'])) {
    $action = $_POST['inventario_action'];
    
    try {
        if ($action == 'create_product') {
            // Crear nuevo producto
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            $precio = $_POST['precio'];
            $cantidad = $_POST['cantidad'];
            $categoria = $_POST['categoria'];
            $estado = $_POST['estado'];
            $imagen = '';
            
            // Validaciones
            if (empty($nombre) || empty($descripcion) || empty($precio) || empty($cantidad) || empty($categoria)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } elseif (!is_numeric($precio) || $precio <= 0) {
                $_SESSION['error'] = "El precio debe ser un número positivo";
            } elseif (!is_numeric($cantidad) || $cantidad < 0) {
                $_SESSION['error'] = "La cantidad debe ser un número positivo o cero";
            } else {
                // Procesar imagen si se subió
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $imagen = procesarImagen($_FILES['imagen']);
                    if (!$imagen) {
                        // El error ya está establecido en la función procesarImagen
                    }
                } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $_SESSION['error'] = "Error al subir la imagen: " . $_FILES['imagen']['error'];
                }
                
                if (!isset($_SESSION['error'])) {
                    // Insertar nuevo producto
                    $sql = "INSERT INTO productos_stock (nombre, descripcion, precio, cantidad, categoria, estado, imagen, fecha_creacion) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion, $precio, $cantidad, $categoria, $estado, $imagen]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Producto creado', "Nuevo producto agregado: $nombre", 'inventario');
                    
                    $_SESSION['success'] = "Producto agregado correctamente al inventario";
                }
            }
            
        } elseif ($action == 'update_product') {
            // Actualizar producto existente
            $product_id = $_POST['product_id'];
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            $precio = $_POST['precio'];
            $cantidad = $_POST['cantidad'];
            $categoria = $_POST['categoria'];
            $estado = $_POST['estado'];
            $current_imagen = $_POST['current_imagen'] ?? '';
            $imagen = $current_imagen;
            
            // Validaciones básicas
            if (empty($nombre) || empty($descripcion) || empty($precio) || empty($cantidad) || empty($categoria)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } elseif (!is_numeric($precio) || $precio <= 0) {
                $_SESSION['error'] = "El precio debe ser un número positivo";
            } elseif (!is_numeric($cantidad) || $cantidad < 0) {
                $_SESSION['error'] = "La cantidad debe ser un número positivo o cero";
            } else {
                // Procesar nueva imagen si se subió
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $nueva_imagen = procesarImagen($_FILES['imagen']);
                    if ($nueva_imagen) {
                        // Eliminar imagen anterior si existe
                        if (!empty($current_imagen)) {
                            $ruta_imagen_anterior = 'uploads/' . $current_imagen;
                            if (file_exists($ruta_imagen_anterior)) {
                                unlink($ruta_imagen_anterior);
                            }
                        }
                        $imagen = $nueva_imagen;
                    }
                } elseif ($_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $_SESSION['error'] = "Error al subir la imagen: " . $_FILES['imagen']['error'];
                }
                
                if (!isset($_SESSION['error'])) {
                    // Actualizar producto
                    $sql = "UPDATE productos_stock SET nombre = ?, descripcion = ?, precio = ?, cantidad = ?, categoria = ?, estado = ?, imagen = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion, $precio, $cantidad, $categoria, $estado, $imagen, $product_id]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Producto actualizado', "Producto actualizado: $nombre", 'inventario');
                    
                    $_SESSION['success'] = "Producto actualizado correctamente";
                }
            }
            
        } elseif ($action == 'delete_product') {
            // Eliminar producto
            $product_id = $_POST['product_id'];
            
            // Obtener información del producto antes de eliminarlo
            $sqlInfo = "SELECT nombre, imagen FROM productos_stock WHERE id = ?";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([$product_id]);
            $producto = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            if ($producto) {
                // Eliminar imagen si existe
                if (!empty($producto['imagen'])) {
                    $ruta_imagen = 'uploads/' . $producto['imagen'];
                    if (file_exists($ruta_imagen)) {
                        unlink($ruta_imagen);
                    }
                }
                
                $sql = "DELETE FROM productos_stock WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_id]);
                
                // Registrar actividad
                registrarActividad($pdo, $_SESSION['usuario_id'], 'Producto eliminado', "Producto eliminado: " . $producto['nombre'], 'inventario');
                
                $_SESSION['success'] = "Producto eliminado correctamente";
            } else {
                $_SESSION['error'] = "Producto no encontrado";
            }
        }
        
        // Redirigir para evitar reenvío del formulario
        header('Location: admin.php?seccion=inventario');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header('Location: admin.php?seccion=inventario');
        exit();
    }
}

// Obtener iniciales del nombre REAL del usuario
$nombreCompleto = $_SESSION['usuario_nombre'];
$partesNombre = explode(' ', $nombreCompleto);
$iniciales = '';
if (count($partesNombre) >= 2) {
    $iniciales = strtoupper(substr($partesNombre[0], 0, 1) . substr($partesNombre[count($partesNombre)-1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombreCompleto, 0, 2));
}

// Consultas para el dashboard (solo se ejecutan en la sección dashboard)
$totalVentasMes = 0;
$totalPedidos = 0;
$totalClientes = 0;
$totalStock = 0;
$productosDestacados = [];
$ventasSucursal = [];
$actividades = [];

// Determinar qué sección mostrar
$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'dashboard';

// Solo ejecutar consultas del dashboard si estamos en esa sección
if ($seccion == 'dashboard') {
    try {
        // Ventas del mes actual
        $sqlVentasMes = "SELECT COALESCE(SUM(total), 0) as total_ventas 
                         FROM ventas 
                         WHERE MONTH(fecha_venta) = MONTH(CURRENT_DATE()) 
                         AND YEAR(fecha_venta) = YEAR(CURRENT_DATE())";
        $stmtVentas = $pdo->query($sqlVentasMes);
        $ventasMes = $stmtVentas->fetch(PDO::FETCH_ASSOC);
        $totalVentasMes = $ventasMes['total_ventas'];
        
        // Total de pedidos
        $sqlPedidos = "SELECT COUNT(*) as total_pedidos FROM pedidos";
        $stmtPedidos = $pdo->query($sqlPedidos);
        $pedidos = $stmtPedidos->fetch(PDO::FETCH_ASSOC);
        $totalPedidos = $pedidos['total_pedidos'];
        
        // Clientes activos
        $sqlClientes = "SELECT COUNT(*) as total_clientes FROM clientes_activos WHERE estado = 'activo'";
        $stmtClientes = $pdo->query($sqlClientes);
        $clientes = $stmtClientes->fetch(PDO::FETCH_ASSOC);
        $totalClientes = $clientes['total_clientes'];
        
        // Productos en stock
        $sqlProductos = "SELECT SUM(cantidad) as total_stock FROM productos_stock WHERE estado = 'activo'";
        $stmtProductos = $pdo->query($sqlProductos);
        $productos = $stmtProductos->fetch(PDO::FETCH_ASSOC);
        $totalStock = $productos['total_stock'];
        
        // Productos destacados del mes (más vendidos)
        $sqlProductosDestacados = "
            SELECT 
                ps.id,
                ps.nombre,
                ps.descripcion,
                ps.precio,
                ps.cantidad as stock,
                COALESCE(SUM(dp.cantidad), 0) as total_vendido,
                COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) as ingresos_totales
            FROM productos_stock ps
            LEFT JOIN detalle_pedidos dp ON ps.id = dp.producto_id
            LEFT JOIN pedidos p ON dp.pedido_id = p.id
            LEFT JOIN ventas v ON p.id = v.pedido_id
            WHERE MONTH(v.fecha_venta) = MONTH(CURRENT_DATE()) 
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            GROUP BY ps.id, ps.nombre, ps.descripcion, ps.precio, ps.cantidad
            ORDER BY total_vendido DESC
            LIMIT 5
        ";
        
        $stmtProductosDestacados = $pdo->query($sqlProductosDestacados);
        $productosDestacados = $stmtProductosDestacados->fetchAll(PDO::FETCH_ASSOC);
        
        // Ventas por sucursal
        $sqlVentasSucursal = "
            SELECT 
                s.nombre as sucursal,
                COALESCE(SUM(v.total), 0) as ventas_totales
            FROM sucursales s
            LEFT JOIN ventas v ON s.id = v.sucursal_id
            WHERE MONTH(v.fecha_venta) = MONTH(CURRENT_DATE()) 
            AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
            GROUP BY s.id, s.nombre
            ORDER BY ventas_totales DESC
        ";
        
        $stmtVentasSucursal = $pdo->query($sqlVentasSucursal);
        $ventasSucursal = $stmtVentasSucursal->fetchAll(PDO::FETCH_ASSOC);
        
        // Actividades recientes - Solo las 6 más recientes para la vista
        $sqlActividades = "
            SELECT 
                a.id,
                a.accion,
                a.descripcion,
                a.fecha_registro,
                u.nombre as usuario_nombre,
                r.nombre as rol_nombre,
                a.tipo,
                a.referencia_id
            FROM actividades a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY a.fecha_registro DESC
            LIMIT 6
        ";
        
        $stmtActividades = $pdo->query($sqlActividades);
        $actividades = $stmtActividades->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        // En caso de error, establecer valores por defecto
        $totalVentasMes = 0;
        $totalPedidos = 0;
        $totalClientes = 0;
        $totalStock = 0;
        $productosDestacados = [];
        $ventasSucursal = [];
        $actividades = [];
    }
}

// Calcular porcentajes (por ahora en 0 ya que no hay datos históricos)
$porcentajeVentas = 0;
$porcentajePedidos = 0;
$porcentajeClientes = 0;
$porcentajeStock = 0;

// Función para formatear el tiempo relativo
function tiempoRelativo($fecha) {
    $ahora = new DateTime();
    $fechaObj = new DateTime($fecha);
    $diferencia = $ahora->diff($fechaObj);
    
    if ($diferencia->y > 0) return "hace " . $diferencia->y . " año" . ($diferencia->y > 1 ? 's' : '');
    if ($diferencia->m > 0) return "hace " . $diferencia->m . " mes" . ($diferencia->m > 1 ? 'es' : '');
    if ($diferencia->d > 0) return "hace " . $diferencia->d . " día" . ($diferencia->d > 1 ? 's' : '');
    if ($diferencia->h > 0) return "hace " . $diferencia->h . " hora" . ($diferencia->h > 1 ? 's' : '');
    if ($diferencia->i > 0) return "hace " . $diferencia->i . " minuto" . ($diferencia->i > 1 ? 's' : '');
    return "hace unos segundos";
}

// Obtener el título y descripción de la sección actual
function obtenerInfoSeccion($seccion) {
    $secciones = [
        'dashboard' => [
            'titulo' => 'Panel Principal',
            'descripcion' => 'Vista general del sistema y métricas principales',
            'icono' => 'dashboard'
        ],
        'usuarios' => [
            'titulo' => 'Gestión de Usuarios',
            'descripcion' => 'Administrar usuarios, roles y permisos del sistema',
            'icono' => 'group'
        ],
        'reportes' => [
            'titulo' => 'Reportes Financieros',
            'descripcion' => 'Reportes detallados de ventas, ingresos y finanzas',
            'icono' => 'analytics'
        ],
        'inventario' => [
            'titulo' => 'Gestión de Inventario',
            'descripcion' => 'Control de stock, productos y categorías',
            'icono' => 'inventory_2'
        ],
        'promociones' => [
            'titulo' => 'Promociones y Descuentos',
            'descripcion' => 'Configurar promociones, cupones y ofertas especiales',
            'icono' => 'local_offer'
        ],
        'configuracion' => [
            'titulo' => 'Configuración Global',
            'descripcion' => 'Configuración general del sistema y parámetros',
            'icono' => 'settings'
        ],
        'auditoria' => [
            'titulo' => 'Auditoría del Sistema',
            'descripcion' => 'Registros de actividades y logs del sistema',
            'icono' => 'security'
        ]
    ];
    
    return $secciones[$seccion] ?? $secciones['dashboard'];
}

$infoSeccion = obtenerInfoSeccion($seccion);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $infoSeccion['titulo']; ?> - Panel Administrativo</title>
    <!-- Linking Google Fonts for Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="css/stilo.css" />
    <link rel="stylesheet" href="css/stilo3.css" />
    <link rel="stylesheet" href="css/menu-styles.css"/>
    <link rel="stylesheet" href="css/stilo4.css" />
    <link rel="stylesheet" href="css/stilo_inventario.css" />

  </head>
  <body>
    <!-- Nuevo Header Superior -->
    <header class="top-header">
      <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
          <span class="material-symbols-rounded">menu</span>
        </button>
        <div class="logo">
          <span class="material-symbols-rounded">dashboard</span>
          <span class="logo-text">Panel Admin</span>
        </div>
      </div>
      <!-- Barra de búsqueda deshabilitada
      <div class="header-center">
        <div class="search-box">
          <span class="material-symbols-rounded">search</span>
          <input type="text" placeholder="Buscar en el sistema...">
        </div>
      </div>
      -->
      
      <div class="header-right">
        <div class="header-actions">
          <button class="header-btn">
            <span class="material-symbols-rounded">notifications</span>
            <span class="notification-badge">3</span>
          </button>
          <button class="header-btn">
            <span class="material-symbols-rounded">mail</span>
          </button>
          <button class="header-btn">
            <span class="material-symbols-rounded">settings</span>
          </button>
        </div>
        
        <div class="user-profile">
          <div class="user-avatar"><?php echo $iniciales; ?></div>
          <div class="user-info">
            <div class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></div>
            <div class="user-role"><?php echo $_SESSION['rol']; ?></div>
          </div>
          <button class="profile-dropdown">
            <span class="material-symbols-rounded">expand_more</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Nuevo Sidebar Moderno -->
    <aside class="modern-sidebar collapsed" id="sidebar">
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-title">Principal</div>
          <ul class="nav-menu">
            <li class="nav-item <?php echo $seccion == 'dashboard' ? 'active' : ''; ?>">
              <a href="?seccion=dashboard" class="nav-link">
                <span class="nav-icon material-symbols-rounded">dashboard</span>
                <span class="nav-text">Panel Principal</span>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="nav-section">
          <div class="nav-title">Gestión</div>
          <ul class="nav-menu">
            <li class="nav-item <?php echo $seccion == 'usuarios' ? 'active' : ''; ?>">
              <a href="?seccion=usuarios" class="nav-link">
                <span class="nav-icon material-symbols-rounded">group</span>
                <span class="nav-text">Gestión de Usuarios</span>
              </a>
            </li>
            <li class="nav-item <?php echo $seccion == 'inventario' ? 'active' : ''; ?>">
              <a href="?seccion=inventario" class="nav-link">
                <span class="nav-icon material-symbols-rounded">inventory_2</span>
                <span class="nav-text">Inventario</span>
              </a>
            </li>
            <li class="nav-item <?php echo $seccion == 'promociones' ? 'active' : ''; ?>">
              <a href="?seccion=promociones" class="nav-link">
                <span class="nav-icon material-symbols-rounded">local_offer</span>
                <span class="nav-text">Promociones</span>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="nav-section">
          <div class="nav-title">Reportes</div>
          <ul class="nav-menu">
            <li class="nav-item <?php echo $seccion == 'reportes' ? 'active' : ''; ?>">
              <a href="?seccion=reportes" class="nav-link">
                <span class="nav-icon material-symbols-rounded">analytics</span>
                <span class="nav-text">Reportes Financieros</span>
              </a>
            </li>
            <li class="nav-item <?php echo $seccion == 'auditoria' ? 'active' : ''; ?>">
              <a href="?seccion=auditoria" class="nav-link">
                <span class="nav-icon material-symbols-rounded">security</span>
                <span class="nav-text">Auditoría</span>
              </a>
            </li>
          </ul>
        </div>
        
        <div class="nav-section">
          <div class="nav-title">Sistema</div>
          <ul class="nav-menu">
            <li class="nav-item <?php echo $seccion == 'configuracion' ? 'active' : ''; ?>">
              <a href="?seccion=configuracion" class="nav-link">
                <span class="nav-icon material-symbols-rounded">settings</span>
                <span class="nav-text">Configuración</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <span class="nav-icon material-symbols-rounded">help</span>
                <span class="nav-text">Ayuda</span>
              </a>
            </li>
            <li class="nav-item">
              <a href="logout.php" class="nav-link">
                <span class="nav-icon material-symbols-rounded">logout</span>
                <span class="nav-text">Cerrar Sesión</span>
              </a>
            </li>
          </ul>
        </div>
      </nav>
    </aside>
    
    <!-- Contenido Principal -->
    <main class="main-content">
      <!-- Encabezado dinámico según la sección -->
      <header class="page-header">
        <div class="header-content">
          <div>
            <h1 class="header-title"><?php echo $infoSeccion['titulo']; ?></h1>
            <div class="header-subtitle"><?php echo $infoSeccion['descripcion']; ?></div>
            <div class="header-badge">
              <span class="material-symbols-rounded">verified</span>
              Sesión activa como <?php echo $_SESSION['rol']; ?>
            </div>
          </div>
          <div class="header-icon">
            <span class="material-symbols-rounded"><?php echo $infoSeccion['icono']; ?></span>
          </div>
        </div>
      </header>
      
      <?php if ($seccion == 'dashboard'): ?>
        <!-- CONTENIDO DEL DASHBOARD (el código original del dashboard) -->
        <div class="dashboard-cards">
          <!-- Tarjeta de Ventas del Mes -->
          <div class="card sales">
            <div class="card-header">
              <div>
                <div class="card-title">Ventas del Mes</div>
                <div class="card-value">$<?php echo number_format($totalVentasMes, 2); ?></div>
              </div>
              <div class="card-icon">
                <span class="material-symbols-rounded">trending_up</span>
              </div>
            </div>
            <div class="card-change <?php echo $porcentajeVentas == 0 ? 'neutral' : ($porcentajeVentas > 0 ? '' : 'negative'); ?>">
              <span class="material-symbols-rounded"><?php echo $porcentajeVentas == 0 ? 'remove' : ($porcentajeVentas > 0 ? 'arrow_upward' : 'arrow_downward'); ?></span>
              <?php echo $porcentajeVentas == 0 ? '0%' : abs($porcentajeVentas) . '%'; ?> desde el mes pasado
            </div>
          </div>
          
          <!-- Tarjeta de Pedidos Totales -->
          <div class="card orders">
            <div class="card-header">
              <div>
                <div class="card-title">Pedidos Totales</div>
                <div class="card-value"><?php echo $totalPedidos; ?></div>
              </div>
              <div class="card-icon">
                <span class="material-symbols-rounded">shopping_cart</span>
              </div>
            </div>
            <div class="card-change <?php echo $porcentajePedidos == 0 ? 'neutral' : ($porcentajePedidos > 0 ? '' : 'negative'); ?>">
              <span class="material-symbols-rounded"><?php echo $porcentajePedidos == 0 ? 'remove' : ($porcentajePedidos > 0 ? 'arrow_upward' : 'arrow_downward'); ?></span>
              <?php echo $porcentajePedidos == 0 ? '0%' : abs($porcentajePedidos) . '%'; ?> desde la semana pasada
            </div>
          </div>
          
          <!-- Tarjeta de Clientes Activos -->
          <div class="card clients">
            <div class="card-header">
              <div>
                <div class="card-title">Clientes Activos</div>
                <div class="card-value"><?php echo $totalClientes; ?></div>
              </div>
              <div class="card-icon">
                <span class="material-symbols-rounded">group</span>
              </div>
            </div>
            <div class="card-change <?php echo $porcentajeClientes == 0 ? 'neutral' : ($porcentajeClientes > 0 ? '' : 'negative'); ?>">
              <span class="material-symbols-rounded"><?php echo $porcentajeClientes == 0 ? 'remove' : ($porcentajeClientes > 0 ? 'arrow_upward' : 'arrow_downward'); ?></span>
              <?php echo $porcentajeClientes == 0 ? '0%' : abs($porcentajeClientes) . '%'; ?> desde el mes pasado
            </div>
          </div>
          
          <!-- Tarjeta de Productos en Stock -->
          <div class="card stock">
            <div class="card-header">
              <div>
                <div class="card-title">Productos en Stock</div>
                <div class="card-value"><?php echo $totalStock; ?></div>
              </div>
              <div class="card-icon">
                <span class="material-symbols-rounded">inventory</span>
              </div>
            </div>
            <div class="card-change <?php echo $porcentajeStock == 0 ? 'neutral' : ($porcentajeStock > 0 ? '' : 'negative'); ?>">
              <span class="material-symbols-rounded"><?php echo $porcentajeStock == 0 ? 'remove' : ($porcentajeStock > 0 ? 'arrow_upward' : 'arrow_downward'); ?></span>
              <?php echo $porcentajeStock == 0 ? '0%' : abs($porcentajeStock) . '%'; ?> desde la semana pasada
            </div>
          </div>
        </div>
        
        <!-- Sección de Productos Destacados -->
        <section class="featured-products">
          <h2 class="section-title">
            <span class="material-symbols-rounded">star</span>
            Productos Destacados del Mes
          </h2>
          
          <?php if (!empty($productosDestacados)): ?>
            <div class="products-grid">
              <?php foreach ($productosDestacados as $producto): ?>
                <div class="product-card">
                  <div class="product-header">
                    <div>
                      <div class="product-name"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                      <div class="product-description"><?php echo htmlspecialchars($producto['descripcion']); ?></div>
                    </div>
                    <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                  </div>
                  
                  <div class="product-stats">
                    <div class="stat-item">
                      <span class="stat-label">Ventas</span>
                      <span class="stat-value sales-stat"><?php echo $producto['total_vendido']; ?> unidades</span>
                    </div>
                    <div class="stat-item">
                      <span class="stat-label">Stock</span>
                      <span class="stat-value stock-stat"><?php echo $producto['stock']; ?> disponibles</span>
                    </div>
                    <div class="stat-item">
                      <span class="stat-label">Ingresos</span>
                      <span class="stat-value revenue-stat">$<?php echo number_format($producto['ingresos_totales'], 2); ?></span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="no-data">
              <span class="material-symbols-rounded">inventory_2</span>
              <p>No hay datos de productos destacados disponibles</p>
            </div>
          <?php endif; ?>
        </section>
        
        <!-- Layout para ventas por sucursal y actividades recientes -->
        <div class="dashboard-layout">
          <!-- Sección de Ventas por Sucursal -->
          <section class="branch-sales">
            <h2 class="section-title">
              <span class="material-symbols-rounded">store</span>
              Ventas por Sucursal
            </h2>
            
            <?php if (!empty($ventasSucursal)): ?>
              <div class="branch-cards">
                <?php foreach ($ventasSucursal as $sucursal): ?>
                  <div class="branch-card">
                    <div class="branch-header">
                      <div>
                        <div class="branch-name"><?php echo htmlspecialchars($sucursal['sucursal']); ?></div>
                        <div class="branch-location"><?php echo str_replace('Sucursal ', '', $sucursal['sucursal']); ?></div>
                      </div>
                      <div class="branch-icon">
                        <span class="material-symbols-rounded">storefront</span>
                      </div>
                    </div>
                    <div class="branch-sales-amount">
                      $<?php echo number_format($sucursal['ventas_totales'], 2); ?>
                    </div>
                    <div class="branch-sales-change">
                      <span class="material-symbols-rounded">trending_up</span>
                      Ventas del mes actual
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="no-data">
                <span class="material-symbols-rounded">store</span>
                <p>No hay datos de ventas por sucursal disponibles</p>
              </div>
            <?php endif; ?>
          </section>
          
          <!-- Sección de Actividades Recientes -->
          <section class="recent-activities">
            <div class="activities-header">
              <h2 class="section-title">
                <span class="material-symbols-rounded">history</span>
                Actividades Recientes
              </h2>
            </div>
            
            <?php if (!empty($actividades)): ?>
              <div class="activities-container">
                <div class="activities-list">
                  <?php foreach ($actividades as $actividad): ?>
                    <div class="activity-item">
                      <div class="activity-icon <?php echo $actividad['tipo']; ?>">
                        <?php 
                        $iconos = [
                          'venta' => 'payments',
                          'inventario' => 'inventory_2',
                          'cliente' => 'person_add',
                          'devolucion' => 'assignment_return',
                          'pedido' => 'local_shipping',
                          'alerta' => 'warning',
                          'sistema' => 'settings'
                        ];
                        $icono = $iconos[$actividad['tipo']] ?? 'notifications';
                        ?>
                        <span class="material-symbols-rounded"><?php echo $icono; ?></span>
                      </div>
                      <div class="activity-content">
                        <div class="activity-action"><?php echo htmlspecialchars($actividad['accion']); ?></div>
                        <div class="activity-description"><?php echo htmlspecialchars($actividad['descripcion']); ?></div>
                        <div class="activity-meta">
                          <div class="activity-user">
                            <span><?php echo htmlspecialchars($actividad['usuario_nombre']); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($actividad['rol_nombre']); ?></span>
                          </div>
                          <div class="activity-time">
                            <?php echo tiempoRelativo($actividad['fecha_registro']); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="no-data">
                <span class="material-symbols-rounded">history</span>
                <p>No hay actividades recientes</p>
              </div>
            <?php endif; ?>
          </section>
        </div>
      
      <?php elseif ($seccion == 'usuarios'): ?>
        <!-- SECCIÓN DE GESTIÓN DE USUARIOS -->
        <div class="section-content">
          <div class="usuarios-header">
            <h2>Gestión de Usuarios</h2>
            <div class="usuarios-actions">
              <!-- Botón de filtro por roles -->
              <div class="filter-dropdown">
                <button class="filter-btn" id="filterBtn">
                  <span class="material-symbols-rounded">filter_list</span>
                  Filtrar por Rol
                </button>
                <div class="filter-menu" id="filterMenu">
                  <div class="filter-option active" data-rol="todos">Todos los roles</div>
                  <?php
                  // Obtener todos los roles disponibles
                  try {
                    $sqlRoles = "SELECT id, nombre FROM roles ORDER BY nombre";
                    $stmtRoles = $pdo->query($sqlRoles);
                    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach($roles as $rol) {
                      echo '<div class="filter-option" data-rol="' . $rol['id'] . '">' . ucfirst($rol['nombre']) . '</div>';
                    }
                  } catch(PDOException $e) {
                    // En caso de error, usar roles por defecto
                    $roles = [
                      ['id' => 1, 'nombre' => 'administrador'],
                      ['id' => 2, 'nombre' => 'vendedor'],
                      ['id' => 3, 'nombre' => 'cajero'],
                      ['id' => 4, 'nombre' => 'almacen'],
                      ['id' => 5, 'nombre' => 'diseñador']
                    ];
                    
                    foreach($roles as $rol) {
                      echo '<div class="filter-option" data-rol="' . $rol['id'] . '">' . ucfirst($rol['nombre']) . '</div>';
                    }
                  }
                  ?>
                </div>
              </div>
              
              <!-- Barra de búsqueda -->
              <div class="search-container">
                <input type="text" id="searchUsers" placeholder="Buscar usuarios..." class="search-input">
                <span class="material-symbols-rounded">search</span>
              </div>
              
              <!-- Botón para registrar nuevo usuario -->
              <button class="btn-primary" id="addUserBtn">
                <span class="material-symbols-rounded">person_add</span>
                Registrar Usuario
              </button>
            </div>
          </div>
          
          <!-- Mensajes de éxito/error -->
          <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
              <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
          <?php endif; ?>
          
          <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
              <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
          <?php endif; ?>
          
          <!-- Tabla de usuarios -->
          <div class="table-container">
            <table class="users-table" id="usersTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Correo</th>
                  <th>Rol</th>
                  <th>Fecha de Registro</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php
                try {
                  // Consulta para obtener usuarios con información de roles
                  $sqlUsuarios = "SELECT u.id, u.nombre, u.correo, u.fecha_registro, r.nombre as rol_nombre, r.id as rol_id 
                               FROM usuarios u 
                               LEFT JOIN roles r ON u.rol_id = r.id 
                               ORDER BY u.fecha_registro DESC";
                  $stmtUsuarios = $pdo->query($sqlUsuarios);
                  $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
                  
                  if (count($usuarios) > 0) {
                    foreach($usuarios as $usuario) {
                      echo '<tr data-rol="' . $usuario['rol_id'] . '">';
                      echo '<td>' . $usuario['id'] . '</td>';
                      echo '<td>' . htmlspecialchars($usuario['nombre']) . '</td>';
                      echo '<td>' . htmlspecialchars($usuario['correo']) . '</td>';
                      echo '<td><span class="role-badge role-' . $usuario['rol_id'] . '">' . ucfirst($usuario['rol_nombre']) . '</span></td>';
                      echo '<td>' . date('d/m/Y H:i', strtotime($usuario['fecha_registro'])) . '</td>';
                      echo '<td class="actions">';
                      echo '<button class="btn-icon edit-user" data-id="' . $usuario['id'] . '" data-nombre="' . htmlspecialchars($usuario['nombre']) . '" data-correo="' . htmlspecialchars($usuario['correo']) . '" data-rol="' . $usuario['rol_id'] . '" title="Editar">';
                      echo '<span class="material-symbols-rounded">edit</span>';
                      echo '</button>';
                      echo '<button class="btn-icon delete-user" data-id="' . $usuario['id'] . '" data-nombre="' . htmlspecialchars($usuario['nombre']) . '" title="Eliminar">';
                      echo '<span class="material-symbols-rounded">delete</span>';
                      echo '</button>';
                      echo '</td>';
                      echo '</tr>';
                    }
                  } else {
                    echo '<tr><td colspan="6" class="no-data">No hay usuarios registrados</td></tr>';
                  }
                } catch(PDOException $e) {
                  echo '<tr><td colspan="6" class="no-data">Error al cargar los usuarios: ' . $e->getMessage() . '</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal para registrar/editar usuario -->
        <div class="modal" id="userModal">
          <div class="modal-content">
            <div class="modal-header">
              <h3 id="modalTitle">Registrar Nuevo Usuario</h3>
              <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <form class="user-form" id="userForm" method="POST">
              <input type="hidden" id="user_id" name="user_id">
              <input type="hidden" id="form_action" name="action" value="create_user">
              
              <div class="form-group">
                <label for="nombre">Nombre completo *</label>
                <input type="text" id="nombre" name="nombre" required>
              </div>
              
              <div class="form-group">
                <label for="correo">Correo electrónico *</label>
                <input type="email" id="correo" name="correo" required>
              </div>
              
              <div class="form-group">
                <label for="rol_id">Rol *</label>
                <select id="rol_id" name="rol_id" required>
                  <option value="">Seleccionar rol</option>
                  <?php
                  foreach($roles as $rol) {
                    echo '<option value="' . $rol['id'] . '">' . ucfirst($rol['nombre']) . '</option>';
                  }
                  ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="password">Contraseña *</label>
                <input type="password" id="password" name="password" required>
                <small>La contraseña debe tener al menos 6 caracteres</small>
              </div>
              
              <div class="form-group">
                <label for="confirmPassword">Confirmar contraseña *</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required>
              </div>
              
              <div class="form-actions">
                <button type="button" class="btn-secondary" id="cancelBtn">Cancelar</button>
                <button type="submit" class="btn-primary" id="saveUserBtn">Guardar Usuario</button>
              </div>
            </form>
          </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Elementos del DOM
          const filterBtn = document.getElementById('filterBtn');
          const filterMenu = document.getElementById('filterMenu');
          const searchInput = document.getElementById('searchUsers');
          const addUserBtn = document.getElementById('addUserBtn');
          const userModal = document.getElementById('userModal');
          const closeModal = document.getElementById('closeModal');
          const cancelBtn = document.getElementById('cancelBtn');
          const userForm = document.getElementById('userForm');
          const usersTable = document.getElementById('usersTable');
          const modalTitle = document.getElementById('modalTitle');
          const userIdInput = document.getElementById('user_id');
          const formActionInput = document.getElementById('form_action');
          const passwordField = document.getElementById('password');
          const confirmPasswordField = document.getElementById('confirmPassword');
          
          // Filtro por roles
          filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterMenu.classList.toggle('show');
          });
          
          // Cerrar menú de filtro al hacer clic fuera
          document.addEventListener('click', function() {
            filterMenu.classList.remove('show');
          });
          
          // Prevenir que el menú se cierre al hacer clic dentro
          filterMenu.addEventListener('click', function(e) {
            e.stopPropagation();
          });
          
          // Aplicar filtro por rol
          const filterOptions = document.querySelectorAll('.filter-option');
          filterOptions.forEach(option => {
            option.addEventListener('click', function() {
              const rolId = this.getAttribute('data-rol');
              
              // Actualizar estado activo
              filterOptions.forEach(opt => opt.classList.remove('active'));
              this.classList.add('active');
              
              // Filtrar tabla
              const rows = usersTable.querySelectorAll('tbody tr');
              rows.forEach(row => {
                if (rolId === 'todos') {
                  row.style.display = '';
                } else {
                  const rowRol = row.getAttribute('data-rol');
                  row.style.display = rowRol === rolId ? '' : 'none';
                }
              });
              
              filterMenu.classList.remove('show');
            });
          });
          
          // Búsqueda de usuarios
          searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = usersTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
              const nombre = row.cells[1].textContent.toLowerCase();
              const correo = row.cells[2].textContent.toLowerCase();
              const rol = row.cells[3].textContent.toLowerCase();
              
              if (nombre.includes(searchTerm) || correo.includes(searchTerm) || rol.includes(searchTerm)) {
                row.style.display = '';
              } else {
                row.style.display = 'none';
              }
            });
          });
          
          // Abrir modal para registrar usuario
          addUserBtn.addEventListener('click', function() {
            modalTitle.textContent = 'Registrar Nuevo Usuario';
            userForm.reset();
            userIdInput.value = '';
            formActionInput.value = 'create_user';
            passwordField.required = true;
            confirmPasswordField.required = true;
            userModal.classList.add('show');
          });
          
          // Cerrar modal
          closeModal.addEventListener('click', function() {
            userModal.classList.remove('show');
          });
          
          cancelBtn.addEventListener('click', function() {
            userModal.classList.remove('show');
          });
          
          // Editar usuario
          document.querySelectorAll('.edit-user').forEach(btn => {
            btn.addEventListener('click', function() {
              const userId = this.getAttribute('data-id');
              const userName = this.getAttribute('data-nombre');
              const userEmail = this.getAttribute('data-correo');
              const userRol = this.getAttribute('data-rol');
              
              // Llenar el formulario con los datos del usuario
              modalTitle.textContent = 'Editar Usuario';
              userIdInput.value = userId;
              document.getElementById('nombre').value = userName;
              document.getElementById('correo').value = userEmail;
              document.getElementById('rol_id').value = userRol;
              passwordField.required = false;
              confirmPasswordField.required = false;
              formActionInput.value = 'update_user';
              
              userModal.classList.add('show');
            });
          });
          
          // Eliminar usuario
          document.querySelectorAll('.delete-user').forEach(btn => {
            btn.addEventListener('click', function() {
              const userId = this.getAttribute('data-id');
              const userName = this.getAttribute('data-nombre');
              
              if (confirm('¿Está seguro de que desea eliminar al usuario "' + userName + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
              }
            });
          });
          
          // Validación del formulario antes de enviar
          userForm.addEventListener('submit', function(e) {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            const action = formActionInput.value;
            
            // Para crear usuario, la contraseña es obligatoria
            if (action === 'create_user' && password.length < 6) {
              e.preventDefault();
              alert('La contraseña debe tener al menos 6 caracteres');
              return;
            }
            
            // Si se está editando y se ingresó una contraseña, validar
            if (action === 'update_user' && password !== '') {
              if (password.length < 6) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 6 caracteres');
                return;
              }
            }
            
            // Validar que las contraseñas coincidan si se están cambiando
            if ((action === 'create_user' || (action === 'update_user' && password !== '')) && password !== confirmPassword) {
              e.preventDefault();
              alert('Las contraseñas no coinciden');
              return;
            }
          });
        });
        </script>
      
      <?php elseif ($seccion == 'inventario'): ?>
        <!-- SECCIÓN DE INVENTARIO CON IMÁGENES -->
<div class="section-content">
  <div class="inventario-header">
    <h2 style="margin: 0; color: #1e293b;">Gestión de Inventario</h2>
    <div class="inventario-actions">
      <!-- Barra de búsqueda -->
      <div class="search-container">
        <input type="text" id="searchProducts" placeholder="Buscar productos..." class="search-input">
        <span class="material-symbols-rounded">search</span>
      </div>
      
      <!-- Botón para agregar nuevo producto -->
      <button class="btn-primary" id="addProductBtn">
        <span class="material-symbols-rounded">add</span>
        Agregar Producto
      </button>
    </div>
  </div>
  
  <!-- Mensajes de éxito/error -->
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
      <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
  <?php endif; ?>
  
  <!-- Tabla de productos -->
  <div class="table-container">
    <table class="products-table" id="productsTable">
      <thead>
        <tr>
          <th>Imagen</th>
          <th>ID</th>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Precio</th>
          <th>Cantidad</th>
          <th>Categoría</th>
          <th>Estado</th>
          <th>Fecha Creación</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php
        try {
          // Consulta para obtener productos del inventario
          $sqlProductos = "SELECT id, nombre, descripcion, precio, cantidad, categoria, estado, fecha_creacion, imagen 
                       FROM productos_stock 
                       ORDER BY fecha_creacion DESC";
          $stmtProductos = $pdo->query($sqlProductos);
          $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
          
          if (count($productos) > 0) {
            foreach($productos as $producto) {
              echo '<tr>';
              // Columna de imagen
              echo '<td>';
              if (!empty($producto['imagen'])) {
                echo '<div class="product-image">';
                echo '<img src="uploads/' . htmlspecialchars($producto['imagen']) . '" alt="' . htmlspecialchars($producto['nombre']) . '" class="product-thumbnail">';
                echo '</div>';
              } else {
                echo '<div class="product-image no-image">';
                echo '<span class="material-symbols-rounded">image</span>';
                echo '</div>';
              }
              echo '</td>';
              
              echo '<td>' . $producto['id'] . '</td>';
              echo '<td><strong>' . htmlspecialchars($producto['nombre']) . '</strong></td>';
              echo '<td>' . htmlspecialchars($producto['descripcion']) . '</td>';
              echo '<td><strong>$' . number_format($producto['precio'], 2) . '</strong></td>';
              echo '<td>' . $producto['cantidad'] . '</td>';
              echo '<td><span class="categoria-badge">' . ucfirst(htmlspecialchars($producto['categoria'])) . '</span></td>';
              echo '<td><span class="status-badge status-' . $producto['estado'] . '">' . ucfirst($producto['estado']) . '</span></td>';
              echo '<td>' . date('d/m/Y H:i', strtotime($producto['fecha_creacion'])) . '</td>';
              echo '<td class="actions">';
              echo '<button class="btn-icon edit-product" data-id="' . $producto['id'] . '" data-nombre="' . htmlspecialchars($producto['nombre']) . '" data-descripcion="' . htmlspecialchars($producto['descripcion']) . '" data-precio="' . $producto['precio'] . '" data-cantidad="' . $producto['cantidad'] . '" data-categoria="' . htmlspecialchars($producto['categoria']) . '" data-estado="' . $producto['estado'] . '" data-imagen="' . htmlspecialchars($producto['imagen']) . '" title="Editar">';
              echo '<span class="material-symbols-rounded">edit</span>';
              echo '</button>';
              echo '<button class="btn-icon delete-product" data-id="' . $producto['id'] . '" data-nombre="' . htmlspecialchars($producto['nombre']) . '" title="Eliminar">';
              echo '<span class="material-symbols-rounded">delete</span>';
              echo '</button>';
              echo '</td>';
              echo '</tr>';
            }
          } else {
            echo '<tr><td colspan="10" class="no-data">No hay productos en el inventario</td></tr>';
          }
        } catch(PDOException $e) {
          echo '<tr><td colspan="10" class="no-data">Error al cargar los productos: ' . $e->getMessage() . '</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal para agregar/editar producto -->
<div class="modal" id="productModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="productModalTitle">Agregar Nuevo Producto</h3>
      <button class="close-modal" id="closeProductModal">&times;</button>
    </div>
    <form class="product-form" id="productForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" id="product_id" name="product_id">
      <input type="hidden" id="product_form_action" name="inventario_action" value="create_product">
      <input type="hidden" id="current_imagen" name="current_imagen">
      
      <div class="form-group">
        <label for="product_nombre">Nombre del Producto *</label>
        <input type="text" id="product_nombre" name="nombre" required>
      </div>
      
      <div class="form-group">
        <label for="product_descripcion">Descripción *</label>
        <textarea id="product_descripcion" name="descripcion" rows="3" required></textarea>
      </div>
      
      <div class="form-group">
        <label for="product_precio">Precio *</label>
        <input type="number" id="product_precio" name="precio" step="0.01" min="0" required>
      </div>
      
      <div class="form-group">
        <label for="product_cantidad">Cantidad en Stock *</label>
        <input type="number" id="product_cantidad" name="cantidad" min="0" required>
      </div>
      
      <div class="form-group">
        <label for="product_categoria">Categoría *</label>
        <select id="product_categoria" name="categoria" required>
          <option value="">Seleccionar categoría</option>
          <?php foreach($categorias as $categoria): ?>
            <option value="<?php echo $categoria; ?>"><?php echo ucfirst($categoria); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label for="product_estado">Estado *</label>
        <select id="product_estado" name="estado" required>
          <option value="activo">Activo</option>
          <option value="inactivo">Inactivo</option>
          <option value="agotado">Agotado</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="product_imagen">Imagen del Producto</label>
        <input type="file" id="product_imagen" name="imagen" accept="image/*">
        <small>Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB</small>
        <div id="image-preview" class="image-preview" style="margin-top: 10px; display: none;">
          <img id="preview-image" src="" alt="Vista previa" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
        </div>
        <div id="current-image" class="current-image" style="margin-top: 10px; display: none;">
          <p>Imagen actual:</p>
          <img id="current-image-preview" src="" alt="Imagen actual" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
        </div>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn-secondary" id="cancelProductBtn">Cancelar</button>
        <button type="submit" class="btn-primary" id="saveProductBtn">Guardar Producto</button>
      </div>
    </form>
  </div>
</div>

<style>
/* Estilos adicionales para imágenes */
.product-image {
  width: 50px;
  height: 50px;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  border: 2px solid #e2e8f0;
}

.product-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.product-image.no-image {
  color: #cbd5e1;
  background: #f1f5f9;
}

.image-preview, .current-image {
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
  border: 2px dashed #e2e8f0;
}

.current-image p {
  margin: 0 0 8px 0;
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 600;
}

.form-group small {
  display: block;
  margin-top: 4px;
  color: #64748b;
  font-size: 0.75rem;
}

/* Ajustes para la tabla con imágenes */
.products-table td:first-child {
  width: 70px;
  text-align: center;
}

/* Mejoras responsive */
@media (max-width: 768px) {
  .product-image {
    width: 40px;
    height: 40px;
  }
  
  .products-table {
    min-width: 900px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Elementos del DOM para inventario
  const searchInput = document.getElementById('searchProducts');
  const addProductBtn = document.getElementById('addProductBtn');
  const productModal = document.getElementById('productModal');
  const closeProductModal = document.getElementById('closeProductModal');
  const cancelProductBtn = document.getElementById('cancelProductBtn');
  const productForm = document.getElementById('productForm');
  const productsTable = document.getElementById('productsTable');
  const productModalTitle = document.getElementById('productModalTitle');
  const productIdInput = document.getElementById('product_id');
  const productFormActionInput = document.getElementById('product_form_action');
  const currentImagenInput = document.getElementById('current_imagen');
  const productImagenInput = document.getElementById('product_imagen');
  const imagePreview = document.getElementById('image-preview');
  const previewImage = document.getElementById('preview-image');
  const currentImageDiv = document.getElementById('current-image');
  const currentImagePreview = document.getElementById('current-image-preview');
  
  // Vista previa de imagen seleccionada
  productImagenInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImage.src = e.target.result;
        imagePreview.style.display = 'block';
      }
      reader.readAsDataURL(file);
    } else {
      imagePreview.style.display = 'none';
    }
  });
  
  // Búsqueda de productos
  searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = productsTable.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
      const nombre = row.cells[2].textContent.toLowerCase();
      const descripcion = row.cells[3].textContent.toLowerCase();
      const categoria = row.cells[6].textContent.toLowerCase();
      
      if (nombre.includes(searchTerm) || descripcion.includes(searchTerm) || categoria.includes(searchTerm)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
  
  // Abrir modal para agregar producto
  addProductBtn.addEventListener('click', function() {
    productModalTitle.textContent = 'Agregar Nuevo Producto';
    productForm.reset();
    productIdInput.value = '';
    currentImagenInput.value = '';
    productFormActionInput.value = 'create_product';
    imagePreview.style.display = 'none';
    currentImageDiv.style.display = 'none';
    productModal.classList.add('show');
  });
  
  // Cerrar modal
  closeProductModal.addEventListener('click', function() {
    productModal.classList.remove('show');
  });
  
  cancelProductBtn.addEventListener('click', function() {
    productModal.classList.remove('show');
  });
  
  // Editar producto
  document.querySelectorAll('.edit-product').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.getAttribute('data-id');
      const productNombre = this.getAttribute('data-nombre');
      const productDescripcion = this.getAttribute('data-descripcion');
      const productPrecio = this.getAttribute('data-precio');
      const productCantidad = this.getAttribute('data-cantidad');
      const productCategoria = this.getAttribute('data-categoria');
      const productEstado = this.getAttribute('data-estado');
      const productImagen = this.getAttribute('data-imagen');
      
      // Llenar el formulario con los datos del producto
      productModalTitle.textContent = 'Editar Producto';
      productIdInput.value = productId;
      document.getElementById('product_nombre').value = productNombre;
      document.getElementById('product_descripcion').value = productDescripcion;
      document.getElementById('product_precio').value = productPrecio;
      document.getElementById('product_cantidad').value = productCantidad;
      document.getElementById('product_categoria').value = productCategoria;
      document.getElementById('product_estado').value = productEstado;
      productFormActionInput.value = 'update_product';
      
      // Manejar la imagen actual
      if (productImagen) {
        currentImagenInput.value = productImagen;
        currentImagePreview.src = 'uploads/' + productImagen;
        currentImageDiv.style.display = 'block';
      } else {
        currentImagenInput.value = '';
        currentImageDiv.style.display = 'none';
      }
      
      imagePreview.style.display = 'none';
      productModal.classList.add('show');
    });
  });
  
  // Eliminar producto
  document.querySelectorAll('.delete-product').forEach(btn => {
    btn.addEventListener('click', function() {
      const productId = this.getAttribute('data-id');
      const productNombre = this.getAttribute('data-nombre');
      
      if (confirm('¿Está seguro de que desea eliminar el producto "' + productNombre + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'inventario_action';
        actionInput.value = 'delete_product';
        form.appendChild(actionInput);
        
        const productIdInput = document.createElement('input');
        productIdInput.name = 'product_id';
        productIdInput.value = productId;
        form.appendChild(productIdInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    });
  });
});
</script>
      
      <?php else: ?>
        <!-- CONTENIDO DE LAS OTRAS SECCIONES -->
        <div class="section-content">
          <?php if ($seccion == 'reportes'): ?>
            <div class="section-placeholder">
              <span class="material-symbols-rounded">analytics</span>
              <h3>Reportes Financieros</h3>
              <p>Acceda a reportes detallados y análisis financieros del negocio.</p>
              <ul class="feature-list">
                <li>Reporte de ventas por período</li>
                <li>Análisis de ingresos y gastos</li>
                <li>Estadísticas de productos más vendidos</li>
                <li>Comparativas mensuales y anuales</li>
                <li>Exportación de datos en múltiples formatos</li>
              </ul>
            </div>
          
          <?php elseif ($seccion == 'promociones'): ?>
            <div class="section-placeholder">
              <span class="material-symbols-rounded">local_offer</span>
              <h3>Promociones y Descuentos</h3>
              <p>Configure y administre promociones para impulsar las ventas.</p>
              <ul class="feature-list">
                <li>Crear cupones de descuento</li>
                <li>Programar promociones temporales</li>
                <li>Descuentos por volumen</li>
                <li>Promociones por categoría</li>
                <li>Seguimiento de efectividad</li>
              </ul>
            </div>
          
          <?php elseif ($seccion == 'configuracion'): ?>
            <div class="section-placeholder">
              <span class="material-symbols-rounded">settings</span>
              <h3>Configuración Global</h3>
              <p>Personalice y configure los parámetros del sistema.</p>
              <ul class="feature-list">
                <li>Configuración general de la tienda</li>
                <li>Parámetros de facturación</li>
                <li>Configuración de impuestos</li>
                <li>Preferencias del sistema</li>
                <li>Integraciones con otros servicios</li>
              </ul>
            </div>
          
          <?php elseif ($seccion == 'auditoria'): ?>
            <div class="section-placeholder">
              <span class="material-symbols-rounded">security</span>
              <h3>Auditoría del Sistema</h3>
              <p>Monitoreo y registro de todas las actividades del sistema.</p>
              <ul class="feature-list">
                <li>Registros de acceso al sistema</li>
                <li>Historial completo de actividades</li>
                <li>Reportes de seguridad</li>
                <li>Alertas de eventos importantes</li>
                <li>Exportación de logs</li>
              </ul>
            </div>
          
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
    
    <script>
      // Toggle del sidebar - MOSTRAR/OCULTAR al hacer clic en las 3 rayitas
      document.getElementById('menuToggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
      });

      // Cerrar sidebar al hacer clic fuera de él
      document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.getElementById('menuToggle');
        
        if (!sidebar.classList.contains('collapsed') && 
            !sidebar.contains(event.target) && 
            !menuToggle.contains(event.target)) {
          sidebar.classList.add('collapsed');
          document.querySelector('.main-content').classList.add('sidebar-collapsed');
        }
      });

      // En pantallas grandes, mantener el comportamiento normal
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          // Comportamiento para pantallas grandes
        }
      });
    </script>
  </body>
</html>
