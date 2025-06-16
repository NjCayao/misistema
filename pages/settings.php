<?php
// pages/settings.php - Página de Configuración
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

// Obtener configuraciones del usuario (crear tabla si no existe)
try {
    $db = Database::getInstance()->getConnection();
    
    // Crear tabla de configuraciones de usuario si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_setting (user_id, setting_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Función para obtener configuración del usuario
    function getUserSetting($userId, $key, $default = '') {
        global $db;
        $stmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?");
        $stmt->execute([$userId, $key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    }
    
    // Función para guardar configuración del usuario
    function setUserSetting($userId, $key, $value) {
        global $db;
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        return $stmt->execute([$userId, $key, $value]);
    }
    
} catch (Exception $e) {
    logError("Error configurando tabla user_settings: " . $e->getMessage());
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_settings'])) {
            // Guardar configuraciones
            $settings = [
                'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
                'newsletter_subscribe' => isset($_POST['newsletter_subscribe']) ? '1' : '0',
                'order_notifications' => isset($_POST['order_notifications']) ? '1' : '0',
                'promo_notifications' => isset($_POST['promo_notifications']) ? '1' : '0',
                'language' => sanitize($_POST['language'] ?? 'es'),
                'timezone' => sanitize($_POST['timezone'] ?? 'America/Lima'),
                'currency' => sanitize($_POST['currency'] ?? 'USD'),
                'items_per_page' => intval($_POST['items_per_page'] ?? 12),
                'default_view' => sanitize($_POST['default_view'] ?? 'grid'),
                'auto_download' => isset($_POST['auto_download']) ? '1' : '0',
                'download_notifications' => isset($_POST['download_notifications']) ? '1' : '0',
                'theme' => sanitize($_POST['theme'] ?? 'light')
            ];
            
            $saved = 0;
            foreach ($settings as $key => $value) {
                if (setUserSetting($user['id'], $key, $value)) {
                    $saved++;
                }
            }
            
            if ($saved > 0) {
                setFlashMessage('success', 'Configuraciones guardadas exitosamente');
                redirect('/configuracion');
            } else {
                $errors[] = 'No se pudieron guardar las configuraciones';
            }
            
        } elseif (isset($_POST['delete_account'])) {
            // Confirmar eliminación de cuenta
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $confirmText = sanitize($_POST['confirm_text'] ?? '');
            
            if (empty($confirmPassword)) {
                $errors[] = 'Ingresa tu contraseña para confirmar';
            } elseif (!verifyPassword($confirmPassword, $user['password'])) {
                $errors[] = 'Contraseña incorrecta';
            } elseif (strtolower($confirmText) !== 'eliminar mi cuenta') {
                $errors[] = 'Debes escribir exactamente "eliminar mi cuenta"';
            } else {
                // Proceder con eliminación (marcar como inactivo en lugar de eliminar)
                $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$user['id']])) {
                    // Log de eliminación
                    logError("Usuario eliminó su cuenta: {$user['email']} (ID: {$user['id']})");
                    
                    // Cerrar sesión
                    logoutUser();
                    
                    setFlashMessage('success', 'Tu cuenta ha sido desactivada exitosamente');
                    redirect('/');
                } else {
                    $errors[] = 'Error al eliminar la cuenta';
                }
            }
        }
        
    } catch (Exception $e) {
        logError("Error en configuraciones: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde';
    }
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Configura tus preferencias y opciones de cuenta">
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
        
        .settings-section {
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
        
        .setting-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .setting-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .custom-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .custom-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #007bff;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
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
        
        .danger-zone {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 10px;
            padding: 2rem;
        }
        
        .danger-zone .section-header {
            background: #fed7d7;
            margin: -2rem -2rem 1.5rem -2rem;
            color: #742a2a;
        }
        
        .modal-danger .modal-header {
            background: #dc3545;
            color: white;
        }
        
        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs-custom .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs-custom .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            background: none;
        }
        
        .tab-content {
            padding-top: 2rem;
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
                    <li class="breadcrumb-item active">Configuración</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 mb-2">Configuración</h1>
                    <p class="lead mb-0">
                        Personaliza tu experiencia y gestiona tus preferencias
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
        
        <!-- Tabs de Configuración -->
        <ul class="nav nav-tabs nav-tabs-custom" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                    <i class="fas fa-bell me-2"></i>Notificaciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>Preferencias
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                    <i class="fas fa-shield-alt me-2"></i>Privacidad
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                    <i class="fas fa-user-cog me-2"></i>Cuenta
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="settingsTabContent">
            <!-- Tab Notificaciones -->
            <div class="tab-pane fade show active" id="notifications" role="tabpanel">
                <form method="POST" id="settingsForm">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <div class="settings-section">
                        <div class="section-header">
                            <h5 class="mb-0">Notificaciones por Email</h5>
                        </div>
                        <div class="section-body">
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Notificaciones Generales</div>
                                        <div class="setting-description">Recibir emails sobre actualizaciones de cuenta y sistema</div>
                                    </div>
                                    <label class="custom-switch">
                                        <input type="checkbox" name="email_notifications" <?php echo getUserSetting($user['id'], 'email_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Notificaciones de Órdenes</div>
                                        <div class="setting-description">Recibir confirmaciones de compras y cambios de estado</div>
                                    </div>
                                    <label class="custom-switch">
                                        <input type="checkbox" name="order_notifications" <?php echo getUserSetting($user['id'], 'order_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Newsletter</div>
                                        <div class="setting-description">Recibir noticias, ofertas y productos nuevos</div>
                                    </div>
                                    <label class="custom-switch">
                                        <input type="checkbox" name="newsletter_subscribe" <?php echo getUserSetting($user['id'], 'newsletter_subscribe', '0') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="setting-label">Promociones</div>
                                        <div class="setting-description">Recibir ofertas especiales y descuentos</div>
                                    </div>
                                    <label class="custom-switch">
                                        <input type="checkbox" name="promo_notifications" <?php echo getUserSetting($user['id'], 'promo_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tab Preferencias -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <div class="settings-section">
                    <div class="section-header">
                        <h5 class="mb-0">Preferencias de Visualización</h5>
                    </div>
                    <div class="section-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Idioma</label>
                                <select class="form-select" name="language" form="settingsForm">
                                    <option value="es" <?php echo getUserSetting($user['id'], 'language', 'es') === 'es' ? 'selected' : ''; ?>>Español</option>
                                    <option value="en" <?php echo getUserSetting($user['id'], 'language', 'es') === 'en' ? 'selected' : ''; ?>>English</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Zona Horaria</label>
                                <select class="form-select" name="timezone" form="settingsForm">
                                    <option value="America/Lima" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Lima' ? 'selected' : ''; ?>>Lima (UTC-5)</option>
                                    <option value="America/Mexico_City" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                    <option value="America/Buenos_Aires" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Buenos_Aires' ? 'selected' : ''; ?>>Buenos Aires (UTC-3)</option>
                                    <option value="Europe/Madrid" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'Europe/Madrid' ? 'selected' : ''; ?>>Madrid (UTC+1)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Moneda</label>
                                <select class="form-select" name="currency" form="settingsForm">
                                    <option value="USD" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="EUR" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="PEN" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'PEN' ? 'selected' : ''; ?>>PEN (S/)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Productos por página</label>
                                <select class="form-select" name="items_per_page" form="settingsForm">
                                    <option value="8" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '8' ? 'selected' : ''; ?>>8</option>
                                    <option value="12" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '12' ? 'selected' : ''; ?>>12</option>
                                    <option value="24" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '24' ? 'selected' : ''; ?>>24</option>
                                    <option value="48" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '48' ? 'selected' : ''; ?>>48</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Vista predeterminada</label>
                                <select class="form-select" name="default_view" form="settingsForm">
                                    <option value="grid" <?php echo getUserSetting($user['id'], 'default_view', 'grid') === 'grid' ? 'selected' : ''; ?>>Cuadrícula</option>
                                    <option value="list" <?php echo getUserSetting($user['id'], 'default_view', 'grid') === 'list' ? 'selected' : ''; ?>>Lista</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Tema</label>
                                <select class="form-select" name="theme" form="settingsForm">
                                    <option value="light" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'light' ? 'selected' : ''; ?>>Claro</option>
                                    <option value="dark" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'dark' ? 'selected' : ''; ?>>Oscuro</option>
                                    <option value="auto" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'auto' ? 'selected' : ''; ?>>Automático</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <h5 class="mb-0">Preferencias de Descarga</h5>
                    </div>
                    <div class="section-body">
                        <div class="setting-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="setting-label">Descarga Automática</div>
                                    <div class="setting-description">Descargar automáticamente después de la compra</div>
                                </div>
                                <label class="custom-switch">
                                    <input type="checkbox" name="auto_download" form="settingsForm" <?php echo getUserSetting($user['id'], 'auto_download', '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="setting-label">Notificaciones de Descarga</div>
                                    <div class="setting-description">Notificar cuando estén disponibles nuevas versiones</div>
                                </div>
                                <label class="custom-switch">
                                    <input type="checkbox" name="download_notifications" form="settingsForm" <?php echo getUserSetting($user['id'], 'download_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Privacidad -->
            <div class="tab-pane fade" id="privacy" role="tabpanel">
                <div class="settings-section">
                    <div class="section-header">
                        <h5 class="mb-0">Configuraciones de Privacidad</h5>
                    </div>
                    <div class="section-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Información:</strong> Respetamos tu privacidad. Tus datos nunca serán compartidos con terceros sin tu consentimiento.
                        </div>
                        
                        <h6>Datos que recopilamos:</h6>
                        <ul>
                            <li>Información de perfil (nombre, email, país)</li>
                            <li>Historial de compras y descargas</li>
                            <li>Preferencias de configuración</li>
                            <li>Logs de actividad (para seguridad)</li>
                        </ul>
                        
                        <h6 class="mt-4">Tus derechos:</h6>
                        <ul>
                            <li>Acceder a tu información personal</li>
                            <li>Corregir datos incorrectos</li>
                            <li>Solicitar eliminación de cuenta</li>
                            <li>Exportar tus datos</li>
                        </ul>
                        
                        <div class="mt-4">
                            <a href="<?php echo SITE_URL; ?>/poltica-de-privacidad" class="btn btn-outline-primary me-2">
                                <i class="fas fa-file-alt me-2"></i>Política de Privacidad
                            </a>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportUserData()">
                                <i class="fas fa-download me-2"></i>Exportar Mis Datos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Cuenta -->
            <div class="tab-pane fade" id="account" role="tabpanel">
                <div class="settings-section">
                    <div class="section-header">
                        <h5 class="mb-0">Gestión de Cuenta</h5>
                    </div>
                    <div class="section-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Acciones de Cuenta</h6>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo SITE_URL; ?>/perfil" class="btn btn-outline-primary">
                                        <i class="fas fa-user-edit me-2"></i>Editar Perfil
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" onclick="resetSettings()">
                                        <i class="fas fa-undo me-2"></i>Restablecer Configuraciones
                                    </button>
                                    <a href="<?php echo SITE_URL; ?>/logout" class="btn btn-outline-secondary">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Información de Cuenta</h6>
                                <ul class="list-unstyled">
                                    <li><strong>ID de Usuario:</strong> #<?php echo $user['id']; ?></li>
                                    <li><strong>Registro:</strong> <?php echo formatDate($user['created_at']); ?></li>
                                    <li><strong>Último acceso:</strong> <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Primer acceso'; ?></li>
                                    <li><strong>Estado:</strong> 
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge bg-success">Verificada</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sin verificar</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Zona de Peligro -->
                <div class="danger-zone">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Zona de Peligro
                        </h5>
                    </div>
                    <div class="text-center">
                        <h6 class="text-danger">Eliminar Cuenta</h6>
                        <p class="text-muted mb-3">
                            Esta acción desactivará permanentemente tu cuenta. No podrás acceder a tus compras ni descargas.
                        </p>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash me-2"></i>Eliminar Mi Cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón Guardar -->
        <div class="text-center mt-4">
            <button type="submit" form="settingsForm" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-save me-2"></i>Guardar Configuraciones
            </button>
        </div>
    </div>
    
    <!-- Modal Eliminar Cuenta -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-danger">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación de Cuenta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="delete_account" value="1">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>¡Atención!</strong> Esta acción no se puede deshacer. Tu cuenta será desactivada permanentemente.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirma tu contraseña:</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Escribe "eliminar mi cuenta" para confirmar:</label>
                            <input type="text" class="form-control" name="confirm_text" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function exportUserData() {
            alert('Función de exportación próximamente disponible');
            // TODO: Implementar exportación de datos
        }
        
        function resetSettings() {
            if (confirm('¿Estás seguro de que quieres restablecer todas las configuraciones a sus valores predeterminados?')) {
                // TODO: Implementar reset de configuraciones
                alert('Configuraciones restablecidas');
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Guardar configuraciones automáticamente al cambiar
        document.querySelectorAll('input[type="checkbox"], select').forEach(element => {
            element.addEventListener('change', function() {
                // Opcional: guardar automáticamente
                // document.getElementById('settingsForm').submit();
            });
        });
    </script>
</body>
</html>