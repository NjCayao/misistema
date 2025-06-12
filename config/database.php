<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'misistema');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// ====================================

// config/constants.php
define('SITE_URL', 'http://localhost/misistema');
define('ADMIN_URL', SITE_URL . '/admin');
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/assets/uploads');
define('DOWNLOADS_PATH', __DIR__ . '/../downloads');
define('UPLOADS_PATH', __DIR__ . '/../assets/uploads');

// Configuraciones de seguridad
define('SESSION_NAME', 'sistema_session');
define('ADMIN_SESSION_NAME', 'admin_sistema_session');
define('PASSWORD_MIN_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY', 30); // minutos

// Configuraciones de productos
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_FILE_TYPES', ['zip', 'rar', '7z']);
define('DEFAULT_DOWNLOAD_LIMIT', 5);
define('DEFAULT_UPDATE_MONTHS', 12);

// Configuraciones de email
define('FROM_EMAIL', 'noreply@misistema.com');
define('FROM_NAME', 'Mi sistema');

// Estados de orden
define('ORDER_PENDING', 'pending');
define('ORDER_COMPLETED', 'completed');
define('ORDER_FAILED', 'failed');
define('ORDER_REFUNDED', 'refunded');

// Métodos de pago
define('PAYMENT_STRIPE', 'stripe');
define('PAYMENT_PAYPAL', 'paypal');
define('PAYMENT_MERCADOPAGO', 'mercadopago');

// ====================================

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

// ====================================

// config/settings.php
class Settings {
    private static $settings = null;
    
    public static function get($key, $default = '') {
        if (self::$settings === null) {
            self::loadSettings();
        }
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
    
    public static function set($key, $value) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        if ($stmt->execute([$key, $value])) {
            self::$settings[$key] = $value;
            return true;
        }
        return false;
    }
    
    private static function loadSettings() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        self::$settings = [];
        
        while ($row = $stmt->fetch()) {
            self::$settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    public static function getPaymentConfig($gateway) {
        switch($gateway) {
            case 'stripe':
                return [
                    'publishable_key' => self::get('stripe_publishable_key'),
                    'secret_key' => self::get('stripe_secret_key')
                ];
            case 'paypal':
                return [
                    'client_id' => self::get('paypal_client_id'),
                    'client_secret' => self::get('paypal_client_secret'),
                    'sandbox' => self::get('paypal_sandbox', 'true') === 'true'
                ];
            case 'mercadopago':
                return [
                    'public_key' => self::get('mercadopago_public_key'),
                    'access_token' => self::get('mercadopago_access_token')
                ];
            default:
                return [];
        }
    }
}
?>