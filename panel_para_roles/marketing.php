<?php
session_start();

// seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 6) {
    header('Location: ../login.php');
    exit();
}

// Datos usuarios
$nombreCompleto = $_SESSION['usuario_nombre'];
$partesNombre = explode(' ', $nombreCompleto);
$iniciales = '';
if (count($partesNombre) >= 2) {
    $iniciales = strtoupper(substr($partesNombre[0], 0, 1) . substr($partesNombre[count($partesNombre)-1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombreCompleto, 0, 2));
}

// conexion
$host = 'localhost';
$db   = 'tienda_ropa';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// =====================================================
// FUNCIONES GENERALES
// =====================================================

// Obtener campañas activas
function getCampanasActivas($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM campanas_marketing WHERE estado = 'activa'");
        $resultado = $stmt->fetch();
        return $resultado ? (int)$resultado['total'] : 0;
    } catch (PDOException $e) {
        error_log("Error en getCampanasActivas: " . $e->getMessage());
        return 0;
    }
}

// Obtener ROI promedio de todas las campañas
function getRoiPromedio($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                SUM(costo_real) as costo_total,
                SUM(ingresos_generados) as ingresos_totales
            FROM campanas_marketing 
            WHERE costo_real > 0
        ");
        
        $data = $stmt->fetch();
        
        if ($data && $data['costo_total'] > 0) {
            $costo = (float)$data['costo_total'];
            $ingresos = (float)$data['ingresos_totales'];
            $roi = (($ingresos - $costo) / $costo) * 100;
            return round($roi, 2);
        }
        return 0;
    } catch (PDOException $e) {
        error_log("Error en getRoiPromedio: " . $e->getMessage());
        return 0;
    }
}

// Obtener ventas totales
function getVentasTotales($pdo) {
    try {
        $stmt = $pdo->query("SELECT SUM(total) AS total_ventas FROM ventas WHERE estado = 'completada'");
        $resultado = $stmt->fetch();
        return $resultado ? (float)$resultado['total_ventas'] : 0.00;
    } catch (PDOException $e) {
        error_log("Error en getVentasTotales: " . $e->getMessage());
        return 0.00;
    }
}

// Obtener clientes nuevos (últimos 30 días)
function getClientesNuevos($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(id) AS nuevos_clientes 
            FROM clientes_activos 
            WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $resultado = $stmt->fetch();
        return $resultado ? (int)$resultado['nuevos_clientes'] : 0;
    } catch (PDOException $e) {
        error_log("Error en getClientesNuevos: " . $e->getMessage());
        return 0;
    }
}

// Obtener campañas recientes con métricas (prioridad a activas)
function getCampanasRecientes($pdo, $limit = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                nombre_campana as nombre, 
                estado, 
                fecha_inicio, 
                fecha_fin,
                presupuesto,
                costo_real, 
                ingresos_generados,
                0 as impresiones,
                0 as clics,
                0 as conversiones
            FROM campanas_marketing
            ORDER BY 
                CASE WHEN estado = 'activa' THEN 1 
                     WHEN estado = 'pausada' THEN 2
                     ELSE 3 END,
                fecha_inicio DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $campanas = $stmt->fetchAll();

        foreach ($campanas as &$campana) {
            $costo = (float)$campana['costo_real'];
            $ingresos = (float)$campana['ingresos_generados'];
            
            // Calcular ROI
            if ($costo > 0) {
                $campana['roi_porcentaje'] = round((($ingresos - $costo) / $costo) * 100, 2);
            } else {
                $campana['roi_porcentaje'] = 0;
            }

            // Calcular display de fecha
            if ($campana['estado'] == 'activa') {
                $campana['fecha_display'] = 'En curso';
            } elseif ($campana['fecha_fin']) {
                $fechaFin = new DateTime($campana['fecha_fin']);
                $fechaHoy = new DateTime();
                $intervalo = $fechaFin->diff($fechaHoy);
                $campana['fecha_display'] = 'Finalizada hace ' . $intervalo->days . ' días';
            } else {
                $campana['fecha_display'] = 'Sin fecha de fin';
            }
        }
        
        return $campanas;

    } catch (PDOException $e) {
        error_log("Error en getCampanasRecientes: " . $e->getMessage());
        return [];
    }
}

// Obtener actividad reciente
function getActividadReciente($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.accion, 
                a.descripcion, 
                a.tipo, 
                a.fecha_registro, 
                u.nombre as usuario
            FROM actividades a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            ORDER BY a.fecha_registro DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error en getActividadReciente: " . $e->getMessage());
        return [];
    }
}

// Obtener datos para gráfico de ventas mensuales
function getVentasMensuales($pdo, $meses = 6) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(fecha_venta, '%b %Y') as mes_nombre,
                SUM(total) as total_ventas
            FROM ventas
            WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL :meses MONTH)
            AND estado = 'completada'
            GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
            ORDER BY YEAR(fecha_venta) ASC, MONTH(fecha_venta) ASC
        ");
        
        $stmt->bindParam(':meses', $meses, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error en getVentasMensuales: " . $e->getMessage());
        return [];
    }
}

// Obtener distribución de ventas por canal de marketing
function getDistribucionCanales($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                canal,
                COUNT(*) as cantidad,
                SUM(ingresos_generados) as ingresos_totales,
                SUM(costo_real) as costo_total
            FROM campanas_marketing
            GROUP BY canal
            ORDER BY ingresos_totales DESC
        ");
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error en getDistribucionCanales: " . $e->getMessage());
        return [];
    }
}

// Obtener reseñas de clientes
function getResenasClientes($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                c.nombre AS cliente_nombre,
                c.correo,
                r.calificacion,
                r.comentario,
                r.fecha_registro,
                r.estado
            FROM resenas_clientes r
            JOIN clientes_activos c ON r.cliente_id = c.id
            ORDER BY r.calificacion DESC, r.fecha_registro DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener reseñas: " . $e->getMessage());
        return [];
    }
}

// Obtener reseñas destacadas
function getResenasDestacadas($pdo, $limit = 3) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                c.nombre AS cliente_nombre,
                r.calificacion,
                r.comentario,
                r.fecha_registro
            FROM resenas_clientes r
            JOIN clientes_activos c ON r.cliente_id = c.id
            WHERE r.estado = 'activa'
            ORDER BY r.calificacion DESC, r.fecha_registro DESC
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error en getResenasDestacadas: " . $e->getMessage());
        return [];
    }
}

// Función auxiliar de tiempo transcurrido
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' atrás' : 'justo ahora';
}

// =====================================================
// CRUD CAMPAÑAS
// =====================================================

// Obtener todas las campañas
function getTodasCampanas($pdo) {
    try {
        $sql = "SELECT id, nombre_campana, canal, presupuesto, costo_real, ingresos_generados, fecha_inicio, fecha_fin, estado 
                FROM campanas_marketing 
                ORDER BY fecha_inicio DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener campañas: " . $e->getMessage());
        return [];
    }
}

// Obtener una campaña por ID
function getCampanaPorId($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre_campana, canal, presupuesto, costo_real, ingresos_generados, fecha_inicio, fecha_fin, estado FROM campanas_marketing WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener campaña por ID: " . $e->getMessage());
        return null;
    }
}

// Crear campaña
function crearCampana($pdo, $nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin) {
    try {
        $sql = "INSERT INTO campanas_marketing 
                (nombre_campana, canal, presupuesto, costo_real, estado, ingresos_generados, fecha_inicio, fecha_fin) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al crear campaña: " . $e->getMessage());
        return false;
    }
}

// Actualizar campaña
function actualizarCampana($pdo, $id, $nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin) {
    try {
        $sql = "UPDATE campanas_marketing 
                SET nombre_campana = ?, canal = ?, presupuesto = ?, costo_real = ?, estado = ?, ingresos_generados = ?, fecha_inicio = ?, fecha_fin = ? 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin, $id]);
        
        if ($success) {
            // Registrar actividad
            $actividadStmt = $pdo->prepare("INSERT INTO actividades (accion, descripcion, tipo, usuario_id, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
            $actividadStmt->execute([
                "Campaña actualizada",
                "Campaña '$nombre' fue actualizada",
                "campaña",
                $_SESSION['usuario_id']
            ]);
            
            return true;
        }
        return false;
        
    } catch (PDOException $e) {
        error_log("Error al actualizar la campaña: " . $e->getMessage());
        return false;
    }
}

