<?php
// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tienda_ropa";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Consulta SQL para obtener productos de relojes
$sql = "SELECT id, nombre, descripcion, precio, descuento, porcentaje_descuento, precio_final, cantidad, categoria, imagen, estado
        FROM productos_stock 
        WHERE categoria = 'relojes' 
        AND estado = 'activo' 
        ORDER BY fecha_creacion DESC";

$result = $conn->query($sql);

$products = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Corregir la ruta de la imagen
        if (!empty($row['imagen'])) {
            // Agregar la ruta base si no la tiene
            if (strpos($row['imagen'], 'panel_para_roles/uploads/') === false) {
                $row['imagen'] = 'panel_para_roles/uploads/' . $row['imagen'];
            }
        } else {
            // Imagen por defecto si no hay imagen
            $row['imagen'] = 'https://via.placeholder.com/300x400?text=Reloj+No+Disponible';
        }
        $products[] = $row;
    }
}

// Cerrar conexión
$conn->close();

// Devolver datos en formato JSON
header('Content-Type: application/json');
echo json_encode($products);
?>