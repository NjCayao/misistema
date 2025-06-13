<?php
// pages/reset-password.php - Resetear contraseña con token
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
$token = $_GET['token'] ?? '';
$validToken = false;
$user = null;

// Verificar token
if (empty($token)) {
    $errors[] = 'Token de recuperación no válido';
} else {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar usuario con token válido
        $stmt = $db->prepare("
            SELECT id, first_name, email, reset_token_expires 
            FROM users 
            WHERE reset_token = ? AND is_active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $errors[] = 'Token de recuperación no válido o ya utilizado';
        } elseif (strtotime($user['reset_token_expires']) < time()) {
            $errors[] = 'El token de recuperación ha expirado. Solicita uno nuevo.';
        } else {
            $validToken = true;
        }
        
    } catch (Exception $e) {
        logError("Error verificando token de reset: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde.';
    }
}

// Procesar formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($newPassword)) {
        $errors[] = 'La nueva contraseña es obligatoria';
    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }
    
    if (empty($confirmPassword)) {
        $errors[] = 'Confirma tu nueva contraseña';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Hash de la nueva contraseña
            $hashedPassword = hashPassword($newPassword);
            
            // Actualizar contraseña y limpiar token
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$hashedPassword, $user['id']])) {
                // Enviar email de confirmación
                $emailSent = sendPasswordChangedEmail($user['email'], $user['first_name']);
                
                $success = '¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión con tu nueva contraseña.';
                
                // Log del cambio
                logError("Contraseña cambiada exitosamente para: {$user['email']}", 'password_changes.log');
                
                // Redirigir después de 3 segundos
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '/pages/login.php?reset=1';
                    }, 3000);
                </script>";
            } else {
                $errors[] = 'Error al actualizar la contraseña. Inténtalo más tarde.';
            }
            
        } catch (Exception $e) {
            logError("Error actualizando contraseña: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde.';
        }
    }
}

