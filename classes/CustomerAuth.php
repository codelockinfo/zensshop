<?php
require_once __DIR__ . '/Database.php';

class CustomerAuth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function register($name, $email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            return $this->db->insert(
                "INSERT INTO customers (name, email, password) VALUES (?, ?, ?)",
                [$name, $email, $hashedPassword]
            );
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
        }
        
        $this->setCustomerSession($customer);
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

    public function getCurrentCustomer() {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetchOne("SELECT * FROM customers WHERE id = ?", [$_SESSION['customer_id']]);
    }
}