// Eliminar campaña (solo si no está activa)
function eliminarCampana($pdo, $id) {
    try {
        // Verificar si la campaña está activa
        $stmt = $pdo->prepare("SELECT estado FROM campanas_marketing WHERE id = ?");
        $stmt->execute([$id]);
        $campana = $stmt->fetch();
        
        if ($campana && $campana['estado'] === 'activa') {
            return false; // No se puede eliminar una campaña activa
        }
        
        $sql = "DELETE FROM campanas_marketing WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error al eliminar la campaña: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// CRUD LEADS
// =====================================================

// Obtener todos los leads
function getTodosLeads($pdo) {
    try {
        $sql = "SELECT id, nombre, correo, telefono, estado, fuente_adquisicion, fecha_registro 
                FROM clientes_activos 
                ORDER BY fecha_registro DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener leads: " . $e->getMessage());
        return [];
    }
}

// Obtener un lead por ID
function getLeadPorId($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, correo, telefono, estado, fuente_adquisicion FROM clientes_activos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener lead por ID: " . $e->getMessage());
        return null;
    }
}

// Crear lead
function crearLead($pdo, $nombre, $correo, $telefono, $estado, $fuente) {
    try {
        $sql = "INSERT INTO clientes_activos (nombre, correo, telefono, estado, fuente_adquisicion, fecha_registro) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $correo, $telefono, $estado, $fuente]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al crear lead: " . $e->getMessage());
        return false;
    }
}

// Actualizar lead
function actualizarLead($pdo, $id, $nombre, $correo, $telefono, $estado, $fuente) {
    try {
        $sql = "UPDATE clientes_activos 
                SET nombre = ?, correo = ?, telefono = ?, estado = ?, fuente_adquisicion = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $correo, $telefono, $estado, $fuente, $id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al actualizar lead: " . $e->getMessage());
        return false;
    }
}

// Eliminar lead (solo si está inactivo)
function eliminarLead($pdo, $id) {
    try {
        // Verificar si el lead está activo
        $stmt = $pdo->prepare("SELECT estado FROM clientes_activos WHERE id = ?");
        $stmt->execute([$id]);
        $lead = $stmt->fetch();
        
        if ($lead && $lead['estado'] === 'activo') {
            return false; // No se puede eliminar un lead activo
        }
        
        $sql = "DELETE FROM clientes_activos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error al eliminar lead: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// CRUD RESEÑAS
// =====================================================

// Obtener todas las reseñas
function getTodasResenas($pdo) {
    try {
        $sql = "
            SELECT 
                r.id,
                c.nombre AS cliente_nombre,
                c.correo,
                r.calificacion,
                r.comentario,
                r.fecha_registro,
                r.estado
            FROM resenas_clientes r
            JOIN clientes_activos c ON r.cliente_id = c.id
            ORDER BY r.fecha_registro DESC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener reseñas: " . $e->getMessage());
        return [];
    }
}

// Cambiar estado de reseña
function cambiarEstadoResena($pdo, $id, $estado) {
    try {
        $sql = "UPDATE resenas_clientes SET estado = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$estado, $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error al cambiar estado de reseña: " . $e->getMessage());
        return false;
    }
}

// =====================================================
// PROCESAMIENTO DE FORMULARIOS
// =====================================================

// CREAR CAMPAÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_campana') {
    $nombre = trim($_POST['nombre_campana']);
    $canal = $_POST['canal'];
    $presupuesto = (float)($_POST['presupuesto'] ?? 0);
    $costo_real = (float)($_POST['costo_real'] ?? 0);
    $estado = $_POST['estado'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : NULL;
    $ingresos_generados = (float)($_POST['ingresos_generados'] ?? 0);
    
    // Validaciones
    if (empty($nombre) || empty($fecha_inicio)) {
        $_SESSION['error'] = "El nombre y fecha de inicio son obligatorios.";
    } elseif ($presupuesto < 0 || $costo_real < 0 || $ingresos_generados < 0) {
        $_SESSION['error'] = "Los valores numéricos no pueden ser negativos.";
    } elseif ($fecha_fin && $fecha_fin < $fecha_inicio) {
        $_SESSION['error'] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    } else {
        if (crearCampana($pdo, $nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin)) {
            $_SESSION['mensaje'] = "Campaña '{$nombre}' creada exitosamente.";
        } else {
            $_SESSION['error'] = "Error al crear la campaña.";
        }
    }
    
    header('Location: marketing.php?seccion=campanas');
    exit();
}

// ACTUALIZAR CAMPAÑA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_campana') {
    $id = (int)$_POST['id_campana'];
    $nombre = trim($_POST['nombre_campana']);
    $canal = $_POST['canal'];
    $presupuesto = (float)($_POST['presupuesto'] ?? 0);
    $costo_real = (float)($_POST['costo_real'] ?? 0);
    $estado = $_POST['estado'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : NULL;
    $ingresos_generados = (float)($_POST['ingresos_generados'] ?? 0);
    
    // Validaciones
    if (empty($nombre) || empty($fecha_inicio)) {
        $_SESSION['error'] = "El nombre y fecha de inicio son obligatorios.";
    } elseif ($presupuesto < 0 || $costo_real < 0 || $ingresos_generados < 0) {
        $_SESSION['error'] = "Los valores numéricos no pueden ser negativos.";
    } elseif ($fecha_fin && $fecha_fin < $fecha_inicio) {
        $_SESSION['error'] = "La fecha de fin no puede ser anterior a la fecha de inicio.";
    } else {
        try {
            $sql = "UPDATE campanas_marketing 
                    SET nombre_campana = ?, canal = ?, presupuesto = ?, costo_real = ?, estado = ?, ingresos_generados = ?, fecha_inicio = ?, fecha_fin = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$nombre, $canal, $presupuesto, $costo_real, $estado, $ingresos_generados, $fecha_inicio, $fecha_fin, $id]);
            
            if ($success) {
                // Registrar actividad
                $actividadStmt = $pdo->prepare("INSERT INTO actividades (accion, descripcion, tipo, usuario_id, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
                $actividadStmt->execute([
                    "Campaña actualizada",
                    "Campaña '$nombre' fue actualizada",
                    "sistema",
                    $_SESSION['usuario_id']
                ]);
                
                $_SESSION['mensaje'] = "Campaña '{$nombre}' actualizada exitosamente.";
            } else {
                $_SESSION['error'] = "Error al actualizar la campaña. Por favor, intenta de nuevo.";
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar campaña: " . $e->getMessage());
            $_SESSION['error'] = "Error al actualizar la campaña: " . $e->getMessage();
        }
    }
    
    header('Location: marketing.php?seccion=campanas');
    exit();
}

// ELIMINAR CAMPAÑA
if (isset($_GET['eliminar_campana'])) {
    $id = (int)$_GET['eliminar_campana'];
    
    $campana = getCampanaPorId($pdo, $id);
    
    if ($campana) {
        if (eliminarCampana($pdo, $id)) {
            $_SESSION['mensaje'] = "Campaña '{$campana['nombre_campana']}' eliminada exitosamente.";
        } else {
            $_SESSION['error'] = "No se puede eliminar una campaña activa. Cambia el estado a 'Finalizada' primero.";
        }
    } else {
        $_SESSION['error'] = "Error al eliminar la campaña.";
    }
    
    header('Location: marketing.php?seccion=campanas');
    exit();
}

// Endpoint para obtener datos de campaña (AJAX)
if (isset($_GET['obtener_campana'])) {
    $id = (int)$_GET['obtener_campana'];
    $campana = getCampanaPorId($pdo, $id);
    header('Content-Type: application/json');
    echo json_encode($campana);
    exit();
}

// CREAR LEAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_lead') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $estado = $_POST['estado'];
    $fuente = trim($_POST['fuente_adquisicion']);
    
    // Validaciones
    if (empty($nombre) || empty($correo)) {
        $_SESSION['error'] = "El nombre y correo son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "El formato del correo electrónico no es válido.";
    } elseif ($telefono && !preg_match('/^[0-9\-\+\s\(\)]{10,}$/', $telefono)) {
        $_SESSION['error'] = "El formato del teléfono no es válido.";
    } else {
        if (crearLead($pdo, $nombre, $correo, $telefono, $estado, $fuente)) {
            $_SESSION['mensaje'] = "Lead '{$nombre}' creado exitosamente.";
        } else {
            $_SESSION['error'] = "Error al crear el lead.";
        }
    }
    
    header('Location: marketing.php?seccion=leads');
    exit();
}

