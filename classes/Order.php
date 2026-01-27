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
                SUM(oi.quantity) as total_quantity,
                (SELECT p.featured_image 
                 FROM order_items oi2 
                 LEFT JOIN products p ON (oi2.product_id = p.product_id OR (oi2.product_id < 1000000000 AND oi2.product_id = p.id))
                 WHERE oi2.order_num = o.order_number 
                 LIMIT 1) as product_image
                FROM orders o
                LEFT JOIN order_items oi ON o.order_number = oi.order_num
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND o.user_id = ?";
            $params[] = $filters['user_id'];
        }

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
            $order['items'] = $this->getOrderItems($order['order_number']);
        }
        
        return $order;
    }

    /**
     * Get order by Order Number
     */
    public function getByOrderNumber($orderNumber) {
        $order = $this->db->fetchOne(
            "SELECT * FROM orders WHERE order_number = ?",
            [$orderNumber]
        );
        
        if ($order) {
            $order['items'] = $this->getOrderItems($order['order_number']);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     * @param string $orderNumber The order number string
     */
    public function getOrderItems($orderNumber) {
        return $this->db->fetchAll(
            "SELECT oi.*, p.name as product_name, p.featured_image as product_image, p.sku as product_sku, p.slug as product_slug
             FROM order_items oi 
             LEFT JOIN products p ON (oi.product_id = p.product_id OR (oi.product_id < 1000000000 AND oi.product_id = p.id))
             WHERE oi.order_num = ?",
            [$orderNumber]
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
        
        // Automatic Customer Matching
        if ((empty($data['user_id']) || $data['user_id'] < 1000000000) && !empty($data['customer_email'])) {
            try {
                $existingCustomer = $this->db->fetchOne(
                    "SELECT customer_id FROM customers WHERE email = ?", 
                    [$data['customer_email']]
                );
                if ($existingCustomer) {
                    $data['user_id'] = $existingCustomer['customer_id'];
                }
            } catch (Exception $e) {}
        }
        
        // Insert order
        $orderId = $this->db->insert(
            "INSERT INTO orders 
            (order_number, user_id, customer_name, customer_email, customer_phone,
             billing_address, shipping_address, subtotal, discount_amount, 
             shipping_amount, tax_amount, total_amount, payment_method, 
             payment_status, order_status, razorpay_payment_id, razorpay_order_id, delivery_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
                'pending',
                $data['razorpay_payment_id'] ?? null,
                $data['razorpay_order_id'] ?? null,
                date('Y-m-d', strtotime('+3 days'))
            ]
        );
        
        // Insert order items - USING ORDER NUMBER NOW
        foreach ($data['items'] as $item) {
            $this->db->insert(
                "INSERT INTO order_items 
                (order_num, product_id, product_name, product_sku, quantity, price, subtotal, variant_attributes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderNumber, // Storing Order Number explicitly
                    $item['product_id'],
                    $item['name'] ?? $item['product_name'],
                    $item['sku'] ?? $item['product_sku'] ?? null,
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity'],
                    isset($item['variant_attributes']) ? (is_array($item['variant_attributes']) ? json_encode($item['variant_attributes']) : $item['variant_attributes']) : null
                ]
            );
        }

        // Auto-sync customer data
        if (!empty($data['user_id'])) {
            try {
                $customerId = $data['user_id'];
                $customerData = $this->db->fetchOne("SELECT * FROM customers WHERE customer_id = ?", [$customerId]);
                
                if ($customerData) {
                    $updates = [];
                    $params = [];
                    // ... (Address checks omitted for brevity, same as before) ...
                    // Update Phone
                    if (empty($customerData['phone']) && !empty($data['customer_phone'])) {
                        $updates[] = "phone = ?";
                        $params[] = $data['customer_phone'];
                    }
                    // Update Billing
                    $newBilling = json_encode($data['billing_address']);
                    if (empty($customerData['billing_address']) && !empty($data['billing_address'])) {
                        $updates[] = "billing_address = ?";
                        $params[] = $newBilling;
                    }
                    // Update Shipping
                    $newShipping = json_encode($data['shipping_address']);
                    if (empty($customerData['shipping_address']) && !empty($data['shipping_address'])) {
                        $updates[] = "shipping_address = ?";
                        $params[] = $newShipping;
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $customerId;
                        $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE customer_id = ?";
                        $this->db->execute($sql, $params);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to auto-sync customer data for order #$orderNumber: " . $e->getMessage());
            }
        }
        
        // Notifications
        require_once __DIR__ . '/Notification.php';
        $notification = new Notification();
        $notification->notifyNewOrder($orderNumber, $data['customer_name'], '₹' . number_format($totalAmount, 2));
        
        try {
            require_once __DIR__ . '/Email.php';
            $email = new Email();
            $email->sendOrderConfirmation($data['customer_email'], $orderNumber, $data['customer_name'], $totalAmount, $data['items']);
        } catch (Exception $e) { error_log("Failed to send order confirmation email: " . $e->getMessage()); }
        
        return [
            'id' => $orderId,
            'order_number' => $orderNumber
        ];
    }
    
    // ... [updateStatus, updatePaymentStatus, updateTracking, update methods unchanged] ...
    public function updateStatus($id, $status) {
        return $this->db->execute("UPDATE orders SET order_status = ? WHERE id = ?", [$status, $id]);
    }
    public function updatePaymentStatus($id, $status) {
        return $this->db->execute("UPDATE orders SET payment_status = ? WHERE id = ?", [$status, $id]);
    }
    public function updateTracking($id, $trackingNumber) {
        return $this->db->execute("UPDATE orders SET tracking_number = ? WHERE id = ?", [$trackingNumber, $id]);
    }
    public function update($id, $data) {
        $updates = [];
        $params = [];
        $allowedFields = ['order_status', 'payment_status', 'tracking_number', 'notes', 'customer_name', 'customer_email', 'customer_phone', 'billing_address', 'shipping_address', 'subtotal', 'discount_amount', 'shipping_amount', 'tax_amount', 'total_amount', 'payment_method'];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'billing_address' || $field === 'shipping_address') {
                    $updates[] = "{$field} = ?";
                    $params[] = is_array($data[$field]) ? json_encode($data[$field]) : ($data[$field] ?? null);
                } else {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field] ?? null;
                }
            }
        }
        if (empty($updates)) return false;
        $params[] = $id;
        return $this->db->execute("UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?", $params);
    }

    /**
     * Add item to order
     * @param int|string $orderId The int ID or string Order Number
     */
    public function addOrderItem($orderId, $itemData) {
        // Resolve Order Number
        $orderNumber = $orderId;
        if (is_numeric($orderId)) {
            $order = $this->getById($orderId);
            $orderNumber = $order['order_number'];
        }

        $itemId = $this->db->insert(
            "INSERT INTO order_items 
            (order_num, product_id, product_name, product_sku, quantity, price, subtotal, variant_attributes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderNumber, // Always use Order Number
                $itemData['product_id'],
                $itemData['product_name'],
                $itemData['product_sku'] ?? null,
                $itemData['quantity'],
                $itemData['price'],
                $itemData['price'] * $itemData['quantity'],
                isset($itemData['variant_attributes']) ? (is_array($itemData['variant_attributes']) ? json_encode($itemData['variant_attributes']) : $itemData['variant_attributes']) : null
            ]
        );
        
        $this->recalculateTotals($orderNumber);
        
        return $itemId;
    }
    
    /**
     * Update order item
     */
    public function updateOrderItem($itemId, $itemData) {
        $result = $this->db->execute(
            "UPDATE order_items 
            SET product_name = ?, quantity = ?, price = ?, subtotal = ? 
            WHERE id = ?",
            [
                $itemData['product_name'],
                $itemData['quantity'],
                $itemData['price'],
                $itemData['price'] * $itemData['quantity'],
                $itemId
            ]
        );
        
        // Get order_num from item
        $item = $this->db->fetchOne("SELECT order_num FROM order_items WHERE id = ?", [$itemId]);
        if ($item) {
            $this->recalculateTotals($item['order_num']);
        }
        
        return $result;
    }
    
    /**
     * Delete order item
     */
    public function deleteOrderItem($itemId) {
        $item = $this->db->fetchOne("SELECT order_num FROM order_items WHERE id = ?", [$itemId]);
        
        $result = $this->db->execute("DELETE FROM order_items WHERE id = ?", [$itemId]);
        
        if ($item) {
            $this->recalculateTotals($item['order_num']);
        }
        
        return $result;
    }
    
    /**
     * Recalculate order totals based on items
     * @param int|string $identifier Order ID or Order Number
     */
    public function recalculateTotals($identifier) {
        // Handle Identifier (ID or Number)
        $order = null;
        if (is_numeric($identifier) && strpos((string)$identifier, 'ORD-') === false) {
             $order = $this->getById($identifier);
        } else {
             $order = $this->getByOrderNumber($identifier);
        }

        if (!$order) return false;

        $orderId = $order['id'];
        $orderNumber = $order['order_number'];

        // Get items using order_number
        $items = $this->getOrderItems($orderNumber);
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
        }
        
        $discountAmount = $order['discount_amount'] ?? 0;
        $shippingAmount = $order['shipping_amount'] ?? 0;
        $taxAmount = $order['tax_amount'] ?? 0;
        $totalAmount = $subtotal - $discountAmount + $shippingAmount + $taxAmount;
        
        return $this->db->execute(
            "UPDATE orders SET subtotal = ?, total_amount = ? WHERE id = ?",
            [$subtotal, $totalAmount, $orderId]
        );
    }
}


