<?php
/**
 * Settings Management Class
 */

require_once __DIR__ . '/Database.php';

class Settings {
    private $db;
    private static $cache = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Try primary settings table
        $result = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        
        // Fallback to site_settings table (for logo, appearance, etc.)
        if (!$result) {
            try {
                $result = $this->db->fetchOne(
                    "SELECT setting_value FROM site_settings WHERE setting_key = ?",
                    [$key]
                );
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
        }
        
        $value = $result ? $result['setting_value'] : $default;
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value, $group = 'general') {
        try {
            $this->db->insert(
                "INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
                [$key, $value, $group, $value]
            );
            
            // Update cache
            self::$cache[$key] = $value;
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to set setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all settings by group
     */
    public function getByGroup($group) {
        return $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_group = ?",
            [$group]
        );
    }
    
    /**
     * Get all settings
     */
    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM settings ORDER BY setting_group, setting_key");
    }
    
    /**
     * Delete a setting
     */
    public function delete($key) {
        $this->db->execute("DELETE FROM settings WHERE setting_key = ?", [$key]);
        unset(self::$cache[$key]);
    }
    
    /**
     * Helper method to get email settings as constants
     */
    public static function loadEmailConfig() {
        $settings = new self();
        
        // Define SITE_NAME first so it can be used as default
        if (!defined('SITE_NAME')) {
            define('SITE_NAME', $settings->get('site_name', 'Zens Shop'));
        }
        
        // Only define if not already defined
        if (!defined('SMTP_HOST')) {
            define('SMTP_HOST', $settings->get('smtp_host', 'smtp.gmail.com'));
        }
        if (!defined('SMTP_PORT')) {
            define('SMTP_PORT', $settings->get('smtp_port', 587));
        }
        if (!defined('SMTP_ENCRYPTION')) {
            define('SMTP_ENCRYPTION', $settings->get('smtp_encryption', 'tls'));
        }
        
        // Define Site Logo
        if (!defined('SITE_LOGO')) {
            define('SITE_LOGO', $settings->get('site_logo', 'logo.png'));
        }
        if (!defined('SITE_LOGO_TYPE')) {
            define('SITE_LOGO_TYPE', $settings->get('site_logo_type', 'image'));
        }
        if (!defined('SITE_LOGO_TEXT')) {
            define('SITE_LOGO_TEXT', $settings->get('site_logo_text', SITE_NAME));
        }
        if (!defined('SMTP_USERNAME')) {
            define('SMTP_USERNAME', $settings->get('smtp_username', ''));
        }
        if (!defined('SMTP_PASSWORD')) {
            define('SMTP_PASSWORD', $settings->get('smtp_password', ''));
        }
        if (!defined('SMTP_FROM_EMAIL')) {
            define('SMTP_FROM_EMAIL', $settings->get('smtp_from_email', ''));
        }
        if (!defined('SMTP_FROM_NAME')) {
            define('SMTP_FROM_NAME', $settings->get('smtp_from_name', 'Zens Shop'));
        }
        
        // Don't override OTP_EXPIRY_MINUTES if already defined in constants.php
        if (!defined('OTP_EXPIRY_MINUTES')) {
            define('OTP_EXPIRY_MINUTES', $settings->get('otp_expiry_minutes', 10));
        }
    }
    
    /**
     * Helper method to get API settings as constants
     */
    public static function loadApiConfig() {
        $settings = new self();
        
        // Razorpay Configuration
        if (!defined('RAZORPAY_KEY_ID')) {
            define('RAZORPAY_KEY_ID', $settings->get('razorpay_key_id', ''));
        }
        if (!defined('RAZORPAY_KEY_SECRET')) {
            define('RAZORPAY_KEY_SECRET', $settings->get('razorpay_key_secret', ''));
        }
        if (!defined('RAZORPAY_MODE')) {
            define('RAZORPAY_MODE', $settings->get('razorpay_mode', 'test'));
        }
        
        // Google Auth Configuration
        if (!defined('GOOGLE_CLIENT_ID')) {
            define('GOOGLE_CLIENT_ID', $settings->get('google_client_id', ''));
        }
    }
}
