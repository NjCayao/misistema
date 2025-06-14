<?php
// api/payments/paypal_return.php - Manejo de retorno de PayPal
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/payments.php';

try {
    // Obtener parámetros de PayPal
    $token = $_GET['token'] ?? '';
    $payerId = $_GET['PayerID'] ?? '';
    $orderNumber = $_GET['order'] ?? '';
    
    // Validar parámetros requeridos
    if (empty($token) || empty($payerId)) {
        logError("PayPal return: parámetros faltantes - Token: $token, PayerID: $payerId");
        redirect(SITE_URL . '/pages/failed.php?reason=invalid_data&message=' . urlencode('Parámetros de PayPal incompletos'));
    }
    
    // Obtener configuración de PayPal
    $config = PaymentProcessor::getGatewayConfig('paypal');
    if (!$config['enabled']) {
        throw new Exception('PayPal no está habilitado');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Buscar orden por payment_id (token de PayPal)
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE payment_id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();
    
    if (!$order) {
        logError("PayPal return: Orden no encontrada para token $token");
        redirect(SITE_URL . '/pages/failed.php?reason=unknown&message=' . urlencode('Orden no encontrada'));
    }
    
    // Obtener token de acceso de PayPal
    $accessToken = getPayPalAccessToken($config);
    if (!$accessToken['success']) {
        throw new Exception('Error obteniendo token de PayPal: ' . $accessToken['message']);
    }
    
    // Capturar el pago en PayPal
    $baseUrl = $config['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    
    $captureData = [
        'payer_id' => $payerId
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v2/checkout/orders/{$token}/capture");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($captureData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken['access_token']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 201) {
        logError("PayPal capture error: HTTP $httpCode - Response: $response");
        redirect(SITE_URL . '/pages/failed.php?reason=gateway_error&message=' . urlencode('Error procesando pago con PayPal'));
    }
    
    $captureResponse = json_decode($response, true);
    
    if (!$captureResponse || $captureResponse['status'] !== 'COMPLETED') {
        $status = $captureResponse['status'] ?? 'UNKNOWN';
        logError("PayPal capture failed: Status $status");
        redirect(SITE_URL . '/pages/failed.php?reason=declined&message=' . urlencode('Pago no completado'));
    }
    
    // Obtener datos del capture
    $capture = $captureResponse['purchase_units'][0]['payments']['captures'][0] ?? null;
    if (!$capture) {
        throw new Exception('Datos de capture no encontrados en respuesta de PayPal');
    }
    
    // Preparar datos para guardar
    $paymentData = [
        'paypal_order_id' => $token,
        'paypal_capture_id' => $capture['id'],
        'payer_id' => $payerId,
        'amount_received' => floatval($capture['amount']['value']),
        'currency' => $capture['amount']['currency_code'],
        'paypal_fee' => floatval($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0),
        'net_amount' => floatval($capture['seller_receivable_breakdown']['net_amount']['value'] ?? 0),
        'payer_email' => $captureResponse['payer']['email_address'] ?? '',
        'payer_name' => ($captureResponse['payer']['name']['given_name'] ?? '') . ' ' . ($captureResponse['payer']['name']['surname'] ?? ''),
        'completed_at' => date('Y-m-d H:i:s'),
        'paypal_response' => $response
    ];
    
    // Completar el pago
    $result = PaymentProcessor::completePayment($order['id'], $capture['id'], $paymentData);
    
    if ($result['success']) {
        // Log de éxito
        logError("PayPal pago completado exitosamente - Orden: {$order['order_number']} - Capture: {$capture['id']}", 'paypal_success.log');
        
        // Limpiar carrito si existe
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
            unset($_SESSION['cart_totals']);
        }
        
        // Redirigir a página de éxito
        redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
        
    } else {
        throw new Exception('Error completando pago: ' . $result['message']);
    }
    
} catch (Exception $e) {
    logError("Error en PayPal return: " . $e->getMessage());
    redirect(SITE_URL . '/pages/failed.php?reason=gateway_error&message=' . urlencode('Error procesando respuesta de PayPal'));
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true, 
                'access_token' => $data['access_token']
            ];
        } else {
            logError("PayPal token error: HTTP $httpCode - Response: $response");
            return [
                'success' => false, 
                'message' => 'Error obteniendo token de PayPal'
            ];
        }
        
    } catch (Exception $e) {
        logError("PayPal token exception: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => $e->getMessage()
        ];
    }
}