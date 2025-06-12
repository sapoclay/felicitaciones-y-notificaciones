<?php
/**
 * Sistema de Login para Correos Masivos
 */

require_once 'includes/auth.php';

// Redirigir si ya está autenticado
if (estaAutenticado()) {
    header('Location: index.php');
    exit;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        if (verificarCredenciales($usuario, $password)) {
            iniciarSesion($usuario);
            $success = 'Login exitoso. Redirigiendo...';
            
            // Redirigir después de 1 segundo
            header('Refresh: 1; url=index.php');
        } else {
            $error = 'Usuario o contraseña incorrectos.';
            
            // Log de intento de login fallido (opcional)
            error_log("Intento de login fallido para usuario: " . $usuario . " desde IP: " . $_SERVER['REMOTE_ADDR']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Correos Masivos</title>
    <link rel="icon" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iIzIxOTZGMyIgZD0iTTIwIDRINEMyLjkgNCAyIDQuOSAyIDY2di0xMmMwIDEuMS45IDIuOSAyIDJoMTZjMS4xIDAgMi0uOSAyLTItMlY6YzAtMS4xLS45LTItMi0yek0yMCA4bC04IDUtOC01VjZsOCA1IDgtNXYyeiIvPjwvc3ZnPg==" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: white;
        }
        
        .login-header {
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .login-logo {
            max-width: 120px;
            max-height: 80px;
            width: auto;
            height: auto;
            display: block;
            object-fit: contain;
            margin: 0 auto 15px auto;
        }
        
        .login-header h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .login-header p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input:invalid {
            border-color: #e74c3c;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            transition: opacity 0.5s ease, transform 0.5s ease;
            transform: translateY(0);
        }
        
        .alert-error {
            background-color: #ffeaea;
            color: #d63384;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0a3622;
            border: 1px solid #a3cfbb;
        }
        
        .credentials-info {
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            text-align: left;
            font-size: 13px;
        }
        
        .credentials-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 14px;
        }
        
        .credentials-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .credentials-info code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .loading .login-btn {
            background: #ccc;
        }
        
        /* Estilos para el toggle de contraseña en login */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            width: 100%;
            padding-right: 35px !important;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            padding: 0;
            color: #666;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .toggle-password:hover {
            opacity: 1;
        }

        .toggle-password:focus {
            outline: none;
            color: #667eea;
        }
        
        @media (max-width: 480px) {
            .login-box {
                margin: 20px;
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box" id="loginBox">
            <div class="login-header">
                <img src="img/logo.png" alt="Logo" class="login-logo">
                <h1>Sistema de Correos</h1>
                <p>Acceso al sistema de envío masivo</p>
            </div>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success) && !empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Éxito:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="usuario">👤 Usuario</label>
                    <input type="text" 
                           id="usuario" 
                           name="usuario" 
                           required 
                           autocomplete="username"
                           value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                           placeholder="Ingrese su usuario">
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Contraseña</label>
                    <div class="password-container">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               autocomplete="current-password"
                               placeholder="Ingrese su contraseña">
                        <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">👁️</button>
                    </div>
                </div>

                <div class="credentials-info">
                    <h4>Credenciales de acceso de ejemplo</h4>
                    <p><strong>Usuario:</strong> <code>entreunosyceros</code></p>
                    <p><strong>Contraseña:</strong> <code>entreunosyceros</code></p>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Efecto de carga en el formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBox = document.getElementById('loginBox');
            const loginBtn = document.getElementById('loginBtn');
            
            loginBox.classList.add('loading');
            loginBtn.textContent = 'Iniciando sesión...';
        });
        
        // Enfocar campo usuario al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('usuario').focus();
            
            // Auto-ocultar mensajes de error y éxito después de 5 segundos
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    
                    // Remover completamente el elemento después de la animación
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 500);
                }, 5000); // 5 segundos
            });
            
            // Toggle de contraseña en login
            const togglePassword = document.querySelector('.toggle-password');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const passwordInput = document.getElementById('password');
                    const type = passwordInput.getAttribute('type');
                    
                    if (type === 'password') {
                        passwordInput.setAttribute('type', 'text');
                        this.textContent = '🔒';
                        this.setAttribute('aria-label', 'Ocultar contraseña');
                    } else {
                        passwordInput.setAttribute('type', 'password');
                        this.textContent = '👁️';
                        this.setAttribute('aria-label', 'Mostrar contraseña');
                    }
                });
            }
        });
        
        // Manejar Enter para enviar formulario
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
