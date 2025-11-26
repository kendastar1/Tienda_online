
<?php
session_start();
require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $sql = "SELECT u.*, r.nombre as rol_nombre FROM usuarios u 
                INNER JOIN roles r ON u.rol_id = r.id 
                WHERE u.correo = :username OR u.nombre = :username";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar contraseña sin hash (texto plano)
            if ($password === $usuario['password']) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol_nombre'];
                $_SESSION['rol_id'] = $usuario['rol_id'];
                
                // Redirigir según el rol
                switch ($usuario['rol_id']) {
                    case 1: // Admin
                        header('Location: admin.php');
                        break;
                    case 2: // Vendedor
                        header('Location: vendedor.php');
                        break;
                    case 3: // Cajero
                        header('Location: cajero.php');
                        break;
                    
                    case 6: // Marketing
                        header('Location: marketing.php');
                        break;
                    case 7: // Proveedor
                        header('Location: proveedores.php');
                        break;
                    default:
                        $error = 'Rol no válido';
                }
                exit();
            } else {
                $error = 'Contraseña incorrecta';
            }
        } else {
            $error = 'Usuario no encontrado';
        }
    } catch(PDOException $e) {
        $error = 'Error en el sistema: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Estilo Real</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 2px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            border: 1px solid #e0e0e0;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 600;
            color: #1a1a1a;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            color: #666;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-field {
            width: 100%;
            padding: 12px 0;
            border: none;
            border-bottom: 1px solid #d0d0d0;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: transparent;
            color: #333;
            border-radius: 0;
        }

        .input-field:focus {
            outline: none;
            border-bottom-color: #1a1a1a;
            border-bottom-width: 2px;
        }

        .input-field::placeholder {
            color: #999;
            font-style: italic;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: #1a1a1a;
            color: white;
            border: none;
            border-radius: 1px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
        }

        .btn-login:hover {
            background: #333;
        }

        .error-message {
            color: #d32f2f;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .input-field.error {
            border-bottom-color: #d32f2f;
            border-bottom-width: 2px;
        }

        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 2px;
            text-align: center;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 40px 25px;
            }
            
            .brand-name {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand-header">
            <div class="brand-name">ESTILO REAL</div>
            <div class="brand-subtitle">Acceso Exclusivo</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Email o Usuario</label>
                <input type="text" id="username" name="username" class="input-field" placeholder="usuario o email@ejemplo.com" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="input-field" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-login">Ingresar</button>
        </form>
    </div>
</body>
</html>
