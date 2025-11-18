<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ESTILO REAL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .welcome {
            color: #333;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .logout {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .logout:hover {
            background: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="welcome">Bienvenido a ESTILO REAL, <?php echo htmlspecialchars($_SESSION['cliente_nombre']); ?>!</h1>
        <p>Has iniciado sesión correctamente como cliente.</p>
        <p>Aquí podrás gestionar tu perfil, ver tu historial de compras y explorar nuestros productos.</p>
        <a href="logout.php" class="logout">Cerrar Sesión</a>
    </div>
</body>
</html>