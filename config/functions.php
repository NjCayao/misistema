<?php
// config/functions.php
session_start();

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
    $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
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

// Función para formatear precio
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Función para verificar si es admin
function isAdmin() {
    return isset($_SESSION[ADMIN_SESSION_NAME]) && !empty($_SESSION[ADMIN_SESSION_NAME]);
}

// Función para verificar si usuario está logueado
function isLoggedIn() {
    return isset($_SESSION[SESSION_NAME]) && !empty($_SESSION[SESSION_NAME]);
}

// Función para redireccionar
function redirect($url) {
    header("Location: $url");
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
function uploadFile($file, $destination) {
    $allowedTypes = ALLOWED_FILE_TYPES;
    $maxSize = MAX_FILE_SIZE;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir archivo'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Archivo muy grande'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Error al mover archivo'];
}

// Función para enviar email
function sendEmail($to, $subject, $body, $isHTML = true) {
    // Aquí implementaremos PHPMailer más adelante
    // Por ahora simulamos el envío
    return true;
}

// Función para log de errores
function logError($message, $file = 'error.log') {
    $logPath = __DIR__ . '/../logs/' . $file;
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
?>