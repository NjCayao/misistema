<?php
// api/downloads/secure_download.php - Sistema de descargas seguras
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

try {
    $productId = intval($_GET['id'] ?? 0);
    $orderNumber = $_GET['order'] ?? '';
    $token = $_GET['token'] ?? '';
    $version = $_GET['version'] ?? '';
    
    // Validar parámetros básicos
    if ($productId <= 0 || empty($orderNumber)) {
        http_response_code(400);
        die('Parámetros inválidos');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el producto existe
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        http_response_code(404);
        die('Producto no encontrado');
    }
    
    // Verificar que la orden existe y está completada
    $stmt = $db->prepare("
        SELECT o.*, oi.quantity
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.order_number = ? AND oi.product_id = ? 
        AND o.payment_status = 'completed'
    ");
    $stmt->execute([$orderNumber, $productId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        http_response_code(403);
        die('Acceso denegado: Orden no válida o pago no completado');
    }
    
    // Si hay usuario logueado, verificar licencia
    $userLicense = null;
    if ($order['user_id'] && isLoggedIn()) {
        $currentUser = getCurrentUser();
        if ($currentUser && $currentUser['id'] == $order['user_id']) {
            $stmt = $db->prepare("
                SELECT * FROM user_licenses 
                WHERE user_id = ? AND product_id = ? AND is_active = 1
            ");
            $stmt->execute([$order['user_id'], $productId]);
            $userLicense = $stmt->fetch();
            
            // Verificar límite de descargas
            if ($userLicense && $userLicense['downloads_used'] >= $userLicense['downloads_limit']) {
                http_response_code(403);
                die('Límite de descargas excedido');
            }
        }
    }
    
    // Obtener versión a descargar
    $productVersion = null;
    if ($version) {
        // Versión específica solicitada
        $stmt = $db->prepare("
            SELECT * FROM product_versions 
            WHERE product_id = ? AND version = ?
        ");
        $stmt->execute([$productId, $version]);
        $productVersion = $stmt->fetch();
    } else {
        // Versión actual
        $stmt = $db->prepare("
            SELECT * FROM product_versions 
            WHERE product_id = ? AND is_current = 1
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $productVersion = $stmt->fetch();
    }
    
    if (!$productVersion) {
        http_response_code(404);
        die('Versión del producto no encontrada');
    }
    
    // Construir ruta del archivo
    $filePath = DOWNLOADS_PATH . '/' . $productVersion['file_path'];
    
    if (!file_exists($filePath)) {
        logError("Archivo no encontrado: $filePath");
        http_response_code(404);
        die('Archivo no disponible');
    }
    
    // Registrar descarga
    $stmt = $db->prepare("
        INSERT INTO download_logs (user_id, product_id, version_id, license_id, ip_address, user_agent, download_type)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $downloadType = $order['total_amount'] > 0 ? 'purchase' : 'free';
    if ($userLicense && $userLicense['downloads_used'] > 0) {
        $downloadType = 'redownload';
    }
    
    $stmt->execute([
        $order['user_id'],
        $productId,
        $productVersion['id'],
        $userLicense['id'] ?? null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $downloadType
    ]);
    
    // Actualizar contador de licencia
    if ($userLicense) {
        $stmt = $db->prepare("
            UPDATE user_licenses 
            SET downloads_used = downloads_used + 1, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userLicense['id']]);
    }
    
    // Actualizar contador del producto
    $stmt = $db->prepare("
        UPDATE products 
        SET download_count = download_count + 1 
        WHERE id = ?
    ");
    $stmt->execute([$productId]);
    
    // Actualizar contador de la versión
    $stmt = $db->prepare("
        UPDATE product_versions 
        SET download_count = download_count + 1 
        WHERE id = ?
    ");
    $stmt->execute([$productVersion['id']]);
    
    // Log de descarga exitosa
    logError("Descarga iniciada - Producto: {$product['name']} v{$productVersion['version']} - Usuario: {$order['customer_email']} - IP: {$_SERVER['REMOTE_ADDR']}", 'downloads.log');
    
    // Preparar descarga
    $fileName = sanitizeFileName($product['name'] . '_v' . $productVersion['version'] . '.' . pathinfo($filePath, PATHINFO_EXTENSION));
    $fileSize = filesize($filePath);
    $mimeType = getMimeType($filePath);
    
    // Headers para descarga segura
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    
    // Prevenir timeout para archivos grandes
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }
    
    // Enviar archivo en chunks para archivos grandes
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
            
            // Verificar si la conexión sigue activa
            if (connection_aborted()) {
                break;
            }
        }
        fclose($handle);
    } else {
        http_response_code(500);
        die('Error al acceder al archivo');
    }
    
} catch (Exception $e) {
    logError("Error en descarga segura: " . $e->getMessage());
    http_response_code(500);
    die('Error interno del servidor');
}

/**
 * Sanitizar nombre de archivo
 */
function sanitizeFileName($filename) {
    // Remover caracteres no seguros
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    // Limitar longitud
    if (strlen($filename) > 100) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 90);
        $filename = $name . '.' . $extension;
    }
    return $filename;
}

/**
 * Obtener MIME type del archivo
 */
function getMimeType($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'exe' => 'application/octet-stream'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}