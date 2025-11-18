<?php
session_start();
require_once 'conexion.php';

// Verificar si el usuario está logueado y es cajero (rol_id = 3)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 3) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Cajero - Estilo Real</title>
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
            background: #d35400;
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
            background: #d35400;
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
        <h1>ESTILO REAL - PANEL DE CAJERO</h1>
    </div>
    
    <div class="container">
        <div class="welcome">
            <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h2>
            <p>Rol: <?php echo htmlspecialchars($_SESSION['rol']); ?></p>
        </div>
        
        <div style="text-align: center; padding: 40px;">
            <h3>ESTA ES LA PÁGINA DE CAJERO</h3>
            <p>Desde aquí puedes procesar pagos, gestionar transacciones y generar recibos.</p>
        </div>

        <div class="menu">
            <a href="#">Procesar Pago</a>
            <a href="#">Transacciones</a>
            <a href="#">Corte de Caja</a>
            <a href="#">Recibos</a>
        </div>

        <div class="logout">
            <a href="logout.php">Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>