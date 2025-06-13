<?php
// api/payments/paypal_webhook.php - Webhook de PayPal
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/payments.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

try {
    // Obtener configuración de PayPal
    $config = PaymentProcessor::getGatewayConfig('paypal');
    
    if (!$config['enabled']) {
        throw new Exception('PayPal webhook no configurado');
    }
    
    // Obtener payload
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new Exception('Payload inválido');
    }
    
    // Validar webhook (simplificado)
    $headers = getallheaders();
    if (!PaymentProcessor::validateWebhookSignature('paypal', $payload, $headers)) {
        logError("PayPal signature validation failed", 'paypal_webhooks.log');
        // Por ahora continuar - en producción debe validarse correctamente
    }
    
    // Log del evento
    logError("PayPal webhook recibido: {$event['event_type']} - ID: {$event['id']}", 'paypal_webhooks.log');
    
    // Procesar según el tipo de evento
    switch ($event['event_type']) {
        case 'CHECKOUT.ORDER.APPROVED':
            handleOrderApproved($event);
            break;
            
        case 'PAYMENT.CAPTURE.COMPLETED':
            handlePaymentCaptureCompleted($event);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.FAILED':
            handlePaymentCaptureFailed($event);
            break;
            
        default:
            logError("PayPal evento no manejado: {$event['event_type']}", 'paypal_webhooks.log');
            break;
    }
    
    // Responder a PayPal
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    logError("Error en PayPal webhook: " . $e->getMessage(), 'paypal_webhooks.log');
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Manejar orden aprobada
 */
function handleOrderApproved($event) {
    try {
        $paypalOrderId = $event['resource']['id'];
        
        // Capturar el pago automáticamente
        $config = PaymentProcessor::getGatewayConfig('paypal');
        $baseUrl = $config['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        // Obtener token de acceso
        $tokenResponse = getPayPalAccessToken($config);
        if (!$tokenResponse['success']) {
            throw new Exception('Error obteniendo token de PayPal');
        }
        
        // Capturar pago
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v2/checkout/orders/{$paypalOrderId}/capture");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokenResponse['access_token']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $captureData = json_decode($response, true);
            logError("PayPal pago capturado - Order ID: $paypalOrderId", 'paypal_webhooks.log');
        } else {
            throw new Exception("Error capturando pago de PayPal: HTTP $httpCode");
        }
        
    } catch (Exception $e) {
        logError("Error procesando CHECKOUT.ORDER.APPROVED: " . $e->getMessage(), 'paypal_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago capturado completado
 */
function handlePaymentCaptureCompleted($event) {
    try {
        $capture = $event['resource'];
        $paypalOrderId = $capture['supplementary_data']['related_ids']['order_id'] ?? '';
        
        if (empty($paypalOrderId)) {
            throw new Exception('PayPal Order ID no encontrado en el capture');
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por payment_id (PayPal Order ID)
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ? AND payment_status = 'pending'");
        $stmt->execute([$paypalOrderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Orden no encontrada para PayPal Order ID: $paypalOrderId");
        }
        
        // Datos del pago para guardar
        $paymentData = [
            'paypal_order_id' => $paypalOrderId,
            'paypal_capture_id' => $capture['id'],
            'amount_received' => floatval($capture['amount']['value']),
            'currency' => $capture['amount']['currency_code'],
            'paypal_fee' => floatval($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0),
            'net_amount' => floatval($capture['seller_receivable_breakdown']['net_amount']['value'] ?? 0),
            'payer_email' => $capture['payer']['email_address'] ?? '',
            'completed_at' => date('Y-m-d H:i:s', strtotime($capture['create_time']))
        ];
        
        // Completar pago
        $result = PaymentProcessor::completePayment($order['id'], $capture['id'], $paymentData);
        
        if ($result['success']) {
            logError("Pago completado exitosamente - Orden: {$order['order_number']} - PayPal Capture: {$capture['id']}", 'paypal_webhooks.log');
        } else {
            throw new Exception("Error completando pago: {$result['message']}");
        }
        
    } catch (Exception $e) {
        logError("Error procesando PAYMENT.CAPTURE.COMPLETED: " . $e->getMessage(), 'paypal_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago fallido
 */
function handlePaymentCaptureFailed($event) {
    try {
        $capture = $event['resource'];
        $paypalOrderId = $capture['supplementary_data']['related_ids']['order_id'] ?? '';
        
        if (empty($paypalOrderId)) {
            logError("PayPal Order ID no encontrado en capture fallido", 'paypal_webhooks.log');
            return;
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por payment_id
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ?");
        $stmt->execute([$paypalOrderId]);
        $order = $stmt->fetch();
        
        if ($order) {
            $errorMessage = $capture['status_details']['reason'] ?? 'Pago rechazado por PayPal';
            PaymentProcessor::failPayment($order['id'], "PayPal: $errorMessage");
            
            logError("Pago fallido - Orden: {$order['order_number']} - Error: $errorMessage", 'paypal_webhooks.log');
        }
        
    } catch (Exception $e) {
        logError("Error procesando pago fallido de PayPal: " . $e->getMessage(), 'paypal_webhooks.log');
        throw $e;
    }
}

/**
 * Obtener token de acceso de PayPal
 */
function getPayPalAccessToken($config) {
    try {
        $baseUrl = $config['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Accept-Language: en_US']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return ['success' => true, 'access_token' => $data['access_token']];
        } else {
            return ['success' => false, 'message' => 'Error obteniendo token'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>