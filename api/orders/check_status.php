<?php
// api/orders/check_status.php - API para verificar estado de orden
header('Content-Type: application/json');

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

try {
    $orderNumber = $_GET['order'] ?? '';
    
    if (empty($orderNumber)) {
        echo json_encode(['success' => false, 'message' => 'Número de orden requerido']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener orden
    $stmt = $db->prepare("
        SELECT id, order_number, payment_status, payment_method, total_amount, 
               created_at, updated_at, payment_data
        FROM orders 
        WHERE order_number = ?
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
        exit;
    }
    
    // Determinar razón de falla si aplica
    $failureReason = null;
    if ($order['payment_status'] === 'failed') {
        $paymentData = json_decode($order['payment_data'], true) ?: [];
        $failureReason = $paymentData['failure_reason'] ?? 'unknown';
    }
    
    // Respuesta básica
    $response = [
        'success' => true,
        'order_number' => $order['order_number'],
        'status' => $order['payment_status'],
        'payment_method' => $order['payment_method'],
        'total_amount' => floatval($order['total_amount']),
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at']
    ];
    
    // Agregar razón de falla si existe
    if ($failureReason) {
        $response['reason'] = $failureReason;
    }
    
    // Si está completada, agregar información adicional
    if ($order['payment_status'] === 'completed') {
        // Obtener items de la orden
        $stmt = $db->prepare("
            SELECT oi.product_id, oi.product_name, oi.price, oi.quantity,
                   p.slug, p.image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll();
        
        $response['items'] = array_map(function($item) {
            return [
                'product_id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => floatval($item['price']),
                'quantity' => intval($item['quantity']),
                'slug' => $item['slug'],
                'image' => $item['image'],
                'download_url' => SITE_URL . '/download/' . $item['product_id'] . '?order=' . $order['order_number']
            ];
        }, $items);
        
        // Si hay usuario, obtener licencias
        if ($order['user_id']) {
            $stmt = $db->prepare("
                SELECT ul.*, p.name as product_name
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                WHERE ul.order_id = ?
            ");
            $stmt->execute([$order['id']]);
            $licenses = $stmt->fetchAll();
            
            $response['licenses'] = array_map(function($license) {
                return [
                    'product_id' => $license['product_id'],
                    'product_name' => $license['product_name'],
                    'downloads_used' => intval($license['downloads_used']),
                    'downloads_limit' => intval($license['downloads_limit']),
                    'updates_until' => $license['updates_until'],
                    'is_active' => $license['is_active'] == 1
                ];
            }, $licenses);
        }
    }
    
    // Si está pendiente, agregar tiempo estimado
    if ($order['payment_status'] === 'pending') {
        $timeElapsed = time() - strtotime($order['created_at']);
        $response['time_elapsed_minutes'] = round($timeElapsed / 60);
        
        // Tiempo estimado según método de pago
        $estimatedTimes = [
            'stripe' => 5,
            'paypal' => 15,
            'mercadopago' => 60
        ];
        
        $response['estimated_time_minutes'] = $estimatedTimes[$order['payment_method']] ?? 30;
        $response['should_be_completed'] = $timeElapsed > ($response['estimated_time_minutes'] * 60);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logError("Error en check_status API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}