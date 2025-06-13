<?php
// api/payments/mercadopago_webhook.php - Webhook de MercadoPago
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
    // Obtener configuración de MercadoPago
    $config = PaymentProcessor::getGatewayConfig('mercadopago');
    
    if (!$config['enabled']) {
        throw new Exception('MercadoPago webhook no configurado');
    }
    
    // Obtener payload
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new Exception('Payload inválido');
    }
    
    // Validar signature
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if (!empty($config['webhook_secret'])) {
        if (!PaymentProcessor::validateWebhookSignature('mercadopago', $payload, $signature)) {
            throw new Exception('Signature inválida');
        }
    }
    
    // Log del evento
    logError("MercadoPago webhook recibido: {$event['type']} - ID: {$event['id']}", 'mercadopago_webhooks.log');
    
    // Procesar según el tipo de evento
    switch ($event['type']) {
        case 'payment':
            handlePaymentEvent($event, $config);
            break;
            
        default:
            logError("MercadoPago evento no manejado: {$event['type']}", 'mercadopago_webhooks.log');
            break;
    }
    
    // Responder a MercadoPago
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    logError("Error en MercadoPago webhook: " . $e->getMessage(), 'mercadopago_webhooks.log');
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Manejar evento de pago
 */
function handlePaymentEvent($event, $config) {
    try {
        $paymentId = $event['data']['id'];
        
        // Obtener detalles del pago de MercadoPago
        $baseUrl = 'https://api.mercadopago.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v1/payments/{$paymentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Error obteniendo pago de MercadoPago: HTTP $httpCode");
        }
        
        $payment = json_decode($response, true);
        
        if (!$payment) {
            throw new Exception('Respuesta inválida de MercadoPago');
        }
        
        // Obtener external_reference (order_number)
        $orderNumber = $payment['external_reference'] ?? '';
        
        if (empty($orderNumber)) {
            throw new Exception('External reference no encontrado');
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por order_number
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Orden no encontrada: $orderNumber");
        }
        
        // Procesar según el estado del pago
        switch ($payment['status']) {
            case 'approved':
                handlePaymentApproved($order, $payment);
                break;
                
            case 'rejected':
            case 'cancelled':
                handlePaymentRejected($order, $payment);
                break;
                
            case 'pending':
            case 'in_process':
                handlePaymentPending($order, $payment);
                break;
                
            default:
                logError("Estado de pago no manejado: {$payment['status']}", 'mercadopago_webhooks.log');
                break;
        }
        
    } catch (Exception $e) {
        logError("Error procesando evento de pago: " . $e->getMessage(), 'mercadopago_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago aprobado
 */
function handlePaymentApproved($order, $payment) {
    try {
        // Solo procesar si está pendiente
        if ($order['payment_status'] !== 'pending') {
            logError("Orden {$order['order_number']} ya procesada, estado actual: {$order['payment_status']}", 'mercadopago_webhooks.log');
            return;
        }
        
        // Datos del pago para guardar
        $paymentData = [
            'mercadopago_payment_id' => $payment['id'],
            'amount_received' => floatval($payment['transaction_amount']),
            'currency' => $payment['currency_id'],
            'payment_method' => $payment['payment_method_id'],
            'payment_type' => $payment['payment_type_id'],
            'payer_email' => $payment['payer']['email'] ?? '',
            'mercadopago_fee' => floatval($payment['fee_details'][0]['amount'] ?? 0),
            'net_amount' => floatval($payment['transaction_amount']) - floatval($payment['fee_details'][0]['amount'] ?? 0),
            'status_detail' => $payment['status_detail'],
            'completed_at' => date('Y-m-d H:i:s', strtotime($payment['date_approved']))
        ];
        
        // Completar pago
        $result = PaymentProcessor::completePayment($order['id'], $payment['id'], $paymentData);
        
        if ($result['success']) {
            logError("Pago completado exitosamente - Orden: {$order['order_number']} - MercadoPago ID: {$payment['id']}", 'mercadopago_webhooks.log');
        } else {
            throw new Exception("Error completando pago: {$result['message']}");
        }
        
    } catch (Exception $e) {
        logError("Error procesando pago aprobado: " . $e->getMessage(), 'mercadopago_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago rechazado
 */
function handlePaymentRejected($order, $payment) {
    try {
        $errorMessage = $payment['status_detail'] ?? 'Pago rechazado por MercadoPago';
        PaymentProcessor::failPayment($order['id'], "MercadoPago: $errorMessage");
        
        logError("Pago rechazado - Orden: {$order['order_number']} - Razón: $errorMessage", 'mercadopago_webhooks.log');
        
    } catch (Exception $e) {
        logError("Error procesando pago rechazado: " . $e->getMessage(), 'mercadopago_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago pendiente
 */
function handlePaymentPending($order, $payment) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Actualizar datos del pago pero mantener status pendiente
        $paymentData = json_decode($order['payment_data'], true) ?: [];
        $paymentData['mercadopago_payment_id'] = $payment['id'];
        $paymentData['mercadopago_status'] = $payment['status'];
        $paymentData['status_detail'] = $payment['status_detail'];
        $paymentData['last_updated'] = date('Y-m-d H:i:s');
        
        $stmt = $db->prepare("UPDATE orders SET payment_data = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($paymentData), $order['id']]);
        
        logError("Pago pendiente actualizado - Orden: {$order['order_number']} - Estado: {$payment['status']}", 'mercadopago_webhooks.log');
        
    } catch (Exception $e) {
        logError("Error procesando pago pendiente: " . $e->getMessage(), 'mercadopago_webhooks.log');
        throw $e;
    }
}
?>