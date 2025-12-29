<?php
/**
 * Order Management Class
 */

require_once __DIR__ . '/Database.php';

class Order {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all orders with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT o.*, 
                COUNT(oi.id) as item_count,
                SUM(oi.quantity) as total_quantity
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['order_status'])) {
            $sql .= " AND o.order_status = ?";
            $params[] = $filters['order_status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $sql .= " AND o.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get order by ID
     */
    public function getById($id) {
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE id = ?",
            [$id]
        );
        
        if ($order) {
            $order['items'] = $this->getOrderItems($id);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     */
    public function getOrderItems($orderId) {
        return $this->db->fetchAll(
            "SELECT oi.*, p.name as product_name, p.featured_image 
             FROM order_items oi 
             LEFT JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = ?",
            [$orderId]
        );
    }
    
    /**
     * Create order
     */
    public function create($data) {
        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $discountAmount = $data['discount_amount'] ?? 0;
        $shippingAmount = $data['shipping_amount'] ?? 0;
        $taxAmount = $data['tax_amount'] ?? 0;
        $totalAmount = $subtotal - $discountAmount + $shippingAmount + $taxAmount;
        
        // Insert order
        $orderId = $this->db->insert(
            "INSERT INTO orders 
            (order_number, user_id, customer_name, customer_email, customer_phone,
             billing_address, shipping_address, subtotal, discount_amount, 
             shipping_amount, tax_amount, total_amount, payment_method, 
             payment_status, order_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderNumber,
                $data['user_id'] ?? null,
                $data['customer_name'],
                $data['customer_email'],
                $data['customer_phone'] ?? null,
                json_encode($data['billing_address']),
                json_encode($data['shipping_address']),
                $subtotal,
                $discountAmount,
                $shippingAmount,
                $taxAmount,
                $totalAmount,
                $data['payment_method'] ?? null,
                $data['payment_status'] ?? 'pending',
                'pending'
            ]
        );
        
        // Insert order items
        foreach ($data['items'] as $item) {
            $this->db->insert(
                "INSERT INTO order_items 
                (order_id, product_id, product_name, product_sku, quantity, price, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['product_sku'] ?? null,
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                ]
            );
        }
        
        return $orderId;
    }
    
    /**
     * Update order status
     */
    public function updateStatus($id, $status) {
        return $this->db->execute(
            "UPDATE orders SET order_status = ? WHERE id = ?",
            [$status, $id]
        );
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($id, $status) {
        return $this->db->execute(
            "UPDATE orders SET payment_status = ? WHERE id = ?",
            [$status, $id]
        );
    }
    
    /**
     * Update tracking number
     */
    public function updateTracking($id, $trackingNumber) {
        return $this->db->execute(
            "UPDATE orders SET tracking_number = ? WHERE id = ?",
            [$trackingNumber, $id]
        );
    }
}

