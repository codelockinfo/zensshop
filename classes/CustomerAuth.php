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
        try {
            $customerId = $this->db->insert(
                "INSERT INTO customers (name, email, password) VALUES (?, ?, ?)",
                [$name, $email, $hashedPassword]
            );
            
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
        $customer = $this->db->fetchOne("SELECT * FROM customers WHERE email = ?", [$email]);
        if (!$customer || !password_verify($password, $customer['password'])) {
            throw new Exception("Invalid email or password");
        }
        
        $this->setCustomerSession($customer);
        // Always enable persistent login (auto-login on return)
        $this->setRememberMe($customer['id']);
        
        return $customer;
    }
    
    public function loginWithGoogle($googleId, $email, $name) {
        $customer = $this->db->fetchOne("SELECT * FROM customers WHERE email = ? OR google_id = ?", [$email, $googleId]);
        
        if ($customer) {
            if (!$customer['google_id']) {
                $this->db->execute("UPDATE customers SET google_id = ? WHERE id = ?", [$googleId, $customer['id']]);
            }
        } else {
            $id = $this->db->insert(
                "INSERT INTO customers (name, email, google_id) VALUES (?, ?, ?)",
                [$name, $email, $googleId]
            );
            $customer = $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$id]);
            
            // Create notification for admin (new Google customer)
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification();
            $notification->notifyNewCustomer($name, $id);
            
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
        $this->setRememberMe($customer['id']);
        return $customer;
    }
    
    private function setCustomerSession($customer) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_name'] = $customer['name'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_logged_in'] = true;
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
        
        $params[] = $_SESSION['customer_id'];
        $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE id = ?";
        return $this->db->execute($sql, $params);
    }

    private function setRememberMe($customerId) {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $token = hash('sha256', $validator);
            $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60); // 30 days
            
            $this->db->execute(
                "INSERT INTO customer_sessions (customer_id, selector, token, expires_at) VALUES (?, ?, ?, ?)",
                [$customerId, $selector, $token, $expires]
            );
            
            setcookie('remember_me', $selector . ':' . $validator, time() + 30 * 24 * 60 * 60, '/', '', false, true);
        } catch (Exception $e) {
            // Ignore generic errors to prevent login block
        }
    }

    private function checkRememberMe() {
        if ($this->isLoggedIn()) return;
        if (!isset($_COOKIE['remember_me'])) return;
        
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) !== 2) return;
        
        list($selector, $validator) = $parts;
        
        try {
            $session = $this->db->fetchOne(
                "SELECT * FROM customer_sessions WHERE selector = ? AND expires_at > NOW()", 
                [$selector]
            );
            
            if ($session && hash_equals($session['token'], hash('sha256', $validator))) {
                $customer = $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$session['customer_id']]);
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
        return $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$_SESSION['customer_id']]);
    }
}
