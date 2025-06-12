<?php
// config/settings.php
class Settings {
    private static $settings = null;
    
    public static function get($key, $default = '') {
        if (self::$settings === null) {
            self::loadSettings();
        }
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
    
    public static function set($key, $value) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        if ($stmt->execute([$key, $value])) {
            self::$settings[$key] = $value;
            return true;
        }
        return false;
    }
    
    private static function loadSettings() {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        self::$settings = [];
        
        while ($row = $stmt->fetch()) {
            self::$settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    public static function getPaymentConfig($gateway) {
        switch($gateway) {
            case 'stripe':
                return [
                    'publishable_key' => self::get('stripe_publishable_key'),
                    'secret_key' => self::get('stripe_secret_key')
                ];
            case 'paypal':
                return [
                    'client_id' => self::get('paypal_client_id'),
                    'client_secret' => self::get('paypal_client_secret'),
                    'sandbox' => self::get('paypal_sandbox', 'true') === 'true'
                ];
            case 'mercadopago':
                return [
                    'public_key' => self::get('mercadopago_public_key'),
                    'access_token' => self::get('mercadopago_access_token')
                ];
            default:
                return [];
        }
    }
}
?>