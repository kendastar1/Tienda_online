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
            $_SESSION['usuario_correo'] = $usuario['correo'];
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

// Procesar actualización de configuración del usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['config_action'])) {
    $action = $_POST['config_action'];
    
    try {
        if ($action == 'update_profile') {
            $user_id = $_SESSION['usuario_id'];
            $nombre = trim($_POST['nombre']);
            $correo = trim($_POST['correo']);
            $password_actual = $_POST['password_actual'];
            $nueva_password = $_POST['nueva_password'];
            $confirmar_password = $_POST['confirmar_password'];
            
            // Validaciones básicas
            if (empty($nombre) || empty($correo)) {
                $_SESSION['error'] = "Nombre y correo son obligatorios";
            } else {
                // Verificar si el correo ya existe (excluyendo el usuario actual)
                $sqlCheck = "SELECT id FROM usuarios WHERE correo = ? AND id != ?";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([$correo, $user_id]);
                
                if ($stmtCheck->fetch()) {
                    $_SESSION['error'] = "El correo electrónico ya está registrado";
                } else {
                    // Obtener datos actuales del usuario
                    $sqlCurrent = "SELECT password FROM usuarios WHERE id = ?";
                    $stmtCurrent = $pdo->prepare($sqlCurrent);
                    $stmtCurrent->execute([$user_id]);
                    $usuario_actual = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
                    
                    // Si se quiere cambiar la contraseña
                    if (!empty($nueva_password)) {
                        // Verificar contraseña actual
                        if (empty($password_actual)) {
                            $_SESSION['error'] = "Debe ingresar su contraseña actual para cambiar la contraseña";
                        } elseif (!password_verify($password_actual, $usuario_actual['password'])) {
                            $_SESSION['error'] = "La contraseña actual es incorrecta";
                        } elseif (strlen($nueva_password) < 6) {
                            $_SESSION['error'] = "La nueva contraseña debe tener al menos 6 caracteres";
                        } elseif ($nueva_password !== $confirmar_password) {
                            $_SESSION['error'] = "Las nuevas contraseñas no coinciden";
                        } else {
                            // Hash de la nueva contraseña
                            $passwordHash = password_hash($nueva_password, PASSWORD_DEFAULT);
                            $sql = "UPDATE usuarios SET nombre = ?, correo = ?, password = ? WHERE id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$nombre, $correo, $passwordHash, $user_id]);
                            
                            $_SESSION['success'] = "Perfil y contraseña actualizados correctamente";
                            $_SESSION['usuario_nombre'] = $nombre;
                            $_SESSION['usuario_correo'] = $correo;
                        }
                    } else {
                        // Actualizar solo nombre y correo
                        $sql = "UPDATE usuarios SET nombre = ?, correo = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nombre, $correo, $user_id]);
                        
                        $_SESSION['success'] = "Perfil actualizado correctamente";
                        $_SESSION['usuario_nombre'] = $nombre;
                        $_SESSION['usuario_correo'] = $correo;
                    }
                    
                    if (!isset($_SESSION['error'])) {
                        // Registrar actividad
                        registrarActividad($pdo, $_SESSION['usuario_id'], 'Perfil actualizado', "Usuario actualizó su perfil", 'sistema');
                    }
                }
            }
            
            // Redirigir a la sección de configuración
            header('Location: admin.php?seccion=configuracion');
            exit();
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header('Location: admin.php?seccion=configuracion');
        exit();
    }
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

// Consulta para obtener sucursales
try {
    $sqlSucursales = "SELECT id, nombre FROM sucursales WHERE estado = 'activa' ORDER BY nombre";
    $stmtSucursales = $pdo->query($sqlSucursales);
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $sucursales = [];
    error_log("Error al cargar sucursales: " . $e->getMessage());
}

// Procesar operaciones de usuarios (crear, actualizar, eliminar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action == 'create_user') {
            // Crear nuevo usuario
            $nombre = trim($_POST['nombre']);
            $correo = trim($_POST['correo']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirmPassword'];
            $rol_id = $_POST['rol_id'];
            
            // Validaciones
            if (empty($nombre) || empty($correo) || empty($password) || empty($rol_id)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } elseif ($password !== $confirmPassword) {
                $_SESSION['error'] = "Las contraseñas no coinciden";
            } elseif (strlen($password) < 6) {
                $_SESSION['error'] = "La contraseña debe tener al menos 6 caracteres";
            } else {
                // Verificar si el correo ya existe
                $sqlCheck = "SELECT id FROM usuarios WHERE correo = ?";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([$correo]);
                
                if ($stmtCheck->fetch()) {
                    $_SESSION['error'] = "El correo electrónico ya está registrado";
                } else {
                    // Hash de la contraseña
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insertar nuevo usuario
                    $sql = "INSERT INTO usuarios (nombre, correo, password, rol_id, fecha_registro, estado) 
                            VALUES (?, ?, ?, ?, NOW(), 'activo')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $correo, $passwordHash, $rol_id]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Usuario creado', "Nuevo usuario registrado: $nombre", 'sistema');
                    
                    $_SESSION['success'] = "Usuario registrado correctamente";
                }
            }
            
        } elseif ($action == 'update_user') {
            // Actualizar usuario existente
            $user_id = $_POST['user_id'];
            $nombre = trim($_POST['nombre']);
            $correo = trim($_POST['correo']);
            $password = $_POST['password'];
            $rol_id = $_POST['rol_id'];
            
            // Validaciones básicas
            if (empty($nombre) || empty($correo) || empty($rol_id)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } else {
                // Verificar si el correo ya existe (excluyendo el usuario actual)
                $sqlCheck = "SELECT id FROM usuarios WHERE correo = ? AND id != ?";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([$correo, $user_id]);
                
                if ($stmtCheck->fetch()) {
                    $_SESSION['error'] = "El correo electrónico ya está registrado";
                } else {
                    // Actualizar usuario (con o sin contraseña)
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $_SESSION['error'] = "La contraseña debe tener al menos 6 caracteres";
                        } else {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $sql = "UPDATE usuarios SET nombre = ?, correo = ?, password = ?, rol_id = ? WHERE id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$nombre, $correo, $passwordHash, $rol_id, $user_id]);
                        }
                    } else {
                        $sql = "UPDATE usuarios SET nombre = ?, correo = ?, rol_id = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$nombre, $correo, $rol_id, $user_id]);
                    }
                    
                    if (!isset($_SESSION['error'])) {
                        // Registrar actividad
                        registrarActividad($pdo, $_SESSION['usuario_id'], 'Usuario actualizado', "Usuario actualizado: $nombre", 'sistema');
                        
                        $_SESSION['success'] = "Usuario actualizado correctamente";
                    }
                }
            }
            
        } elseif ($action == 'delete_user') {
            // Eliminar usuario
            $user_id = $_POST['user_id'];
            
            // Obtener información del usuario antes de eliminarlo
            $sqlInfo = "SELECT nombre FROM usuarios WHERE id = ?";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([$user_id]);
            $usuario = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // No permitir eliminar al usuario actual
                if ($user_id == $_SESSION['usuario_id']) {
                    $_SESSION['error'] = "No puede eliminar su propio usuario";
                } else {
                    $sql = "DELETE FROM usuarios WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Usuario eliminado', "Usuario eliminado: " . $usuario['nombre'], 'sistema');
                    
                    $_SESSION['success'] = "Usuario eliminado correctamente";
                }
            } else {
                $_SESSION['error'] = "Usuario no encontrado";
            }
        }
        
        // Redirigir para evitar reenvío del formulario
        header('Location: admin.php?seccion=usuarios');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header('Location: admin.php?seccion=usuarios');
        exit();
    }
}

