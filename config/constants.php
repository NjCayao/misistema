<?php
// config/constants.php

// URLs del sistema
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/misistema');
}
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', SITE_URL . '/admin');
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', SITE_URL . '/assets');
}
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', SITE_URL . '/assets/uploads');
}
if (!defined('VENDOR_URL')) {
    define('VENDOR_URL', SITE_URL . '/vendor');
}
if (!defined('ADMINLTE_URL')) {
    define('ADMINLTE_URL', VENDOR_URL . '/adminlte');
}

// Configuraciones de base de datos (solo si no están definidas)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'misistema');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Configuraciones de sesión
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'misistema_session');
}
if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'admin_misistema_session');
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 hora
}

// Configuraciones de seguridad
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 6);
}
if (!defined('VERIFICATION_CODE_EXPIRY')) {
    define('VERIFICATION_CODE_EXPIRY', 30); // minutos
}
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}
if (!defined('LOGIN_LOCKOUT_TIME')) {
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
}

// Configuraciones de productos
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
}
if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', '7z']);
}
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
}
if (!defined('DEFAULT_DOWNLOAD_LIMIT')) {
    define('DEFAULT_DOWNLOAD_LIMIT', 5);
}
if (!defined('DEFAULT_UPDATE_MONTHS')) {
    define('DEFAULT_UPDATE_MONTHS', 12);
}

// Configuraciones de email
if (!defined('FROM_EMAIL')) {
    define('FROM_EMAIL', 'noreply@misistema.com');
}
if (!defined('FROM_NAME')) {
    define('FROM_NAME', 'MiSistema');
}

// Estados de orden
if (!defined('ORDER_PENDING')) {
    define('ORDER_PENDING', 'pending');
}
if (!defined('ORDER_COMPLETED')) {
    define('ORDER_COMPLETED', 'completed');
}
if (!defined('ORDER_FAILED')) {
    define('ORDER_FAILED', 'failed');
}
if (!defined('ORDER_REFUNDED')) {
    define('ORDER_REFUNDED', 'refunded');
}

// Métodos de pago
if (!defined('PAYMENT_STRIPE')) {
    define('PAYMENT_STRIPE', 'stripe');
}
if (!defined('PAYMENT_PAYPAL')) {
    define('PAYMENT_PAYPAL', 'paypal');
}
if (!defined('PAYMENT_MERCADOPAGO')) {
    define('PAYMENT_MERCADOPAGO', 'mercadopago');
}

// Timezone
date_default_timezone_set('America/Lima');

// Configuración de errores (solo en desarrollo)
if (strpos(SITE_URL, 'localhost') !== false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}