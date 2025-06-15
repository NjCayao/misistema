<?php
// config/functions.php
session_start();

// Incluir el sistema de email
require_once __DIR__ . '/email.php';

// Función para obtener configuraciones del sitio
function getSetting($key, $default = '') {
    static $settings = null;
    
    if ($settings === null) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Función para actualizar configuración
function updateSetting($key, $value) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    return $stmt->execute([$value, $key]);
}

// Función para sanitizar input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Función para generar slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Función para generar código de verificación
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Función para generar número de orden
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Función para formatear precio - CORREGIDA
function formatPrice($price) {
    // Usar getSetting en lugar de Settings::get
    $currencySymbol = getSetting('currency_symbol', '$');
    return $currencySymbol . number_format($price, 2);
}

// Función para verificar si es admin
function isAdmin() {
    return isset($_SESSION[ADMIN_SESSION_NAME]) && !empty($_SESSION[ADMIN_SESSION_NAME]);
}

// Función para verificar si usuario está logueado
function isLoggedIn() {
    return isset($_SESSION[SESSION_NAME]) && !empty($_SESSION[SESSION_NAME]);
}

// Función para obtener datos del usuario logueado
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = $_SESSION[SESSION_NAME]['user_id'];
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Función para login de usuario
function loginUser($email, $password) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT id, email, password, first_name, last_name, is_verified, is_active 
            FROM users 
            WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Usuario no encontrado'];
        }
        
        if (!$user['is_verified']) {
            return ['success' => false, 'message' => 'Cuenta no verificada. Revisa tu email'];
        }
        
        if (!verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Contraseña incorrecta'];
        }
        
        // Actualizar último login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Crear sesión
        $_SESSION[SESSION_NAME] = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'login_time' => time()
        ];
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        logError("Error en login: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error del sistema'];
    }
}

// Función para logout de usuario
function logoutUser() {
    if (isset($_SESSION[SESSION_NAME])) {
        unset($_SESSION[SESSION_NAME]);
    }
    session_destroy();
}

// Función para redireccionar
function redirect($url) {
    // Si la URL ya es completa (http/https), usarla tal como está
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        header("Location: $url");
        exit();
    }
    
    // Si empieza con /, agregar SITE_URL
    if (strpos($url, '/') === 0) {
        header("Location: " . SITE_URL . $url);
        exit();
    }
    
    // Si no empieza con /, agregar SITE_URL/
    header("Location: " . SITE_URL . '/' . $url);
    exit();
}

// Función para mostrar mensajes flash
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Función para subir archivos
function uploadFile($file, $destination, $allowedTypes = null) {
    // Tipos permitidos por defecto
    if ($allowedTypes === null) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }
    
    $maxSize = MAX_FILE_SIZE;
    
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo es muy grande (límite del servidor)',
            UPLOAD_ERR_FORM_SIZE => 'El archivo es muy grande (límite del formulario)',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir archivo',
            UPLOAD_ERR_EXTENSION => 'Extensión bloqueada'
        ];
        
        $errorMsg = $errorMessages[$file['error']] ?? 'Error desconocido al subir archivo';
        return ['success' => false, 'message' => $errorMsg];
    }
    
    // Verificar tamaño
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / (1024 * 1024), 1);
        return ['success' => false, 'message' => "Archivo muy grande. Máximo permitido: {$maxSizeMB}MB"];
    }
    
    // Verificar que el archivo existe y no está vacío
    if ($file['size'] == 0) {
        return ['success' => false, 'message' => 'El archivo está vacío'];
    }
    
    // Obtener extensión del archivo
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Verificar extensión
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowedTypes)];
    }
    
    // Verificar MIME type para mayor seguridad
    $allowedMimes = [
        'jpg' => ['image/jpeg', 'image/jpg'],
        'jpeg' => ['image/jpeg', 'image/jpg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/x-rar-compressed', 'application/rar'],
        '7z' => ['application/x-7z-compressed']
    ];
    
    $fileMime = $file['type'];
    $validMimes = $allowedMimes[$extension] ?? [];
    
    if (!empty($validMimes) && !in_array($fileMime, $validMimes)) {
        return ['success' => false, 'message' => 'Tipo MIME no válido para extensión .' . $extension];
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    // Crear directorio si no existe
    if (!is_dir($destination)) {
        if (!mkdir($destination, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de destino'];
        }
    }
    
    // Verificar permisos de escritura
    if (!is_writable($destination)) {
        return ['success' => false, 'message' => 'No hay permisos de escritura en el directorio'];
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Verificar que el archivo se movió correctamente
        if (file_exists($filepath)) {
            return [
                'success' => true, 
                'filename' => $filename, 
                'path' => $filepath,
                'size' => filesize($filepath),
                'original_name' => $originalName
            ];
        } else {
            return ['success' => false, 'message' => 'El archivo no se guardó correctamente'];
        }
    } else {
        return ['success' => false, 'message' => 'Error al mover el archivo al destino final'];
    }
}

// Función para log de errores
function logError($message, $file = 'error.log') {
    $logPath = __DIR__ . '/../logs/' . $file;
    
    // Crear directorio de logs si no existe
    if (!is_dir(dirname($logPath))) {
        mkdir(dirname($logPath), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
}

// Función para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para hash de password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Función para verificar password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para generar token de recuperación
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Función para limpiar URLs
function cleanUrl($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

// Función para truncar texto
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

// Función para formatear fecha
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Función para formatear fecha con hora
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    return date($format, strtotime($datetime));
}

// Función para tiempo relativo (hace X minutos, etc.)
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'hace unos segundos';
    if ($time < 3600) return 'hace ' . floor($time/60) . ' minutos';
    if ($time < 86400) return 'hace ' . floor($time/3600) . ' horas';
    if ($time < 2592000) return 'hace ' . floor($time/86400) . ' días';
    if ($time < 31536000) return 'hace ' . floor($time/2592000) . ' meses';
    return 'hace ' . floor($time/31536000) . ' años';
}

?>