// Función para enviar email de confirmación de cambio de contraseña
function sendPasswordChangedEmail($email, $firstName) {
    $siteName = Settings::get('site_name', 'MiSistema');
    $subject = "Contraseña actualizada - $siteName";
    
    $body = "
    <h2>Hola $firstName,</h2>
    <p>Tu contraseña ha sido cambiada exitosamente.</p>
    <p><strong>Detalles del cambio:</strong></p>
    <ul>
        <li>Fecha: " . date('d/m/Y H:i:s') . "</li>
        <li>IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "</li>
    </ul>
    <p>Si no realizaste este cambio, contacta con nuestro soporte inmediatamente.</p>
    <p><a href='" . SITE_URL . "/pages/login.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Iniciar Sesión</a></p>
    <hr>
    <p><small>Equipo de $siteName</small></p>
    ";
    
    return EmailSystem::sendEmail($email, $subject, $body, true);
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Establece tu nueva contraseña">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .auth-section {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
            background: linear-gradient(45deg, #4facfe, #00f2fe);
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
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #007bff;
        }
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #28a745; width: 75%; }
        .strength-strong { background: #007bff; width: 100%; }
        
        .auth-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        
        .security-tips {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .security-tips h6 {
            color: #0c5460;
            margin-bottom: 0.5rem;
        }
        
        .security-tips ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
        
        .security-tips li {
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
                                <i class="fas fa-lock"></i>
                            </div>
                            <h2 class="mb-2">Nueva Contraseña</h2>
                            <p class="mb-0 opacity-75">
                                <?php if ($validToken && $user): ?>
                                    Hola <?php echo htmlspecialchars($user['first_name']); ?>, establece tu nueva contraseña
                                <?php else: ?>
                                    Recuperación de contraseña
                                <?php endif; ?>
                            </p>
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
                                
                                <div class="text-center">
                                    <a href="/pages/forgot-password.php" class="btn btn-primary">
                                        <i class="fas fa-redo me-2"></i>Solicitar Nuevo Token
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                
                                <div class="text-center">
                                    <p class="text-muted mb-3">Serás redirigido al login automáticamente...</p>
                                    <a href="/pages/login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>Ir al Login Ahora
                                    </a>
                                </div>
                            <?php elseif ($validToken): ?>
                                <!-- Consejos de seguridad -->
                                <div class="security-tips">
                                    <h6><i class="fas fa-shield-alt me-1"></i>Consejos de seguridad:</h6>
                                    <ul>
                                        <li>Usa una contraseña única que no hayas usado antes</li>
                                        <li>Combina letras mayúsculas, minúsculas, números y símbolos</li>
                                        <li>Evita información personal como nombres o fechas</li>
                                        <li>Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</li>
                                    </ul>
                                </div>
                                
                                <!-- Formulario -->
                                <form method="POST" id="resetForm" novalidate>
                                    <!-- Nueva Contraseña -->
                                    <div class="form-floating mb-3 position-relative">
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Nueva contraseña" required autofocus>
                                        <label for="new_password">
                                            <i class="fas fa-lock me-2"></i>Nueva Contraseña
                                        </label>
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')">
                                            <i class="fas fa-eye" id="toggleIcon1"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <small id="passwordHelp" class="form-text text-muted mb-3">
                                        Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                                    </small>
                                    
                                    <!-- Confirmar Contraseña -->
                                    <div class="form-floating mb-4 position-relative">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirmar contraseña" required>
                                        <label for="confirm_password">
                                            <i class="fas fa-lock me-2"></i>Confirmar Contraseña
                                        </label>
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                            <i class="fas fa-eye" id="toggleIcon2"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Botón -->
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i>Actualizar Contraseña
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Footer -->
                        <div class="auth-footer">
                            <p class="mb-0">
                                <a href="/pages/login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Volver al Login
                                </a>
                            </p>
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
            const form = document.getElementById('resetForm');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordHelp = document.getElementById('passwordHelp');
            
            if (form && newPasswordInput && confirmPasswordInput) {
                // Validador de fuerza de contraseña
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strength = calculatePasswordStrength(password);
                    
                    // Actualizar barra de fuerza
                    strengthBar.className = 'password-strength-bar';
                    if (strength.score > 0) {
                        strengthBar.classList.add(`strength-${strength.level}`);
                    }
                    
                    // Actualizar texto de ayuda
                    passwordHelp.textContent = strength.message;
                    passwordHelp.className = `form-text text-${strength.color} mb-3`;
                });
                
                // Validar confirmación de contraseña
                confirmPasswordInput.addEventListener('input', function() {
                    const password = newPasswordInput.value;
                    const confirm = this.value;
                    
                    if (confirm && password !== confirm) {
                        this.setCustomValidity('Las contraseñas no coinciden');
                        this.classList.add('is-invalid');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('is-invalid');
                        if (confirm) this.classList.add('is-valid');
                    }
                });
                
                // Validación en tiempo real
                [newPasswordInput, confirmPasswordInput].forEach(input => {
                    input.addEventListener('blur', validateInput);
                    input.addEventListener('input', validateInput);
                });
                
                function validateInput() {
                    if (this.validity.valid) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
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
            }
        });
        
        function togglePassword(fieldId, iconId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        function calculatePasswordStrength(password) {
            let score = 0;
            let level = 'weak';
            let message = 'Muy débil';
            let color = 'danger';
            
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;
            
            switch (score) {
                case 0:
                case 1:
                    level = 'weak';
                    message = 'Muy débil';
                    color = 'danger';
                    break;
                case 2:
                    level = 'fair';
                    message = 'Débil';
                    color = 'warning';
                    break;
                case 3:
                    level = 'good';
                    message = 'Buena';
                    color = 'success';
                    break;
                case 4:
                case 5:
                    level = 'strong';
                    message = 'Muy fuerte';
                    color = 'primary';
                    break;
            }
            
            return { score, level, message, color };
        }
    </script>
</body>
</html>