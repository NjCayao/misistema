<?php
// config/user_manager.php - Sistema de gestión de usuarios post-compra

/**
 * Clase para manejar usuarios automáticamente tras compras
 */
class UserManager {
    
    /**
     * Crear usuario automáticamente tras compra (si no existe)
     */
    public static function createUserFromOrder($orderData, $customerData) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar si el usuario ya existe
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$customerData['email']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // Usuario ya existe
                return [
                    'success' => true,
                    'user_id' => $existingUser['id'],
                    'action' => 'existing',
                    'user' => $existingUser
                ];
            }
            
            // Verificar si se debe crear cuenta automáticamente
            $autoCreateAccount = Settings::get('auto_create_accounts', '1') == '1';
            $userWantsAccount = isset($customerData['create_account']) && $customerData['create_account'];
            
            if (!$autoCreateAccount && !$userWantsAccount) {
                return [
                    'success' => true,
                    'user_id' => null,
                    'action' => 'guest',
                    'message' => 'Compra como invitado'
                ];
            }
            
            // Generar contraseña temporal si no se proporcionó
            $tempPassword = self::generateTemporaryPassword();
            $hashedPassword = hashPassword($tempPassword);
            
            // Crear nuevo usuario
            $stmt = $db->prepare("
                INSERT INTO users (
                    email, password, first_name, last_name, phone, country,
                    is_verified, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())
            ");
            
            $stmt->execute([
                $customerData['email'],
                $hashedPassword,
                $customerData['first_name'],
                $customerData['last_name'],
                $customerData['phone'] ?? '',
                $customerData['country'] ?? ''
            ]);
            
            $userId = $db->lastInsertId();
            
            // Actualizar orden con user_id
            $stmt = $db->prepare("UPDATE orders SET user_id = ? WHERE id = ?");
            $stmt->execute([$userId, $orderData['order_id']]);
            
            // Obtener usuario completo
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();
            
            // Log de usuario creado
            logError("Usuario creado automáticamente - Email: {$customerData['email']} - ID: $userId - Orden: {$orderData['order_number']}", 'users.log');
            
            // Enviar email de bienvenida con contraseña temporal
            self::sendWelcomeEmailWithPassword($newUser, $tempPassword, $orderData);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'action' => 'created',
                'user' => $newUser,
                'temp_password' => $tempPassword
            ];
            
        } catch (Exception $e) {
            logError("Error creando usuario automáticamente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Activar usuario tras primera compra
     */
    public static function activateUserAfterPurchase($userId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                UPDATE users 
                SET is_verified = 1, is_active = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            logError("Usuario activado tras compra - ID: $userId", 'users.log');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            logError("Error activando usuario: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Generar contraseña temporal segura
     */
    private static function generateTemporaryPassword($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Enviar email de bienvenida con contraseña temporal
     */
    private static function sendWelcomeEmailWithPassword($user, $tempPassword, $orderData) {
        try {
            $siteName = Settings::get('site_name', 'MiSistema');
            $subject = "Bienvenido a $siteName - Tu cuenta ha sido creada";
            
            $loginUrl = SITE_URL . '/pages/login.php';
            $dashboardUrl = SITE_URL . '/pages/dashboard.php';
            $changePasswordUrl = SITE_URL . '/pages/profile.php';
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h2 style='margin: 0; font-size: 28px;'>🎉 ¡Bienvenido a $siteName!</h2>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Tu cuenta ha sido creada automáticamente</p>
                </div>
                
                <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <h3 style='color: #333; margin-bottom: 20px;'>Hola {$user['first_name']},</h3>
                    
                    <div style='background: #e8f5e8; border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin: 20px 0;'>
                        <h4 style='color: #28a745; margin-top: 0;'>✅ Tu compra fue exitosa</h4>
                        <p style='margin: 0; color: #333;'>
                            <strong>Orden:</strong> #{$orderData['order_number']}<br>
                            <strong>Total:</strong> " . formatPrice($orderData['total_amount']) . "
                        </p>
                    </div>
                    
                    <p style='color: #666; line-height: 1.6;'>
                        Hemos creado automáticamente una cuenta para ti para que puedas:
                    </p>
                    
                    <ul style='color: #666; line-height: 1.8; padding-left: 20px;'>
                        <li>✅ Acceder a tus descargas en cualquier momento</li>
                        <li>✅ Re-descargar productos cuando necesites</li>
                        <li>✅ Recibir actualizaciones automáticamente</li>
                        <li>✅ Ver el historial de tus compras</li>
                        <li>✅ Gestionar tus licencias de productos</li>
                    </ul>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #007bff;'>
                        <h4 style='color: #007bff; margin-top: 0;'>🔑 Datos de Acceso</h4>
                        <p style='margin: 5px 0; font-family: monospace; background: white; padding: 10px; border-radius: 4px;'>
                            <strong>Email:</strong> {$user['email']}<br>
                            <strong>Contraseña temporal:</strong> <span style='color: #dc3545; font-weight: bold;'>$tempPassword</span>
                        </p>
                        <p style='margin: 10px 0 0 0; font-size: 14px; color: #856404;'>
                            ⚠️ <strong>Importante:</strong> Cambia tu contraseña después del primer inicio de sesión
                        </p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$loginUrl' style='
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 15px 30px;
                            text-decoration: none;
                            border-radius: 25px;
                            font-weight: bold;
                            display: inline-block;
                            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                        '>🚀 Acceder a Mi Cuenta</a>
                    </div>
                    
                    <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                        <h5 style='color: #856404; margin-top: 0;'>📱 Próximos Pasos</h5>
                        <ol style='color: #856404; margin: 0; padding-left: 20px;'>
                            <li>Inicia sesión con los datos de arriba</li>
                            <li>Ve a tu dashboard personal</li>
                            <li>Cambia tu contraseña temporal</li>
                            <li>Descarga tus productos</li>
                        </ol>
                    </div>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    
                    <div style='text-align: center; color: #999; font-size: 12px;'>
                        <p>Este email fue enviado desde $siteName</p>
                        <p>Si no solicitaste esta cuenta, contacta nuestro soporte</p>
                    </div>
                </div>
            </div>
            ";
            
            return EmailSystem::sendEmail($user['email'], $subject, $body, true);
            
        } catch (Exception $e) {
            logError("Error enviando email de bienvenida: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Asociar compras de invitado a usuario registrado
     */
    public static function mergeGuestPurchases($email, $userId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Buscar órdenes de invitado con el mismo email
            $stmt = $db->prepare("
                UPDATE orders 
                SET user_id = ? 
                WHERE customer_email = ? AND user_id IS NULL
            ");
            $stmt->execute([$userId, $email]);
            
            $mergedOrders = $db->rowCount();
            
            if ($mergedOrders > 0) {
                logError("Órdenes de invitado fusionadas - Usuario: $userId - Email: $email - Órdenes: $mergedOrders", 'users.log');
                
                // Generar licencias para las órdenes fusionadas
                $stmt = $db->prepare("
                    SELECT id FROM orders 
                    WHERE user_id = ? AND payment_status = 'completed'
                ");
                $stmt->execute([$userId]);
                $orders = $stmt->fetchAll();
                
                foreach ($orders as $order) {
                    LicenseManager::generateLicensesFromOrder($order['id']);
                }
            }
            
            return [
                'success' => true,
                'merged_orders' => $mergedOrders
            ];
            
        } catch (Exception $e) {
            logError("Error fusionando compras de invitado: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de usuarios (para admin)
     */
    public static function getUserStats() {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Total usuarios
            $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
            $totalUsers = $stmt->fetch()['total'];
            
            // Usuarios registrados hoy
            $stmt = $db->query("
                SELECT COUNT(*) as today_count 
                FROM users 
                WHERE DATE(created_at) = CURDATE() AND is_active = 1
            ");
            $usersToday = $stmt->fetch()['today_count'];
            
            // Usuarios con compras
            $stmt = $db->query("
                SELECT COUNT(DISTINCT user_id) as buyers_count
                FROM orders 
                WHERE user_id IS NOT NULL AND payment_status = 'completed'
            ");
            $usersWithPurchases = $stmt->fetch()['buyers_count'];
            
            // Top compradores
            $stmt = $db->query("
                SELECT u.first_name, u.last_name, u.email, 
                       COUNT(o.id) as order_count,
                       SUM(o.total_amount) as total_spent
                FROM users u
                JOIN orders o ON u.id = o.user_id
                WHERE o.payment_status = 'completed'
                GROUP BY u.id
                ORDER BY total_spent DESC
                LIMIT 10
            ");
            $topBuyers = $stmt->fetchAll();
            
            // Usuarios por país
            $stmt = $db->query("
                SELECT country, COUNT(*) as user_count
                FROM users 
                WHERE is_active = 1 AND country IS NOT NULL AND country != ''
                GROUP BY country
                ORDER BY user_count DESC
                LIMIT 10
            ");
            $usersByCountry = $stmt->fetchAll();
            
            return [
                'success' => true,
                'stats' => [
                    'total_users' => $totalUsers,
                    'users_today' => $usersToday,
                    'users_with_purchases' => $usersWithPurchases,
                    'conversion_rate' => $totalUsers > 0 ? round(($usersWithPurchases / $totalUsers) * 100, 2) : 0,
                    'top_buyers' => $topBuyers,
                    'users_by_country' => $usersByCountry
                ]
            ];
            
        } catch (Exception $e) {
            logError("Error obteniendo estadísticas de usuarios: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar recordatorio para cambiar contraseña temporal
     */
    public static function sendPasswordChangeReminder($userId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }
            
            $siteName = Settings::get('site_name', 'MiSistema');
            $subject = "Recordatorio: Cambia tu contraseña temporal - $siteName";
            $changePasswordUrl = SITE_URL . '/pages/profile.php';
            
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #ffc107; color: #856404; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h2 style='margin: 0;'>🔐 Recordatorio de Seguridad</h2>
                </div>
                
                <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <h3 style='color: #333;'>Hola {$user['first_name']},</h3>
                    
                    <p style='color: #666; line-height: 1.6;'>
                        Notamos que aún estás usando la contraseña temporal que te enviamos cuando se creó tu cuenta.
                    </p>
                    
                    <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                        <h4 style='color: #856404; margin-top: 0;'>⚠️ Importante para tu Seguridad</h4>
                        <p style='color: #856404; margin: 0;'>
                            Te recomendamos cambiar tu contraseña temporal por una personalizada para proteger tu cuenta y productos.
                        </p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$changePasswordUrl' style='
                            background: #ffc107;
                            color: #856404;
                            padding: 15px 30px;
                            text-decoration: none;
                            border-radius: 25px;
                            font-weight: bold;
                            display: inline-block;
                        '>🔒 Cambiar Contraseña</a>
                    </div>
                    
                    <p style='color: #666; font-size: 14px; text-align: center;'>
                        Este recordatorio se envía automáticamente por tu seguridad
                    </p>
                </div>
            </div>
            ";
            
            return EmailSystem::sendEmail($user['email'], $subject, $body, true);
            
        } catch (Exception $e) {
            logError("Error enviando recordatorio de contraseña: " . $e->getMessage());
            return false;
        }
    }
}

// Funciones helper para mantener compatibilidad
function createUserFromPurchase($orderData, $customerData) {
    return UserManager::createUserFromOrder($orderData, $customerData);
}

function activateUserAfterPurchase($userId) {
    return UserManager::activateUserAfterPurchase($userId);
}

function mergeGuestPurchases($email, $userId) {
    return UserManager::mergeGuestPurchases($email, $userId);
}