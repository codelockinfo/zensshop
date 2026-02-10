<?php
require_once __DIR__ . '/Database.php';

class CustomerAuth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->checkRememberMe();
    }
    
    public function register($name, $email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Generate unique 10-digit customer ID
        $customCustomerId = mt_rand(1000000000, 9999999999);
        // Determine Store ID (Priority: current domain/constant)
        if (function_exists('getCurrentStoreId')) {
            $storeId = getCurrentStoreId();
        } else {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        try {
            $insertedId = $this->db->insert(
                "INSERT INTO customers (customer_id, name, email, password, store_id) VALUES (?, ?, ?, ?, ?)",
                [$customCustomerId, $name, $email, $hashedPassword, $storeId]
            );
            
            $customerId = $customCustomerId; // Use the 10-digit ID as the reference
            
            // Create notification for admin
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification();
            $notification->notifyNewCustomer($name, $customerId);
            
            // Send Welcome Email
            try {
                require_once __DIR__ . '/Email.php';
                $emailService = new Email();
                $emailService->sendWelcomeEmail($email, $name);
            } catch (Exception $e) {
                error_log("Failed to send welcome email: " . $e->getMessage());
            }
            
            return $customerId;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("Email already exists");
            }
            throw $e;
        }
    }
    
    public function login($email, $password) {
        $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
        
        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE email = ? AND (store_id = ? OR store_id IS NULL)", 
            [$email, $storeId]
        );
        if (!$customer || !password_verify($password, $customer['password'])) {
            throw new Exception("Invalid email or password");
        }
        
        $this->setCustomerSession($customer);
        // Always enable persistent login (auto-login on return)
        $this->setRememberMe($customer['customer_id'], $customer['store_id'] ?? null);
        
        return $customer;
    }
    
    public function loginWithGoogle($googleId, $email, $name, $avatar = null) {
        $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
        
        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE (email = ? OR google_id = ?) AND (store_id = ? OR store_id IS NULL)", 
            [$email, $googleId, $storeId]
        );
        
        if ($customer) {
            // Update Google ID and Avatar if missing
            $updates = [];
            $params = [];
            
            if (!$customer['google_id']) {
                $updates[] = "google_id = ?";
                $params[] = $googleId;
            }
            
            if (!$customer['avatar'] && $avatar) {
                $updates[] = "avatar = ?";
                $params[] = $avatar;
            }
            
            if (!empty($updates)) {
                $params[] = $customer['id'];
                $this->db->execute("UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?", $params);
                // Refresh customer data
                $customer = $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customer['id']]);
            }
        } else {
            // Generate unique 10-digit customer ID
            $customCustomerId = mt_rand(1000000000, 9999999999);

            // Determine Store ID
            if (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }

            $this->db->insert(
                "INSERT INTO customers (customer_id, name, email, google_id, avatar, store_id, auth_provider) VALUES (?, ?, ?, ?, ?, ?, 'google')",
                [$customCustomerId, $name, $email, $googleId, $avatar, $storeId]
            );
            $customer = $this->db->fetchOne("SELECT * FROM customers WHERE customer_id = ?", [$customCustomerId]);
            
            // Create notification for admin (new Google customer)
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification();
            $notification->notifyNewCustomer($name, $customCustomerId);
            
            // Send Welcome Email
            try {
                require_once __DIR__ . '/Email.php';
                $emailService = new Email();
                $emailService->sendWelcomeEmail($email, $name);
            } catch (Exception $e) {
                error_log("Failed to send welcome email: " . $e->getMessage());
            }
        }
        
        $this->setCustomerSession($customer);
        $this->setRememberMe($customer['customer_id'], $customer['store_id'] ?? null);
        return $customer;
    }
    
    private function setCustomerSession($customer) {
        $_SESSION['customer_id'] = $customer['customer_id'];
        $_SESSION['customer_name'] = $customer['name'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_avatar'] = $customer['avatar'] ?? null;
        $_SESSION['store_id'] = $customer['store_id'] ?? null;
        $_SESSION['customer_logged_in'] = true;

        // Sync Cart and Wishlist from cookies
        try {
            require_once __DIR__ . '/Cart.php';
            $cart = new Cart();
            // syncCartAfterLogin will merge cookie items into DB and clear cookie
            $cart->syncCartAfterLogin($customer['customer_id']);

            require_once __DIR__ . '/Wishlist.php';
            $wishlist = new Wishlist();
            // syncWishlistAfterLogin will merge cookie items into DB and clear cookie
            $wishlist->syncWishlistAfterLogin($customer['customer_id']);
        } catch (Exception $e) {
            error_log("Error syncing cart/wishlist after login: " . $e->getMessage());
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
    }
    
    public function logout() {
        $this->deleteRememberMe();
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_name']);
        unset($_SESSION['customer_email']);
        unset($_SESSION['customer_logged_in']);
    }
    
    public function updateProfile($data) {
        if (!$this->isLoggedIn()) return false;
        
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        
        if (empty($fields)) return false;
        
        $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
        $params[] = $_SESSION['customer_id'];
        $params[] = $storeId;
        
        $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE customer_id = ? AND (store_id = ? OR store_id IS NULL)";
        return $this->db->execute($sql, $params);
    }

    private function setRememberMe($customerId, $storeId = null) {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $token = hash('sha256', $validator);
            $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60); // 30 days
            
            // Get store_id if not provided
            if (!$storeId) {
                $customer = $this->db->fetchOne("SELECT store_id FROM customers WHERE customer_id = ?", [$customerId]);
                $storeId = $customer['store_id'] ?? null;
            }

            $this->db->execute(
                "INSERT INTO customer_sessions (customer_id, selector, token, expires_at, store_id) VALUES (?, ?, ?, ?, ?)",
                [$customerId, $selector, $token, $expires, $storeId]
            );
            
            setcookie('remember_me', $selector . ':' . $validator, time() + 30 * 24 * 60 * 60, '/', '', false, true);
        } catch (Exception $e) {
            // Ignore generic errors to prevent login block
            error_log("Remember Me Error: " . $e->getMessage());
        }
    }

    private function checkRememberMe() {
        if ($this->isLoggedIn()) return;
        if (!isset($_COOKIE['remember_me'])) return;
        
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) !== 2) return;
        
        list($selector, $validator) = $parts;
        
        try {
            $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : null;
            $sql = "SELECT * FROM customer_sessions WHERE selector = ? AND expires_at > NOW()";
            $params = [$selector];
            
            if ($storeId) {
                $sql .= " AND (store_id = ? OR store_id IS NULL)";
                $params[] = $storeId;
            }

            $session = $this->db->fetchOne($sql, $params);
            
            if ($session && hash_equals($session['token'], hash('sha256', $validator))) {
                $customer = $this->db->fetchOne("SELECT * FROM customers WHERE customer_id = ?", [$session['customer_id']]);
                if ($customer) {
                    $this->setCustomerSession($customer);
                }
            }
        } catch (Exception $e) {
            // Silently fail auto-login
        }
    }

    private function deleteRememberMe() {
        if (isset($_COOKIE['remember_me'])) {
            $parts = explode(':', $_COOKIE['remember_me']);
            if (count($parts) === 2) {
                try {
                    $this->db->execute("DELETE FROM customer_sessions WHERE selector = ?", [$parts[0]]);
                } catch (Exception $e) {}
            }
            setcookie('remember_me', '', time() - 3600, '/');
            unset($_COOKIE['remember_me']);
        }
    }

    public function getCurrentCustomer() {
        if (!$this->isLoggedIn()) return null;
        $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
        
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE customer_id = ? AND (store_id = ? OR store_id IS NULL)", 
            [$_SESSION['customer_id'], $storeId]
        );
    }
}
