<?php
// config/constants.php
define('SITE_URL', 'http://localhost/misistema');
define('ADMIN_URL', SITE_URL . '/admin');
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/assets/uploads');  
define('VENDOR_URL', SITE_URL . '/vendor');
define('ADMINLTE_URL', VENDOR_URL . '/adminlte');

// Rutas de archivos (manteniendo tu estructura original)
define('UPLOADS_PATH', __DIR__ . '/../assets/uploads');     
define('DOWNLOADS_PATH', __DIR__ . '/../downloads');        

// Configuraciones de seguridad
define('SESSION_NAME', 'misistema_session');
define('ADMIN_SESSION_NAME', 'admin_misistema_session');
define('PASSWORD_MIN_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY', 30); // minutos

// Configuraciones de productos
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', '7z']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('DEFAULT_DOWNLOAD_LIMIT', 5);
define('DEFAULT_UPDATE_MONTHS', 12);

// Configuraciones de email
define('FROM_EMAIL', 'noreply@misistema.com');
define('FROM_NAME', 'MiSistema');

// Estados de orden
define('ORDER_PENDING', 'pending');
define('ORDER_COMPLETED', 'completed');
define('ORDER_FAILED', 'failed');
define('ORDER_REFUNDED', 'refunded');

// Métodos de pago
define('PAYMENT_STRIPE', 'stripe');
define('PAYMENT_PAYPAL', 'paypal');
define('PAYMENT_MERCADOPAGO', 'mercadopago');
?>