<?php
// api/payments/mercadopago_return.php - Manejo de retorno de MercadoPago
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/payments.php';

try {
    // Obtener parámetros de MercadoPago
    $status = $_GET['status'] ?? '';
    $paymentId = $_GET['payment_id'] ?? '';
    $preferenceId = $_GET['preference_id'] ?? '';
    $collectionId = $_GET['collection_id'] ?? '';
    $collectionStatus = $_GET['collection_status'] ?? '';
    $externalReference = $_GET['external_reference'] ?? '';
    
    // Log de parámetros recibidos
    logError("MercadoPago return params: status=$status, payment_id=$paymentId, external_ref=$externalReference", 'mercadopago_returns.log');
    
    // Validar que tengamos al menos uno de los identificadores
    if (empty($paymentId) && empty($externalReference) && empty($preferenceId)) {
        logError("MercadoPago return: Sin identificadores válidos");
        redirect(SITE_URL . '/pages/failed.php?reason=invalid_data&message=' . urlencode('Parámetros de MercadoPago incompletos'));
    }
    
    // Obtener configuración de MercadoPago
    $config = PaymentProcessor::getGatewayConfig('mercadopago');
    if (!$config['enabled']) {
        throw new Exception('MercadoPago no está habilitado');
    }
    
    $db = Database::getInstance()->getConnection();
    $order = null;
    
    // Buscar orden por external_reference (order_number) primero
    if ($externalReference) {
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE order_number = ? AND payment_status IN ('pending', 'completed')
        ");
        $stmt->execute([$externalReference]);
        $order = $stmt->fetch();
    }
    
    // Si no se encontró por external_reference, buscar por payment_id (preference_id)
    if (!$order && $preferenceId) {
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE payment_id = ? AND payment_status IN ('pending', 'completed')
        ");
        $stmt->execute([$preferenceId]);
        $order = $stmt->fetch();
    }
    
    if (!$order) {
        logError("MercadoPago return: Orden no encontrada - external_ref: $externalReference, preference_id: $preferenceId");
        redirect(SITE_URL . '/pages/failed.php?reason=unknown&message=' . urlencode('Orden no encontrada'));
    }
    
    // Si ya está completada, redirigir a success
    if ($order['payment_status'] === 'completed') {
        redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
    }
    
    // Procesar según el status
    switch ($status) {
        case 'approved':
            handleApprovedPayment($order, $paymentId, $config);
            break;
            
        case 'pending':
        case 'in_process':
            handlePendingPayment($order, $paymentId, $config);
            break;
            
        case 'rejected':
        case 'cancelled':
            handleRejectedPayment($order, $paymentId, $config, $status);
            break;
            
        default:
            // Status desconocido - verificar con API
            if ($paymentId) {
                verifyPaymentWithAPI($order, $paymentId, $config);
            } else {
                redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
            }
            break;
    }
    
} catch (Exception $e) {
    logError("Error en MercadoPago return: " . $e->getMessage());
    redirect(SITE_URL . '/pages/failed.php?reason=gateway_error&message=' . urlencode('Error procesando respuesta de MercadoPago'));
}

/**
 * Manejar pago aprobado
 */