// ACTUALIZAR LEAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_lead') {
    $id = (int)$_POST['id_lead'];
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $estado = $_POST['estado'];
    $fuente = trim($_POST['fuente_adquisicion']);
    
    // Validaciones
    if (empty($nombre) || empty($correo)) {
        $_SESSION['error'] = "El nombre y correo son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "El formato del correo electrónico no es válido.";
    } elseif ($telefono && !preg_match('/^[0-9\-\+\s\(\)]{10,}$/', $telefono)) {
        $_SESSION['error'] = "El formato del teléfono no es válido.";
    } else {
        try {
            $sql = "UPDATE clientes_activos 
                    SET nombre = ?, correo = ?, telefono = ?, estado = ?, fuente_adquisicion = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$nombre, $correo, $telefono, $estado, $fuente, $id]);
            
            if ($success) {
                $_SESSION['mensaje'] = "Lead '{$nombre}' actualizado exitosamente.";
            } else {
                $_SESSION['error'] = "Error al actualizar el lead. Por favor, intenta de nuevo.";
            }
        } catch (PDOException $e) {
            error_log("Error al actualizar lead: " . $e->getMessage());
            $_SESSION['error'] = "Error al actualizar el lead: " . $e->getMessage();
        }
    }
    
    header('Location: marketing.php?seccion=leads');
    exit();
}


// ELIMINAR LEAD
if (isset($_GET['eliminar_lead'])) {
    $id = (int)$_GET['eliminar_lead'];
    
    $lead = getLeadPorId($pdo, $id);
    
    if ($lead) {
        if (eliminarLead($pdo, $id)) {
            $_SESSION['mensaje'] = "Lead '{$lead['nombre']}' eliminado exitosamente.";
        } else {
            $_SESSION['error'] = "No se puede eliminar un lead activo. Cambia el estado a 'Inactivo' primero.";
        }
    } else {
        $_SESSION['error'] = "Error al eliminar el lead.";
    }
    
    header('Location: marketing.php?seccion=leads');
    exit();
}

// Endpoint para obtener datos de lead (AJAX)
if (isset($_GET['obtener_lead'])) {
    $id = (int)$_GET['obtener_lead'];
    $lead = getLeadPorId($pdo, $id);
    header('Content-Type: application/json');
    echo json_encode($lead);
    exit();
}

// CAMBIAR ESTADO RESEÑA
if (isset($_GET['cambiar_estado_resena'])) {
    $id = (int)$_GET['cambiar_estado_resena'];
    $estado = $_GET['estado'];
    
    if (cambiarEstadoResena($pdo, $id, $estado)) {
        $_SESSION['mensaje'] = "Estado de reseña actualizado exitosamente.";
    } else {
        $_SESSION['error'] = "Error al actualizar el estado de la reseña.";
    }
    
    header('Location: marketing.php?seccion=resenas');
    exit();
}

// =====================================================
// CONFIGURACIÓN INICIAL
// =====================================================

// seccion actual
$seccion = $_GET['seccion'] ?? 'panel';

// KPIs principales
$campanasActivas = getCampanasActivas($pdo);
$roi_porcentaje = getRoiPromedio($pdo);
$ventas_totales = getVentasTotales($pdo);
$clientes_nuevos = getClientesNuevos($pdo);

// Campañas y actividades
$campanasRecientes = getCampanasRecientes($pdo, 3);
$actividadReciente = getActividadReciente($pdo, 10);

// Datos para gráficos
$datosVentasMensuales = getVentasMensuales($pdo, 6);
$distribucionCanales = getDistribucionCanales($pdo);

// ===== PREPARAR DATOS PARA GRÁFICO DE VENTAS =====
$labelsChartVentas = [];
$dataChartVentas = [];

if (!empty($datosVentasMensuales)) {
    foreach ($datosVentasMensuales as $venta) {
        $labelsChartVentas[] = $venta['mes_nombre'];
        $dataChartVentas[] = (float)$venta['total_ventas'];
    }
} else {
    $labelsChartVentas = ['Sin datos'];
    $dataChartVentas = [0];
}

// ===== PREPARAR DATOS PARA GRÁFICO DE CANALES =====
$labelsCanales = [];
$dataCanales = [];
$coloresCanales = [
    '#667eea', '#a2720aff', '#4facfe', '#43e97b', '#fa9d1cff'
];

if (!empty($distribucionCanales)) {
    $coloresUsados = [];
    foreach ($distribucionCanales as $index => $canal) {
        $nombreCanal = match($canal['canal']) {
            'redes_sociales' => 'Redes Sociales',
            'email' => 'Email Marketing',
            'publicidad_pagada' => 'Publicidad Pagada',
            'SEO' => 'SEO',
            'otros' => 'Otros',
            default => ucfirst($canal['canal'])
        };
        
        $labelsCanales[] = $nombreCanal;
        $dataCanales[] = (float)$canal['ingresos_totales'];
        $coloresUsados[] = $coloresCanales[$index % count($coloresCanales)];
    }
    $coloresCanales = $coloresUsados;
} else {
    $labelsCanales = ['Sin datos'];
    $dataCanales = [1];
    $coloresCanales = ['#e2e8f0'];
}

// Convertir a JSON para JavaScript
$jsonLabelsVentas = json_encode($labelsChartVentas);
$jsonDataVentas = json_encode($dataChartVentas);
$jsonLabelsCanales = json_encode($labelsCanales);
$jsonDataCanales = json_encode($dataCanales);
$jsonColoresCanales = json_encode($coloresCanales);

// Obtener datos según la sección
if ($seccion === 'campanas') {
    $campanas = getTodasCampanas($pdo);
}

if ($seccion === 'leads') {
    $leads = getTodosLeads($pdo);
}

if ($seccion === 'resenas') {
    $resenas = getTodasResenas($pdo);
}

