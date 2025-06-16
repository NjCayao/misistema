<?php
// pages/verify-email.php - Verificación de email actualizada con sistema integrado
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
    redirect('/dashboard');
}

$email = $_GET['email'] ?? '';
$errors = [];
$success = '';
$codeExpired = false;

// Validar que se proporcione email
if (empty($email) || !isValidEmail($email)) {
    redirect('/pages/register.php');
}

// Procesar verificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verificationCode = sanitize($_POST['verification_code'] ?? '');
    
    if (empty($verificationCode)) {
        $errors[] = 'Ingresa el código de verificación';
    } elseif (strlen($verificationCode) !== 6 || !ctype_digit($verificationCode)) {
        $errors[] = 'El código debe tener 6 dígitos';
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Buscar usuario con código válido
            $stmt = $db->prepare("
                SELECT id, first_name, is_verified, verification_expires 
                FROM users 
                WHERE email = ? AND verification_code = ? AND is_active = 1
            ");
            $stmt->execute([$email, $verificationCode]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $errors[] = 'Código de verificación inválido';
            } elseif ($user['is_verified']) {
                $success = 'Tu cuenta ya está verificada';
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '" . SITE_URL . "/pages/login.php';
                    }, 2000);
                </script>";
            } elseif (strtotime($user['verification_expires']) < time()) {
                $codeExpired = true;
                $errors[] = 'El código de verificación ha expirado';
            } else {
                // Verificar cuenta
                $stmt = $db->prepare("
                    UPDATE users 
                    SET is_verified = 1, verification_code = NULL, verification_expires = NULL, updated_at = NOW() 
                    WHERE email = ?
                ");
                
                if ($stmt->execute([$email])) {
                    // Enviar email de bienvenida usando el nuevo sistema
                    sendWelcomeEmail($email, $user['first_name']);
                    
                    $success = '¡Cuenta verificada exitosamente! Redirigiendo al login...';
                    
                    // Redirigir después de 2 segundos
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '" . SITE_URL . "/pages/login.php?verified=1';
                        }, 2000);
                    </script>";
                } else {
                    $errors[] = 'Error al verificar la cuenta. Inténtalo más tarde';
                }
            }
            
        } catch (Exception $e) {
            logError("Error en verificación: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
}

// Procesar reenvío de código
if (isset($_POST['resend_code'])) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Verificar que el usuario existe y no está verificado
        $stmt = $db->prepare("
            SELECT id, first_name, is_verified 
            FROM users 
            WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $errors[] = 'Usuario no encontrado';
        } elseif ($user['is_verified']) {
            $success = 'Tu cuenta ya está verificada';
        } else {
            // Generar nuevo código
            $newCode = generateVerificationCode();
            $newExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' minutes'));
            
            $stmt = $db->prepare("
                UPDATE users 
                SET verification_code = ?, verification_expires = ?, updated_at = NOW() 
                WHERE email = ?
            ");
            
            if ($stmt->execute([$newCode, $newExpires, $email])) {
                // Usar el nuevo sistema de email
                if (sendVerificationEmail($email, $user['first_name'], $newCode)) {
                    $success = 'Nuevo código enviado a tu email';
                    $codeExpired = false;
                } else {
                    $errors[] = 'Error al enviar el email. Inténtalo más tarde';
                }
            } else {
                $errors[] = 'Error al generar nuevo código';
            }
        }
        
    } catch (Exception $e) {
        logError("Error reenviando código: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde';
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Email - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Verifica tu cuenta con el código enviado a tu email">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .verify-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .verify-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }
        
        .verify-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            z-index: 2;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .verify-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 3rem 2rem 2rem;
            text-align: center;
        }
        
        .verify-icon {
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
        
        .verify-body {
            padding: 2rem;
        }
        
        .code-input {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }
        
        .code-digit {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .code-digit:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .code-digit.filled {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        
        .countdown {
            text-align: center;
            margin: 1rem 0;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .countdown.expired {
            color: #dc3545;
            font-weight: bold;
        }
        
        .resend-section {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            margin-top: 2rem;
        }
        
        @keyframes float {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100px); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="verify-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="verify-card">
                        <!-- Header -->
                        <div class="verify-header">
                            <div class="verify-icon">
                                <i class="fas fa-envelope-open"></i>
                            </div>
                            <h2 class="mb-2">Verificar tu Email</h2>
                            <p class="mb-0 opacity-75">
                                Hemos enviado un código de 6 dígitos a:<br> No olvides revisar tu carpeta de spam.
                                <strong><?php echo htmlspecialchars($email); ?></strong>
                            </p>
                        </div>
                        
                        <!-- Body -->
                        <div class="verify-body">
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
                            <?php endif; ?>
                            
                            <!-- Formulario de Verificación -->
                            <form method="POST" id="verifyForm">
                                <div class="text-center mb-3">
                                    <label class="form-label">Ingresa el código de 6 dígitos:</label>
                                </div>
                                
                                <!-- Input oculto para el código completo -->
                                <input type="hidden" name="verification_code" id="verification_code">
                                
                                <!-- Inputs individuales para cada dígito -->
                                <div class="code-input">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="0">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="1">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="2">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="3">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="4">
                                    <input type="text" class="form-control code-digit" maxlength="1" data-index="5">
                                </div>
                                
                                <!-- Countdown -->
                                <div class="countdown" id="countdown">
                                    El código expira en <span id="timeLeft"></span>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg w-100" id="verifyBtn" disabled>
                                    <i class="fas fa-check me-2"></i>Verificar Código
                                </button>
                            </form>
                            
                            <!-- Sección de reenvío -->
                            <div class="resend-section">
                                <p class="text-muted mb-3">¿No recibiste el código?</p>
                                
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="resend_code" class="btn btn-outline-success" id="resendBtn">
                                        <i class="fas fa-redo me-2"></i>Reenviar Código
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <a href="<?php echo SITE_URL; ?>/pages/register.php" class="text-muted text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>Volver al registro
                                    </a>
                                </div>
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
            const codeInputs = document.querySelectorAll('.code-digit');
            const verificationCodeInput = document.getElementById('verification_code');
            const verifyBtn = document.getElementById('verifyBtn');
            const verifyForm = document.getElementById('verifyForm');
            const countdown = document.getElementById('countdown');
            const timeLeft = document.getElementById('timeLeft');
            
            // Configurar comportamiento de inputs de código
            codeInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value;
                    
                    // Solo permitir números
                    if (!/^\d$/.test(value)) {
                        e.target.value = '';
                        return;
                    }
                    
                    e.target.classList.add('filled');
                    
                    // Mover al siguiente input
                    if (value && index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }
                    
                    updateVerificationCode();
                });
                
                input.addEventListener('keydown', function(e) {
                    // Manejar backspace
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        codeInputs[index - 1].focus();
                        codeInputs[index - 1].value = '';
                        codeInputs[index - 1].classList.remove('filled');
                        updateVerificationCode();
                    }
                    
                    // Manejar paste
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (verifyBtn.disabled === false) {
                            verifyForm.submit();
                        }
                    }
                });
                
                // Manejar paste de código completo
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text');
                    const digits = pastedData.replace(/\D/g, '').slice(0, 6);
                    
                    if (digits.length === 6) {
                        codeInputs.forEach((inp, idx) => {
                            inp.value = digits[idx] || '';
                            inp.classList.toggle('filled', !!digits[idx]);
                        });
                        updateVerificationCode();
                    }
                });
            });
            
            function updateVerificationCode() {
                const code = Array.from(codeInputs).map(input => input.value).join('');
                verificationCodeInput.value = code;
                verifyBtn.disabled = code.length !== 6;
                
                // Auto-submit cuando se completa el código
                if (code.length === 6) {
                    setTimeout(() => {
                        verifyForm.submit();
                    }, 500);
                }
            }
            
            // Countdown timer
            let expiryTime = <?php echo VERIFICATION_CODE_EXPIRY * 60; ?>; // en segundos
            
            function updateCountdown() {
                if (expiryTime <= 0) {
                    countdown.innerHTML = '<span class="text-danger">El código ha expirado</span>';
                    countdown.classList.add('expired');
                    verifyBtn.disabled = true;
                    return;
                }
                
                const minutes = Math.floor(expiryTime / 60);
                const seconds = expiryTime % 60;
                timeLeft.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                expiryTime--;
            }
            
            // Inicializar countdown si no hay errores de código expirado
            <?php if (!$codeExpired): ?>
                updateCountdown();
                setInterval(updateCountdown, 1000);
            <?php else: ?>
                countdown.innerHTML = '<span class="text-danger">El código ha expirado</span>';
                countdown.classList.add('expired');
            <?php endif; ?>
            
            // Efecto de shake en error
            <?php if (!empty($errors)): ?>
                document.querySelector('.verify-card').classList.add('shake');
                setTimeout(() => {
                    document.querySelector('.verify-card').classList.remove('shake');
                }, 500);
            <?php endif; ?>
            
            // Auto-focus primer input
            codeInputs[0].focus();
        });
    </script>
</body>
</html>