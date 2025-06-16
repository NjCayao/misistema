<?php
// pages/profile.php - Página de Mi Perfil
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Obtener datos del usuario
$user = getCurrentUser();
if (!$user) {
    logoutUser();
    redirect('/login');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');
$errors = [];

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        if (isset($_POST['update_profile'])) {
            // Actualizar perfil
            $firstName = sanitize($_POST['first_name'] ?? '');
            $lastName = sanitize($_POST['last_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $country = sanitize($_POST['country'] ?? '');
            
            // Validaciones
            if (empty($firstName)) {
                $errors[] = 'El nombre es obligatorio';
            }
            if (empty($lastName)) {
                $errors[] = 'El apellido es obligatorio';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, country = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$firstName, $lastName, $phone, $country, $user['id']])) {
                    // Actualizar sesión
                    $_SESSION[SESSION_NAME]['first_name'] = $firstName;
                    $_SESSION[SESSION_NAME]['last_name'] = $lastName;
                    
                    setFlashMessage('success', 'Perfil actualizado exitosamente');
                    redirect('/perfil');
                } else {
                    $errors[] = 'Error al actualizar el perfil';
                }
            }
            
        } elseif (isset($_POST['change_password'])) {
            // Cambiar contraseña
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validaciones
            if (empty($currentPassword)) {
                $errors[] = 'Ingresa tu contraseña actual';
            }
            if (empty($newPassword)) {
                $errors[] = 'Ingresa la nueva contraseña';
            } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'La nueva contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Las contraseñas no coinciden';
            }
            
            // Verificar contraseña actual
            if (empty($errors) && !verifyPassword($currentPassword, $user['password'])) {
                $errors[] = 'La contraseña actual es incorrecta';
            }
            
            if (empty($errors)) {
                $hashedPassword = hashPassword($newPassword);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$hashedPassword, $user['id']])) {
                    setFlashMessage('success', 'Contraseña cambiada exitosamente');
                    redirect('/perfil');
                } else {
                    $errors[] = 'Error al cambiar la contraseña';
                }
            }
            
        } elseif (isset($_POST['upload_avatar'])) {
            // Subir avatar (implementar más adelante)
            $errors[] = 'Función de avatar próximamente disponible';
        }
        
    } catch (Exception $e) {
        logError("Error en perfil: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde';
    }
}

// Refrescar datos del usuario
$user = getCurrentUser();

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Edita tu perfil y configuraciones de cuenta">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0 2rem;
        }
        
        .profile-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-body {
            padding: 2rem;
        }
        
        .avatar-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 35px;
            height: 35px;
            background: #007bff;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .avatar-upload:hover {
            background: #0056b3;
            transform: scale(1.1);
        }
        
        .info-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #495057;
        }
        
        .info-value {
            color: #6c757d;
        }
        
        .breadcrumb-custom {
            background: transparent;
            padding: 1rem 0;
        }
        
        .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.7);
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: white;
        }
        
        .form-floating label {
            color: #6c757d;
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
        
        .activity-item {
            padding: 1rem;
            border-left: 3px solid #007bff;
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="breadcrumb-custom">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Mi Perfil</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 mb-2">Mi Perfil</h1>
                    <p class="lead mb-0">
                        Gestiona tu información personal y configuraciones de cuenta
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Mostrar mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Columna Principal -->
            <div class="col-lg-8">
                <!-- Información Personal -->
                <div class="profile-section">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>Información Personal
                        </h5>
                    </div>
                    <div class="section-body">
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                               placeholder="Nombre" required>
                                        <label for="first_name">Nombre</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                               placeholder="Apellido" required>
                                        <label for="last_name">Apellido</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               placeholder="Email" disabled>
                                        <label for="email">Email (no se puede cambiar)</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               placeholder="Teléfono">
                                        <label for="phone">Teléfono</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <select class="form-select" id="country" name="country">
                                            <option value="">Seleccionar país</option>
                                            <option value="PE" <?php echo ($user['country'] ?? '') === 'PE' ? 'selected' : ''; ?>>Perú</option>
                                            <option value="AR" <?php echo ($user['country'] ?? '') === 'AR' ? 'selected' : ''; ?>>Argentina</option>
                                            <option value="CL" <?php echo ($user['country'] ?? '') === 'CL' ? 'selected' : ''; ?>>Chile</option>
                                            <option value="CO" <?php echo ($user['country'] ?? '') === 'CO' ? 'selected' : ''; ?>>Colombia</option>
                                            <option value="EC" <?php echo ($user['country'] ?? '') === 'EC' ? 'selected' : ''; ?>>Ecuador</option>
                                            <option value="MX" <?php echo ($user['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>México</option>
                                            <option value="ES" <?php echo ($user['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>España</option>
                                            <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>Estados Unidos</option>
                                            <option value="OTHER" <?php echo ($user['country'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                        <label for="country">País</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Actualizar Perfil
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cambiar Contraseña -->
                <div class="profile-section">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock me-2"></i>Cambiar Contraseña
                        </h5>
                    </div>
                    <div class="section-body">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="current_password" name="current_password" 
                                               placeholder="Contraseña actual" required>
                                        <label for="current_password">Contraseña Actual</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Nueva contraseña" required>
                                        <label for="new_password">Nueva Contraseña</label>
                                    </div>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <small id="passwordHelp" class="form-text text-muted">
                                        Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                                    </small>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirmar contraseña" required>
                                        <label for="confirm_password">Confirmar Contraseña</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Avatar -->
                <div class="profile-section">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-camera me-2"></i>Foto de Perfil
                        </h5>
                    </div>
                    <div class="section-body">
                        <div class="avatar-section">
                            <div class="avatar-container">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div class="avatar-upload" onclick="document.getElementById('avatar-upload').click()">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            <h6><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                            
                            <form method="POST" enctype="multipart/form-data" style="display: none;">
                                <input type="hidden" name="upload_avatar" value="1">
                                <input type="file" id="avatar-upload" name="avatar" accept="image/*" onchange="this.form.submit()">
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Información de Cuenta -->
                <div class="profile-section">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Información de Cuenta
                        </h5>
                    </div>
                    <div class="section-body">
                        <div class="info-card">
                            <div class="info-item">
                                <span class="info-label">Estado de Cuenta:</span>
                                <span class="info-value">
                                    <?php if ($user['is_verified']): ?>
                                        <span class="badge bg-success">Verificada</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Sin Verificar</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Miembro desde:</span>
                                <span class="info-value"><?php echo formatDate($user['created_at'], 'F Y'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Último acceso:</span>
                                <span class="info-value">
                                    <?php if ($user['last_login']): ?>
                                        <?php echo timeAgo($user['last_login']); ?>
                                    <?php else: ?>
                                        Primer acceso
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actividad Reciente -->
                <div class="profile-section">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Actividad Reciente
                        </h5>
                    </div>
                    <div class="section-body">
                        <div class="activity-item">
                            <div><strong>Perfil actualizado</strong></div>
                            <div class="activity-time">Hace 2 horas</div>
                        </div>
                        <div class="activity-item">
                            <div><strong>Inicio de sesión</strong></div>
                            <div class="activity-time">Hoy</div>
                        </div>
                        <div class="activity-item">
                            <div><strong>Cuenta creada</strong></div>
                            <div class="activity-time"><?php echo formatDate($user['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordHelp = document.getElementById('passwordHelp');
            
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
                passwordHelp.className = `form-text text-${strength.color}`;
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
            
            // Auto-dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
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