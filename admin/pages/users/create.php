<?php
// admin/pages/users/create.php - Crear usuario desde admin
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$errors = [];
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    $sendWelcomeEmail = isset($_POST['send_welcome_email']);
    
    // Validaciones
    if (empty($firstName)) {
        $errors[] = 'El nombre es obligatorio';
    }
    
    if (empty($lastName)) {
        $errors[] = 'El apellido es obligatorio';
    }
    
    if (empty($email) || !isValidEmail($email)) {
        $errors[] = 'Ingresa un email válido';
    }
    
    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    // Verificar si el email ya existe
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Este email ya está registrado';
            }
        } catch (Exception $e) {
            logError("Error verificando email existente: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
    
    // Crear usuario si no hay errores
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Generar código de verificación si no está verificado
            $verificationCode = $isVerified ? null : generateVerificationCode();
            $verificationExpires = $isVerified ? null : date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' minutes'));
            
            // Hash de la contraseña
            $hashedPassword = hashPassword($password);
            
            $stmt = $db->prepare("
                INSERT INTO users (
                    first_name, last_name, email, phone, country, 
                    password, verification_code, verification_expires, 
                    is_verified, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([
                $firstName, $lastName, $email, $phone, $country,
                $hashedPassword, $verificationCode, $verificationExpires,
                $isVerified, $isActive
            ])) {
                $userId = $db->lastInsertId();
                
                // Enviar email de bienvenida si está seleccionado
                if ($sendWelcomeEmail) {
                    if ($isVerified) {
                        // Enviar email de bienvenida
                        EmailSystem::sendWelcomeEmail($email, $firstName);
                    } else {
                        // Enviar email de verificación
                        EmailSystem::sendVerificationEmail($email, $firstName, $verificationCode);
                    }
                }
                
                setFlashMessage('success', "Usuario '$firstName $lastName' creado exitosamente.");
                redirect('index.php');
            } else {
                $errors[] = 'Error al crear el usuario';
            }
            
        } catch (Exception $e) {
            logError("Error creando usuario: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear Usuario | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
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
        
        .form-section {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Crear Usuario</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                                <li class="breadcrumb-item active">Crear</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    
                    <!-- Mensajes de error -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario -->
                    <form method="POST" id="createUserForm">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Información Personal -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-user me-2"></i>Información Personal
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="first_name">Nombre *</label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="last_name">Apellido *</label>
                                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="email">Email *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phone">Teléfono</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="country">País</label>
                                            <select class="form-control" id="country" name="country">
                                                <option value="">Seleccionar país</option>
                                                <option value="PE" <?php echo ($_POST['country'] ?? '') === 'PE' ? 'selected' : ''; ?>>Perú</option>
                                                <option value="AR" <?php echo ($_POST['country'] ?? '') === 'AR' ? 'selected' : ''; ?>>Argentina</option>
                                                <option value="CL" <?php echo ($_POST['country'] ?? '') === 'CL' ? 'selected' : ''; ?>>Chile</option>
                                                <option value="CO" <?php echo ($_POST['country'] ?? '') === 'CO' ? 'selected' : ''; ?>>Colombia</option>
                                                <option value="EC" <?php echo ($_POST['country'] ?? '') === 'EC' ? 'selected' : ''; ?>>Ecuador</option>
                                                <option value="MX" <?php echo ($_POST['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>México</option>
                                                <option value="ES" <?php echo ($_POST['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>España</option>
                                                <option value="US" <?php echo ($_POST['country'] ?? '') === 'US' ? 'selected' : ''; ?>>Estados Unidos</option>
                                                <option value="OTHER" <?php echo ($_POST['country'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contraseña -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-lock me-2"></i>Contraseña
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="password">Contraseña *</label>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                    <div class="password-strength">
                                                        <div class="password-strength-bar" id="strengthBar"></div>
                                                    </div>
                                                    <small id="passwordHelp" class="form-text text-muted">
                                                        Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="confirm_password">Confirmar Contraseña *</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Configuración -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-cogs me-2"></i>Configuración
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                       <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                                                <label class="custom-control-label" for="is_active">Usuario Activo</label>
                                            </div>
                                            <small class="text-muted">El usuario puede iniciar sesión</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="is_verified" name="is_verified"
                                                       <?php echo isset($_POST['is_verified']) ? 'checked' : 'checked'; ?>>
                                                <label class="custom-control-label" for="is_verified">Email Verificado</label>
                                            </div>
                                            <small class="text-muted">Marcar como verificado o enviar código</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="send_welcome_email" name="send_welcome_email" checked>
                                                <label class="custom-control-label" for="send_welcome_email">Enviar Email de Bienvenida</label>
                                            </div>
                                            <small class="text-muted">Enviar email tras crear la cuenta</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="card">
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-save me-2"></i>Crear Usuario
                                        </button>
                                        <a href="index.php" class="btn btn-secondary btn-block">
                                            <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Información -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-info-circle me-2"></i>Información
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <small class="text-muted">
                                            <ul class="pl-3">
                                                <li>Los campos marcados con * son obligatorios</li>
                                                <li>Si no está verificado, se enviará código por email</li>
                                                <li>El usuario recibirá un email con sus credenciales</li>
                                                <li>Puede cambiar su contraseña después del primer login</li>
                                            </ul>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        $(document).ready(function() {
            const passwordInput = $('#password');
            const confirmPasswordInput = $('#confirm_password');
            const strengthBar = $('#strengthBar');
            const passwordHelp = $('#passwordHelp');
            
            // Validador de fuerza de contraseña
            passwordInput.on('input', function() {
                const password = $(this).val();
                const strength = calculatePasswordStrength(password);
                
                // Actualizar barra de fuerza
                strengthBar.removeClass('strength-weak strength-fair strength-good strength-strong');
                if (strength.score > 0) {
                    strengthBar.addClass(`strength-${strength.level}`);
                }
                
                // Actualizar texto de ayuda
                passwordHelp.text(strength.message);
                passwordHelp.removeClass('text-muted text-danger text-warning text-success text-primary');
                passwordHelp.addClass(`text-${strength.color}`);
            });
            
            // Validar confirmación de contraseña
            confirmPasswordInput.on('input', function() {
                const password = passwordInput.val();
                const confirm = $(this).val();
                
                if (confirm && password !== confirm) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                    if (confirm) $(this).addClass('is-valid');
                }
            });
            
            // Generar contraseña automática
            function generatePassword() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%&*';
                let password = '';
                for (let i = 0; i < 12; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return password;
            }
            
            // Agregar botón para generar contraseña
            const generateBtn = $('<button type="button" class="btn btn-outline-secondary btn-sm mt-2">Generar Contraseña</button>');
            generateBtn.on('click', function() {
                const newPassword = generatePassword();
                passwordInput.val(newPassword);
                confirmPasswordInput.val(newPassword);
                passwordInput.trigger('input');
                confirmPasswordInput.trigger('input');
            });
            passwordInput.parent().append(generateBtn);
        });
        
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