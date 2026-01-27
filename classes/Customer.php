<?php
/**
 * Customer Management Class
 */

require_once __DIR__ . '/Database.php';

class Customer {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Check if customers table exists
     */
    private function tableExists() {
        try {
            $result = $this->db->fetchOne("SHOW TABLES LIKE 'customers'");
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all customers with filters
     */
    public function getAll($filters = []) {
        // Check if table exists, if not return empty array
        if (!$this->tableExists()) {
            return [];
        }
        
        $sql = "SELECT c.*, 
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(o.created_at) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.customer_id = o.user_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY c.customer_id ORDER BY c.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get customers from orders (for non-registered customers)
     */
    public function getCustomersFromOrders() {
        $sql = "SELECT 
                customer_email as email,
                customer_name as name,
                customer_phone as phone,
                MAX(billing_address) as billing_address,
                MAX(shipping_address) as shipping_address,
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                MAX(created_at) as last_order_date,
                MIN(created_at) as first_order_date
                FROM orders
                WHERE user_id IS NULL OR user_id = 0
                GROUP BY customer_email, customer_name, customer_phone
                ORDER BY last_order_date DESC";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get all customers (registered + from orders)
     */
    public function getAllCustomers($filters = []) {
        $registered = $this->getAll($filters);
        $fromOrders = $this->getCustomersFromOrders();
        
        // Combine and format
        $allCustomers = [];
        
        // Add registered customers
        foreach ($registered as $customer) {
            $allCustomers[] = [
                'id' => $customer['customer_id'], // Use 10-digit ID here
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'billing_address' => $customer['billing_address'],
                'shipping_address' => $customer['shipping_address'],
                'status' => $customer['status'],
                'is_registered' => true,
                'total_orders' => (int)$customer['total_orders'],
                'total_spent' => (float)($customer['total_spent'] ?? 0),
                'last_order_date' => $customer['last_order_date'],
                'created_at' => $customer['created_at']
            ];
        }
        
        // Add customers from orders (non-registered)
        foreach ($fromOrders as $customer) {
            $allCustomers[] = [
                'id' => null,
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'billing_address' => $customer['billing_address'],
                'shipping_address' => $customer['shipping_address'],
                'status' => 'active',
                'is_registered' => false,
                'total_orders' => (int)$customer['total_orders'],
                'total_spent' => (float)($customer['total_spent'] ?? 0),
                'last_order_date' => $customer['last_order_date'],
                'created_at' => $customer['first_order_date']
            ];
        }
        
        // Sort by last order date
        usort($allCustomers, function($a, $b) {
            return strtotime($b['last_order_date'] ?? $b['created_at']) - strtotime($a['last_order_date'] ?? $a['created_at']);
        });
        
        return $allCustomers;
    }
    
    /**
     * Get customer by ID
     */
    public function getById($id) {
        if (!$this->tableExists()) {
            return null;
        }
        
        $customer = $this->db->fetchOne(
            "SELECT * FROM customers WHERE customer_id = ?",
            [$id]
        );
        
        if ($customer) {
            // Get order stats
            $stats = $this->db->fetchOne(
                "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_spent,
                MAX(created_at) as last_order_date
                FROM orders WHERE user_id = ?",
                [$customer['customer_id']]
            );
            
            $customer['total_orders'] = (int)($stats['total_orders'] ?? 0);
            $customer['total_spent'] = (float)($stats['total_spent'] ?? 0);
            $customer['last_order_date'] = $stats['last_order_date'];
        }
        
        return $customer;
    }
    
    public function getByCustomerId($customerId) {
        return $this->getById($customerId);
    }
    
    /**
     * Get customer by email
     */
    public function getByEmail($email) {
        if (!$this->tableExists()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM customers WHERE email = ?",
            [$email]
        );
    }
    
    /**
     * Get customer orders
     */
    public function getCustomerOrders($customerId, $email = null) {
        if ($customerId) {
            return $this->db->fetchAll(
                "SELECT o.*, 
                COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.order_number = oi.order_num
                WHERE o.user_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC",
                [$customerId]
            );
        } else if ($email) {
            return $this->db->fetchAll(
                "SELECT o.*, 
                COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.order_number = oi.order_num
                WHERE o.customer_email = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC",
                [$email]
            );
        }
        
        return [];
    }
    
    /**
     * Get customer details by email (for non-registered customers)
     */
    public function getCustomerByEmail($email) {
        $orders = $this->db->fetchAll(
            "SELECT DISTINCT 
            customer_name as name,
            customer_email as email,
            customer_phone as phone,
            billing_address,
            shipping_address
            FROM orders
            WHERE customer_email = ?
            LIMIT 1",
            [$email]
        );
        
        if (!empty($orders)) {
            $customer = $orders[0];
            $customer['id'] = null;
            $customer['is_registered'] = false;
            
            // Get order stats
            $stats = $this->db->fetchOne(
                "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                MAX(created_at) as last_order_date,
                MIN(created_at) as first_order_date
                FROM orders
                WHERE customer_email = ?",
                [$email]
            );
            
            $customer['total_orders'] = (int)($stats['total_orders'] ?? 0);
            $customer['total_spent'] = (float)($stats['total_spent'] ?? 0);
            $customer['last_order_date'] = $stats['last_order_date'];
            $customer['created_at'] = $stats['first_order_date'];
            
            return $customer;
        }
        
        return null;
    }
    
    /**
     * Update customer status
     */
    public function updateStatus($id, $status) {
        return $this->db->execute(
            "UPDATE customers SET status = ? WHERE customer_id = ?",
            [$status, $id]
        );
    }
}