// Procesar operaciones de inventario (crear, actualizar, eliminar productos)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inventario_action'])) {
    $action = $_POST['inventario_action'];
    
    try {
        if ($action == 'create_product') {
            // Crear nuevo producto
            $nombre = trim($_POST['nombre']);
            $descripcion = trim($_POST['descripcion']);
            $precio = $_POST['precio'];
            $porcentaje_descuento = !empty($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : 0;
            $descuento = ($precio * $porcentaje_descuento) / 100;
            $cantidad = $_POST['cantidad'];
            $categoria = $_POST['categoria'];
            $sucursal_id = $_POST['sucursal_id'];
            $estado = $_POST['estado'];
            $imagen = '';
            
            // Validaciones
            if (empty($nombre) || empty($descripcion) || empty($precio) || empty($cantidad) || empty($categoria) || empty($sucursal_id)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } elseif (!is_numeric($precio) || $precio <= 0) {
                $_SESSION['error'] = "El precio debe ser un número positivo";
            } elseif (!is_numeric($cantidad) || $cantidad < 0) {
                $_SESSION['error'] = "La cantidad debe ser un número positivo o cero";
            } elseif (!is_numeric($porcentaje_descuento) || $porcentaje_descuento < 0 || $porcentaje_descuento > 100) {
                $_SESSION['error'] = "El porcentaje de descuento debe estar entre 0 y 100";
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
                    $sql = "INSERT INTO productos_stock (nombre, descripcion, precio, descuento, porcentaje_descuento, cantidad, categoria, sucursal_id, estado, imagen, fecha_creacion) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion, $precio, $descuento, $porcentaje_descuento, $cantidad, $categoria, $sucursal_id, $estado, $imagen]);
                    
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
            $porcentaje_descuento = !empty($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : 0;
            $descuento = ($precio * $porcentaje_descuento) / 100;
            $cantidad = $_POST['cantidad'];
            $categoria = $_POST['categoria'];
            $sucursal_id = $_POST['sucursal_id'];
            $estado = $_POST['estado'];
            $current_imagen = $_POST['current_imagen'] ?? '';
            $imagen = $current_imagen;
            
            // Validaciones básicas
            if (empty($nombre) || empty($descripcion) || empty($precio) || empty($cantidad) || empty($categoria) || empty($sucursal_id)) {
                $_SESSION['error'] = "Todos los campos son obligatorios";
            } elseif (!is_numeric($precio) || $precio <= 0) {
                $_SESSION['error'] = "El precio debe ser un número positivo";
            } elseif (!is_numeric($cantidad) || $cantidad < 0) {
                $_SESSION['error'] = "La cantidad debe ser un número positivo o cero";
            } elseif (!is_numeric($porcentaje_descuento) || $porcentaje_descuento < 0 || $porcentaje_descuento > 100) {
                $_SESSION['error'] = "El porcentaje de descuento debe estar entre 0 y 100";
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
                    $sql = "UPDATE productos_stock SET nombre = ?, descripcion = ?, precio = ?, descuento = ?, porcentaje_descuento = ?, cantidad = ?, categoria = ?, sucursal_id = ?, estado = ?, imagen = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nombre, $descripcion, $precio, $descuento, $porcentaje_descuento, $cantidad, $categoria, $sucursal_id, $estado, $imagen, $product_id]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Producto actualizado', "Producto actualizado: $nombre", 'inventario');
                    
                    $_SESSION['success'] = "Producto actualizado correctamente";
                }
            }
          }elseif ($action == 'transferencia_stock') {
            $producto_id = $_POST['producto_id'];
            $sucursal_origen = $_POST['sucursal_origen'];
            $sucursal_destino = $_POST['sucursal_destino'];
            $cantidad = intval($_POST['cantidad']);
            $motivo = $_POST['motivo'] ?? '';

            if ($sucursal_origen == $sucursal_destino) {
                $_SESSION['error'] = "La sucursal origen y destino no pueden ser la misma.";
            } else {
                // Verificar stock en origen
                $sql = "SELECT cantidad FROM productos_stock WHERE id = ? AND sucursal_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$producto_id, $sucursal_origen]);
                $origen = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$origen || $origen['cantidad'] < $cantidad) {
                    $_SESSION['error'] = "No hay suficiente stock en la sucursal origen.";
                } else {

                    // RESTAR STOCK EN ORIGEN
                    $pdo->prepare("UPDATE productos_stock SET cantidad = cantidad - ? WHERE id = ? AND sucursal_id = ?")
                        ->execute([$cantidad, $producto_id, $sucursal_origen]);

                    // SUMAR STOCK EN DESTINO
                    $pdo->prepare("UPDATE productos_stock SET cantidad = cantidad + ? WHERE id = ? AND sucursal_id = ?")
                        ->execute([$cantidad, $producto_id, $sucursal_destino]);

                    // Registrar movimiento salida
                    $pdo->prepare("INSERT INTO movimientos_stock (producto_id, tipo, cantidad, motivo, fecha, usuario_id, sucursal_id)
                        VALUES (?, 'salida', ?, ?, NOW(), ?, ?)")
                        ->execute([$producto_id, $cantidad, $motivo, $_SESSION['usuario_id'], $sucursal_origen]);

                    // Registrar movimiento entrada
                    $pdo->prepare("INSERT INTO movimientos_stock (producto_id, tipo, cantidad, motivo, fecha, usuario_id, sucursal_id)
                        VALUES (?, 'entrada', ?, ?, NOW(), ?, ?)")
                        ->execute([$producto_id, $cantidad, $motivo, $_SESSION['usuario_id'], $sucursal_destino]);

                    $_SESSION['success'] = "Transferencia realizada correctamente.";
                }
            }

            header("Location: admin.php?seccion=inventario");
            exit();
        }
 
        elseif ($action == 'delete_product') {
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
              elseif ($action == 'movimiento_stock') {

              $producto_id = intval($_POST['producto_id']);
              $tipo = $_POST['tipo']; // entrada / salida / ajuste
              $cantidad = intval($_POST['cantidad']);
              $motivo = trim($_POST['motivo'] ?? '');
              $usuario_id = $_SESSION['usuario_id'];

              // Validaciones
              if ($cantidad <= 0) {
                  $_SESSION['error'] = "La cantidad debe ser mayor que 0";
              } else {
                  // Obtener cantidad actual
                  $sqlActual = "SELECT cantidad FROM productos_stock WHERE id = ?";
                  $stmtActual = $pdo->prepare($sqlActual);
                  $stmtActual->execute([$producto_id]);
                  $producto = $stmtActual->fetch(PDO::FETCH_ASSOC);

                  if (!$producto) {
                      $_SESSION['error'] = "Producto no encontrado";
                  } else {
                      $cantidadActual = intval($producto['cantidad']);
                      $nuevaCantidad = $cantidadActual;

                      if ($tipo == 'entrada') {
                          $nuevaCantidad += $cantidad;
                      } elseif ($tipo == 'salida') {
                          if ($cantidad > $cantidadActual) {
                              $_SESSION['error'] = "No puedes retirar más de lo disponible";
                          } else {
                              $nuevaCantidad -= $cantidad;
                          }
                      } elseif ($tipo == 'ajuste') {
                          $nuevaCantidad = $cantidad;
                      }

                      if (!isset($_SESSION['error'])) {

                          // Actualizar stock
                          $sqlUpdate = "UPDATE productos_stock SET cantidad = ? WHERE id = ?";
                          $stmtUpdate = $pdo->prepare($sqlUpdate);
                          $stmtUpdate->execute([$nuevaCantidad, $producto_id]);

                          // Registrar movimiento en la tabla nueva
                          $sqlMov = "INSERT INTO movimientos_stock 
                                      (producto_id, tipo, cantidad, motivo, fecha, usuario_id) 
                                      VALUES (?, ?, ?, ?, NOW(), ?)";

                          $stmtMov = $pdo->prepare($sqlMov);
                          $stmtMov->execute([$producto_id, $tipo, $cantidad, $motivo, $usuario_id]);

                          // Registrar actividad en bitácora
                          registrarActividad($pdo, $_SESSION['usuario_id'], 
                              'Movimiento de inventario',
                              "Movimiento: $tipo | Cantidad: $cantidad | Producto ID: $producto_id",
                              'inventario');

                          $_SESSION['success'] = "Movimiento registrado correctamente";
                      }
                  }
              }

              header("Location: admin.php?seccion=inventario");
              exit();
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

// Procesar operaciones de sucursales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sucursal_action'])) {
    $action = $_POST['sucursal_action'];
    
    try {
        if ($action == 'create_sucursal') {
            // Crear nueva sucursal
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $telefono = trim($_POST['telefono']);
            $encargado = trim($_POST['encargado']);
            $estado = $_POST['estado'];
            
            // Validaciones
            if (empty($nombre) || empty($direccion) || empty($telefono)) {
                $_SESSION['error'] = "Nombre, dirección y teléfono son obligatorios";
            } else {
                // Insertar nueva sucursal
                $sql = "INSERT INTO sucursales (nombre, direccion, telefono, encargado, estado, fecha_creacion) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $direccion, $telefono, $encargado, $estado]);
                
                // Registrar actividad
                registrarActividad($pdo, $_SESSION['usuario_id'], 'Sucursal creada', "Nueva sucursal: $nombre", 'sistema');
                
                $_SESSION['success'] = "Sucursal registrada correctamente";
            }
            
        } elseif ($action == 'update_sucursal') {
            // Actualizar sucursal existente
            $sucursal_id = $_POST['sucursal_id'];
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $telefono = trim($_POST['telefono']);
            $encargado = trim($_POST['encargado']);
            $estado = $_POST['estado'];
            
            // Validaciones básicas
            if (empty($nombre) || empty($direccion) || empty($telefono)) {
                $_SESSION['error'] = "Nombre, dirección y teléfono son obligatorios";
            } else {
                // Actualizar sucursal
                $sql = "UPDATE sucursales SET nombre = ?, direccion = ?, telefono = ?, encargado = ?, estado = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $direccion, $telefono, $encargado, $estado, $sucursal_id]);
                
                // Registrar actividad
                registrarActividad($pdo, $_SESSION['usuario_id'], 'Sucursal actualizada', "Sucursal actualizada: $nombre", 'sistema');
                
                $_SESSION['success'] = "Sucursal actualizada correctamente";
            }
            
        } elseif ($action == 'delete_sucursal') {
            // Eliminar sucursal
            $sucursal_id = $_POST['sucursal_id'];
            
            // Obtener información de la sucursal antes de eliminarla
            $sqlInfo = "SELECT nombre FROM sucursales WHERE id = ?";
            $stmtInfo = $pdo->prepare($sqlInfo);
            $stmtInfo->execute([$sucursal_id]);
            $sucursal = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            if ($sucursal) {
                // Verificar si hay productos asociados a esta sucursal
                $sqlCheck = "SELECT COUNT(*) as total FROM productos_stock WHERE sucursal_id = ?";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([$sucursal_id]);
                $productos = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($productos['total'] > 0) {
                    $_SESSION['error'] = "No se puede eliminar la sucursal porque tiene productos asociados";
                } else {
                    $sql = "DELETE FROM sucursales WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$sucursal_id]);
                    
                    // Registrar actividad
                    registrarActividad($pdo, $_SESSION['usuario_id'], 'Sucursal eliminada', "Sucursal eliminada: " . $sucursal['nombre'], 'sistema');
                    
                    $_SESSION['success'] = "Sucursal eliminada correctamente";
                }
            } else {
                $_SESSION['error'] = "Sucursal no encontrada";
            }
        }
        
        // Redirigir para evitar reenvío del formulario
        header('Location: admin.php?seccion=sucursales');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error en la base de datos: " . $e->getMessage();
        header('Location: admin.php?seccion=sucursales');
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

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

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
        // VERIFICAR ESTRUCTURA DE BASE DE DATOS
        // Verificar si hay ventas en la base de datos
        $sqlCheckVentas = "SELECT COUNT(*) as total_ventas FROM ventas WHERE estado = 'completada'";
        $stmtCheckVentas = $pdo->query($sqlCheckVentas);
        $totalVentas = $stmtCheckVentas->fetch(PDO::FETCH_ASSOC)['total_ventas'];
        
        // Verificar si hay detalles de ventas
        $sqlCheckDetalles = "SELECT COUNT(*) as total_detalles FROM detalle_ventas";
        $stmtCheckDetalles = $pdo->query($sqlCheckDetalles);
        $totalDetalles = $stmtCheckDetalles->fetch(PDO::FETCH_ASSOC)['total_detalles'];
        
        // Verificar productos activos
        $sqlCheckProductos = "SELECT COUNT(*) as total_productos FROM productos_stock WHERE estado = 'activo'";
        $stmtCheckProductos = $pdo->query($sqlCheckProductos);
        $totalProductos = $stmtCheckProductos->fetch(PDO::FETCH_ASSOC)['total_productos'];
        
        // DEBUG: Mostrar información de la base de datos
        echo "<!-- DEBUG BD: Ventas=$totalVentas, Detalles=$totalDetalles, Productos=$totalProductos -->";

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
        
        // Productos destacados del mes - CONSULTA MEJORADA
        $sqlProductosDestacados = "
            SELECT 
                ps.id,
                ps.nombre,
                ps.descripcion,
                ps.precio,
                ps.cantidad as stock,
                COALESCE(SUM(dv.cantidad), 0) as total_vendido,
                COALESCE(SUM(dv.subtotal), 0) as ingresos_totales,
                COUNT(dv.id) as total_ventas
            FROM productos_stock ps
            LEFT JOIN detalle_ventas dv ON ps.id = dv.producto_id
            LEFT JOIN ventas v ON dv.venta_id = v.id 
                AND MONTH(v.fecha_venta) = MONTH(CURRENT_DATE()) 
                AND YEAR(v.fecha_venta) = YEAR(CURRENT_DATE())
                AND v.estado = 'completada'
            WHERE ps.estado = 'activo'
            GROUP BY ps.id, ps.nombre, ps.descripcion, ps.precio, ps.cantidad
            HAVING total_vendido > 0
            ORDER BY total_vendido DESC, ingresos_totales DESC
            LIMIT 5
        ";
        
        $stmtProductosDestacados = $pdo->query($sqlProductosDestacados);
        $productosDestacados = $stmtProductosDestacados->fetchAll(PDO::FETCH_ASSOC);
        
        // Si no hay productos vendidos este mes, mostrar productos con stock
        if (empty($productosDestacados)) {
            $sqlProductosAlternativos = "
                SELECT 
                    id,
                    nombre,
                    descripcion,
                    precio,
                    cantidad as stock,
                    0 as total_vendido,
                    0 as ingresos_totales,
                    0 as total_ventas
                FROM productos_stock 
                WHERE estado = 'activo' 
                AND cantidad > 0
                ORDER BY fecha_creacion DESC, cantidad DESC
                LIMIT 5
            ";
            
            $stmtAlternativos = $pdo->query($sqlProductosAlternativos);
            $productosDestacados = $stmtAlternativos->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // DEBUG: Mostrar información de productos destacados
        echo "<!-- DEBUG: totalProductosDestacados = " . count($productosDestacados) . " -->";
        if (!empty($productosDestacados)) {
            echo "<!-- DEBUG Primer Producto: " . htmlspecialchars($productosDestacados[0]['nombre']) . " -->";
        }
        
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
        error_log("Error en dashboard: " . $e->getMessage());
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
        'inventario' => [
            'titulo' => 'Gestión de Inventario',
            'descripcion' => 'Control de stock, productos y categorías',
            'icono' => 'inventory_2'
        ],
        'sucursales' => [
            'titulo' => 'Gestión de Sucursales',
            'descripcion' => 'Administrar sucursales y encargados',
            'icono' => 'store'
        ],
        'configuracion' => [
            'titulo' => 'Configuración de Perfil',
            'descripcion' => 'Actualizar información personal y contraseña',
            'icono' => 'settings'
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
    <style>
     
      
      /* Estilos para paginación */
      .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 20px;
        gap: 10px;
      }

      .pagination button {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .pagination button:hover:not(:disabled) {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
      }

      .pagination button:disabled {
        background: #f3f4f6;
        color: #9ca3af;
        cursor: not-allowed;
      }

      .pagination .page-numbers {
        display: flex;
        gap: 5px;
      }

      .pagination .page-number {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .pagination .page-number:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
      }

      .pagination .page-number.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
      }

      /* Estilos para productos destacados */
      .featured-products .badge {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 10px;
      }

      .product-card.featured {
        border: 2px solid #f59e0b;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
      }

      .product-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #10b981;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
      }

      .stat-value.low-stock {
        color: #ef4444;
        font-weight: 600;
      }

      .stat-value.good-stock {
        color: #10b981;
        font-weight: 600;
      }

      .no-data {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        border: 2px dashed #e2e8f0;
      }

      .no-data .material-symbols-rounded {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 20px;
      }

      .no-data h3 {
        color: #64748b;
        margin-bottom: 10px;
      }

      .no-data p {
        color: #94a3b8;
        margin-bottom: 20px;
      }
    </style>
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

/* Estilos para las tarjetas de métricas del inventario */
.inventory-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.inventory-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.inventory-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.inventory-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.inventory-card.total-products::before {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.inventory-card.low-stock::before {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}

.inventory-card.with-discount::before {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.inventory-card.total-stock::before {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.total-products .card-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.low-stock .card-icon {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.with-discount .card-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.total-stock .card-icon {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.card-icon .material-symbols-rounded {
    font-size: 28px;
}

.card-content {
    flex: 1;
}

.card-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 4px;
}

.card-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.card-trend {
    color: #10b981;
    display: flex;
    align-items: center;
}

.card-trend.negative {
    color: #ef4444;
}

.card-trend .material-symbols-rounded {
    font-size: 24px;
}

/* Estilos para stock bajo en la tabla */
.low-stock-row {
    background-color: #fef2f2 !important;
    border-left: 4px solid #ef4444;
}

.with-discount-row {
    background-color: #fffbeb !important;
    border-left: 4px solid #f59e0b;
}

.low-stock-badge {
    background: #ef4444;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.875rem;
}

.discount-badge {
    background: #f59e0b;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.875rem;
}

.final-price {
    color: #10b981;
    font-size: 1.1rem;
}

.sucursal-badge {
    background: #3b82f6;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.875rem;
}

.categoria-badge {
    background: #8b5cf6;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 0.875rem;
}

/* Form responsive */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Estilos para los nuevos filtros */
.filters-container {
    display: flex;
    gap: 15px;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 2px;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 0.875rem;
    color: #374151;
    min-width: 150px;
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Mejoras responsive */
@media (max-width: 768px) {
  .product-image {
    width: 40px;
    height: 40px;
  }
  
  .products-table {
    min-width: 1000px;
  }

  .inventory-cards {
      grid-template-columns: 1fr;
      gap: 16px;
  }
  
  .inventory-card {
      padding: 20px;
  }
  
  .card-value {
      font-size: 1.75rem;
  }

  .form-row {
      grid-template-columns: 1fr;
  }

  .filters-container {
      flex-direction: column;
      gap: 10px;
  }

  .filter-select {
      min-width: 100%;
  }
}
.percentage-input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.percentage-input-container input {
    padding-right: 40px;
}

.percentage-symbol {
    position: absolute;
    right: 12px;
    color: #64748b;
    font-weight: 600;
}

/* Estilos para el desglose de precios */
.price-breakdown {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.price-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
}

.price-item.discount {
    color: #ef4444;
    font-weight: 600;
}

.price-item.final {
    border-top: 2px solid #e2e8f0;
    margin-top: 8px;
    padding-top: 10px;
    font-size: 1.2rem;
    font-weight: bold;
    color: #10b981;
}

.sucursales-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.sucursal-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    min-height: 280px; 
    display: flex;
    flex-direction: column;
}

.sucursal-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.sucursal-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.sucursal-card.inactiva::before {
    background: linear-gradient(135deg, #6b7280, #4b5563);
}

.sucursal-header {
    display: flex;
    justify-content: between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.sucursal-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    flex: 1;
}

.sucursal-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-activa {
    background: #dcfce7;
    color: #166534;
}

.status-inactiva {
    background: #f3f4f6;
    color: #6b7280;
}

.sucursal-info {
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 12px;
    padding: 8px 0;
}

.info-icon {
    width: 20px;
    margin-right: 12px;
    color: #64748b;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 2px;
}

.info-value {
    font-size: 0.95rem;
    color: #1e293b;
    font-weight: 600;
}

.sucursal-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    border-top: 1px solid #e2e8f0;
    padding-top: 20px;
    margin-top: auto;
    flex-shrink: 0;
}

.sucursal-form {
    padding: 0;
    margin-top:25px;
}

.sucursal-form .form-group {
    margin-bottom: 20px;
}

.sucursal-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.sucursal-form input,
.sucursal-form textarea,
.sucursal-form select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
    box-sizing: border-box;
}

.sucursal-form input:focus,
.sucursal-form textarea:focus,
.sucursal-form select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.sucursal-form textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.sucursal-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.sucursal-form .form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 25px;
    padding: 20px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    
}

.sucursal-form .btn-primary,
.sucursal-form .btn-secondary {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    min-width: 120px;
}

.sucursal-form .btn-primary {
    background: #3b82f6;
    color: white;
}

.sucursal-form .btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.sucursal-form .btn-secondary {
    background: #6b7280;
    color: white;
}

.sucursal-form .btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

/* Asegurar que el contenido del formulario tenga padding */
.sucursal-form > div:first-child {
    padding: 24px 24px 0 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .sucursales-grid {
        grid-template-columns: 1fr;
    }
    
    .sucursal-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .sucursal-actions {
        flex-direction: column;
    }

    .sucursal-form .form-row {
        grid-template-columns: 1fr;
    }

    .sucursal-form .form-actions {
        flex-direction: column;
        padding: 20px;
    }

    .sucursal-form .btn-primary,
    .sucursal-form .btn-secondary {
        min-width: auto;
        width: 100%;
    }
}

/* Mejoras al modal general */
.modal-content {
    max-width: 600px;
    width: 90%;
    margin: 50px auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 600;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: #f3f4f6;
    color: #374151;
}

/* Estilos para el dropdown del perfil */
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

.user-profile {
    position: relative;
    cursor: pointer;
}

/* Estilos para la sección de configuración */
.config-section {
    max-width: 600px;
    margin: 0 auto;
}

.config-form {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: 1px solid #e2e8f0;
}

.config-form .form-group {
    margin-bottom: 25px;
}

.config-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.config-form input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
    background: #fafafa;
}

.config-form input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    background: white;
}

.config-form .form-info {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
}

.config-form .form-info p {
    margin: 0;
    color: #0369a1;
    font-size: 0.9rem;
}

.config-form .form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #e5e7eb;
}

.config-form .btn-primary,
.config-form .btn-secondary {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    min-width: 120px;
}

.config-form .btn-primary {
    background: #3b82f6;
    color: white;
}

.config-form .btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.config-form .btn-secondary {
    background: #6b7280;
    color: white;
}

.config-form .btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.user-role-display {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 25px;
    font-weight: 600;
    color: #0369a1;
}
</style>
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
      
      <div class="header-right">
        <div class="user-profile" id="userProfile">
          <div class="user-avatar"><?php echo $iniciales; ?></div>
          <div class="user-info">
            <div class="user-name"><?php echo $_SESSION['usuario_nombre']; ?></div>
            <div class="user-role"><?php echo $_SESSION['rol']; ?></div>
          </div>
          <button class="profile-dropdown" id="profileDropdown">
            <span class="material-symbols-rounded">expand_more</span>
          </button>
          <!-- Dropdown del perfil -->
          <div class="profile-dropdown-menu" id="profileDropdownMenu">
            
            <a href="logout.php" class="profile-dropdown-item">
              <span class="material-symbols-rounded">logout</span>
              Cerrar Sesión
            </a>
          </div>
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
            <li class="nav-item <?php echo $seccion == 'sucursales' ? 'active' : ''; ?>">
              <a href="?seccion=sucursales" class="nav-link">
                <span class="nav-icon material-symbols-rounded">store</span>
                <span class="nav-text">Sucursales</span>
              </a>
            </li>
          </ul>
        </div>
      </nav>
    </aside>
    
    <!-- Contenido Principal -->
    <main class="main-content">
      <!-- Encabezado dinámico según la sección actual -->
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
        <!-- CONTENIDO DEL DASHBOARD -->
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
            Productos Destacados
            <?php if (!empty($productosDestacados) && $productosDestacados[0]['total_vendido'] > 0): ?>
              <span class="badge">Este Mes</span>
            <?php else: ?>
              <span class="badge">Recomendados</span>
            <?php endif; ?>
          </h2>
          
          <?php if (!empty($productosDestacados)): ?>
            <div class="products-grid">
              <?php foreach ($productosDestacados as $index => $producto): ?>
                <div class="product-card <?php echo $index === 0 ? 'featured' : ''; ?>">
                  <div class="product-header">
                    <div>
                      <div class="product-name"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                      <div class="product-description"><?php echo htmlspecialchars($producto['descripcion']); ?></div>
                    </div>
                    <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                  </div>
                  
                  <div class="product-stats">
                    <?php if ($producto['total_vendido'] > 0): ?>
                      <div class="stat-item">
                        <span class="stat-label">Vendidos este mes</span>
                        <span class="stat-value sales-stat"><?php echo $producto['total_vendido']; ?> unidades</span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">Ingresos generados</span>
                        <span class="stat-value revenue-stat">$<?php echo number_format($producto['ingresos_totales'], 2); ?></span>
                      </div>
                    <?php else: ?>
                      <div class="stat-item">
                        <span class="stat-label">Disponibles en stock</span>
                        <span class="stat-value stock-stat"><?php echo $producto['stock']; ?> unidades</span>
                      </div>
                      <div class="stat-item">
                        <span class="stat-label">Estado</span>
                        <span class="stat-value status-stat">Disponible</span>
                      </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                      <span class="stat-label">Stock actual</span>
                      <span class="stat-value <?php echo $producto['stock'] < 10 ? 'low-stock' : 'good-stock'; ?>">
                        <?php echo $producto['stock']; ?> unidades
                      </span>
                    </div>
                  </div>
                  
                  <?php if ($producto['total_vendido'] > 0): ?>
                    <div class="product-badge">
                      <span class="material-symbols-rounded">trending_up</span>
                      Popular
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="no-data">
              <span class="material-symbols-rounded">inventory_2</span>
              <h3>No hay productos disponibles</h3>
              <p>No se encontraron productos activos en el inventario.</p>
              <a href="?seccion=inventario" class="btn-primary">
                <span class="material-symbols-rounded">add</span>
                Agregar Productos
              </a>
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
        <?php
        // Obtener total de usuarios para paginación
        $sqlTotalUsuarios = "SELECT COUNT(*) as total FROM usuarios";
        $stmtTotalUsuarios = $pdo->query($sqlTotalUsuarios);
        $totalUsuarios = $stmtTotalUsuarios->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPaginas = ceil($totalUsuarios / $registros_por_pagina);
        
        // Consulta para obtener usuarios con información de roles y paginación
        $sqlUsuarios = "SELECT u.id, u.nombre, u.correo, u.fecha_registro, r.nombre as rol_nombre, r.id as rol_id 
                       FROM usuarios u 
                       LEFT JOIN roles r ON u.rol_id = r.id 
                       ORDER BY u.fecha_registro DESC
                       LIMIT $registros_por_pagina OFFSET $offset";
        $stmtUsuarios = $pdo->query($sqlUsuarios);
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
        ?>
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
                ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINACIÓN -->
          <?php if ($totalPaginas > 1): ?>
          <div class="pagination">
            <button onclick="cambiarPagina(<?php echo max(1, $pagina_actual - 1); ?>)" <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>>
              <span class="material-symbols-rounded">chevron_left</span>
            </button>
            
            <div class="page-numbers">
              <?php
              // Mostrar números de página
              $inicio = max(1, $pagina_actual - 2);
              $fin = min($totalPaginas, $pagina_actual + 2);
              
              for ($i = $inicio; $i <= $fin; $i++):
              ?>
                <button class="page-number <?php echo $i == $pagina_actual ? 'active' : ''; ?>" onclick="cambiarPagina(<?php echo $i; ?>)">
                  <?php echo $i; ?>
                </button>
              <?php endfor; ?>
            </div>
            
            <button onclick="cambiarPagina(<?php echo min($totalPaginas, $pagina_actual + 1); ?>)" <?php echo $pagina_actual >= $totalPaginas ? 'disabled' : ''; ?>>
              <span class="material-symbols-rounded">chevron_right</span>
            </button>
          </div>
          <?php endif; ?>
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
        function cambiarPagina(pagina) {
          window.location.href = `?seccion=usuarios&pagina=${pagina}`;
        }

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
        <?php
        // Obtener total de productos para paginación
        $sqlTotalProductos = "SELECT COUNT(*) as total FROM productos_stock";
        $stmtTotalProductos = $pdo->query($sqlTotalProductos);
        $totalProductos = $stmtTotalProductos->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPaginas = ceil($totalProductos / $registros_por_pagina);
        
        // Consulta para obtener productos del inventario con sucursal y paginación
        $sqlProductos = "SELECT ps.id, ps.nombre, ps.descripcion, ps.precio, ps.descuento, ps.porcentaje_descuento, ps.precio_final, ps.cantidad, ps.categoria, ps.sucursal_id, ps.estado, ps.fecha_creacion, ps.imagen, s.nombre as sucursal_nombre
                         FROM productos_stock ps
                         LEFT JOIN sucursales s ON ps.sucursal_id = s.id
                         ORDER BY ps.fecha_creacion DESC
                         LIMIT $registros_por_pagina OFFSET $offset";
        $stmtProductos = $pdo->query($sqlProductos);
        $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="section-content">
          <div class="inventario-header">
            <h2 style="margin: 0; color: #1e293b;">Gestión de Inventario</h2>
            <div class="inventario-actions">
              <!-- FILTROS: Sucursales y Categorías -->
              <div class="filters-container">
                <!-- Filtro por Sucursal -->
                <div class="filter-group">
                  <select id="filterSucursal" class="filter-select">
                    <option value="todas">Todas las sucursales</option>
                    <?php foreach($sucursales as $sucursal): ?>
                      <option value="<?php echo $sucursal['id']; ?>">
                        <?php echo htmlspecialchars($sucursal['nombre']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Filtro por Categoría -->
                <div class="filter-group">
                  <select id="filterCategoria" class="filter-select">
                    <option value="todas">Todas las categorías</option>
                    <?php foreach($categorias as $categoria): ?>
                      <option value="<?php echo $categoria; ?>">
                        <?php echo ucfirst($categoria); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

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

          <!-- Tarjetas de métricas del inventario -->
          <div class="inventory-cards">
            <?php
            // Consultas para las métricas del inventario
            try {
                // Total de productos en stock (todos los productos activos)
                $sqlTotalProductos = "SELECT COUNT(*) as total FROM productos_stock WHERE estado = 'activo'";
                $stmtTotal = $pdo->query($sqlTotalProductos);
                $totalProductos = $stmtTotal->fetch(PDO::FETCH_ASSOC);
                $totalProductos = $totalProductos['total'];

                $sqlStockBajo = "SELECT COUNT(*) as bajo FROM productos_stock WHERE cantidad < 10 AND estado = 'activo'";
                $stmtBajo = $pdo->query($sqlStockBajo);
                $resultBajo = $stmtBajo->fetch(PDO::FETCH_ASSOC);
                $stockBajo = $resultBajo['bajo'];

                // Total stock (suma de todas las cantidades de productos activos)
                $sqlTotalStock = "SELECT SUM(cantidad) as total_stock FROM productos_stock WHERE estado = 'activo'";
                $stmtStock = $pdo->query($sqlTotalStock);
                $totalStock = $stmtStock->fetch(PDO::FETCH_ASSOC);
                $totalStock = $totalStock['total_stock'] ?? 0;

                $sqlConDescuento = "SELECT COUNT(*) as con_descuento FROM productos_stock WHERE descuento > 0 AND estado = 'activo'";
                $stmtDescuento = $pdo->query($sqlConDescuento);
                $resultDescuento = $stmtDescuento->fetch(PDO::FETCH_ASSOC);
                $conDescuento = $resultDescuento['con_descuento'];

            } catch(PDOException $e) {
                $totalProductos = 0;
                $stockBajo = 0;
                $totalStock = 0;
                $conDescuento = 0;
                error_log("Error en consultas de inventario: " . $e->getMessage());
            }
            ?>

            <!-- Tarjeta 1: Total Productos -->
            <div class="inventory-card total-products">
              <div class="card-icon">
                <span class="material-symbols-rounded">inventory_2</span>
              </div>
              <div class="card-content">
                <div class="card-value"><?php echo $totalProductos; ?></div>
                <div class="card-label">Total Productos Activos</div>
              </div>
              <div class="card-trend">
                <span class="material-symbols-rounded">trending_up</span>
              </div>
            </div>

            <!-- Tarjeta 2: Stock Bajo -->
            <div class="inventory-card low-stock">
              <div class="card-icon">
                <span class="material-symbols-rounded">warning</span>
              </div>
              <div class="card-content">
                <div class="card-value"><?php echo $stockBajo; ?></div>
                <div class="card-label">Stock Bajo (<10 unidades)</div>
              </div>
              <div class="card-trend <?php echo $stockBajo > 0 ? 'negative' : ''; ?>">
                <span class="material-symbols-rounded"><?php echo $stockBajo > 0 ? 'warning' : 'check_circle'; ?></span>
              </div>
            </div>

            <!-- Tarjeta 3: Con Descuento -->
            <div class="inventory-card with-discount">
              <div class="card-icon">
                <span class="material-symbols-rounded">local_offer</span>
              </div>
              <div class="card-content">
                <div class="card-value"><?php echo $conDescuento; ?></div>
                <div class="card-label">Productos con Descuento</div>
              </div>
              <div class="card-trend">
                <span class="material-symbols-rounded">campaign</span>
              </div>
            </div>

            <!-- Tarjeta 4: Total Stock -->
            <div class="inventory-card total-stock">
              <div class="card-icon">
                <span class="material-symbols-rounded">warehouse</span>
              </div>
              <div class="card-content">
                <div class="card-value"><?php echo $totalStock; ?></div>
                <div class="card-label">Total Unidades en Stock</div>
              </div>
              <div class="card-trend">
                <span class="material-symbols-rounded">inventory</span>
              </div>
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
          
          <div class="table-container">
            <table class="products-table" id="productsTable">
              <thead>
                <tr>
                  <th>Imagen</th>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Descripción</th>
                  <th>Precio</th>
                  <th>Descuento</th>
                  <th>Precio Final</th>
                  <th>Cantidad</th>
                  <th>Categoría</th>
                  <th>Sucursal</th>
                  <th>Estado</th>
                  <th>Fecha Creación</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (count($productos) > 0) {
                  foreach($productos as $producto) {
                    // Determinar clase para stock bajo
                    $stockClass = ($producto['cantidad'] < 10 && $producto['estado'] == 'activo') ? 'low-stock-row' : '';
                    $discountClass = ($producto['descuento'] > 0) ? 'with-discount-row' : '';
                    
                    echo '<tr class="' . $stockClass . ' ' . $discountClass . '" data-sucursal="' . $producto['sucursal_id'] . '" data-categoria="' . htmlspecialchars($producto['categoria']) . '">';
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
                    
                    // Columna de descuento
                    if ($producto['descuento'] > 0) {
                      echo '<td><span class="discount-badge">-$' . number_format($producto['descuento'], 2) . '</span></td>';
                    } else {
                      echo '<td>-</td>';
                    }
                    
                    // Columna de precio final
                    echo '<td><strong class="final-price">$' . number_format($producto['precio_final'], 2) . '</strong></td>';
                    
                    // Resaltar cantidad si es stock bajo
                    if ($producto['cantidad'] < 10 && $producto['estado'] == 'activo') {
                      echo '<td><span class="low-stock-badge">' . $producto['cantidad'] . '</span></td>';
                    } else {
                      echo '<td>' . $producto['cantidad'] . '</td>';
                    }
                    
                    echo '<td><span class="categoria-badge">' . ucfirst(htmlspecialchars($producto['categoria'])) . '</span></td>';
                    echo '<td><span class="sucursal-badge">' . htmlspecialchars($producto['sucursal_nombre'] ?? 'N/A') . '</span></td>';
                    echo '<td><span class="status-badge status-' . $producto['estado'] . '">' . ucfirst($producto['estado']) . '</span></td>';
                    echo '<td>' . date('d/m/Y H:i', strtotime($producto['fecha_creacion'])) . '</td>';
                    echo '<td class="actions">';
                    // BOTÓN TRANSFERIR STOCK ENTRE SUCURSALES
                    echo '<button class="btn-icon transfer-stock"
                            data-id="' . $producto['id'] . '"
                            data-nombre="' . htmlspecialchars($producto['nombre']) . '"
                            data-sucursal="' . $producto['sucursal_id'] . '"
                            data-cantidad="' . $producto['cantidad'] . '"
                            title="Transferir Stock">
                            <span class="material-symbols-rounded">swap_horiz</span>
                          </button>';

                    // BOTÓN DE MOVIMIENTO DE STOCK
                    echo '<button class="btn-icon stock-move"
                            title="Registrar movimiento"
                            onclick="abrirModalMovimiento(' . $producto['id'] . ', \'' . htmlspecialchars($producto['nombre']) . '\', ' . $producto['cantidad'] . ')">
                            <span class="material-symbols-rounded">sync_alt</span>
                          </button>';

                    // BOTÓN EDITAR
                    echo '<button class="btn-icon edit-product"
                            data-id="' . $producto['id'] . '"
                            data-nombre="' . htmlspecialchars($producto['nombre']) . '"
                            data-descripcion="' . htmlspecialchars($producto['descripcion']) . '"
                            data-precio="' . $producto['precio'] . '"
                            data-descuento="' . $producto['descuento'] . '"
                            data-porcentaje_descuento="' . $producto['porcentaje_descuento'] . '"
                            data-cantidad="' . $producto['cantidad'] . '"
                            data-categoria="' . htmlspecialchars($producto['categoria']) . '"
                            data-sucursal_id="' . $producto['sucursal_id'] . '"
                            data-estado="' . $producto['estado'] . '"
                            data-imagen="' . htmlspecialchars($producto['imagen']) . '"
                            title="Editar">
                            <span class="material-symbols-rounded">edit</span>
                          </button>';

                    // BOTÓN ELIMINAR
                    echo '<button class="btn-icon delete-product"
                            data-id="' . $producto['id'] . '"
                            data-nombre="' . htmlspecialchars($producto['nombre']) . '"
                            title="Eliminar">
                            <span class="material-symbols-rounded">delete</span>
                          </button>';

                    echo '</td>';

                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="13" class="no-data">No hay productos en el inventario</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINACIÓN -->
          <?php if ($totalPaginas > 1): ?>
          <div class="pagination">
            <button onclick="cambiarPaginaInventario(<?php echo max(1, $pagina_actual - 1); ?>)" <?php echo $pagina_actual <= 1 ? 'disabled' : ''; ?>>
              <span class="material-symbols-rounded">chevron_left</span>
            </button>
            
            <div class="page-numbers">
              <?php
              // Mostrar números de página
              $inicio = max(1, $pagina_actual - 2);
              $fin = min($totalPaginas, $pagina_actual + 2);
              
              for ($i = $inicio; $i <= $fin; $i++):
              ?>
                <button class="page-number <?php echo $i == $pagina_actual ? 'active' : ''; ?>" onclick="cambiarPaginaInventario(<?php echo $i; ?>)">
                  <?php echo $i; ?>
                </button>
              <?php endfor; ?>
            </div>
            
            <button onclick="cambiarPaginaInventario(<?php echo min($totalPaginas, $pagina_actual + 1); ?>)" <?php echo $pagina_actual >= $totalPaginas ? 'disabled' : ''; ?>>
              <span class="material-symbols-rounded">chevron_right</span>
            </button>
          </div>
          <?php endif; ?>
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
              <input type="hidden" id="descuento" name="descuento" value="0">
              
              <div class="form-group">
                <label for="product_nombre">Nombre del Producto *</label>
                <input type="text" id="product_nombre" name="nombre" required>
              </div>
              
              <div class="form-group">
                <label for="product_descripcion">Descripción *</label>
                <textarea id="product_descripcion" name="descripcion" rows="3" required></textarea>
              </div>
              
              <div class="form-row">
                <div class="form-group">
                  <label for="product_precio">Precio Original *</label>
                  <input type="number" id="product_precio" name="precio" step="0.01" min="0" required>
                </div>
                
                                <div class="form-group">
                    <label for="product_porcentaje_descuento">Porcentaje de Descuento</label>
                    <div class="percentage-input-container">
                        <input type="number" id="product_porcentaje_descuento" name="porcentaje_descuento" 
                              step="0.01" min="0" max="100" value="0" 
                              oninput="validarPorcentaje(this)">
                        <span class="percentage-symbol">%</span>
                    </div>
                    <small>Porcentaje de descuento (0-100%)</small>
                </div>
              </div>
              
              <div class="form-group">
                <div id="precio-final-container" style="display: none;">
                  <div class="price-breakdown">
                    <div class="price-item">
                      <span>Precio Original:</span>
                      <span id="precio-original-value">$0.00</span>
                    </div>
                    <div class="price-item discount">
                      <span>Descuento:</span>
                      <span id="descuento-value">-$0.00 (0%)</span>
                    </div>
                    <div class="price-item final">
                      <span>Precio Final:</span>
                      <span id="precio-final-value">$0.00</span>
                    </div>
                  </div>
                </div>
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
                <label for="product_sucursal">Sucursal *</label>
                <select id="product_sucursal" name="sucursal_id" required>
                  <option value="">Seleccionar sucursal</option>
                  <?php foreach($sucursales as $sucursal): ?>
                    <option value="<?php echo $sucursal['id']; ?>">
                      <?php echo htmlspecialchars($sucursal['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="form-group">
                <label for="product_estado">Estado *</label>
                <select id="product_estado" name="estado" required>
                  <option value="activo">Activo</option>
                  <option value="inactivo">Inactivo</option>
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
        <!-- MODAL TRANSFERENCIA DE STOCK -->
        <div class="modal" id="modalTransferencia">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Transferir Stock</h3>
                    <button class="close-modal" onclick="cerrarModalTransferencia()">&times;</button>
                </div>

                <form method="POST" action="admin.php?seccion=inventario">
                    <input type="hidden" name="inventario_action" value="transferencia_stock">
                    <input type="hidden" id="transfer_producto_id" name="producto_id">
                    <input type="hidden" id="transfer_sucursal_origen" name="sucursal_origen">

                    <div style="padding: 0 24px;">

                        <p><strong>Producto:</strong> <span id="transfer_producto_nombre"></span></p>
                        <p><strong>Sucursal Origen:</strong> <span id="transfer_sucursal_origen_nombre"></span></p>
                        <p><strong>Cantidad disponible:</strong> <span id="transfer_stock_disponible"></span></p>

                        <div class="form-group">
                            <label for="sucursal_destino">Sucursal Destino *</label>
                            <select name="sucursal_destino" id="sucursal_destino" required>
                                <option value="">Seleccione sucursal</option>
                                <?php
                                foreach ($sucursales as $s) {
                                    echo '<option value="' . $s['id'] . '">' . htmlspecialchars($s['nombre']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="transfer_cantidad">Cantidad a transferir *</label>
                            <input type="number" name="cantidad" id="transfer_cantidad" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Motivo (opcional)</label>
                            <textarea name="motivo" rows="3"></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="cerrarModalTransferencia()">Cancelar</button>
                        <button type="submit" class="btn-primary">Transferir</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal para movimientos de inventario -->
        <div class="modal" id="movimientoModal">
          <div class="modal-content">
            <div class="modal-header">
              <h3 id="movimientoModalTitle">Registrar Movimiento</h3>
              <button class="close-modal" onclick="document.getElementById('movimientoModal').classList.remove('show')">&times;</button>
            </div>

            <form method="POST" class="product-form">
              <input type="hidden" name="inventario_action" value="registrar_movimiento">
              <input type="hidden" id="mov_producto_id" name="producto_id">

              <div class="form-group">
                <label>Tipo de movimiento</label>
                <select name="tipo" required>
                  <option value="entrada">Entrada</option>
                  <option value="salida">Salida</option>
                  <option value="ajuste">Ajuste</option>
                </select>
              </div>

              <div class="form-group">
                <label>Cantidad</label>
                <input type="number" name="cantidad" min="1" required>
              </div>

              <div class="form-group">
                <label>Motivo</label>
                <textarea name="motivo" rows="3"></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-primary">Guardar</button>
              </div>
            </form>
          </div>
        </div>


        <script>
        function cambiarPaginaInventario(pagina) {
          window.location.href = `?seccion=inventario&pagina=${pagina}`;
        }

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
          
          // Elementos para descuento porcentual
          const productPrecioInput = document.getElementById('product_precio');
          const productPorcentajeInput = document.getElementById('product_porcentaje_descuento');
          const descuentoHiddenInput = document.getElementById('descuento');
          const precioFinalContainer = document.getElementById('precio-final-container');
          const precioOriginalValue = document.getElementById('precio-original-value');
          const descuentoValue = document.getElementById('descuento-value');
          const precioFinalValue = document.getElementById('precio-final-value');
          
          // NUEVOS ELEMENTOS: Filtros
          const filterSucursal = document.getElementById('filterSucursal');
          const filterCategoria = document.getElementById('filterCategoria');
          
          // Función para aplicar todos los filtros
          function aplicarFiltros() {
            const searchTerm = searchInput.value.toLowerCase();
            const sucursalSeleccionada = filterSucursal.value;
            const categoriaSeleccionada = filterCategoria.value;
            
            const rows = productsTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
              const nombre = row.cells[2].textContent.toLowerCase();
              const descripcion = row.cells[3].textContent.toLowerCase();
              const categoria = row.cells[8].textContent.toLowerCase();
              const sucursal = row.cells[9].textContent.toLowerCase();
              
              const datosFila = {
                sucursalId: row.getAttribute('data-sucursal'),
                categoria: row.getAttribute('data-categoria')
              };
              
              // Aplicar filtro de búsqueda
              const coincideBusqueda = searchTerm === '' || 
                                     nombre.includes(searchTerm) || 
                                     descripcion.includes(searchTerm) || 
                                     categoria.includes(searchTerm) || 
                                     sucursal.includes(searchTerm);
              
              // Aplicar filtro de sucursal
              const coincideSucursal = sucursalSeleccionada === 'todas' || 
                                      datosFila.sucursalId === sucursalSeleccionada;
              
              // Aplicar filtro de categoría
              const coincideCategoria = categoriaSeleccionada === 'todas' || 
                                       datosFila.categoria === categoriaSeleccionada;
              
              // Mostrar u ocultar fila según todos los filtros
              if (coincideBusqueda && coincideSucursal && coincideCategoria) {
                row.style.display = '';
              } else {
                row.style.display = 'none';
              }
            });
          }
          
          // Event listeners para los filtros
          filterSucursal.addEventListener('change', aplicarFiltros);
          filterCategoria.addEventListener('change', aplicarFiltros);
          searchInput.addEventListener('input', aplicarFiltros);
          
          // Función para calcular precio final con porcentaje
         // Función para calcular precio final con porcentaje
          function calcularPrecioFinal() {
              const precio = parseFloat(productPrecioInput.value) || 0;
              let porcentaje = parseFloat(productPorcentajeInput.value) || 0;
              
              // Asegurarse de que el porcentaje esté entre 0 y 100
              if (porcentaje > 100) {
                  porcentaje = 100;
                  productPorcentajeInput.value = 100;
              }
              if (porcentaje < 0) {
                  porcentaje = 0;
                  productPorcentajeInput.value = 0;
              }
              
              const descuentoMonto = (precio * porcentaje) / 100;
              const precioFinal = Math.max(0, precio - descuentoMonto);
              
              // Actualizar campo hidden de descuento
              descuentoHiddenInput.value = descuentoMonto.toFixed(2);
              
              if (precio > 0) {
                  precioOriginalValue.textContent = '$' + precio.toFixed(2);
                  descuentoValue.textContent = '-$' + descuentoMonto.toFixed(2) + ' (' + porcentaje.toFixed(2) + '%)';
                  precioFinalValue.textContent = '$' + precioFinal.toFixed(2);
                  precioFinalContainer.style.display = 'block';
              } else {
                  precioFinalContainer.style.display = 'none';
              }
          }
                      
          // Event listeners para calcular precio final
          productPrecioInput.addEventListener('input', calcularPrecioFinal);
          productPorcentajeInput.addEventListener('input', calcularPrecioFinal);
          
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
          
          // Abrir modal para agregar producto
          addProductBtn.addEventListener('click', function() {
            productModalTitle.textContent = 'Agregar Nuevo Producto';
            productForm.reset();
            productIdInput.value = '';
            currentImagenInput.value = '';
            productFormActionInput.value = 'create_product';
            imagePreview.style.display = 'none';
            currentImageDiv.style.display = 'none';
            precioFinalContainer.style.display = 'none';
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
              const productDescuento = this.getAttribute('data-descuento');
              const productPorcentajeDescuento = this.getAttribute('data-porcentaje_descuento');
              const productCantidad = this.getAttribute('data-cantidad');
              const productCategoria = this.getAttribute('data-categoria');
              const productSucursalId = this.getAttribute('data-sucursal_id');
              const productEstado = this.getAttribute('data-estado');
              const productImagen = this.getAttribute('data-imagen');
              
              // Llenar el formulario con los datos del producto
              productModalTitle.textContent = 'Editar Producto';
              productIdInput.value = productId;
              document.getElementById('product_nombre').value = productNombre;
              document.getElementById('product_descripcion').value = productDescripcion;
              document.getElementById('product_precio').value = productPrecio;
              document.getElementById('product_porcentaje_descuento').value = productPorcentajeDescuento || 0;
              document.getElementById('product_cantidad').value = productCantidad;
              document.getElementById('product_categoria').value = productCategoria;
              document.getElementById('product_sucursal').value = productSucursalId;
              document.getElementById('product_estado').value = productEstado;
              productFormActionInput.value = 'update_product';
              
              // Calcular precio final
              calcularPrecioFinal();
              
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
          // Registrar movimiento
          document.querySelectorAll('.movimiento-producto').forEach(btn => {
            btn.addEventListener('click', function() {
              document.getElementById('mov_producto_id').value = this.getAttribute('data-id');
              document.getElementById('movimientoModal').classList.add('show');
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
      
      <?php elseif ($seccion == 'sucursales'): ?>
        <!-- SECCIÓN DE GESTIÓN DE SUCURSALES -->
        <?php
        // Consulta para obtener todas las sucursales
        try {
            $sqlSucursales = "SELECT * FROM sucursales ORDER BY estado DESC, nombre";
            $stmtSucursales = $pdo->query($sqlSucursales);
            $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $sucursales = [];
            error_log("Error al cargar sucursales: " . $e->getMessage());
        }
        ?>
        <div class="section-content">
            <div class="sucursales-header">
                <h2 style="margin: 0; color: #1e293b;">Gestión de Sucursales</h2>
                <div class="sucursales-actions">
                    <!-- Botón para agregar nueva sucursal -->
                    <button class="btn-primary" id="addSucursalBtn">
                        <span class="material-symbols-rounded">add</span>
                        Nueva Sucursal
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
            
            <!-- Grid de tarjetas de sucursales -->
            <div class="sucursales-grid" id="sucursalesGrid">
                <?php if (count($sucursales) > 0): ?>
                    <?php foreach($sucursales as $sucursal): ?>
                        <div class="sucursal-card <?php echo $sucursal['estado'] == 'inactiva' ? 'inactiva' : ''; ?>">
                            <div class="sucursal-header">
                                <h3 class="sucursal-name"><?php echo htmlspecialchars($sucursal['nombre']); ?></h3>
                                <span class="sucursal-status status-<?php echo $sucursal['estado']; ?>">
                                    <?php echo ucfirst($sucursal['estado']); ?>
                                </span>
                            </div>
                            
                            <div class="sucursal-info">
                                <div class="info-item">
                                    <span class="info-icon material-symbols-rounded">location_on</span>
                                    <div class="info-content">
                                        <div class="info-label">Dirección</div>
                                        <div class="info-value"><?php echo htmlspecialchars($sucursal['direccion']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-icon material-symbols-rounded">call</span>
                                    <div class="info-content">
                                        <div class="info-label">Teléfono</div>
                                        <div class="info-value"><?php echo htmlspecialchars($sucursal['telefono']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($sucursal['encargado'])): ?>
                                <div class="info-item">
                                    <span class="info-icon material-symbols-rounded">person</span>
                                    <div class="info-content">
                                        <div class="info-label">Encargado</div>
                                        <div class="info-value"><?php echo htmlspecialchars($sucursal['encargado']); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-icon material-symbols-rounded">calendar_today</span>
                                    <div class="info-content">
                                        <div class="info-label">Fecha de Creación</div>
                                        <div class="info-value"><?php echo date('d/m/Y', strtotime($sucursal['fecha_creacion'])); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="sucursal-actions">
                                <button class="btn-icon edit-sucursal" 
                                        data-id="<?php echo $sucursal['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($sucursal['nombre']); ?>"
                                        data-direccion="<?php echo htmlspecialchars($sucursal['direccion']); ?>"
                                        data-telefono="<?php echo htmlspecialchars($sucursal['telefono']); ?>"
                                        data-encargado="<?php echo htmlspecialchars($sucursal['encargado'] ?? ''); ?>"
                                        data-estado="<?php echo $sucursal['estado']; ?>"
                                        title="Editar">
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                                <button class="btn-icon delete-sucursal" 
                                        data-id="<?php echo $sucursal['id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($sucursal['nombre']); ?>"
                                        title="Eliminar">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data" style="grid-column: 1 / -1;">
                        <span class="material-symbols-rounded">store</span>
                        <p>No hay sucursales registradas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para agregar/editar sucursal - MEJORADO -->
        <div class="modal" id="sucursalModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="sucursalModalTitle">Registrar Nueva Sucursal</h3>
                    <button class="close-modal" id="closeSucursalModal">&times;</button>
                </div>
                <form class="sucursal-form" id="sucursalForm" method="POST">
                    <input type="hidden" id="sucursal_id" name="sucursal_id">
                    <input type="hidden" id="sucursal_form_action" name="sucursal_action" value="create_sucursal">
                    
                    <div style="padding: 0 24px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sucursal_nombre">Nombre de la Sucursal *</label>
                                <input type="text" id="sucursal_nombre" name="nombre" required placeholder="Ej: Sucursal Centro">
                            </div>
                            
                            <div class="form-group">
                                <label for="sucursal_telefono">Teléfono *</label>
                                <input type="text" id="sucursal_telefono" name="telefono" required placeholder="Ej: +52 55 1234 5678">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="sucursal_direccion">Dirección *</label>
                            <textarea id="sucursal_direccion" name="direccion" rows="3" required placeholder="Ej: Av. Principal #123, Col. Centro"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sucursal_encargado">Encargado</label>
                                <input type="text" id="sucursal_encargado" name="encargado" placeholder="Nombre del encargado">
                            </div>
                            
                            <div class="form-group">
                                <label for="sucursal_estado">Estado *</label>
                                <select id="sucursal_estado" name="estado" required>
                                    <option value="activa">Activa</option>
                                    <option value="inactiva">Inactiva</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="cancelSucursalBtn">Cancelar</button>
                        <button type="submit" class="btn-primary" id="saveSucursalBtn">Guardar Sucursal</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        // ABRIR MODAL DE TRANSFERENCIA
        document.addEventListener('click', function(e) {
            if (e.target.closest('.transfer-stock')) {
                const btn = e.target.closest('.transfer-stock');

                document.getElementById('transfer_producto_id').value = btn.dataset.id;
                document.getElementById('transfer_producto_nombre').innerText = btn.dataset.nombre;
                document.getElementById('transfer_sucursal_origen').value = btn.dataset.sucursal;
                document.getElementById('transfer_sucursal_origen_nombre').innerText = btn.dataset.sucursal;
                document.getElementById('transfer_stock_disponible').innerText = btn.dataset.cantidad;

                document.getElementById('modalTransferencia').classList.add('show');
            }
        });

        function cerrarModalTransferencia() {
            document.getElementById('modalTransferencia').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Elementos del DOM para sucursales
            const addSucursalBtn = document.getElementById('addSucursalBtn');
            const sucursalModal = document.getElementById('sucursalModal');
            const closeSucursalModal = document.getElementById('closeSucursalModal');
            const cancelSucursalBtn = document.getElementById('cancelSucursalBtn');
            const sucursalForm = document.getElementById('sucursalForm');
            const sucursalModalTitle = document.getElementById('sucursalModalTitle');
            const sucursalIdInput = document.getElementById('sucursal_id');
            const sucursalFormActionInput = document.getElementById('sucursal_form_action');
            
            // Abrir modal para agregar sucursal
            addSucursalBtn.addEventListener('click', function() {
                sucursalModalTitle.textContent = 'Registrar Nueva Sucursal';
                sucursalForm.reset();
                sucursalIdInput.value = '';
                sucursalFormActionInput.value = 'create_sucursal';
                sucursalModal.classList.add('show');
            });
            
            // Cerrar modal
            closeSucursalModal.addEventListener('click', function() {
                sucursalModal.classList.remove('show');
            });
            
            cancelSucursalBtn.addEventListener('click', function() {
                sucursalModal.classList.remove('show');
            });
            
            // Editar sucursal
            document.querySelectorAll('.edit-sucursal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sucursalId = this.getAttribute('data-id');
                    const sucursalNombre = this.getAttribute('data-nombre');
                    const sucursalDireccion = this.getAttribute('data-direccion');
                    const sucursalTelefono = this.getAttribute('data-telefono');
                    const sucursalEncargado = this.getAttribute('data-encargado');
                    const sucursalEstado = this.getAttribute('data-estado');
                    
                    // Llenar el formulario con los datos de la sucursal
                    sucursalModalTitle.textContent = 'Editar Sucursal';
                    sucursalIdInput.value = sucursalId;
                    document.getElementById('sucursal_nombre').value = sucursalNombre;
                    document.getElementById('sucursal_direccion').value = sucursalDireccion;
                    document.getElementById('sucursal_telefono').value = sucursalTelefono;
                    document.getElementById('sucursal_encargado').value = sucursalEncargado;
                    document.getElementById('sucursal_estado').value = sucursalEstado;
                    sucursalFormActionInput.value = 'update_sucursal';
                    
                    sucursalModal.classList.add('show');
                });
            });
            
            // Eliminar sucursal
            document.querySelectorAll('.delete-sucursal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sucursalId = this.getAttribute('data-id');
                    const sucursalNombre = this.getAttribute('data-nombre');
                    
                    if (confirm('¿Está seguro de que desea eliminar la sucursal "' + sucursalNombre + '"?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        
                        const actionInput = document.createElement('input');
                        actionInput.name = 'sucursal_action';
                        actionInput.value = 'delete_sucursal';
                        form.appendChild(actionInput);
                        
                        const sucursalIdInput = document.createElement('input');
                        sucursalIdInput.name = 'sucursal_id';
                        sucursalIdInput.value = sucursalId;
                        form.appendChild(sucursalIdInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        </script>
      
        
      <?php endif; ?>
    </main>
    <!-- MODAL DE MOVIMIENTO DE INVENTARIO -->
        <div class="modal" id="modalMovimiento">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Registrar Movimiento</h3>
                    <button class="close-modal" onclick="document.getElementById('modalMovimiento').classList.remove('show')">&times;</button>
                </div>

                <form method="POST" action="admin.php?seccion=inventario" id="formMovimientoStock">
                    <input type="hidden" name="inventario_action" value="movimiento_stock">
                    <input type="hidden" id="movimiento_producto_id" name="producto_id">

                    <div style="padding: 0 24px;">
                        <p><strong>Producto:</strong> <span id="movimiento_producto_nombre"></span></p>
                        <p><strong>Cantidad Actual:</strong> <span id="movimiento_cantidad_actual"></span></p>

                        <div class="form-group">
                            <label for="mov_tipo">Tipo de Movimiento *</label>
                            <select name="tipo" id="mov_tipo" required>
                                <option value="entrada">Entrada</option>
                                <option value="salida">Salida</option>
                                <option value="ajuste">Ajuste</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="mov_cantidad">Cantidad *</label>
                            <input type="number" name="cantidad" id="mov_cantidad" required min="1">
                        </div>

                        <div class="form-group">
                            <label for="mov_motivo">Motivo</label>
                            <textarea name="motivo" id="mov_motivo" rows="3" placeholder="Ej: Ajuste por revisión, entrada de mercancía, etc."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('modalMovimiento').classList.remove('show')">Cancelar</button>
                        <button type="submit" class="btn-primary">Guardar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>

    <script>
      function abrirModalMovimiento(id, nombre, cantidad) {
    document.getElementById('movimiento_producto_id').value = id;
    document.getElementById('movimiento_producto_nombre').innerText = nombre;
    document.getElementById('movimiento_cantidad_actual').innerText = cantidad;

    document.getElementById('modalMovimiento').classList.add('show');
}
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

      // Dropdown del perfil de usuario - click en todo el header-right
      document.getElementById('userProfile').addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdownMenu = document.getElementById('profileDropdownMenu');
        dropdownMenu.classList.toggle('show');
      });

      // Cerrar dropdown al hacer clic fuera
      document.addEventListener('click', function() {
        const dropdownMenu = document.getElementById('profileDropdownMenu');
        dropdownMenu.classList.remove('show');
      });

      // Validación del formulario de configuración
      document.getElementById('configForm')?.addEventListener('submit', function(e) {
        const nuevaPassword = document.getElementById('config_nueva_password').value;
        const confirmarPassword = document.getElementById('config_confirmar_password').value;
        const passwordActual = document.getElementById('config_password_actual').value;
        
        // Si se está intentando cambiar la contraseña
        if (nuevaPassword !== '') {
          // Verificar que se ingresó la contraseña actual
          if (passwordActual === '') {
            e.preventDefault();
            alert('Debe ingresar su contraseña actual para cambiar la contraseña');
            return;
          }
          
          // Verificar que la nueva contraseña tenga al menos 6 caracteres
          if (nuevaPassword.length < 6) {
            e.preventDefault();
            alert('La nueva contraseña debe tener al menos 6 caracteres');
            return;
          }
          
          // Verificar que las contraseñas coincidan
          if (nuevaPassword !== confirmarPassword) {
            e.preventDefault();
            alert('Las nuevas contraseñas no coinciden');
            return;
          }
        }
      });

      // En pantallas grandes, mantener el comportamiento normal
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          // Comportamiento para pantallas grandes
        }
      });

    function validarPorcentaje(input) {
    // Primero limpiar ceros a la izquierda
    let valor = limpiarCerosIzquierda(input);
    
    // Convertir a número
    let numericValue = parseFloat(valor);
    
    // Si no es un número válido, establecerlo en 0
    if (isNaN(numericValue)) {
        input.value = '0';
        numericValue = 0;
    }
    
    // Si el valor es mayor a 100, establecerlo en 100
    if (numericValue > 100) {
        input.value = '100';
        numericValue = 100;
    }
    
    // Si el valor es menor a 0, establecerlo en 0
    if (numericValue < 0) {
        input.value = '0';
        numericValue = 0;
    }
    
    // Recalcular el precio final si ya tenemos un precio base
    const precioInput = document.getElementById('product_precio');
    if (precioInput && precioInput.value) {
        calcularPrecioFinal();
    }
    
    return numericValue;
}

// También agrega validación en tiempo real para el evento de teclado
document.addEventListener('DOMContentLoaded', function() {
    const porcentajeInput = document.getElementById('product_porcentaje_descuento');
    
    if (porcentajeInput) {
        // Prevenir que se escriban caracteres no numéricos
        porcentajeInput.addEventListener('keypress', function(e) {
            const charCode = e.which ? e.which : e.keyCode;
            // Permitir solo números, punto decimal y teclas de control
            if (charCode !== 46 && charCode > 31 && (charCode < 48 || charCode > 57)) {
                e.preventDefault();
                return false;
            }
            
            // Prevenir múltiples puntos decimales
            if (charCode === 46 && this.value.includes('.')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Validar cuando se pega texto
        porcentajeInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            const numericValue = pastedText.replace(/[^0-9.]/g, '');
            
            // Remover puntos decimales adicionales
            let cleanValue = numericValue;
            if ((cleanValue.match(/\./g) || []).length > 1) {
                const parts = cleanValue.split('.');
                cleanValue = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Limitar a 100
            let finalValue = parseFloat(cleanValue);
            if (isNaN(finalValue)) finalValue = 0;
            if (finalValue > 100) finalValue = 100;
            if (finalValue < 0) finalValue = 0;
            
            this.value = finalValue;
            calcularPrecioFinal();
        });
        
        // Validar cuando se cambia el valor
       porcentajeInput.addEventListener('change', function() {
    // Limpiar y validar cuando el campo pierde el foco
    let valor = limpiarCerosIzquierda(this);
    validarPorcentaje(this);
    
    // Formatear el valor si tiene decimales .0 o .00
    if (valor.includes('.')) {
        let numericValue = parseFloat(valor);
        if (!isNaN(numericValue)) {
            // Si es un número entero, quitar los decimales
            if (numericValue === Math.floor(numericValue)) {
                this.value = numericValue.toString();
            } else {
                // Mantener hasta 2 decimales
                this.value = numericValue.toFixed(2);
            }
        }
    }
});
        
        // Validar en cada entrada
              porcentajeInput.addEventListener('input', function() {
          let valor = this.value;
          
          // Si el campo está vacío, permitir que el usuario borre
          if (valor === '') {
              calcularPrecioFinal();
              return;
          }
          
          // Remover caracteres no numéricos excepto punto decimal
          valor = valor.replace(/[^0-9.]/g, '');
          
          // Remover puntos decimales adicionales
          if ((valor.match(/\./g) || []).length > 1) {
              const parts = valor.split('.');
              valor = parts[0] + '.' + parts.slice(1).join('');
          }
          
          // Aplicar la función de limpieza de ceros
          valor = limpiarCerosIzquierda(this);
          
          // Validar el valor numérico
          validarPorcentaje(this);
      });
    }
});

function limpiarCerosIzquierda(input) {
    let valor = input.value;
    
    // Si el valor solo contiene ceros, dejarlo como "0"
    if (/^0+$/.test(valor)) {
        input.value = '0';
        return '0';
    }
    
    // Si empieza con ceros seguidos de números, quitarlos
    if (/^0+[1-9]/.test(valor)) {
        valor = valor.replace(/^0+/, '');
        input.value = valor;
    }
    
    return valor;
}
    </script>
  </body>
</html>