function handleApprovedPayment($order, $paymentId, $config) {
    try {
        // Si no tenemos payment_id, completar directamente
        if (!$paymentId) {
            $paymentData = [
                'mercadopago_status' => 'approved',
                'completed_at' => date('Y-m-d H:i:s'),
                'return_processed' => true
            ];
            
            $result = PaymentProcessor::completePayment($order['id'], 'MP_APPROVED_' . time(), $paymentData);
            
            if ($result['success']) {
                logError("MercadoPago pago completado (sin payment_id) - Orden: {$order['order_number']}", 'mercadopago_success.log');
                clearCartAndRedirect($order['order_number']);
            } else {
                throw new Exception('Error completando pago: ' . $result['message']);
            }
            return;
        }
        
        // Verificar con API de MercadoPago si tenemos payment_id
        $paymentDetails = getPaymentFromAPI($paymentId, $config);
        
        if ($paymentDetails && $paymentDetails['status'] === 'approved') {
            $paymentData = [
                'mercadopago_payment_id' => $paymentId,
                'amount_received' => floatval($paymentDetails['transaction_amount']),
                'currency' => $paymentDetails['currency_id'],
                'payment_method' => $paymentDetails['payment_method_id'],
                'payment_type' => $paymentDetails['payment_type_id'],
                'payer_email' => $paymentDetails['payer']['email'] ?? '',
                'mercadopago_fee' => floatval($paymentDetails['fee_details'][0]['amount'] ?? 0),
                'net_amount' => floatval($paymentDetails['transaction_amount']) - floatval($paymentDetails['fee_details'][0]['amount'] ?? 0),
                'status_detail' => $paymentDetails['status_detail'],
                'completed_at' => date('Y-m-d H:i:s'),
                'mercadopago_response' => json_encode($paymentDetails)
            ];
            
            $result = PaymentProcessor::completePayment($order['id'], $paymentId, $paymentData);
            
            if ($result['success']) {
                logError("MercadoPago pago completado - Orden: {$order['order_number']} - Payment: $paymentId", 'mercadopago_success.log');
                clearCartAndRedirect($order['order_number']);
            } else {
                throw new Exception('Error completando pago: ' . $result['message']);
            }
        } else {
            // Status no es approved según API
            $actualStatus = $paymentDetails['status'] ?? 'unknown';
            logError("MercadoPago status mismatch: URL dice approved, API dice $actualStatus");
            redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
        }
        
    } catch (Exception $e) {
        logError("Error procesando pago aprobado de MercadoPago: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Manejar pago pendiente
 */
function handlePendingPayment($order, $paymentId, $config) {
    try {
        // Actualizar datos del pago pero mantener status pendiente
        $db = Database::getInstance()->getConnection();
        
        $paymentData = json_decode($order['payment_data'], true) ?: [];
        $paymentData['mercadopago_payment_id'] = $paymentId;
        $paymentData['mercadopago_status'] = 'pending';
        $paymentData['last_updated'] = date('Y-m-d H:i:s');
        $paymentData['return_processed'] = true;
        
        if ($paymentId) {
            $paymentDetails = getPaymentFromAPI($paymentId, $config);
            if ($paymentDetails) {
                $paymentData['payment_method'] = $paymentDetails['payment_method_id'] ?? '';
                $paymentData['status_detail'] = $paymentDetails['status_detail'] ?? '';
            }
        }
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET payment_data = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([json_encode($paymentData), $order['id']]);
        
        logError("MercadoPago pago pendiente - Orden: {$order['order_number']} - Payment: $paymentId", 'mercadopago_pending.log');
        
        redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago&time=24%20horas');
        
    } catch (Exception $e) {
        logError("Error procesando pago pendiente de MercadoPago: " . $e->getMessage());
        redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
    }
}

/**
 * Manejar pago rechazado
 */
function handleRejectedPayment($order, $paymentId, $config, $status) {
    try {
        $reason = $status === 'cancelled' ? 'cancelled' : 'declined';
        $errorMessage = "Pago $status en MercadoPago";
        
        if ($paymentId) {
            $paymentDetails = getPaymentFromAPI($paymentId, $config);
            if ($paymentDetails && isset($paymentDetails['status_detail'])) {
                $errorMessage .= ": " . $paymentDetails['status_detail'];
            }
        }
        
        PaymentProcessor::failPayment($order['id'], $errorMessage);
        
        logError("MercadoPago pago rechazado - Orden: {$order['order_number']} - Status: $status", 'mercadopago_failed.log');
        
        redirect(SITE_URL . '/pages/failed.php?order=' . $order['order_number'] . '&reason=' . $reason . '&message=' . urlencode($errorMessage));
        
    } catch (Exception $e) {
        logError("Error procesando pago rechazado de MercadoPago: " . $e->getMessage());
        redirect(SITE_URL . '/pages/failed.php?reason=gateway_error');
    }
}

/**
 * Verificar pago con API cuando status es desconocido
 */
function verifyPaymentWithAPI($order, $paymentId, $config) {
    try {
        $paymentDetails = getPaymentFromAPI($paymentId, $config);
        
        if (!$paymentDetails) {
            redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
            return;
        }
        
        $status = $paymentDetails['status'];
        
        switch ($status) {
            case 'approved':
                handleApprovedPayment($order, $paymentId, $config);
                break;
                
            case 'pending':
            case 'in_process':
                handlePendingPayment($order, $paymentId, $config);
                break;
                
            case 'rejected':
            case 'cancelled':
                handleRejectedPayment($order, $paymentId, $config, $status);
                break;
                
            default:
                logError("MercadoPago status desconocido desde API: $status");
                redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
                break;
        }
        
    } catch (Exception $e) {
        logError("Error verificando pago con API de MercadoPago: " . $e->getMessage());
        redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
    }
}

/**
 * Obtener detalles del pago desde API de MercadoPago
 */
function getPaymentFromAPI($paymentId, $config) {
    try {
        $baseUrl = $config['sandbox'] ? 'https://api.mercadopago.com' : 'https://api.mercadopago.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v1/payments/{$paymentId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            logError("MercadoPago API error: HTTP $httpCode - Response: $response");
            return null;
        }
        
    } catch (Exception $e) {
        logError("MercadoPago API exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Limpiar carrito y redirigir a success
 */
function clearCartAndRedirect($orderNumber) {
    // Limpiar carrito si existe
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
        unset($_SESSION['cart_totals']);
    }
    
    redirect(SITE_URL . '/pages/success.php?order=' . $orderNumber);
}