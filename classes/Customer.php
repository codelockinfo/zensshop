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
        
        if (!empty($filters['store_id'])) {
            $sql .= " AND c.store_id = ?";
            $params[] = $filters['store_id'];
        }
        
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
    public function getCustomersFromOrders($storeId = null) {
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
                WHERE (user_id IS NULL OR user_id = 0)";
        $params = [];
        
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }
        
        $sql .= " GROUP BY customer_email, customer_name, customer_phone ORDER BY last_order_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get all customers (registered + from orders)
     */
    public function getAllCustomers($filters = []) {
        $registered = $this->getAll($filters);
        $fromOrders = $this->getCustomersFromOrders($filters['store_id'] ?? null);
        
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
    public function getById($id, $storeId = null) {
        if (!$this->tableExists()) {
            return null;
        }
        
        $sql = "SELECT * FROM customers WHERE customer_id = ?";
        $params = [$id];
        
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }
        
        $customer = $this->db->fetchOne($sql, $params);
        
        if ($customer) {
            // Get order stats
            $statsSql = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_spent,
                MAX(created_at) as last_order_date
                FROM orders WHERE user_id = ?";
            $statsParams = [$customer['customer_id']];
            
            if ($storeId) {
                $statsSql .= " AND store_id = ?";
                $statsParams[] = $storeId;
            }
            
            $stats = $this->db->fetchOne($statsSql, $statsParams);
            
            $customer['total_orders'] = (int)($stats['total_orders'] ?? 0);
            $customer['total_spent'] = (float)($stats['total_spent'] ?? 0);
            $customer['last_order_date'] = $stats['last_order_date'];
        }
        
        return $customer;
    }
    
    public function getByCustomerId($customerId, $storeId = null) {
        return $this->getById($customerId, $storeId);
    }
    
    /**
     * Get customer by email
     */
    public function getByEmail($email, $storeId = null) {
        if (!$this->tableExists()) {
            return null;
        }
        
        $sql = "SELECT * FROM customers WHERE email = ?";
        $params = [$email];
        
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }
        
        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * Get customer orders
     */
    public function getCustomerOrders($customerId, $email = null, $storeId = null) {
        $params = [];
        if ($customerId) {
            $sql = "SELECT o.*, 
                    COUNT(oi.id) as item_count
                    FROM orders o
                    LEFT JOIN order_items oi ON o.order_number = oi.order_num
                    WHERE o.user_id = ?";
            $params[] = $customerId;
            if ($storeId) {
                $sql .= " AND o.store_id = ?";
                $params[] = $storeId;
            }
            $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
            return $this->db->fetchAll($sql, $params);
        } else if ($email) {
            $sql = "SELECT o.*, 
                    COUNT(oi.id) as item_count
                    FROM orders o
                    LEFT JOIN order_items oi ON o.order_number = oi.order_num
                    WHERE o.customer_email = ?";
            $params[] = $email;
            if ($storeId) {
                $sql .= " AND o.store_id = ?";
                $params[] = $storeId;
            }
            $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
            return $this->db->fetchAll($sql, $params);
        }
        
        return [];
    }
    
    /**
     * Get customer details by email (for non-registered customers)
     */
    public function getCustomerByEmail($email, $storeId = null) {
        $sql = "SELECT DISTINCT 
                customer_name as name,
                customer_email as email,
                customer_phone as phone,
                billing_address,
                shipping_address
                FROM orders
                WHERE customer_email = ?";
        $params = [$email];
        
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }
        
        $sql .= " LIMIT 1";
        
        $orders = $this->db->fetchAll($sql, $params);
        
        if (!empty($orders)) {
            $customer = $orders[0];
            $customer['id'] = null;
            $customer['is_registered'] = false;
            
            // Get order stats
            $statsSql = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                MAX(created_at) as last_order_date,
                MIN(created_at) as first_order_date
                FROM orders
                WHERE customer_email = ?";
            $statsParams = [$email];
            
            if ($storeId) {
                $statsSql .= " AND store_id = ?";
                $statsParams[] = $storeId;
            }
            
            $stats = $this->db->fetchOne($statsSql, $statsParams);
            
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

