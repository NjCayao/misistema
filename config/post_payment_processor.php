<?php
// config/post_payment_processor.php - Procesador central de post-pago

require_once __DIR__ . '/license_manager.php';
require_once __DIR__ . '/user_manager.php';

/**
 * Clase principal para manejar todo el proceso post-pago
 */
class PostPaymentProcessor {
    
    /**
     * Procesar completamente una orden tras pago exitoso
     */
    public static function processCompletedOrder($orderId, $paymentData = []) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener orden completa
            $stmt = $db->prepare("
                SELECT o.*, 
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as items_count
                FROM orders o 
                WHERE o.id = ? AND o.payment_status = 'completed'
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Orden no encontrada o no completada');
            }
            
            // Extraer datos del cliente desde payment_data
            $orderPaymentData = json_decode($order['payment_data'], true) ?: [];
            $customerData = $orderPaymentData['customer_data'] ?? [];
            
            if (empty($customerData)) {
                throw new Exception('Datos del cliente no encontrados en la orden');
            }
            
            $results = [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'customer_email' => $order['customer_email'],
                'total_amount' => $order['total_amount']
            ];
            
            // Paso 1: Gestionar usuario (crear si no existe)
            $userResult = UserManager::createUserFromOrder($order, $customerData);
            $results['user_management'] = $userResult;
            
            // Actualizar orden con user_id si se creó usuario
            if ($userResult['success'] && $userResult['user_id'] && !$order['user_id']) {
                $stmt = $db->prepare("UPDATE orders SET user_id = ? WHERE id = ?");
                $stmt->execute([$userResult['user_id'], $orderId]);
                $order['user_id'] = $userResult['user_id'];
            }
            
            // Paso 2: Generar licencias
            $licenseResult = LicenseManager::generateLicensesFromOrder($orderId);
            $results['license_generation'] = $licenseResult;
            
            // Paso 3: Enviar email de confirmación
            $emailResult = self::sendCompletePurchaseEmail($order, $customerData, $licenseResult['licenses'] ?? []);
            $results['email_confirmation'] = $emailResult;
            
            // Paso 4: Limpiar carrito si existe sesión
            self::clearUserCart();
            $results['cart_cleared'] = true;
            
            // Paso 5: Notificar al admin
            $adminNotification = self::notifyAdminOfPurchase($order, $customerData, $results);
            $results['admin_notification'] = $adminNotification;
            
            // Paso 6: Registrar actividad
            self::logPostPaymentActivity($order, $results);
            
            // Log general de éxito
            logError("Post-pago procesado exitosamente - Orden: {$order['order_number']} - Usuario: {$order['customer_email']}", 'post_payment.log');
            
