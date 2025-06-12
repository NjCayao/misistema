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