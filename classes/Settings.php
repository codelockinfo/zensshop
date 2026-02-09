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
    public function get($key, $default = null, $storeId = null) {
        if (!$storeId) {
            if (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                // Fallback session logic
                $storeId = $_SESSION['store_id'] ?? null;
                if (!$storeId && isset($_SESSION['user_email'])) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                    $storeId = $storeUser['store_id'] ?? null;
                }
            }
        }
        
        // Ensure clean store_id (remove legacy STORE- prefix if present)
        if ($storeId) {
            $storeId = str_replace('STORE-', '', $storeId);
        }
        
        // Use a store-specific cache key
        $cacheKey = ($storeId ?: 'global') . ':' . $key;
        
        // Check cache first
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // Try primary settings table
        $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
        $params = [$key];
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        } else {
            $sql .= " AND (store_id IS NULL OR store_id = '')";
        }
        $result = $this->db->fetchOne($sql, $params);
        
        // Fallback to site_settings table (for logo, appearance, etc.)
        if (!$result) {
            try {
                $sql = "SELECT setting_value FROM site_settings WHERE setting_key = ?";
                $params = [$key];
                if ($storeId) {
                    $sql .= " AND store_id = ?";
                    $params[] = $storeId;
                } else {
                    $sql .= " AND (store_id IS NULL OR store_id = '')";
                }
                $result = $this->db->fetchOne($sql, $params);
            } catch (Exception $e) {
                // Ignore if table doesn't exist
            }
        }
        
        $value = $result ? $result['setting_value'] : $default;
        self::$cache[$cacheKey] = $value;
        
        return $value;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value, $group = 'general') {
        try {
            // Determine Store ID
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId && isset($_SESSION['user_email'])) {
                 $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                 $storeId = $storeUser['store_id'] ?? null;
            }
            
            // Ensure clean store_id (remove legacy STORE- prefix if present)
            if ($storeId) {
                $storeId = str_replace('STORE-', '', $storeId);
            }

            $this->db->insert(
                "INSERT INTO settings (setting_key, setting_value, setting_group, store_id) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
                [$key, $value, $group, $storeId, $value]
            );
            
            // Update cache
            $cacheKey = ($storeId ?: 'global') . ':' . $key;
            self::$cache[$cacheKey] = $value;
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to set setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all settings by group
     */
    public function getByGroup($group, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId) {
                if (isset($_SESSION['user_email'])) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                    $storeId = $storeUser['store_id'] ?? null;
                }
            }
        }
        
        // Ensure clean store_id (remove legacy STORE- prefix if present)
        if ($storeId) {
            $storeId = str_replace('STORE-', '', $storeId);
        }

        $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_group = ?";
        $params = [$group];
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        } else {
            $sql .= " AND (store_id IS NULL OR store_id = '')";
        }
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get all settings
     */
    public function getAll($storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId) {
                if (isset($_SESSION['user_email'])) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                    $storeId = $storeUser['store_id'] ?? null;
                }
            }
        }

        // Ensure clean store_id (remove legacy STORE- prefix if present)
        if ($storeId) {
            $storeId = str_replace('STORE-', '', $storeId);
        }

        $sql = "SELECT * FROM settings WHERE 1=1";
        $params = [];
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        } else {
            $sql .= " AND (store_id IS NULL OR store_id = '')";
        }
        $sql .= " ORDER BY setting_group, setting_key";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Delete a setting
     */
    public function delete($key, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        $sql = "DELETE FROM settings WHERE setting_key = ?";
        $params = [$key];
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        } else {
            $sql .= " AND (store_id IS NULL OR store_id = '')";
        }

        $this->db->execute($sql, $params);
        $cacheKey = ($storeId ?: 'global') . ':' . $key;
        unset(self::$cache[$cacheKey]);
    }
    
    /**
     * Helper method to get email settings as constants
     */
    public static function loadEmailConfig($storeId = null) {
        $settings = new self();
        
        // Define SITE_NAME first so it can be used as default
        if (!defined('SITE_NAME')) {
            define('SITE_NAME', $settings->get('site_name', 'Zens Shop', $storeId));
        }
        
        // Only define if not already defined
        if (!defined('SMTP_HOST')) {
            define('SMTP_HOST', $settings->get('smtp_host', 'smtp.gmail.com', $storeId));
        }
        if (!defined('SMTP_PORT')) {
            define('SMTP_PORT', $settings->get('smtp_port', 587, $storeId));
        }
        if (!defined('SMTP_ENCRYPTION')) {
            define('SMTP_ENCRYPTION', $settings->get('smtp_encryption', 'tls', $storeId));
        }
        
        // Define Site Logo
        if (!defined('SITE_LOGO')) {
            define('SITE_LOGO', $settings->get('site_logo', 'logo.png', $storeId));
        }
        if (!defined('SITE_LOGO_TYPE')) {
            define('SITE_LOGO_TYPE', $settings->get('site_logo_type', 'image', $storeId));
        }
        if (!defined('SITE_LOGO_TEXT')) {
            define('SITE_LOGO_TEXT', $settings->get('site_logo_text', SITE_NAME, $storeId));
        }
        if (!defined('SMTP_USERNAME')) {
            define('SMTP_USERNAME', $settings->get('smtp_username', '', $storeId));
        }
        if (!defined('SMTP_PASSWORD')) {
            define('SMTP_PASSWORD', $settings->get('smtp_password', '', $storeId));
        }
        if (!defined('SMTP_FROM_EMAIL')) {
            define('SMTP_FROM_EMAIL', $settings->get('smtp_from_email', '', $storeId));
        }
        if (!defined('SMTP_FROM_NAME')) {
            define('SMTP_FROM_NAME', $settings->get('smtp_from_name', 'Zens Shop', $storeId));
        }
        
        // Don't override OTP_EXPIRY_MINUTES if already defined in constants.php
        if (!defined('OTP_EXPIRY_MINUTES')) {
            define('OTP_EXPIRY_MINUTES', $settings->get('otp_expiry_minutes', 5, $storeId));
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
