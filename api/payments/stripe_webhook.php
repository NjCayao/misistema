<?php
// api/payments/stripe_webhook.php - Webhook de Stripe
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
    // Obtener configuración de Stripe
    $config = PaymentProcessor::getGatewayConfig('stripe');
    
    if (!$config['enabled'] || empty($config['webhook_secret'])) {
        throw new Exception('Stripe webhook no configurado');
    }
    
    // Obtener payload y signature
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    // Validar signature
    if (!PaymentProcessor::validateWebhookSignature('stripe', $payload, $signature)) {
        throw new Exception('Signature inválida');
    }
    
    // Parsear evento
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new Exception('Payload inválido');
    }
    
    // Log del evento
    logError("Stripe webhook recibido: {$event['type']} - ID: {$event['id']}", 'stripe_webhooks.log');
    
    // Procesar según el tipo de evento
    switch ($event['type']) {
        case 'payment_intent.succeeded':
            handlePaymentIntentSucceeded($event['data']['object']);
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentIntentFailed($event['data']['object']);
            break;
            
        case 'payment_intent.canceled':
            handlePaymentIntentCanceled($event['data']['object']);
            break;
            
        default:
            logError("Stripe evento no manejado: {$event['type']}", 'stripe_webhooks.log');
            break;
    }
    
    // Responder a Stripe
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    logError("Error en Stripe webhook: " . $e->getMessage(), 'stripe_webhooks.log');
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Manejar pago exitoso
 */
function handlePaymentIntentSucceeded($paymentIntent) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por payment_id
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ? AND payment_status = 'pending'");
        $stmt->execute([$paymentIntent['id']]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Orden no encontrada para payment intent: {$paymentIntent['id']}");
        }
        
        // Datos del pago para guardar
        $paymentData = [
            'stripe_payment_intent_id' => $paymentIntent['id'],
            'stripe_charge_id' => $paymentIntent['charges']['data'][0]['id'] ?? '',
            'amount_received' => $paymentIntent['amount_received'] / 100, // Convertir de centavos
            'payment_method' => $paymentIntent['payment_method'] ?? '',
            'receipt_url' => $paymentIntent['charges']['data'][0]['receipt_url'] ?? '',
            'stripe_fee' => ($paymentIntent['charges']['data'][0]['balance_transaction']['fee'] ?? 0) / 100,
            'completed_at' => date('Y-m-d H:i:s', $paymentIntent['created'])
        ];
        
        // Completar pago
        $result = PaymentProcessor::completePayment($order['id'], $paymentIntent['id'], $paymentData);
        
        if ($result['success']) {
            logError("Pago completado exitosamente - Orden: {$order['order_number']} - Stripe PI: {$paymentIntent['id']}", 'stripe_webhooks.log');
        } else {
            throw new Exception("Error completando pago: {$result['message']}");
        }
        
    } catch (Exception $e) {
        logError("Error procesando payment_intent.succeeded: " . $e->getMessage(), 'stripe_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago fallido
 */
function handlePaymentIntentFailed($paymentIntent) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por payment_id
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ?");
        $stmt->execute([$paymentIntent['id']]);
        $order = $stmt->fetch();
        
        if ($order) {
            $errorMessage = $paymentIntent['last_payment_error']['message'] ?? 'Pago rechazado';
            PaymentProcessor::failPayment($order['id'], "Stripe: $errorMessage");
            
            logError("Pago fallido - Orden: {$order['order_number']} - Error: $errorMessage", 'stripe_webhooks.log');
        }
        
    } catch (Exception $e) {
        logError("Error procesando payment_intent.payment_failed: " . $e->getMessage(), 'stripe_webhooks.log');
        throw $e;
    }
}

/**
 * Manejar pago cancelado
 */
function handlePaymentIntentCanceled($paymentIntent) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar orden por payment_id
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ?");
        $stmt->execute([$paymentIntent['id']]);
        $order = $stmt->fetch();
        
        if ($order) {
            PaymentProcessor::failPayment($order['id'], 'Pago cancelado por el usuario');
            
            logError("Pago cancelado - Orden: {$order['order_number']}", 'stripe_webhooks.log');
        }
        
    } catch (Exception $e) {
        logError("Error procesando payment_intent.canceled: " . $e->getMessage(), 'stripe_webhooks.log');
        throw $e;
    }
}
?>