[file name]: vendedor.php
[file content begin]
<?php
session_start();
require_once 'conexion.php';

// Verificar si el usuario está logueado y es vendedor (rol_id = 2)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Vendedor - Estilo Real</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c5aa0;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .welcome {
            text-align: center;
            margin-bottom: 30px;
        }
        .menu {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }
        .menu a {
            padding: 10px 20px;
            background: #2c5aa0;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .logout {
            text-align: center;
            margin-top: 30px;
        }
        .logout a {
            color: #666;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ESTILO REAL - PANEL DE VENDEDOR</h1>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h2>
            <p>Rol: <?php echo htmlspecialchars($_SESSION['rol']); ?></p>
        </div>
        
        <div style="text-align: center; padding: 40px;">
            <h3>ESTA ES LA PÁGINA DE VENDEDOR</h3>
            <p>Desde aquí puedes realizar ventas, gestionar clientes y consultar productos.</p>
        </div>

        <div class="menu">
            <a href="#">Realizar Venta</a>
            <a href="#">Clientes</a>
            <a href="#">Productos</a>
            <a href="#">Mis Ventas</a>
        </div>

        <div class="logout">
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>
