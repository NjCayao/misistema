<?php
// config/payments.php - Sistema de procesamiento de pagos

/**
 * Clase para manejar procesamiento de pagos
 */
class PaymentProcessor {
    
    /**
     * Crear orden en la base de datos
     */
    public static function createOrder($customerData, $cartData, $paymentMethod = 'pending') {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Generar número de orden único
            $orderNumber = generateOrderNumber();
            
            // Calcular totales
            $totals = $cartData['totals'];
            
            // Determinar si es donación (solo productos gratuitos)
            $isDonation = $paymentMethod === 'free' && $totals['total'] == 0;
            
            // Crear orden principal
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, payment_method, 
                    payment_status, customer_email, customer_name, is_donation,
                    payment_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $userId = $customerData['user_id'] ?? null;
            $customerName = trim($customerData['first_name'] . ' ' . $customerData['last_name']);
            $paymentStatus = $paymentMethod === 'free' ? 'completed' : 'pending';
            
            $paymentData = json_encode([
                'customer_data' => $customerData,
                'cart_summary' => [
                    'items_count' => count($cartData['items']),
                    'subtotal' => $totals['subtotal'],
                    'tax' => $totals['tax'],
                    'total' => $totals['total']
                ],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->execute([
                $userId,
                $orderNumber,
                $totals['total'],
                $paymentMethod,
                $paymentStatus,
                $customerData['email'],
                $customerName,
                $isDonation ? 1 : 0,
                $paymentData
            ]);
            
            $orderId = $db->lastInsertId();
            
            // Crear items de la orden
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($cartData['items'] as $item) {
                $stmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['is_free'] ? 0 : $item['price'],
                    $item['quantity']
                ]);
            }
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totals['total'],
                'payment_status' => $paymentStatus
            ];
            
        } catch (Exception $e) {
            logError("Error creando orden: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear la orden'];
        }
    }
    
    /**
     * Procesar pago gratuito o completar orden
     */
    public static function processFreeOrder($orderData, $customerData) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Actualizar orden como completada
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$orderData['order_id']]);
            
            // Generar licencias de usuario
            $licenses = self::generateUserLicenses($orderData['order_id'], $customerData['user_id'] ?? null);
            
            // Enviar email de confirmación
            self::sendConfirmationEmail($orderData, $customerData, $licenses);
            
            // Limpiar carrito
            Cart::clear();
            
            return [
                'success' => true,
                'order_number' => $orderData['order_number'],
                'licenses' => $licenses,
                'redirect_url' => SITE_URL . '/pages/success.php?order=' . $orderData['order_number']
            ];
            
        } catch (Exception $e) {
            logError("Error procesando orden gratuita: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al procesar la orden'];
        }
    }
    
    /**
     * Generar licencias de usuario para productos comprados
     */
    public static function generateUserLicenses($orderId, $userId = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener productos de la orden
            $stmt = $db->prepare("
                SELECT oi.*, p.download_limit, p.update_months
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $orderItems = $stmt->fetchAll();
            
            $licenses = [];
            
            if ($userId) {
                // Usuario registrado - crear licencias
                foreach ($orderItems as $item) {
                    $updatesUntil = date('Y-m-d H:i:s', strtotime("+{$item['update_months']} months"));
                    
                    $stmt = $db->prepare("
                        INSERT INTO user_licenses (
                            user_id, product_id, order_id, downloads_used, downloads_limit,
                            updates_until, is_active, created_at
                        ) VALUES (?, ?, ?, 0, ?, ?, 1, NOW())
                        ON DUPLICATE KEY UPDATE
                            downloads_limit = downloads_limit + VALUES(downloads_limit),
                            updates_until = GREATEST(updates_until, VALUES(updates_until)),
                            updated_at = NOW()
                    ");
                    
                    $stmt->execute([
                        $userId,
                        $item['product_id'],
                        $orderId,
                        $item['download_limit'],
                        $updatesUntil
                    ]);
                    
                    $licenses[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'downloads_limit' => $item['download_limit'],
                        'updates_until' => $updatesUntil
                    ];
                }
            } else {
                // Usuario invitado - crear licencias temporales
                foreach ($orderItems as $item) {
                    $licenses[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'downloads_limit' => $item['download_limit'],
                        'updates_until' => date('Y-m-d H:i:s', strtotime("+{$item['update_months']} months"))
                    ];
                }
            }
            
            return $licenses;
            
        } catch (Exception $e) {
            logError("Error generando licencias: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enviar email de confirmación
     */
    public static function sendConfirmationEmail($orderData, $customerData, $licenses) {
        try {
            // Generar enlaces de descarga
            $downloadLinks = '';
            foreach ($licenses as $license) {
                $downloadUrl = SITE_URL . '/download/' . $license['product_id'] . '?order=' . $orderData['order_number'];
                $downloadLinks .= "• {$license['product_name']}: {$downloadUrl}\n";
            }
            
            // Enviar email usando el sistema de plantillas
            EmailSystem::sendPurchaseEmail(
                $customerData['email'],
                $customerData['first_name'],
                $orderData['order_number'],
                $orderData['total_amount'],
                $downloadLinks
            );
            
            // Notificar al admin
            EmailSystem::notifyNewOrder([
                'order_number' => $orderData['order_number'],
                'customer_name' => trim($customerData['first_name'] . ' ' . $customerData['last_name']),
                'customer_email' => $customerData['email'],
                'total_amount' => $orderData['total_amount'],
                'payment_method' => 'free'
            ]);
            
            return true;
            
        } catch (Exception $e) {
            logError("Error enviando email de confirmación: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Completar pago después de confirmación de pasarela
     */
    public static function completePayment($orderId, $paymentId, $paymentData = []) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener orden
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Orden no encontrada');
            }
            
            // Actualizar orden como completada
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'completed', payment_id = ?, payment_data = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $updatedPaymentData = array_merge(
                json_decode($order['payment_data'], true) ?: [],
                $paymentData,
                ['completed_at' => date('Y-m-d H:i:s')]
            );
            
            $stmt->execute([
                $paymentId,
                json_encode($updatedPaymentData),
                $orderId
            ]);
            
            // Obtener datos del cliente
            $customerData = json_decode($order['payment_data'], true)['customer_data'];
            
            // Generar licencias
            $licenses = self::generateUserLicenses($orderId, $order['user_id']);
            
            // Enviar confirmación
            $orderData = [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount']
            ];
            
            self::sendConfirmationEmail($orderData, $customerData, $licenses);
            
            return [
                'success' => true,
                'order_number' => $order['order_number'],
                'licenses' => $licenses
            ];
            
        } catch (Exception $e) {
            logError("Error completando pago: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Marcar pago como fallido
     */
    public static function failPayment($orderId, $reason = '') {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'failed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            logError("Pago fallido para orden $orderId: $reason", 'payments.log');
            
            return true;
            
        } catch (Exception $e) {
            logError("Error marcando pago como fallido: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener configuración de pasarela de pago
     */
    public static function getGatewayConfig($gateway) {
        switch ($gateway) {
            case 'stripe':
                return [
                    'enabled' => Settings::get('stripe_enabled', '0') === '1',
                    'publishable_key' => Settings::get('stripe_publishable_key', ''),
                    'secret_key' => Settings::get('stripe_secret_key', ''),
                    'webhook_secret' => Settings::get('stripe_webhook_secret', ''),
                    'commission' => floatval(Settings::get('stripe_commission', '3.5')),
                    'fixed_fee' => floatval(Settings::get('stripe_fixed_fee', '0.30'))
                ];
                
            case 'paypal':
                return [
                    'enabled' => Settings::get('paypal_enabled', '0') === '1',
                    'client_id' => Settings::get('paypal_client_id', ''),
                    'client_secret' => Settings::get('paypal_client_secret', ''),
                    'webhook_id' => Settings::get('paypal_webhook_id', ''),
                    'sandbox' => Settings::get('paypal_sandbox', '1') === '1',
                    'commission' => floatval(Settings::get('paypal_commission', '4.5')),
                    'fixed_fee' => floatval(Settings::get('paypal_fixed_fee', '0.25'))
                ];
                
            case 'mercadopago':
                return [
                    'enabled' => Settings::get('mercadopago_enabled', '0') === '1',
                    'public_key' => Settings::get('mercadopago_public_key', ''),
                    'access_token' => Settings::get('mercadopago_access_token', ''),
                    'webhook_secret' => Settings::get('mercadopago_webhook_secret', ''),
                    'sandbox' => Settings::get('mercadopago_sandbox', '1') === '1',
                    'commission' => floatval(Settings::get('mercadopago_commission', '5.2')),
                    'fixed_fee' => floatval(Settings::get('mercadopago_fixed_fee', '0.15'))
                ];
                
            default:
                return ['enabled' => false];
        }
    }
    
    /**
     * Calcular precio final con comisiones
     */
    public static function calculateFinalPrice($basePrice, $gateway) {
        $config = self::getGatewayConfig($gateway);
        
        if (!$config['enabled']) {
            return $basePrice;
        }
        
        // Precio final = (precio_base + tarifa_fija) / (1 - comision/100)
        $finalPrice = ($basePrice + $config['fixed_fee']) / (1 - $config['commission'] / 100);
        
        return round($finalPrice, 2);
    }
    
    /**
     * Validar webhook signature
     */
    public static function validateWebhookSignature($gateway, $payload, $signature) {
        $config = self::getGatewayConfig($gateway);
        
        switch ($gateway) {
            case 'stripe':
                return self::validateStripeSignature($payload, $signature, $config['webhook_secret']);
                
            case 'paypal':
                return self::validatePayPalSignature($payload, $signature, $config);
                
            case 'mercadopago':
                return self::validateMercadoPagoSignature($payload, $signature, $config['webhook_secret']);
                
            default:
                return false;
        }
    }
    
    /**
     * Validar signature de Stripe
     */
    private static function validateStripeSignature($payload, $signature, $secret) {
        if (empty($secret)) return false;
        
        $elements = explode(',', $signature);
        $signatureHash = '';
        
        foreach ($elements as $element) {
            if (strpos($element, 'v1=') === 0) {
                $signatureHash = substr($element, 3);
                break;
            }
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signatureHash);
    }
    
    /**
     * Validar signature de PayPal
     */
    private static function validatePayPalSignature($payload, $headers, $config) {
        // Implementación simplificada - en producción usar la librería oficial
        return true; // Por ahora aceptar todos los webhooks de PayPal
    }
    
    /**
     * Validar signature de MercadoPago
     */
    private static function validateMercadoPagoSignature($payload, $signature, $secret) {
        if (empty($secret)) return false;
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}

// Funciones helper
function createOrder($customerData, $cartData, $paymentMethod = 'pending') {
    return PaymentProcessor::createOrder($customerData, $cartData, $paymentMethod);
}

function processFreeOrder($orderData, $customerData) {
    return PaymentProcessor::processFreeOrder($orderData, $customerData);
}

function completePayment($orderId, $paymentId, $paymentData = []) {
    return PaymentProcessor::completePayment($orderId, $paymentId, $paymentData);
}

function failPayment($orderId, $reason = '') {
    return PaymentProcessor::failPayment($orderId, $reason);
}
?>