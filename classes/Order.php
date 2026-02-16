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

        if (!empty($filters['store_id'])) {
            $sql .= " AND o.store_id = ?";
            $params[] = $filters['store_id'];
        }
        
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
    public function getById($id, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        $sql = "SELECT * FROM orders WHERE id = ?";
        $params = [$id];

        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }

        $order = $this->db->fetchOne($sql, $params);
        
        if ($order) {
            $order['items'] = $this->getOrderItems($order['order_number']);
        }
        
        return $order;
    }

    /**
     * Get order by Order Number
     */
    public function getByOrderNumber($orderNumber, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        $sql = "SELECT * FROM orders WHERE order_number = ?";
        $params = [$orderNumber];

        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }

        $order = $this->db->fetchOne($sql, $params);
        
        if ($order) {
            $order['items'] = $this->getOrderItems($order['order_number']);
        }
        
        return $order;
    }
    
    /**
     * Get order items
     * @param string $orderNumber The order number string
     */
    public function getOrderItems($orderNumber, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        $sql = "SELECT oi.*, p.name as product_name, p.featured_image as product_image, p.featured_image, p.images, p.sku as product_sku, p.slug as product_slug
             FROM order_items oi 
             LEFT JOIN products p ON (oi.product_id = p.product_id OR (oi.product_id < 1000000000 AND oi.product_id = p.id))
             WHERE oi.order_num = ?";
        $params = [$orderNumber];

        if ($storeId) {
            $sql .= " AND oi.store_id = ?";
            $params[] = $storeId;
        }

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Create order
     */
    public function create($data) {
        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Calculate totals and GST
        $subtotal = 0;
        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;
        
        $sellerState = getSetting('seller_state', 'Maharashtra'); // Default or from settings
        $customerState = is_array($data['shipping_address']) ? ($data['shipping_address']['state'] ?? '') : '';
        
        $processedItems = [];
        foreach ($data['items'] as $item) {
            $pId = $item['product_id'];
            
            // Fetch product GST details
            $productInfo = $this->db->fetchOne(
                "SELECT is_taxable, hsn_code, gst_percent FROM products WHERE (product_id = ? OR id = ?)", 
                [$pId, $pId]
            );
            
            $gstPercent = 0;
            $hsnCode = null;
            if ($productInfo && $productInfo['is_taxable']) {
                $gstPercent = $productInfo['gst_percent'];
                $hsnCode = $productInfo['hsn_code'];
            }
            
            $gstResult = calculateGST($item['price'], $gstPercent, $sellerState, $customerState, $item['quantity']);
            
            $itemSubtotal = $gstResult['subtotal'];
            $subtotal += $itemSubtotal;
            $cgstTotal += $gstResult['cgst'];
            $sgstTotal += $gstResult['sgst'];
            $igstTotal += $gstResult['igst'];
            
            $item['hsn_code'] = $hsnCode;
            $item['gst_percent'] = $gstPercent;
            $item['cgst_amount'] = $gstResult['cgst'];
            $item['sgst_amount'] = $gstResult['sgst'];
            $item['igst_amount'] = $gstResult['igst'];
            $item['line_total'] = $gstResult['total'];
            
            $processedItems[] = $item;
        }
        
        $data['items'] = $processedItems; // Use processed items for insertion
        
        $discountAmount = $data['discount_amount'] ?? 0;
        $shippingAmount = $data['shipping_amount'] ?? 0;
        $taxAmount = $cgstTotal + $sgstTotal + $igstTotal;
        $totalAmount = $subtotal - $discountAmount + $shippingAmount + $taxAmount;
        $grandTotal = $totalAmount; // This is the final payable amount
        
        // Determine Store ID (Omni-store logic)
        if (function_exists('getCurrentStoreId')) {
            $storeId = getCurrentStoreId();
        } else {
            $storeId = $_SESSION['store_id'] ?? null;
        }

        // Automatic Customer Matching (Store Specific)
        if ((empty($data['user_id']) || $data['user_id'] < 1000000000) && !empty($data['customer_email'])) {
            try {
                $existingCustomer = $this->db->fetchOne(
                    "SELECT customer_id FROM customers WHERE email = ? AND (store_id = ? OR store_id IS NULL)", 
                    [$data['customer_email'], $storeId]
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
             billing_address, shipping_address, customer_state, subtotal, discount_amount, coupon_code,
             shipping_amount, tax_amount, cgst_total, sgst_total, igst_total, total_amount, grand_total, payment_method, 
             payment_status, order_status, razorpay_payment_id, razorpay_order_id, delivery_date, store_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderNumber,
                $data['user_id'] ?? null,
                $data['customer_name'],
                $data['customer_email'],
                $data['customer_phone'] ?? null,
                json_encode($data['billing_address']),
                json_encode($data['shipping_address']),
                $customerState,
                $subtotal,
                $discountAmount,
                $data['coupon_code'] ?? null,
                $shippingAmount,
                $taxAmount,
                $cgstTotal,
                $sgstTotal,
                $igstTotal,
                $totalAmount,
                $grandTotal,
                $data['payment_method'] ?? null,
                $data['payment_status'] ?? 'pending',
                'pending',
                $data['razorpay_payment_id'] ?? null,
                $data['razorpay_order_id'] ?? null,
                date('Y-m-d', strtotime('+3 days')),
                $storeId
            ]
        );

        // Update discount usage count
        if (!empty($data['coupon_code'])) {
            try {
                $this->db->execute(
                    "UPDATE discounts SET used_count = used_count + 1 WHERE code = ? AND (store_id = ? OR store_id IS NULL)", 
                    [$data['coupon_code'], $storeId]
                );
            } catch (Exception $e) {
                error_log("Failed to update discount count: " . $e->getMessage());
            }
        }
        
        // Insert order items - USING ORDER NUMBER NOW
        foreach ($data['items'] as $item) {
            $pId = $item['product_id'];
            $vAttrs = $item['variant_attributes'] ?? null;
            $requestedQty = (int)$item['quantity'];
            
            // Get current stock to calculate oversold
            $currentStock = $this->getCurrentStock($pId, $vAttrs);
            $oversoldQty = max(0, $requestedQty - max(0, $currentStock));

            $this->db->insert(
                "INSERT INTO order_items 
                (order_num, product_id, product_name, product_sku, quantity, oversold_quantity, price, subtotal, 
                 hsn_code, gst_percent, cgst_amount, sgst_amount, igst_amount, line_total,
                 variant_attributes, store_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderNumber, // Storing Order Number explicitly
                    $pId,
                    $item['name'] ?? $item['product_name'],
                    $item['sku'] ?? $item['product_sku'] ?? null,
                    $requestedQty,
                    $oversoldQty,
                    $item['price'],
                    $item['price'] * $requestedQty,
                    $item['hsn_code'] ?? null,
                    $item['gst_percent'] ?? 0.00,
                    $item['cgst_amount'] ?? 0.00,
                    $item['sgst_amount'] ?? 0.00,
                    $item['igst_amount'] ?? 0.00,
                    $item['line_total'] ?? ($item['price'] * $requestedQty),
                    isset($vAttrs) ? (is_array($vAttrs) ? json_encode($vAttrs) : $vAttrs) : null,
                    $storeId
                ]
            );

            // Decrease stock quantity
            $this->adjustStock($pId, -$requestedQty, $vAttrs);
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
            require_once __DIR__ . '/Settings.php';
            Settings::loadEmailConfig($storeId);
            $email = new Email($storeId);
            $emailOrderDetails = [
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount
            ];
            $email->sendOrderConfirmation($data['customer_email'], $orderNumber, $data['customer_name'], $totalAmount, $data['items'], $emailOrderDetails);
        } catch (Exception $e) { error_log("Failed to send order confirmation email: " . $e->getMessage()); }
        
        return [
            'id' => $orderId,
            'order_number' => $orderNumber
        ];
    }
    
    // ... [updateStatus, updatePaymentStatus, updateTracking, update methods unchanged] ...
    public function updateStatus($id, $status, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        return $this->db->execute("UPDATE orders SET order_status = ? WHERE id = ? AND store_id = ?", [$status, $id, $storeId]);
    }
    public function updatePaymentStatus($id, $status, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        return $this->db->execute("UPDATE orders SET payment_status = ? WHERE id = ? AND store_id = ?", [$status, $id, $storeId]);
    }
    public function updateTracking($id, $trackingNumber, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        return $this->db->execute("UPDATE orders SET tracking_number = ? WHERE id = ? AND store_id = ?", [$trackingNumber, $id, $storeId]);
    }
    public function update($id, $data, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
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
        $params[] = $storeId;
        return $this->db->execute("UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND store_id = ?", $params);
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

        $pId = $itemData['product_id'];
        $vAttrs = $itemData['variant_attributes'] ?? null;
        $requestedQty = (int)$itemData['quantity'];
        
        // Calculate oversold
        $currentStock = $this->getCurrentStock($pId, $vAttrs);
        $oversoldQty = max(0, $requestedQty - max(0, $currentStock));

        // Determine Store ID
        $storeId = $_SESSION['store_id'] ?? null;
        if (!$storeId && isset($_SESSION['user_email'])) {
            $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
            $storeId = $storeUser['store_id'] ?? null;
        }
        if (!$storeId) {
            $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
            $storeId = $storeUser['store_id'] ?? null;
        }

        $itemId = $this->db->insert(
            "INSERT INTO order_items 
            (order_num, product_id, product_name, product_sku, quantity, oversold_quantity, price, subtotal, variant_attributes, store_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderNumber, // Always use Order Number
                $pId,
                $itemData['product_name'],
                $itemData['product_sku'] ?? null,
                $requestedQty,
                $oversoldQty,
                $itemData['price'],
                $itemData['price'] * $requestedQty,
                isset($vAttrs) ? (is_array($vAttrs) ? json_encode($vAttrs) : $vAttrs) : null,
                $storeId
            ]
        );
        
        // Decrease stock
        $this->adjustStock($pId, -$requestedQty, $vAttrs);

        $this->recalculateTotals($orderNumber);
        
        return $itemId;
    }
    
    /**
     * Update order item
     */
    public function updateOrderItem($itemId, $itemData, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        // Get old item data for stock adjustment
        $oldItem = $this->db->fetchOne("SELECT product_id, quantity, variant_attributes, order_num FROM order_items WHERE id = ? AND store_id = ?", [$itemId, $storeId]);
        
        $result = $this->db->execute(
            "UPDATE order_items 
            SET product_name = ?, quantity = ?, price = ?, subtotal = ? 
            WHERE id = ? AND store_id = ?",
            [
                $itemData['product_name'],
                $itemData['quantity'],
                $itemData['price'],
                $itemData['price'] * $itemData['quantity'],
                $itemId,
                $storeId
            ]
        );
        
        if ($oldItem && $result) {
            $newQuantity = (int)$itemData['quantity'];
            $oldQuantity = (int)$oldItem['quantity'];
            $diff = $oldQuantity - $newQuantity; // If new is 10, old was 5, diff is -5 (decrement)
            
            if ($diff !== 0) {
                $this->adjustStock($oldItem['product_id'], $diff, $oldItem['variant_attributes']);
            }
            
            $this->recalculateTotals($oldItem['order_num'], $storeId);
        }
        
        return $result;
    }
    
    /**
     * Delete order item
     */
    public function deleteOrderItem($itemId, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $item = $this->db->fetchOne("SELECT product_id, quantity, variant_attributes, order_num FROM order_items WHERE id = ? AND store_id = ?", [$itemId, $storeId]);
        
        $result = $this->db->execute("DELETE FROM order_items WHERE id = ? AND store_id = ?", [$itemId, $storeId]);
        
        if ($item && $result) {
            // Restore stock
            $this->adjustStock($item['product_id'], (int)$item['quantity'], $item['variant_attributes']);
            $this->recalculateTotals($item['order_num'], $storeId);
        }
        
        return $result;
    }

    /**
     * Get current stock for a product or variant
     */
    private function getCurrentStock($productId, $attributes = null, $storeId = null) {
        if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
            $storeId = $_SESSION['store_id'] ?? null;
        }
        $pId = $productId;
        $vAttrs = !empty($attributes) ? (is_array($attributes) ? json_encode($attributes) : $attributes) : null;
        
        // Resolve 10-digit product_id
        $productIdValue = $pId;
        if (is_numeric($pId) && (int)$pId < 1000000000) {
            $sql = "SELECT product_id FROM products WHERE id = ?";
            $params = [$pId];
            if ($storeId) {
                $sql .= " AND store_id = ?";
                $params[] = $storeId;
            }
            $prod = $this->db->fetchOne($sql, $params);
            if ($prod) $productIdValue = $prod['product_id'];
        }

        if ($vAttrs) {
            $sql = "SELECT stock_quantity FROM product_variants WHERE product_id = ? AND variant_attributes = ?";
            $params = [$productIdValue, $vAttrs];
            if ($storeId) {
                $sql .= " AND store_id = ?";
                $params[] = $storeId;
            }
            $v = $this->db->fetchOne($sql, $params);
            return $v ? (int)$v['stock_quantity'] : 0;
        } else {
            $sql = "SELECT stock_quantity FROM products WHERE (product_id = ? OR id = ?)";
            $params = [$productIdValue, $pId];
            if ($storeId) {
                $sql .= " AND store_id = ?";
                $params[] = $storeId;
            }
            $p = $this->db->fetchOne($sql, $params);
            return $p ? (int)$p['stock_quantity'] : 0;
        }
    }

    /**
     * Adjust stock for a product and its optional variant
     * @param int|string $productId Product ID (auto-increment or 10-digit)
     * @param int $quantity Amount to add (negative to subtract)
     * @param string|array $attributes Variant attributes
     */
    private function adjustStock($productId, $quantity, $attributes = null, $storeId = null) {
        try {
            if (!$storeId && strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false) {
                $storeId = $_SESSION['store_id'] ?? null;
            }
            $qty = (int)$quantity;
            $pId = $productId;
            $vAttrs = !empty($attributes) ? (is_array($attributes) ? json_encode($attributes) : $attributes) : null;

            // 1. Resolve product_id (10-digit) if auto-increment ID passed
            $productIdValue = $pId;
            if (is_numeric($pId) && (int)$pId < 1000000000) {
                $sql = "SELECT product_id FROM products WHERE id = ?";
                $params = [$pId];
                if ($storeId) {
                    $sql .= " AND store_id = ?";
                    $params[] = $storeId;
                }
                $prod = $this->db->fetchOne($sql, $params);
                if ($prod) {
                    $productIdValue = $prod['product_id'];
                }
            }

            $sql = "UPDATE products 
                 SET stock_quantity = stock_quantity + ?,
                     total_sales = total_sales - ?
                 WHERE (product_id = ? OR id = ?)";
            $params = [$qty, $qty, $productIdValue, $pId];

            if ($storeId) {
                $sql .= " AND store_id = ?";
                $params[] = $storeId;
            }

            $this->db->execute($sql, $params);

            // 3. Update variant stock if attributes exist
            if ($vAttrs) {
                $sql = "UPDATE product_variants 
                     SET stock_quantity = stock_quantity + ?
                     WHERE product_id = ? AND variant_attributes = ?";
                $params = [$qty, $productIdValue, $vAttrs];

                if ($storeId) {
                    $sql .= " AND store_id = ?";
                    $params[] = $storeId;
                }

                $this->db->execute($sql, $params);
            }
        } catch (Exception $e) {
            error_log("Order::adjustStock Error: " . $e->getMessage());
        }
    }
    
    /**
     * Recalculate order totals based on items
     * @param int|string $identifier Order ID or Order Number
     */
    public function recalculateTotals($identifier, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        // Handle Identifier (ID or Number)
        $order = null;
        if (is_numeric($identifier) && strpos((string)$identifier, 'ORD-') === false) {
             $order = $this->getById($identifier, $storeId);
        } else {
             $order = $this->getByOrderNumber($identifier, $storeId);
        }

        if (!$order) return false;

        $orderId = $order['id'];
        $orderNumber = $order['order_number'];

        // Get items using order_number
        $items = $this->getOrderItems($orderNumber, $storeId);
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
        }
        
        $discountAmount = $order['discount_amount'] ?? 0;
        $shippingAmount = $order['shipping_amount'] ?? 0;
        $taxAmount = $order['tax_amount'] ?? 0;
        $totalAmount = $subtotal - $discountAmount + $shippingAmount + $taxAmount;
        
        return $this->db->execute(
            "UPDATE orders SET subtotal = ?, total_amount = ? WHERE id = ? AND store_id = ?",
            [$subtotal, $totalAmount, $orderId, $storeId]
        );
    }
}


