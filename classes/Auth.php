<?php
/**
 * Authentication Class
 * Handles user authentication, registration, and password reset
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Email.php';
require_once __DIR__ . '/../config/constants.php';

class Auth {
    private $db;
    private $email;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->email = new Email();
        
        // Start session if not already started and headers not sent
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Register new admin user
     */
    public function register($name, $email, $password) {
        // Validate input
        if (empty($name) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        
        // Check if email already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );
        
        if ($existing) {
            throw new Exception("Email already registered");
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate unique Store ID
        $storeId = 'STORE-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Insert user
        $userId = $this->db->insert(
            "INSERT INTO users (name, email, password, role, status, store_id) VALUES (?, ?, ?, 'admin', 'active', ?)",
            [$name, $email, $hashedPassword, $storeId]
        );
        
        return $userId;
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            throw new Exception("Email and password are required");
        }
        
        // Get user
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if (!$user) {
            throw new Exception("Invalid email or password");
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Invalid email or password");
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['store_id'] = $user['store_id'] ?? null;
        $_SESSION['logged_in'] = true;
        
        return $user;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user is an admin
     */
    public function isAdmin() {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        // Fetch from database to get latest data including profile_image
        $user = $this->db->fetchOne(
            "SELECT id, name, email, role, profile_image, store_id FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if ($user) {
            // Fix old /oecom/ paths in profile_image
            $profileImage = $user['profile_image'] ?? null;
            if (!empty($profileImage) && strpos($profileImage, '/oecom/') !== false) {
                require_once __DIR__ . '/../includes/functions.php';
                $normalizedImage = normalizeImageUrl($profileImage);
                
                // Update database with corrected path
                $this->db->execute(
                    "UPDATE users SET profile_image = ? WHERE id = ?",
                    [$normalizedImage, $user['id']]
                );
                
                $user['profile_image'] = $normalizedImage;
            }
            
            return $user;
        }
        
        // Fallback to session data if database fetch fails
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'store_id' => $_SESSION['store_id'] ?? null,
            'profile_image' => null
        ];
    }
    
    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            require_once __DIR__ . '/../includes/functions.php';
            $baseUrl = getBaseUrl();
            header('Location: ' . $baseUrl . '/admin/index.php');
            exit;
        }
    }
    
    /**
     * Generate OTP for password reset
     */
    public function generateOTP($email) {
        if (empty($email)) {
            throw new Exception("Email is required");
        }
        
        // Check if user exists
        $user = $this->db->fetchOne(
            "SELECT id, email FROM users WHERE email = ?",
            [$email]
        );
        
        if (!$user) {
            // Don't reveal if email exists for security
            return true;
        }
        
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
        
        // Insert new OTP with DB-calculated expiry to fix timezone offset issues
        $this->db->insert(
            "INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [$email, $otp, OTP_EXPIRY_MINUTES]
        );
        
        // Send OTP email
        $this->email->sendOTP($email, $otp);
        
        return true;
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($email, $otp) {
        if (empty($email) || empty($otp)) {
            throw new Exception("Email and OTP are required");
        }
        
        // Get valid OTP
        $reset = $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE email = ? AND otp = ? AND expires_at > NOW() AND used = 0",
            [$email, $otp]
        );
        
        if (!$reset) {
            throw new Exception("Invalid or expired OTP");
        }
        
        // Mark OTP as used
        $this->db->execute(
            "UPDATE password_resets SET used = 1 WHERE id = ?",
            [$reset['id']]
        );
        
        return true;
    }
    
    /**
     * Reset password
     */
    public function resetPassword($email, $newPassword) {
        if (empty($email) || empty($newPassword)) {
            throw new Exception("Email and password are required");
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception("Password must be at least 6 characters");
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $this->db->execute(
            "UPDATE users SET password = ? WHERE email = ?",
            [$hashedPassword, $email]
        );
        
        return true;
    }
}