            return [
                'success' => true,
                'message' => 'Proceso post-pago completado exitosamente',
                'results' => $results
            ];
            
        } catch (Exception $e) {
            logError("Error en proceso post-pago: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'order_id' => $orderId ?? null
            ];
        }
    }
    
    /**
     * Enviar email completo de confirmación de compra
     */
    private static function sendCompletePurchaseEmail($order, $customerData, $licenses) {
        try {
            $siteName = Settings::get('site_name', 'MiSistema');
            $subject = "¡Compra confirmada! - Orden #{$order['order_number']} - $siteName";
            
            // Generar enlaces de descarga
            $downloadLinksHtml = '';
            $downloadLinksText = '';
            
            foreach ($licenses as $license) {
                $downloadUrl = $license['download_url'] ?? SITE_URL . '/download/' . $license['product_id'] . '?order=' . $order['order_number'];
                $productName = $license['product_name'] ?? 'Producto';
                
                $downloadLinksHtml .= "
                <div style='background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin: 10px 0;'>
                    <h4 style='margin: 0 0 10px 0; color: #333;'>📦 $productName</h4>
                    <a href='$downloadUrl' style='
                        background: linear-gradient(45deg, #28a745, #20c997);
                        color: white;
                        padding: 10px 20px;
                        text-decoration: none;
                        border-radius: 20px;
                        font-weight: bold;
                        display: inline-block;
                        margin: 5px 0;
                    '>⬇️ Descargar Ahora</a>
                    <p style='margin: 10px 0 0 0; font-size: 14px; color: #666;'>
                        Descargas disponibles: " . ($license['downloads_limit'] ?? 'Ilimitadas') . " | 
                        Actualizaciones hasta: " . ($license['updates_until'] ? date('d/m/Y', strtotime($license['updates_until'])) : 'Permanente') . "
                    </p>
                </div>";
                
                $downloadLinksText .= "• $productName: $downloadUrl\n";
            }
            
            $dashboardUrl = SITE_URL . '/pages/dashboard.php';
            $supportUrl = SITE_URL . '/contacto';
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='margin: 0; font-size: 32px;'>🎉 ¡Compra Confirmada!</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9; font-size: 18px;'>Orden #{$order['order_number']}</p>
                </div>
                
                <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <h3 style='color: #333; margin-bottom: 20px;'>Hola {$customerData['first_name']},</h3>
                    
                    <div style='background: #d4edda; border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin: 20px 0;'>
                        <h4 style='color: #155724; margin-top: 0;'>✅ Tu pago ha sido procesado exitosamente</h4>
                        <div style='color: #155724;'>
                            <strong>Total pagado:</strong> " . formatPrice($order['total_amount']) . "<br>
                            <strong>Método de pago:</strong> " . ucfirst($order['payment_method']) . "<br>
                            <strong>Fecha:</strong> " . formatDateTime($order['created_at']) . "
                        </div>
                    </div>
                    
                    <h3 style='color: #333; margin: 30px 0 20px 0;'>📥 Tus Descargas</h3>
                    <p style='color: #666; margin-bottom: 20px;'>
                        Haz clic en los enlaces de abajo para descargar tus productos. Los enlaces están protegidos y son únicos para ti.
                    </p>
                    
                    $downloadLinksHtml
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #007bff;'>
                        <h4 style='color: #007bff; margin-top: 0;'>🏠 Tu Dashboard Personal</h4>
                        <p style='margin: 10px 0;'>
                            Accede a tu área personal para gestionar todas tus compras, re-descargar productos y recibir actualizaciones.
                        </p>
                        <a href='$dashboardUrl' style='
                            background: #007bff;
                            color: white;
                            padding: 12px 25px;
                            text-decoration: none;
                            border-radius: 20px;
                            font-weight: bold;
                            display: inline-block;
                            margin-top: 10px;
                        '>🎛️ Ir al Dashboard</a>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;'>
                        <h4 style='color: #856404; margin-top: 0;'>📋 Importante</h4>
                        <ul style='color: #856404; margin: 0; padding-left: 20px;'>
                            <li>Guarda estos enlaces de descarga en un lugar seguro</li>
                            <li>Tienes un límite de descargas por producto (consulta los detalles arriba)</li>
                            <li>Las actualizaciones están incluidas por el tiempo especificado</li>
                            <li>Si tienes problemas, contacta nuestro soporte</li>
                        </ul>
                    </div>
                    
                    <div style='background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #17a2b8;'>
                        <h4 style='color: #0c5460; margin-top: 0;'>🆘 ¿Necesitas Ayuda?</h4>
                        <p style='color: #0c5460; margin: 10px 0;'>
                            Nuestro equipo de soporte está disponible para ayudarte con cualquier pregunta.
                        </p>
                        <a href='$supportUrl' style='
                            background: #17a2b8;
                            color: white;
                            padding: 10px 20px;
                            text-decoration: none;
                            border-radius: 20px;
                            font-weight: bold;
                            display: inline-block;
                        '>📧 Contactar Soporte</a>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    
                    <div style='text-align: center; color: #999; font-size: 12px;'>
                        <p>Gracias por tu compra en $siteName</p>
                        <p>Este email fue enviado a {$order['customer_email']}</p>
                    </div>
                </div>
            </div>
            ";
            
            // Enviar email
            $emailSent = EmailSystem::sendEmail($order['customer_email'], $subject, $body, true);
            
            // Log del email
            if ($emailSent) {
                logError("Email de confirmación enviado - Orden: {$order['order_number']} - Email: {$order['customer_email']}", 'emails.log');
            } else {
                logError("Error enviando email de confirmación - Orden: {$order['order_number']}", 'email_errors.log');
            }
            
            return [
                'success' => $emailSent,
                'email' => $order['customer_email'],
                'products_count' => count($licenses)
            ];
            
        } catch (Exception $e) {
            logError("Error enviando email de confirmación completo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Notificar al admin de nueva compra
     */
    private static function notifyAdminOfPurchase($order, $customerData, $results) {
        try {
            $siteName = Settings::get('site_name', 'MiSistema');
            $subject = "Nueva Venta Completada - #{$order['order_number']} - $siteName";
            
            $userAction = $results['user_management']['action'] ?? 'unknown';
            $licenseCount = count($results['license_generation']['licenses'] ?? []);
            
            $adminUrl = ADMIN_URL . '/pages/orders/view.php?id=' . $order['id'];
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #28a745; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0;'>💰 Nueva Venta Completada</h2>
                </div>
                
                <div style='background: white; padding: 20px;'>
                    <h3>Detalles de la Orden</h3>
                    <ul>
                        <li><strong>Orden:</strong> #{$order['order_number']}</li>
                        <li><strong>Cliente:</strong> {$customerData['first_name']} {$customerData['last_name']}</li>
                        <li><strong>Email:</strong> {$order['customer_email']}</li>
                        <li><strong>Total:</strong> " . formatPrice($order['total_amount']) . "</li>
                        <li><strong>Método:</strong> " . ucfirst($order['payment_method']) . "</li>
                        <li><strong>Productos:</strong> {$order['items_count']}</li>
                        <li><strong>Usuario:</strong> " . ($userAction === 'created' ? 'Nuevo (creado automáticamente)' : ($userAction === 'existing' ? 'Existente' : 'Invitado')) . "</li>
                        <li><strong>Licencias generadas:</strong> $licenseCount</li>
                    </ul>
                    
                    <p><a href='$adminUrl' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver en Admin</a></p>
                </div>
            </div>
            ";
            
            return EmailSystem::notifyAdmin($subject, $body, 'sale');
            
        } catch (Exception $e) {
            logError("Error notificando admin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar carrito del usuario
     */
    private static function clearUserCart() {
        try {
            if (isset($_SESSION['cart'])) {
                unset($_SESSION['cart']);
                unset($_SESSION['cart_totals']);
                logError("Carrito limpiado tras compra exitosa", 'post_payment.log');
            }
        } catch (Exception $e) {
            logError("Error limpiando carrito: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar actividad post-pago para analytics
     */
    private static function logPostPaymentActivity($order, $results) {
        try {
            $activity = [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'customer_email' => $order['customer_email'],
                'total_amount' => $order['total_amount'],
                'payment_method' => $order['payment_method'],
                'user_action' => $results['user_management']['action'] ?? 'unknown',
                'licenses_generated' => count($results['license_generation']['licenses'] ?? []),
                'email_sent' => $results['email_confirmation']['success'] ?? false,
                'processed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];
            
            logError("Post-payment activity: " . json_encode($activity), 'post_payment_analytics.log');
            
        } catch (Exception $e) {
            logError("Error registrando actividad post-pago: " . $e->getMessage());
        }
    }
    
    /**
     * Procesar reembolso (para admins)
     */
    public static function processRefund($orderId, $reason = '', $partialAmount = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener orden
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Orden no encontrada');
            }
            
            // Actualizar estado de la orden
            $refundAmount = $partialAmount ?? $order['total_amount'];
            
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'refunded', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Desactivar licencias asociadas
            if ($order['user_id']) {
                $stmt = $db->prepare("
                    UPDATE user_licenses 
                    SET is_active = 0, updated_at = NOW()
                    WHERE order_id = ?
                ");
                $stmt->execute([$orderId]);
            }
            
            // Enviar email de notificación de reembolso
            self::sendRefundNotificationEmail($order, $refundAmount, $reason);
            
            // Log del reembolso
            logError("Reembolso procesado - Orden: {$order['order_number']} - Monto: " . formatPrice($refundAmount) . " - Razón: $reason", 'refunds.log');
            
            return [
                'success' => true,
                'message' => 'Reembolso procesado exitosamente',
                'refund_amount' => $refundAmount
            ];
            
        } catch (Exception $e) {
            logError("Error procesando reembolso: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar email de notificación de reembolso
     */
    private static function sendRefundNotificationEmail($order, $refundAmount, $reason) {
        try {
            $siteName = Settings::get('site_name', 'MiSistema');
            $subject = "Reembolso procesado - Orden #{$order['order_number']} - $siteName";
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #ffc107; color: #856404; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0;'>💰 Reembolso Procesado</h2>
                </div>
                
                <div style='background: white; padding: 30px;'>
                    <h3 style='color: #333;'>Estimado cliente,</h3>
                    
                    <p style='color: #666; line-height: 1.6;'>
                        Te informamos que hemos procesado un reembolso para tu orden #{$order['order_number']}.
                    </p>
                    
                    <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h4 style='color: #856404; margin-top: 0;'>📋 Detalles del Reembolso</h4>
                        <ul style='color: #856404; margin: 0;'>
                            <li><strong>Orden:</strong> #{$order['order_number']}</li>
                            <li><strong>Monto reembolsado:</strong> " . formatPrice($refundAmount) . "</li>
                            <li><strong>Método original:</strong> " . ucfirst($order['payment_method']) . "</li>
                            " . ($reason ? "<li><strong>Motivo:</strong> $reason</li>" : "") . "
                            <li><strong>Fecha:</strong> " . date('d/m/Y H:i') . "</li>
                        </ul>
                    </div>
                    
                    <p style='color: #666; line-height: 1.6;'>
                        El reembolso será procesado a través del mismo método de pago original y puede tomar 
                        entre 3-10 días hábiles en aparecer en tu estado de cuenta, dependiendo de tu banco o proveedor de pagos.
                    </p>
                    
                    <p style='color: #666; line-height: 1.6;'>
                        Si tienes alguna pregunta sobre este reembolso, no dudes en contactarnos.
                    </p>
                </div>
            </div>
            ";
            
            return EmailSystem::sendEmail($order['customer_email'], $subject, $body, true);
            
        } catch (Exception $e) {
            logError("Error enviando email de reembolso: " . $e->getMessage());
            return false;
        }
    }
}

// Funciones helper para mantener compatibilidad
function processCompletedOrder($orderId, $paymentData = []) {
    return PostPaymentProcessor::processCompletedOrder($orderId, $paymentData);
}

function processRefund($orderId, $reason = '', $partialAmount = null) {
    return PostPaymentProcessor::processRefund($orderId, $reason, $partialAmount);
}