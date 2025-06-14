<?php
// config/license_manager.php - Sistema de gestión de licencias de usuario

/**
 * Clase para manejar licencias de usuario automáticamente
 */
class LicenseManager {
    
    /**
     * Generar licencias para una orden completada
     */
    public static function generateLicensesFromOrder($orderId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener orden
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND payment_status = 'completed'");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Orden no encontrada o no completada');
            }
            
            // Obtener items de la orden
            $stmt = $db->prepare("
                SELECT oi.*, p.download_limit, p.update_months, p.name as product_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $orderItems = $stmt->fetchAll();
            
            $generatedLicenses = [];
            
            foreach ($orderItems as $item) {
                // Solo generar licencias si hay usuario
                if ($order['user_id']) {
                    $license = self::createUserLicense(
                        $order['user_id'],
                        $item['product_id'],
                        $orderId,
                        $item['download_limit'],
                        $item['update_months']
                    );
                    
                    if ($license['success']) {
                        $generatedLicenses[] = $license['license'];
                    }
                } else {
                    // Para usuarios invitados, crear licencia temporal
                    $generatedLicenses[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'type' => 'guest',
                        'downloads_limit' => $item['download_limit'],
                        'updates_until' => date('Y-m-d H:i:s', strtotime("+{$item['update_months']} months")),
                        'download_url' => SITE_URL . '/download/' . $item['product_id'] . '?order=' . $order['order_number']
                    ];
                }
            }
            
            // Log de licencias generadas
            logError("Licencias generadas para orden {$order['order_number']}: " . count($generatedLicenses), 'licenses.log');
            
            return [
                'success' => true,
                'licenses' => $generatedLicenses,
                'order' => $order
            ];
            
        } catch (Exception $e) {
            logError("Error generando licencias: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crear licencia de usuario
     */
    public static function createUserLicense($userId, $productId, $orderId, $downloadLimit = null, $updateMonths = null) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener configuraciones por defecto si no se especifican
            if ($downloadLimit === null) {
                $downloadLimit = intval(Settings::get('default_download_limit', DEFAULT_DOWNLOAD_LIMIT));
            }
            if ($updateMonths === null) {
                $updateMonths = intval(Settings::get('default_update_months', DEFAULT_UPDATE_MONTHS));
            }
            
            // Calcular fecha de expiración de actualizaciones
            $updatesUntil = date('Y-m-d H:i:s', strtotime("+{$updateMonths} months"));
            
            // Verificar si ya existe licencia para este usuario/producto
            $stmt = $db->prepare("
                SELECT * FROM user_licenses 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$userId, $productId]);
            $existingLicense = $stmt->fetch();
            
            if ($existingLicense) {
                // Actualizar licencia existente
                $stmt = $db->prepare("
                    UPDATE user_licenses 
                    SET downloads_limit = downloads_limit + ?,
                        updates_until = GREATEST(updates_until, ?),
                        is_active = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$downloadLimit, $updatesUntil, $existingLicense['id']]);
                
                $licenseId = $existingLicense['id'];
                $action = 'updated';
            } else {
                // Crear nueva licencia
                $stmt = $db->prepare("
                    INSERT INTO user_licenses (
                        user_id, product_id, order_id, downloads_used, downloads_limit,
                        updates_until, is_active, created_at
                    ) VALUES (?, ?, ?, 0, ?, ?, 1, NOW())
                ");
                $stmt->execute([$userId, $productId, $orderId, $downloadLimit, $updatesUntil]);
                
                $licenseId = $db->lastInsertId();
                $action = 'created';
            }
            
            // Obtener datos completos de la licencia
            $stmt = $db->prepare("
                SELECT ul.*, p.name as product_name, p.slug as product_slug
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                WHERE ul.id = ?
            ");
            $stmt->execute([$licenseId]);
            $license = $stmt->fetch();
            
            // Log de licencia creada/actualizada
            logError("Licencia $action para usuario $userId - Producto: {$license['product_name']} - ID: $licenseId", 'licenses.log');
            
            return [
                'success' => true,
                'license' => $license,
                'action' => $action
            ];
            
        } catch (Exception $e) {
            logError("Error creando licencia de usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar licencia de usuario para descarga
     */
    public static function verifyLicense($userId, $productId) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT ul.*, p.name as product_name
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                WHERE ul.user_id = ? AND ul.product_id = ? AND ul.is_active = 1
            ");
            $stmt->execute([$userId, $productId]);
            $license = $stmt->fetch();
            
            if (!$license) {
                return [
                    'valid' => false,
                    'reason' => 'no_license',
                    'message' => 'No tienes licencia para este producto'
                ];
            }
            
            // Verificar límite de descargas
            if ($license['downloads_used'] >= $license['downloads_limit']) {
                return [
                    'valid' => false,
                    'reason' => 'download_limit_exceeded',
                    'message' => 'Has excedido el límite de descargas para este producto',
                    'license' => $license
                ];
            }
            
            // Verificar si las actualizaciones han expirado (solo para versiones nuevas)
            $now = new DateTime();
            $expirationDate = new DateTime($license['updates_until']);
            $hasExpiredUpdates = $now > $expirationDate;
            
            return [
                'valid' => true,
                'license' => $license,
                'downloads_remaining' => $license['downloads_limit'] - $license['downloads_used'],
                'updates_expired' => $hasExpiredUpdates,
                'updates_until' => $license['updates_until']
            ];
            
        } catch (Exception $e) {
            logError("Error verificando licencia: " . $e->getMessage());
            return [
                'valid' => false,
                'reason' => 'system_error',
                'message' => 'Error del sistema verificando licencia'
            ];
        }
    }
    
    /**
     * Registrar descarga y actualizar contador
     */
    public static function recordDownload($licenseId, $versionId, $ipAddress, $userAgent) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener licencia
            $stmt = $db->prepare("
                SELECT ul.*, p.name as product_name
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                WHERE ul.id = ?
            ");
            $stmt->execute([$licenseId]);
            $license = $stmt->fetch();
            
            if (!$license) {
                throw new Exception('Licencia no encontrada');
            }
            
            // Registrar descarga en logs
            $stmt = $db->prepare("
                INSERT INTO download_logs (
                    user_id, product_id, version_id, license_id, 
                    ip_address, user_agent, download_type, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'licensed', NOW())
            ");
            $stmt->execute([
                $license['user_id'],
                $license['product_id'],
                $versionId,
                $licenseId,
                $ipAddress,
                $userAgent
            ]);
            
            // Actualizar contador de licencia
            $stmt = $db->prepare("
                UPDATE user_licenses 
                SET downloads_used = downloads_used + 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$licenseId]);
            
            // Log de descarga
            logError("Descarga registrada - Usuario: {$license['user_id']} - Producto: {$license['product_name']} - IP: $ipAddress", 'downloads.log');
            
            return [
                'success' => true,
                'downloads_remaining' => $license['downloads_limit'] - ($license['downloads_used'] + 1)
            ];
            
        } catch (Exception $e) {
            logError("Error registrando descarga: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener licencias de un usuario
     */
    public static function getUserLicenses($userId, $activeOnly = true) {
        try {
            $db = Database::getInstance()->getConnection();
            
            $whereClause = $activeOnly ? 'AND ul.is_active = 1' : '';
            
            $stmt = $db->prepare("
                SELECT ul.*, p.name as product_name, p.slug as product_slug, 
                       p.image as product_image, c.name as category_name,
                       o.order_number, o.created_at as purchase_date
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN orders o ON ul.order_id = o.id
                WHERE ul.user_id = ? $whereClause
                ORDER BY ul.created_at DESC
            ");
            $stmt->execute([$userId]);
            $licenses = $stmt->fetchAll();
            
            // Enriquecer datos de licencias
            foreach ($licenses as &$license) {
                $license['downloads_remaining'] = $license['downloads_limit'] - $license['downloads_used'];
                $license['updates_expired'] = strtotime($license['updates_until']) < time();
                $license['download_url'] = SITE_URL . '/download/' . $license['product_id'];
                $license['product_url'] = SITE_URL . '/producto/' . $license['product_slug'];
                
                if ($license['product_image']) {
                    $license['product_image_url'] = UPLOADS_URL . '/products/' . $license['product_image'];
                }
            }
            
            return [
                'success' => true,
                'licenses' => $licenses
            ];
            
        } catch (Exception $e) {
            logError("Error obteniendo licencias de usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extender licencia (para admins)
     */
    public static function extendLicense($licenseId, $additionalDownloads, $additionalMonths) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Obtener licencia actual
            $stmt = $db->prepare("SELECT * FROM user_licenses WHERE id = ?");
            $stmt->execute([$licenseId]);
            $license = $stmt->fetch();
            
            if (!$license) {
                throw new Exception('Licencia no encontrada');
            }
            
            // Calcular nueva fecha de expiración
            $currentExpiration = $license['updates_until'];
            $newExpiration = date('Y-m-d H:i:s', strtotime($currentExpiration . " +{$additionalMonths} months"));
            
            // Actualizar licencia
            $stmt = $db->prepare("
                UPDATE user_licenses 
                SET downloads_limit = downloads_limit + ?,
                    updates_until = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$additionalDownloads, $newExpiration, $licenseId]);
            
            // Log de extensión
            logError("Licencia extendida - ID: $licenseId - Descargas: +$additionalDownloads - Meses: +$additionalMonths", 'licenses.log');
            
            return [
                'success' => true,
                'message' => 'Licencia extendida exitosamente'
            ];
            
        } catch (Exception $e) {
            logError("Error extendiendo licencia: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Revocar licencia (para admins)
     */
    public static function revokeLicense($licenseId, $reason = '') {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                UPDATE user_licenses 
                SET is_active = 0, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$licenseId]);
            
            // Log de revocación
            logError("Licencia revocada - ID: $licenseId - Razón: $reason", 'licenses.log');
            
            return [
                'success' => true,
                'message' => 'Licencia revocada exitosamente'
            ];
            
        } catch (Exception $e) {
            logError("Error revocando licencia: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener estadísticas de licencias (para admin)
     */
    public static function getLicenseStats() {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Total de licencias
            $stmt = $db->query("SELECT COUNT(*) as total FROM user_licenses WHERE is_active = 1");
            $totalLicenses = $stmt->fetch()['total'];
            
            // Licencias por producto
            $stmt = $db->query("
                SELECT p.name, COUNT(*) as license_count
                FROM user_licenses ul
                JOIN products p ON ul.product_id = p.id
                WHERE ul.is_active = 1
                GROUP BY p.id
                ORDER BY license_count DESC
                LIMIT 10
            ");
            $licensesByProduct = $stmt->fetchAll();
            
            // Licencias expiradas
            $stmt = $db->query("
                SELECT COUNT(*) as expired_count
                FROM user_licenses
                WHERE is_active = 1 AND updates_until < NOW()
            ");
            $expiredLicenses = $stmt->fetch()['expired_count'];
            
            // Licencias próximas a expirar (30 días)
            $stmt = $db->query("
                SELECT COUNT(*) as expiring_count
                FROM user_licenses
                WHERE is_active = 1 
                AND updates_until > NOW() 
                AND updates_until < DATE_ADD(NOW(), INTERVAL 30 DAY)
            ");
            $expiringLicenses = $stmt->fetch()['expiring_count'];
            
            // Top usuarios por licencias
            $stmt = $db->query("
                SELECT u.first_name, u.last_name, u.email, COUNT(*) as license_count
                FROM user_licenses ul
                JOIN users u ON ul.user_id = u.id
                WHERE ul.is_active = 1
                GROUP BY u.id
                ORDER BY license_count DESC
                LIMIT 10
            ");
            $topUsers = $stmt->fetchAll();
            
            return [
                'success' => true,
                'stats' => [
                    'total_licenses' => $totalLicenses,
                    'expired_licenses' => $expiredLicenses,
                    'expiring_licenses' => $expiringLicenses,
                    'licenses_by_product' => $licensesByProduct,
                    'top_users' => $topUsers
                ]
            ];
            
        } catch (Exception $e) {
            logError("Error obteniendo estadísticas de licencias: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Funciones helper para mantener compatibilidad
function generateUserLicense($userId, $productId, $orderId, $downloadLimit = null, $updateMonths = null) {
    return LicenseManager::createUserLicense($userId, $productId, $orderId, $downloadLimit, $updateMonths);
}

function verifyUserLicense($userId, $productId) {
    return LicenseManager::verifyLicense($userId, $productId);
}

function getUserLicenses($userId, $activeOnly = true) {
    return LicenseManager::getUserLicenses($userId, $activeOnly);
}