if ($seccion === 'reportes') {
    $resenasRecientes = getResenasClientes($pdo, 5);
    $resenasDestacadas = getResenasDestacadas($pdo, 3);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gerente de Marketing</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        /* Header Superior - Colores actualizados */
        .top-header {
            background: linear-gradient(135deg, #ffffffff 0%, #ffffffff 100%);
            color: black;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .menu-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: black;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .profile-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #e2e8f0;
            min-width: 200px;
            z-index: 1000;
            display: none;
            margin-top: 0.5rem;
        }

        .profile-dropdown-menu.show {
            display: block;
        }

        .profile-dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .profile-dropdown-item:last-child {
            border-bottom: none;
        }

        .profile-dropdown-item:hover {
            background: #f8fafc;
            color: #3b82f6;
        }

        .profile-dropdown-item .material-symbols-rounded {
            margin-right: 8px;
            font-size: 18px;
        }

        /* Sidebar - Colores actualizados */
        .modern-sidebar {
            position: fixed;
            left: 0;
            top: 73px;
            width: 280px;
            height: calc(100vh - 73px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 90;
        }

        .modern-sidebar.collapsed {
            width: 80px;
        }

        .nav-section {
            padding: 1.5rem 1rem;
        }

        .nav-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            padding: 0 0.75rem;
        }

        .modern-sidebar.collapsed .nav-title {
            display: none;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1rem;
            border-radius: 10px;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #f3f4f6;
            color: #3b82f6;
        }

        .nav-item.active .nav-link {
            background: linear-gradient(135deg, #e7b220ff 0%, #be8a22ff 100%);
            color: white;
        }

        .modern-sidebar.collapsed .nav-text {
            display: none;
        }

        .modern-sidebar.collapsed .nav-link {
            justify-content: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: calc(100vh - 73px);
        }

        .main-content.sidebar-collapsed {
            margin-left: 80px;
        }

        /* Page Header - Colores actualizados */
        .page-header {
            background: #ae620bff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(238, 231, 231, 0.05);
            border: 1px solid #ae620bff;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fdfeffff;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: #ffffffff;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #d29936ff;
            color: #f8f5efff;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid #eba345ff;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eeeeeeff 0%, #fefefeff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: brown;
        }

        .header-icon .material-symbols-rounded {
            font-size: 40px;
        }

        /* Dashboard Cards - Colores actualizados */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .card.campaigns::before {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .card.leads::before {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .card.roi::before {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .card.engagement::before {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .campaigns .card-icon {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .leads .card-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .roi .card-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .engagement .card-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .card-change {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #10b981;
            font-weight: 600;
        }

        .card-change.negative {
            color: #ef4444;
        }

        /* Campaigns Section */
        .campaigns-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Botones - Colores actualizados */
        .btn-primary {
            background: linear-gradient(135deg, #f6bb3bff, #d8971dff);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
        }

        /* Campaigns Grid */
        .campaigns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .campaign-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .campaign-card:hover {
            border-color: #3b82f6;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .campaign-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .campaign-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .campaign-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-paused {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-aprobado {
            background: #dcfce7;
            color: #166534;
        }

        .status-pendiente {
            background: #fef3c7;
            color: #92400e;
        }

        .status-rechazado {
            background: #fee2e2;
            color: #dc2626;
        }

        .campaign-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .metric {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .metric-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .metric-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
        }

        .campaign-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn-icon {
            background: #f3f4f6;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #6b7280;
        }

        .btn-icon:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-icon-danger {
            background: #fef2f2;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #dc2626;
        }

        .btn-icon-danger:hover {
            background: #dc2626;
            color: white;
        }

        .btn-icon-warning {
            background: #fffbeb;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #d97706;
        }

        .btn-icon-warning:hover {
            background: #d97706;
            color: white;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }

        .chart-placeholder {
            width: 100%;
            height: 300px;
            background: #f0f9ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3b82f6;
            font-weight: 600;
        }

        /* Reseñas */
        .review-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #e5e7eb;
            border: 1px solid #e5e7eb;
        }

        .review-card.aprobado {
            border-left-color: #10b981;
        }

        .review-card.pendiente {
            border-left-color: #f59e0b;
        }

        .review-card.rechazado {
            border-left-color: #ef4444;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .reviewer-details h4 {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .reviewer-details p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .rating {
            display: flex;
            gap: 0.25rem;
        }

        .star {
            color: #fbbf24;
            font-size: 1.125rem;
        }

        .review-content {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .review-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .review-date {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Alertas - Colores actualizados */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
            border: 1px solid;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }

        /* Tablas - Colores actualizados */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
        }

        thead {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        thead tr th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
        }

        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody td {
            padding: 1rem;
            color: #4b5563;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-sidebar {
                width: 80px;
            }

            .modern-sidebar .nav-text {
                display: none;
            }

            .main-content {
                margin-left: 80px;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }

            .charts-section {
                grid-template-columns: 1fr;
            }

            .campaigns-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Resto del CSS se mantiene igual... */
        .input-error {
            border-color: #dc2626 !important;
            background-color: #fef2f2 !important;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estrellas de calificación */
        .stars {
            display: flex;
            gap: 0.25rem;
        }

        .star {
            color: #d1d5db;
            font-size: 1.25rem;
        }

        .star.filled {
            color: #fbbf24;
        }
    </style>
</head>
<body>
    <!-- Header Superior -->
    <header class="top-header">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <div class="logo">
                <span class="material-symbols-rounded">campaign</span>
                <span class="logo-text">Marketing Panel</span>
            </div>
        </div>
        
        <div class="user-profile" id="userProfile">
            <div class="user-avatar"><?php echo $iniciales; ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($_SESSION['rol']); ?></div>
            </div>
            <span class="material-symbols-rounded">expand_more</span>
            
            <!-- Dropdown del perfil -->
            <div class="profile-dropdown-menu" id="profileDropdownMenu">
                <a href="logout.php" class="profile-dropdown-item">
                    <span class="material-symbols-rounded">logout</span>
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="modern-sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-title">Principal</div>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo ($seccion === 'panel') ? 'active' : ''; ?>">
                        <a href="marketing.php" class="nav-link">
                            <span class="nav-icon material-symbols-rounded">dashboard</span>
                            <span class="nav-text">Panel Principal</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="nav-section">
                <div class="nav-title">Marketing</div>
                <ul class="nav-menu">
                    <li class="nav-item <?php echo ($seccion === 'campanas') ? 'active' : ''; ?>">
                        <a href="marketing.php?seccion=campanas" class="nav-link">
                            <span class="nav-icon material-symbols-rounded">campaign</span>
                            <span class="nav-text">Campañas</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($seccion === 'leads') ? 'active' : ''; ?>">
                        <a href="marketing.php?seccion=leads" class="nav-link">
                            <span class="nav-icon material-symbols-rounded">group</span>
                            <span class="nav-text">Leads</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($seccion === 'resenas') ? 'active' : ''; ?>">
                        <a href="marketing.php?seccion=resenas" class="nav-link">
                            <span class="nav-icon material-symbols-rounded">reviews</span>
                            <span class="nav-text">Reseñas</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($seccion === 'reportes') ? 'active' : ''; ?>">
                        <a href="marketing.php?seccion=reportes" class="nav-link">
                            <span class="nav-icon material-symbols-rounded">analytics</span>
                            <span class="nav-text">Reportes</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content" id="mainContent">
        
        <?php if ($seccion === 'panel'): ?>
            <!-- ============================================ -->
            <!-- SECCIÓN: PANEL PRINCIPAL -->
            <!-- ============================================ -->
            
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Panel de Marketing</h1>
                        <div class="header-subtitle">Vista general de campañas y métricas principales</div>
                        <div class="header-badge">
                            <span class="material-symbols-rounded">verified</span>
                            Sesión activa como <?php echo htmlspecialchars($_SESSION['rol']); ?>
                        </div>
                    </div>
                    <div class="header-icon">
                        <span class="material-symbols-rounded">campaign</span>
                    </div>
                </div>
            </header>

            <!-- Alerta de bienvenida -->
            <div class="alert alert-success">
                <span class="material-symbols-rounded">check_circle</span>
                <span>Bienvenido al panel de Marketing, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
            </div>

            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card campaigns">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Campañas Activas</div>
                            <div class="card-value"><?php echo $campanasActivas; ?></div>
                        </div>
                        <div class="card-icon">
                            <span class="material-symbols-rounded">campaign</span>
                        </div>
                    </div>
                </div>

                <div class="card leads">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Leads Generados</div>
                            <div class="card-value"><?php echo number_format($clientes_nuevos); ?></div>
                        </div>
                        <div class="card-icon">
                            <span class="material-symbols-rounded">group</span>
                        </div>
                    </div>
                </div>

                <div class="card roi">
                    <div class="card-header">
                        <div>
                            <div class="card-title">ROI Promedio</div>
                            <div class="card-value"><?php echo $roi_porcentaje; ?>%</div>
                        </div>
                        <div class="card-icon">
                            <span class="material-symbols-rounded">trending_up</span>
                        </div>
                    </div>
                </div>

                <div class="card engagement">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Ventas Totales</div>
                            <div class="card-value">$<?php echo number_format($ventas_totales, 2, '.', ','); ?></div>
                        </div>
                        <div class="card-icon">
                            <span class="material-symbols-rounded">favorite</span>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <h3 class="section-title">
                        <span class="material-symbols-rounded">show_chart</span>
                        Rendimiento de Ventas (6 Meses)
                    </h3>
                    <div style="height: 300px; padding: 1rem;">
                        <canvas id="ventasChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3 class="section-title">
                        <span class="material-symbols-rounded">pie_chart</span>
                        Canales de Marketing
                    </h3>
                    <div style="height: 300px; padding: 1rem; display: flex; justify-content: center; align-items: center;">
                        <canvas id="canalesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Campaigns Section -->
            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">campaign</span>
                        Campañas Recientes
                    </h2>
                </div>

                <div class="campaigns-grid">
                    <?php if (empty($campanasRecientes)): ?>
                        <div class="alert alert-warning" style="grid-column: 1 / -1;">
                            <span class="material-symbols-rounded">info</span>
                            <span>No se encontraron campañas recientes en la base de datos.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($campanasRecientes as $campana): ?>
                            <div class="campaign-card">
                                <div class="campaign-header">
                                    <div>
                                        <div class="campaign-title"><?php echo htmlspecialchars($campana['nombre']); ?></div>
                                        <?php
                                            $statusClass = '';
                                            if ($campana['estado'] == 'activa') {
                                                $statusClass = 'status-active';
                                            } elseif ($campana['estado'] == 'pausada') {
                                                $statusClass = 'status-paused';
                                            } else {
                                                $statusClass = 'status-completed';
                                            }
                                        ?>
                                        <span class="campaign-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst($campana['estado'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="campaign-metrics">
                                    <div class="metric">
                                        <div class="metric-label">ROI</div>
                                        <div class="metric-value"><?php echo $campana['roi_porcentaje']; ?>%</div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Presupuesto</div>
                                        <div class="metric-value">$<?php echo number_format($campana['presupuesto'], 0); ?></div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Gastado</div>
                                        <div class="metric-value">$<?php echo number_format($campana['costo_real'], 0); ?></div>
                                    </div>
                                    <div class="metric">
                                        <div class="metric-label">Ingresos</div>
                                        <div class="metric-value">$<?php echo number_format($campana['ingresos_generados'], 0); ?></div>
                                    </div>
                                </div>
                                <div class="campaign-actions">
                                    <a href="marketing.php?seccion=campanas&ver=<?php echo $campana['id']; ?>" class="btn-icon" title="Ver detalles">
                                        <span class="material-symbols-rounded">visibility</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Recent Activity Section -->
            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">history</span>
                        Actividad Reciente
                    </h2>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if (count($actividadReciente) > 0): ?>
                        <?php foreach ($actividadReciente as $actividad): 
                            $icon = 'info';
                            $gradient = 'linear-gradient(135deg, #64748b, #94a3b8)';
                            $metricText = '';

                            switch ($actividad['tipo']) {
                                case 'venta':
                                    $icon = 'shopping_cart';
                                    $gradient = 'linear-gradient(135deg, #e9ba43ff, #f9dc38ff)';
                                    $metricText = 'Venta';
                                    break;
                                case 'inventario':
                                    $icon = 'inventory_2';
                                    $gradient = 'linear-gradient(135deg, #84b413ff, #467105ff)';
                                    $metricText = 'Stock';
                                    break;
                                case 'cliente':
                                    $icon = 'person_add';
                                    $gradient = 'linear-gradient(135deg, #94e84bff, #9dc839ff)';
                                    $metricText = 'Nuevo Lead';
                                    break;
                                case 'pedido':
                                    $icon = 'receipt_long';
                                    $gradient = 'linear-gradient(135deg, #764ba2, #667eea)';
                                    $metricText = 'Pedido';
                                    break;
                                case 'alerta':
                                    $icon = 'warning';
                                    $gradient = 'linear-gradient(135deg, #ffc371, #f5576c)';
                                    $metricText = 'Alerta';
                                    break;
                                default:
                                    if (stripos($actividad['accion'], 'campaña') !== false) {
                                        $icon = 'campaign';
                                        $gradient = 'linear-gradient(135deg, #667eea, #764ba2)';
                                        $metricText = 'Marketing';
                                    } else {
                                        $icon = 'settings';
                                        $metricText = 'Sistema';
                                    }
                                    break;
                            }
                        ?>
                        <div style="display: flex; align-items: center; padding: 1rem; background: #f8fafc; border-radius: 10px; gap: 1rem; border-left: 5px solid <?php echo substr($gradient, strpos($gradient, '#'), 7); ?>;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: <?php echo $gradient; ?>; display: flex; align-items: center; justify-content: center; color: white;">
                                <span class="material-symbols-rounded" style="font-size: 20px;"><?php echo $icon; ?></span>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($actividad['accion']); ?></div>
                                <div style="font-size: 0.875rem; color: #64748b;">
                                    <?php echo htmlspecialchars($actividad['descripcion'] ?: 'Sin descripción'); ?> - 
                                    <strong><?php echo time_elapsed_string($actividad['fecha_registro']); ?></strong>
                                </div>
                            </div>
                            <div style="color: #3b82f6; font-weight: 600; font-size: 0.875rem;">
                                <?php echo $metricText; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <span class="material-symbols-rounded">info</span>
                            <span>No se encontró actividad reciente para mostrar.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($seccion === 'campanas'): ?>
            <!-- ============================================ -->
            <!-- SECCIÓN: GESTIÓN DE CAMPAÑAS -->
            <!-- ============================================ -->
            
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Gestión de Campañas</h1>
                        <div class="header-subtitle">Administra tus campañas de marketing</div>
                    </div>
                    <div class="header-icon">
                        <span class="material-symbols-rounded">campaign</span>
                    </div>
                </div>
            </header>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-rounded">check_circle</span>
                    <span><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-rounded">error</span>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">campaign</span>
                        Todas las Campañas
                    </h2>
                    <button class="btn-primary" onclick="mostrarModalCrear()">
                        <span class="material-symbols-rounded">add</span>
                        Nueva Campaña
                    </button>
                </div>

                <!-- Tabla de Campañas -->
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden;">
                        <thead style="background: linear-gradient(135deg, #dca632ff, #b57b10ff); color: white;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">Nombre</th>
                                <th style="padding: 1rem; text-align: left;">Canal</th>
                                <th style="padding: 1rem; text-align: center;">Estado</th>
                                <th style="padding: 1rem; text-align: right;">Presupuesto</th>
                                <th style="padding: 1rem; text-align: right;">Gastado</th>
                                <th style="padding: 1rem; text-align: right;">Ingresos</th>
                                <th style="padding: 1rem; text-align: center;">ROI</th>
                                <th style="padding: 1rem; text-align: center;">Fechas</th>
                                <th style="padding: 1rem; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($campanas)): ?>
                                <tr>
                                    <td colspan="9" style="padding: 2rem; text-align: center; color: #64748b;">
                                        No hay campañas registradas. Crea tu primera campaña.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($campanas as $campana): 
                                    $roi = 0;
                                    if ($campana['costo_real'] > 0) {
                                        $roi = (($campana['ingresos_generados'] - $campana['costo_real']) / $campana['costo_real']) * 100;
                                    }
                                    
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($campana['estado']) {
                                        case 'activa':
                                            $statusClass = 'status-active';
                                            $statusText = 'Activa';
                                            break;
                                        case 'pausada':
                                            $statusClass = 'status-paused';
                                            $statusText = 'Pausada';
                                            break;
                                        case 'finalizada':
                                            $statusClass = 'status-completed';
                                            $statusText = 'Finalizada';
                                            break;
                                    }

                                    $canalTexto = match($campana['canal']) {
                                        'redes_sociales' => 'Redes Sociales',
                                        'email' => 'Email',
                                        'publicidad_pagada' => 'Publicidad Pagada',
                                        'SEO' => 'SEO',
                                        'otros' => 'Otros',
                                        default => ucfirst($campana['canal'])
                                    };
                                ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 1rem; font-weight: 600; color: #1e293b;">
                                        <?php echo htmlspecialchars($campana['nombre_campana']); ?>
                                    </td>
                                    <td style="padding: 1rem; color: #64748b;">
                                        <?php echo $canalTexto; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span class="campaign-status <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; color: #64748b;">
                                        $<?php echo number_format($campana['presupuesto'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; color: #64748b;">
                                        $<?php echo number_format($campana['costo_real'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; font-weight: 600; color: #10b981;">
                                        $<?php echo number_format($campana['ingresos_generados'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-weight: 700; color: <?php echo $roi >= 0 ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo number_format($roi, 2); ?>%
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-size: 0.875rem; color: #64748b;">
                                        <?php echo date('d/m/Y', strtotime($campana['fecha_inicio'])); ?>
                                        <?php if ($campana['fecha_fin']): ?>
                                            <br>→ <?php echo date('d/m/Y', strtotime($campana['fecha_fin'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <button class="btn-icon" onclick="editarCampana(<?php echo $campana['id']; ?>)" title="Editar">
                                                <span class="material-symbols-rounded">edit</span>
                                            </button>
                                            <?php if ($campana['estado'] !== 'activa'): ?>
                                                <button class="btn-icon-danger" onclick="eliminarCampana(<?php echo $campana['id']; ?>, '<?php echo htmlspecialchars($campana['nombre_campana']); ?>')" title="Eliminar">
                                                    <span class="material-symbols-rounded">delete</span>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon" style="background: #f1f5f9; color: #94a3b8; cursor: not-allowed;" title="No se puede eliminar una campaña activa">
                                                    <span class="material-symbols-rounded">delete</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($seccion === 'leads'): ?>
            <!-- ============================================ -->
            <!-- SECCIÓN: GESTIÓN DE LEADS -->
            <!-- ============================================ -->
            
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Gestión de Leads</h1>
                        <div class="header-subtitle">Administra y da seguimiento a tus clientes potenciales</div>
                    </div>
                    <div class="header-icon">
                        <span class="material-symbols-rounded">group</span>
                    </div>
                </div>
            </header>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-rounded">check_circle</span>
                    <span><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-rounded">error</span>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">group</span>
                        Todos los Leads
                    </h2>
                    <button class="btn-primary" onclick="mostrarModalCrearLead()">
                        <span class="material-symbols-rounded">add</span>
                        Nuevo Lead
                    </button>
                </div>

                <!-- Tabla de Leads -->
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden;">
                        <thead style="background: linear-gradient(135deg, #dca632ff, #b57b10ff); color: white;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">Nombre</th>
                                <th style="padding: 1rem; text-align: left;">Correo</th>
                                <th style="padding: 1rem; text-align: left;">Teléfono</th>
                                <th style="padding: 1rem; text-align: center;">Estado</th>
                                <th style="padding: 1rem; text-align: left;">Fuente</th>
                                <th style="padding: 1rem; text-align: center;">Registro</th>
                                <th style="padding: 1rem; text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                                <tr>
                                    <td colspan="7" style="padding: 2rem; text-align: center; color: #64748b;">
                                        No hay leads registrados. Crea tu primer lead.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): 
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($lead['estado']) {
                                        case 'activo':
                                            $statusClass = 'status-active';
                                            $statusText = 'Activo';
                                            break;
                                        case 'inactivo':
                                            $statusClass = 'status-paused';
                                            $statusText = 'Inactivo';
                                            break;
                                        default:
                                            $statusClass = 'status-paused';
                                            $statusText = ucfirst($lead['estado']);
                                            break;
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 1rem; font-weight: 600; color: #1e293b;">
                                        <?php echo htmlspecialchars($lead['nombre']); ?>
                                    </td>
                                    <td style="padding: 1rem; color: #64748b;">
                                        <?php echo htmlspecialchars($lead['correo']); ?>
                                    </td>
                                    <td style="padding: 1rem; color: #64748b;">
                                        <?php echo htmlspecialchars($lead['telefono']); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span class="campaign-status <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; color: #64748b;">
                                        <?php echo htmlspecialchars($lead['fuente_adquisicion']); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-size: 0.875rem; color: #64748b;">
                                        <?php echo date('d/m/Y', strtotime($lead['fecha_registro'])); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <button class="btn-icon" onclick="editarLead(<?php echo $lead['id']; ?>)" title="Editar">
                                                <span class="material-symbols-rounded">edit</span>
                                            </button>
                                            <?php if ($lead['estado'] !== 'activo'): ?>
                                                <button class="btn-icon-danger" onclick="eliminarLead(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['nombre']); ?>')" title="Eliminar">
                                                    <span class="material-symbols-rounded">delete</span>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon" style="background: #f1f5f9; color: #94a3b8; cursor: not-allowed;" title="No se puede eliminar un lead activo">
                                                    <span class="material-symbols-rounded">delete</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($seccion === 'resenas'): ?>
            <!-- ============================================ -->
            <!-- SECCIÓN: GESTIÓN DE RESEÑAS -->
            <!-- ============================================ -->
            
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Gestión de Reseñas</h1>
                        <div class="header-subtitle">Administra las reseñas y comentarios de clientes</div>
                    </div>
                    <div class="header-icon">
                        <span class="material-symbols-rounded">reviews</span>
                    </div>
                </div>
            </header>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-rounded">check_circle</span>
                    <span><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-rounded">error</span>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">reviews</span>
                        Todas las Reseñas
                    </h2>
                </div>

                <div>
                    <?php if (empty($resenas)): ?>
                        <div class="alert alert-warning">
                            <span class="material-symbols-rounded">info</span>
                            <span>No hay reseñas registradas.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($resenas as $resena): 
                            $statusClass = '';
                            $statusText = '';
                            switch ($resena['estado']) {
                                case 'activa':
                                    $statusClass = 'status-aprobado';
                                    $statusText = 'Aprobado';
                                    break;
                                case 'oculta':
                                    $statusClass = 'status-rechazado';
                                    $statusText = 'Oculto';
                                    break;
                                default:
                                    $statusClass = 'status-pendiente';
                                    $statusText = 'Pendiente';
                                    break;
                            }
                        ?>
                        <div class="review-card <?php echo $resena['estado']; ?>">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php echo strtoupper(substr($resena['cliente_nombre'], 0, 1)); ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h4><?php echo htmlspecialchars($resena['cliente_nombre']); ?></h4>
                                        <p><?php echo htmlspecialchars($resena['correo']); ?></p>
                                    </div>
                                </div>
                                <div class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="material-symbols-rounded star <?php echo $i <= $resena['calificacion'] ? 'filled' : ''; ?>">
                                            star
                                        </span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-content">
                                <?php echo htmlspecialchars($resena['comentario']); ?>
                            </div>
                            <div class="review-footer">
                                <div class="review-date">
                                    <?php echo date('d/m/Y H:i', strtotime($resena['fecha_registro'])); ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span class="campaign-status <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <?php if ($resena['estado'] !== 'activa'): ?>
                                            <a href="marketing.php?seccion=resenas&cambiar_estado_resena=<?php echo $resena['id']; ?>&estado=activa" 
                                            class="btn-icon" 
                                            title="Aprobar"
                                            onclick="return confirm('¿Estás seguro de aprobar esta reseña?')">
                                                <span class="material-symbols-rounded">check</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($resena['estado'] !== 'oculta'): ?>
                                            <a href="marketing.php?seccion=resenas&cambiar_estado_resena=<?php echo $resena['id']; ?>&estado=oculta" 
                                            class="btn-icon-danger" 
                                            title="Ocultar"
                                            onclick="return confirm('¿Estás seguro de ocultar esta reseña?')">
                                                <span class="material-symbols-rounded">close</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>


        <?php elseif ($seccion === 'reportes'): ?>
            <!-- ============================================ -->
            <!-- SECCIÓN: REPORTES -->
            <!-- ============================================ -->
            
            <header class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Reportes y Análisis</h1>
                        <div class="header-subtitle">Métricas detalladas y análisis de desempeño</div>
                    </div>
                    <div class="header-icon">
                        <span class="material-symbols-rounded">analytics</span>
                    </div>
                </div>
            </header>

            <section class="campaigns-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="material-symbols-rounded">insights</span>
                        Métricas Clave
                    </h2>
                    <button class="btn-primary" onclick="generarReporte()">
                        <span class="material-symbols-rounded">download</span>
                        Descargar Reporte
                    </button>
                </div>

                <div class="dashboard-cards">
                    <div class="card campaigns">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Tasa de Conversión</div>
                                <div class="card-value"><?php echo $campanasActivas > 0 ? round(($clientes_nuevos / $campanasActivas) * 100, 2) : 0; ?>%</div>
                            </div>
                            <div class="card-icon">
                                <span class="material-symbols-rounded">trending_up</span>
                            </div>
                        </div>
                    </div>

                    <div class="card leads">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Costo por Lead</div>
                                <div class="card-value">$<?php echo $clientes_nuevos > 0 ? number_format($ventas_totales / $clientes_nuevos, 2) : 0; ?></div>
                            </div>
                            <div class="card-icon">
                                <span class="material-symbols-rounded">paid</span>
                            </div>
                        </div>
                    </div>

                    <div class="card roi">
                        <div class="card-header">
                            <div>
                                <div class="card-title">ROI Mensual</div>
                                <div class="card-value"><?php echo $roi_porcentaje; ?>%</div>
                            </div>
                            <div class="card-icon">
                                <span class="material-symbols-rounded">bar_chart</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reseñas Recientes en Reportes -->
                <div style="margin-top: 2rem;">
                    <h3 class="section-title">
                        <span class="material-symbols-rounded">reviews</span>
                        Reseñas Recientes
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <?php if (empty($resenasRecientes)): ?>
                            <div class="alert alert-warning">
                                <span class="material-symbols-rounded">info</span>
                                <span>No hay reseñas recientes.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($resenasRecientes as $resena): ?>
                                <div class="review-card <?php echo $resena['estado']; ?>" style="margin: 0;">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                <?php echo strtoupper(substr($resena['cliente_nombre'], 0, 1)); ?>
                                            </div>
                                            <div class="reviewer-details">
                                                <h4><?php echo htmlspecialchars($resena['cliente_nombre']); ?></h4>
                                            </div>
                                        </div>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="material-symbols-rounded star <?php echo $i <= $resena['calificacion'] ? 'filled' : ''; ?>">
                                                    star
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <?php echo htmlspecialchars(substr($resena['comentario'], 0, 100)) . (strlen($resena['comentario']) > 100 ? '...' : ''); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reseñas Más Destacadas -->
                <div style="margin-top: 2rem;">
                    <h3 class="section-title">
                        <span class="material-symbols-rounded">star</span>
                        Reseñas Más Destacadas
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-top: 1rem;">
                        <?php if (empty($resenasDestacadas)): ?>
                            <div class="alert alert-warning">
                                <span class="material-symbols-rounded">info</span>
                                <span>No hay reseñas destacadas.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($resenasDestacadas as $resena): ?>
                                <div class="review-card aprobado" style="margin: 0; border-left: 4px solid #fbbf24;">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar" style="background: linear-gradient(135deg, #fbbf24, #f59e0b);">
                                                <?php echo strtoupper(substr($resena['cliente_nombre'], 0, 1)); ?>
                                            </div>
                                            <div class="reviewer-details">
                                                <h4><?php echo htmlspecialchars($resena['cliente_nombre']); ?></h4>
                                            </div>
                                        </div>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="material-symbols-rounded star <?php echo $i <= $resena['calificacion'] ? 'filled' : ''; ?>">
                                                    star
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <?php echo htmlspecialchars($resena['comentario']); ?>
                                    </div>
                                    <div class="review-footer">
                                        <div class="review-date">
                                           <?php echo date('d/m/Y', strtotime($resena['fecha_registro'])); ?>
                                        </div>
                                        <div style="color: #f59e0b; font-weight: 600;">
                                            <?php echo $resena['calificacion']; ?> estrellas
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php endif; ?>

    </main>

    <!-- Modal para crear campaña -->
    <div id="modalCampana" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Nueva Campaña</h2>
                <button onclick="cerrarModal()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #64748b;">×</button>
            </div>

            <form method="POST" action="marketing.php" style="display: flex; flex-direction: column; gap: 1rem;" onsubmit="return validarFormularioCampana()">
                <input type="hidden" name="accion" value="crear_campana">
                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Nombre de la Campaña *</label>
                    <input type="text" name="nombre_campana" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTexto(this)">
                    <div class="error-message" id="error-nombre">Solo se permiten letras, números y espacios</div>
                </div>
                 
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Canal *</label>
                        <select name="canal" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="redes_sociales">Redes Sociales</option>
                            <option value="email">Email Marketing</option>
                            <option value="publicidad_pagada">Publicidad Pagada</option>
                            <option value="SEO">SEO</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Estado *</label>
                        <select name="estado" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="activa">Activa</option>
                            <option value="pausada">Pausada</option>
                            <option value="finalizada">Finalizada</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Presupuesto *</label>
                        <input type="number" name="presupuesto" step="0.01" min="0" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                        <div class="error-message" id="error-presupuesto">El presupuesto debe ser un número positivo</div>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Costo Real</label>
                        <input type="number" name="costo_real" step="0.01" min="0" value="0" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                        <div class="error-message" id="error-costo">El costo debe ser un número positivo</div>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Ingresos Generados</label>
                    <input type="number" name="ingresos_generados" step="0.01" min="0" value="0" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                    <div class="error-message" id="error-ingresos">Los ingresos deben ser un número positivo</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" id="fecha_inicio">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fecha Fin</label>
                        <input type="date" name="fecha_fin" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" id="fecha_fin">
                        <div class="error-message" id="error-fecha">La fecha fin no puede ser anterior a la fecha inicio</div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" onclick="cerrarModal()" style="flex: 1; padding: 0.75rem; background: #f1f5f9; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #64748b;">
                        Cancelar
                    </button>
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #b1982aff, #a96325ff); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Crear Campaña
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para EDITAR campaña -->
    <div id="modalEditarCampana" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Editar Campaña</h2>
                <button onclick="cerrarModalEditar()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #64748b;">×</button>
            </div>

            <form method="POST" action="marketing.php" style="display: flex; flex-direction: column; gap: 1rem;" onsubmit="return validarFormularioCampana()">
                <input type="hidden" name="accion" value="editar_campana">
                <input type="hidden" name="id_campana" id="edit_id_campana">

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Nombre de la Campaña *</label>
                    <input type="text" name="nombre_campana" id="edit_nombre_campana" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTexto(this)">
                    <div class="error-message" id="error-edit-nombre">Solo se permiten letras, números y espacios</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Canal *</label>
                        <select name="canal" id="edit_canal" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="redes_sociales">Redes Sociales</option>
                            <option value="email">Email Marketing</option>
                            <option value="publicidad_pagada">Publicidad Pagada</option>
                            <option value="SEO">SEO</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Estado *</label>
                        <select name="estado" id="edit_estado" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="activa">Activa</option>
                            <option value="pausada">Pausada</option>
                            <option value="finalizada">Finalizada</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Presupuesto *</label>
                        <input type="number" name="presupuesto" id="edit_presupuesto" step="0.01" min="0" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                        <div class="error-message" id="error-edit-presupuesto">El presupuesto debe ser un número positivo</div>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Costo Real</label>
                        <input type="number" name="costo_real" id="edit_costo_real" step="0.01" min="0" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                        <div class="error-message" id="error-edit-costo">El costo debe ser un número positivo</div>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Ingresos Generados</label>
                    <input type="number" name="ingresos_generados" id="edit_ingresos_generados" step="0.01" min="0" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarNumero(this)">
                    <div class="error-message" id="error-edit-ingresos">Los ingresos deben ser un número positivo</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" id="edit_fecha_inicio" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="edit_fecha_fin" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                        <div class="error-message" id="error-edit-fecha">La fecha fin no puede ser anterior a la fecha inicio</div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" onclick="cerrarModalEditar()" style="flex: 1; padding: 0.75rem; background: #f1f5f9; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #64748b;">
                        Cancelar
                    </button>
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #b1982aff, #a96325ff); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para crear lead -->
    <div id="modalLead" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Nuevo Lead</h2>
                <button onclick="cerrarModalLead()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #64748b;">×</button>
            </div>

            <form method="POST" action="marketing.php" style="display: flex; flex-direction: column; gap: 1rem;" onsubmit="return validarFormularioLead()">
                <input type="hidden" name="accion" value="crear_lead">
                
                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Nombre Completo *</label>
                    <input type="text" name="nombre" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTexto(this)">
                    <div class="error-message" id="error-lead-nombre">Solo se permiten letras y espacios</div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Correo Electrónico *</label>
                    <input type="email" name="correo" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarEmail(this)">
                    <div class="error-message" id="error-lead-correo">Formato de correo electrónico inválido</div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Teléfono</label>
                    <input type="tel" name="telefono" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTelefono(this)">
                    <div class="error-message" id="error-lead-telefono">Formato de teléfono inválido (solo números, +, -, espacios)</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Estado *</label>
                        <select name="estado" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fuente *</label>
                        <select name="fuente_adquisicion" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="redes_sociales">Redes Sociales</option>
                            <option value="sitio_web">Sitio Web</option>
                            <option value="referido">Referido</option>
                            <option value="evento">Evento</option>
                            <option value="publicidad">Publicidad</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" onclick="cerrarModalLead()" style="flex: 1; padding: 0.75rem; background: #f1f5f9; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #64748b;">
                        Cancelar
                    </button>
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #b1982aff, #a96325ff); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Crear Lead
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar lead -->
    <div id="modalEditarLead" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Editar Lead</h2>
                <button onclick="cerrarModalEditarLead()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: #64748b;">×</button>
            </div>

            <form method="POST" action="marketing.php" style="display: flex; flex-direction: column; gap: 1rem;" onsubmit="return validarFormularioLead()">
                <input type="hidden" name="accion" value="editar_lead">
                <input type="hidden" name="id_lead" id="edit_id_lead">
                
                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Nombre Completo *</label>
                    <input type="text" name="nombre" id="edit_nombre" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTexto(this)">
                    <div class="error-message" id="error-edit-lead-nombre">Solo se permiten letras y espacios</div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Correo Electrónico *</label>
                    <input type="email" name="correo" id="edit_correo" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarEmail(this)">
                    <div class="error-message" id="error-edit-lead-correo">Formato de correo electrónico inválido</div>
                </div>

                <div>
                    <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Teléfono</label>
                    <input type="tel" name="telefono" id="edit_telefono" style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;" oninput="validarTelefono(this)">
                    <div class="error-message" id="error-edit-lead-telefono">Formato de teléfono inválido (solo números, +, -, espacios)</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Estado *</label>
                        <select name="estado" id="edit_estado" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Fuente *</label>
                        <select name="fuente_adquisicion" id="edit_fuente" required style="width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                            <option value="redes_sociales">Redes Sociales</option>
                            <option value="sitio_web">Sitio Web</option>
                            <option value="referido">Referido</option>
                            <option value="evento">Evento</option>
                            <option value="publicidad">Publicidad</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="button" onclick="cerrarModalEditarLead()" style="flex: 1; padding: 0.75rem; background: #f1f5f9; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: #64748b;">
                        Cancelar
                    </button>
                    <button type="submit" style="flex: 1; padding: 0.75rem; background: linear-gradient(135deg, #b1982aff, #a96325ff); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Toggle del sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
        });

        // Dropdown del perfil
        document.getElementById('userProfile').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdownMenu = document.getElementById('profileDropdownMenu');
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', function() {
            const dropdownMenu = document.getElementById('profileDropdownMenu');
            dropdownMenu.classList.remove('show');
        });

        // Funciones del modal
        function mostrarModalCrear() {
            document.getElementById('modalCampana').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modalCampana').style.display = 'none';
        }

        // Funciones del modal EDITAR
        function cerrarModalEditar() {
            document.getElementById('modalEditarCampana').style.display = 'none';
        }

        function editarCampana(id) {
            // Hacer petición AJAX para obtener los datos de la campaña
            fetch('marketing.php?obtener_campana=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('edit_id_campana').value = data.id;
                        document.getElementById('edit_nombre_campana').value = data.nombre_campana;
                        document.getElementById('edit_canal').value = data.canal;
                        document.getElementById('edit_estado').value = data.estado;
                        document.getElementById('edit_presupuesto').value = data.presupuesto;
                        document.getElementById('edit_costo_real').value = data.costo_real;
                        document.getElementById('edit_ingresos_generados').value = data.ingresos_generados;
                        document.getElementById('edit_fecha_inicio').value = data.fecha_inicio;
                        document.getElementById('edit_fecha_fin').value = data.fecha_fin || '';
                        
                        document.getElementById('modalEditarCampana').style.display = 'flex';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function eliminarCampana(id, nombre) {
            if (confirm('¿Estás seguro de eliminar la campaña "' + nombre + '"?')) {
                window.location.href = 'marketing.php?eliminar_campana=' + id;
            }
        }

        // Funciones para Leads
        function mostrarModalCrearLead() {
            document.getElementById('modalLead').style.display = 'flex';
        }

        function cerrarModalLead() {
            document.getElementById('modalLead').style.display = 'none';
        }

        function cerrarModalEditarLead() {
            document.getElementById('modalEditarLead').style.display = 'none';
        }

        function editarLead(id) {
            // Hacer petición AJAX para obtener los datos del lead
            fetch('marketing.php?obtener_lead=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('edit_id_lead').value = data.id;
                        document.getElementById('edit_nombre').value = data.nombre;
                        document.getElementById('edit_correo').value = data.correo;
                        document.getElementById('edit_telefono').value = data.telefono || '';
                        document.getElementById('edit_estado').value = data.estado;
                        document.getElementById('edit_fuente').value = data.fuente_adquisicion;
                        
                        document.getElementById('modalEditarLead').style.display = 'flex';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function eliminarLead(id, nombre) {
            if (confirm('¿Estás seguro de eliminar el lead "' + nombre + '"?')) {
                window.location.href = 'marketing.php?eliminar_lead=' + id;
            }
        }

        // Función para generar reporte (placeholder)
        function generarReporte() {
            alert('Función de generación de reporte en desarrollo. Próximamente disponible.');
        }

        // ============================================
        // VALIDACIONES DE FORMULARIOS MEJORADAS
        // ============================================

        // Validar texto (solo letras, números y espacios)
        function validarTexto(input) {
            const regex = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]*$/;
            const errorId = input.id.includes('edit') ? 'error-edit-lead-nombre' : 'error-lead-nombre';
            const errorElement = document.getElementById(errorId);
            
            if (!regex.test(input.value)) {
                input.classList.add('input-error');
                errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                errorElement.style.display = 'none';
                return true;
            }
        }

        // Validar números (solo números positivos)
        function validarNumero(input) {
            const value = parseFloat(input.value);
            const errorId = input.name + (input.id.includes('edit') ? '-edit' : '');
            const errorElement = document.getElementById('error-' + errorId);
            
            if (isNaN(value) || value < 0) {
                input.classList.add('input-error');
                if (errorElement) errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                if (errorElement) errorElement.style.display = 'none';
                return true;
            }
        }

        // Validar email
        function validarEmail(input) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const errorId = input.id.includes('edit') ? 'error-edit-lead-correo' : 'error-lead-correo';
            const errorElement = document.getElementById(errorId);
            
            if (!regex.test(input.value)) {
                input.classList.add('input-error');
                errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                errorElement.style.display = 'none';
                return true;
            }
        }

        // Validar teléfono
        function validarTelefono(input) {
            const regex = /^[0-9\-\+\s\(\)]{10,}$/;
            const errorId = input.id.includes('edit') ? 'error-edit-lead-telefono' : 'error-lead-telefono';
            const errorElement = document.getElementById(errorId);
            
            if (input.value && !regex.test(input.value)) {
                input.classList.add('input-error');
                errorElement.style.display = 'block';
                return false;
            } else {
                input.classList.remove('input-error');
                errorElement.style.display = 'none';
                return true;
            }
        }

        // Validar fechas
        function validarFechas() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const errorElement = document.getElementById('error-fecha');
            
            if (fechaFin && fechaFin < fechaInicio) {
                errorElement.style.display = 'block';
                return false;
            } else {
                errorElement.style.display = 'none';
                return true;
            }
        }

        // Validación completa del formulario de campaña
        function validarFormularioCampana() {
            // Remover todas las validaciones que bloquean el envío
            // Solo hacer validaciones básicas del navegador
            
            const fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
            const fechaFin = document.querySelector('input[name="fecha_fin"]').value;
            
            if (fechaFin && fechaFin < fechaInicio) {
                alert('La fecha de fin no puede ser anterior a la fecha de inicio');
                return false;
            }
            
            return true;
        }

        

        // Función auxiliar para mostrar errores
        function mostrarError(input, mensaje) {
            // Remover error anterior
            const errorExistente = input.parentNode.querySelector('.error-message');
            if (errorExistente) {
                errorExistente.remove();
            }
            
            // Agregar nuevo error
            input.classList.add('input-error');
            const errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            errorElement.textContent = mensaje;
            errorElement.style.display = 'block';
            input.parentNode.appendChild(errorElement);
            
            // Auto-remover después de 3 segundos
            setTimeout(() => {
                errorElement.remove();
                input.classList.remove('input-error');
            }, 3000);
        }

        // Event listeners para validación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            // Validación de fechas en tiempo real
            const fechaInicio = document.getElementById('fecha_inicio');
            const fechaFin = document.getElementById('fecha_fin');
            
            if (fechaInicio) {
                fechaInicio.addEventListener('change', validarFechas);
            }
            if (fechaFin) {
                fechaFin.addEventListener('change', validarFechas);
            }
        });
    
        // Datos desde PHP para los gráficos
        const labelsVentas = <?php echo $jsonLabelsVentas; ?>;
        const dataVentas = <?php echo $jsonDataVentas; ?>;
        const labelsCanales = <?php echo $jsonLabelsCanales; ?>;
        const dataCanales = <?php echo $jsonDataCanales; ?>;
        const coloresCanales = <?php echo $jsonColoresCanales; ?>;

        // Gráfico de Ventas Lineales
        if (document.getElementById('ventasChart')) {
            const ctxVentas = document.getElementById('ventasChart').getContext('2d');
            
            const gradientVentas = ctxVentas.createLinearGradient(0, 0, 0, 300);
            gradientVentas.addColorStop(0, 'rgba(102, 126, 234, 0.4)');
            gradientVentas.addColorStop(1, 'rgba(102, 126, 234, 0)');

            new Chart(ctxVentas, {
                type: 'line',
                data: {
                    labels: labelsVentas,
                    datasets: [{
                        label: 'Ventas Totales ($)',
                        data: dataVentas,
                        borderColor: '#667eea',
                        backgroundColor: gradientVentas,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-CO');
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return 'Ventas: $' + context.parsed.y.toLocaleString('es-CO');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico Circular de Canales
        if (document.getElementById('canalesChart')) {
            const ctxCanales = document.getElementById('canalesChart').getContext('2d');
            
            new Chart(ctxCanales, {
                type: 'doughnut',
                data: {
                    labels: labelsCanales,
                    datasets: [{
                        label: 'Ingresos por Canal ($)',
                        data: dataCanales,
                        backgroundColor: coloresCanales,
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: {
                                    size: 12,
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.parsed || 0;
                                    
                                    // Calcular porcentaje
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    
                                    return label + ': $' + value.toLocaleString('es-CO') + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Cerrar modales al presionar ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalEditar();
                cerrarModalLead();
                cerrarModalEditarLead();
            }
        });
    </script>
</body>
</html>