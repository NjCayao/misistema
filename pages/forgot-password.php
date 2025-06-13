<?php
// pages/forgot-password.php - Solicitar recuperación de contraseña
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    // Validaciones
    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Ingresa un email válido';
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar que el usuario existe y está activo
            $stmt = $db->prepare("
                SELECT id, first_name, email, is_verified, is_active 
                FROM users 
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Por seguridad, no revelar si el email existe o no
                $success = 'Si el email existe en nuestro sistema, recibirás las instrucciones para recuperar tu contraseña.';
            } elseif (!$user['is_verified']) {
                $errors[] = 'Tu cuenta no está verificada. Revisa tu email para verificar tu cuenta primero.';
            } else {
                // Generar token de recuperación
                $resetToken = generateResetToken();
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $db->prepare("
                    UPDATE users 
                    SET reset_token = ?, reset_token_expires = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$resetToken, $resetExpires, $user['id']])) {
                    // Enviar email de recuperación
                    $emailSent = EmailSystem::sendPasswordResetEmail($email, $user['first_name'], $resetToken);
                    
                    if ($emailSent) {
                        $success = 'Te hemos enviado las instrucciones para recuperar tu contraseña. Revisa tu email.';
                        logError("Solicitud de recuperación de contraseña para: $email", 'password_resets.log');
                    } else {
                        $errors[] = 'Error al enviar el email. Inténtalo más tarde.';
                        logError("Error enviando email de recuperación para: $email", 'password_reset_errors.log');
                    }
                } else {
                    $errors[] = 'Error del sistema. Inténtalo más tarde.';
                }
            }
            
        } catch (Exception $e) {
            logError("Error en forgot password: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde.';
        }
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Recupera el acceso a tu cuenta">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .auth-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .auth-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }
        
        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            z-index: 2;
            max-width: 450px;
            margin: 0 auto;
        }
        
        .auth-header {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: white;
            padding: 3rem 2rem 2rem;
            text-align: center;
        }
        
        .auth-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
        }
        
        .auth-body {
            padding: 2rem;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .form-floating > .form-control:focus ~ label {
            color: #007bff;
        }
        
        .auth-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-box h6 {
            color: #0c5460;
            margin-bottom: 0.5rem;
        }
        
        .info-box ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
        
        .info-box li {
            color: #0c5460;
            margin-bottom: 0.25rem;
        }
        
        @keyframes float {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100px); }
        }
    </style>
</head>
<body>
    <div class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="auth-card">
                        <!-- Header -->
                        <div class="auth-header">
                            <div class="auth-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <h2 class="mb-2">Recuperar Contraseña</h2>
                            <p class="mb-0 opacity-75">Te ayudaremos a recuperar el acceso a tu cuenta</p>
                        </div>
                        
                        <!-- Body -->
                        <div class="auth-body">
                            <!-- Mostrar mensajes -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                
                                <div class="text-center">
                                    <p class="text-muted mb-3">¿No recibiste el email?</p>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
                                            <i class="fas fa-redo me-2"></i>Intentar Nuevamente
                                        </button>
                                        <a href="/pages/login.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Volver al Login
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Información del proceso -->
                                <div class="info-box">
                                    <h6><i class="fas fa-info-circle me-1"></i>¿Cómo funciona?</h6>
                                    <ul>
                                        <li>Ingresa tu email registrado</li>
                                        <li>Te enviaremos un enlace seguro</li>
                                        <li>Haz clic en el enlace y crea una nueva contraseña</li>
                                        <li>El enlace expira en 1 hora por seguridad</li>
                                    </ul>
                                </div>
                                
                                <!-- Formulario -->
                                <form method="POST" id="forgotForm" novalidate>
                                    <!-- Email -->
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               placeholder="Email" required autofocus>
                                        <label for="email">
                                            <i class="fas fa-envelope me-2"></i>Email de tu cuenta
                                        </label>
                                    </div>
                                    
                                    <!-- Botón -->
                                    <div class="d-grid gap-2 mb-3">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>Enviar Instrucciones
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Footer -->
                        <div class="auth-footer">
                            <p class="mb-2">
                                ¿Recordaste tu contraseña? <a href="/pages/login.php" class="text-decoration-none">Iniciar Sesión</a>
                            </p>
                            <div>
                                <a href="<?php echo SITE_URL; ?>" class="text-muted text-decoration-none">
                                    <i class="fas fa-home me-1"></i>Volver al inicio
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const emailInput = document.getElementById('email');
            
            if (form && emailInput) {
                // Validación en tiempo real
                emailInput.addEventListener('blur', validateInput);
                emailInput.addEventListener('input', validateInput);
                
                function validateInput() {
                    if (emailInput.validity.valid) {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                    } else {
                        emailInput.classList.remove('is-valid');
                        emailInput.classList.add('is-invalid');
                    }
                }
                
                // Envío del formulario
                form.addEventListener('submit', function(e) {
                    const isValid = form.checkValidity();
                    
                    if (!isValid) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                });
                
                // Detectar Enter
                emailInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>