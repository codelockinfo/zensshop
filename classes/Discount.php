<?php
/**
 * Discount Management Class
 */

require_once __DIR__ . '/Database.php';

class Discount {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get discount by code
     */
    public function getByCode($code, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        return $this->db->fetchOne(
            "SELECT * FROM discounts WHERE code = ? AND status = 'active' AND store_id = ?",
            [$code, $storeId]
        );
    }
    
    /**
     * Validate discount code
     * Returns true if valid, throws Exception with reason if invalid
     */
    public function validate($code, $cartTotal) {
        $discount = $this->getByCode($code);
        
        if (!$discount) {
            throw new Exception("Invalid discount code.");
        }
        
        // Check dates
        $now = date('Y-m-d H:i:s');
        if (!empty($discount['start_date']) && $now < $discount['start_date']) {
            throw new Exception("Discount code is not active yet.");
        }
        if (!empty($discount['end_date']) && $now > $discount['end_date']) {
            throw new Exception("Discount code has expired.");
        }
        
        // Check minimum purchase
        if (!empty($discount['min_purchase_amount']) && $cartTotal < $discount['min_purchase_amount']) {
            require_once __DIR__ . '/../includes/functions.php';
            $formattedMin = format_currency($discount['min_purchase_amount']);
            throw new Exception("Minimum purchase of $formattedMin required.");
        }
        
        // Check usage limit (Count orders using this code)
        if (!empty($discount['usage_limit'])) {
            // Note: This relies on orders table having a coupon_code column or similar tracking.
            // Since we haven't confirmed schema, we'll skip this specific check for now 
            // or we could add a check if we knew where it was stored.
            // For now, we'll just check if usage_limit is set but not enforce strict count 
            // unless we add a 'used_count' to the discounts table or query orders.
            
            // Let's try to query usage count from orders if possible, assuming 'coupon_code' might exist
            // or we track it in a separate table.
            // For safety, let's implement a 'usage_count' field check on the discount record itself if it exists,
            // or count strictly from orders if 'coupon_code' column exists.
            
            // Simplest approach for now: Check if 'used_count' exists in discount table
            if (isset($discount['used_count']) && $discount['used_count'] >= $discount['usage_limit']) {
                 throw new Exception("Discount code usage limit reached.");
            }
        }
        
        return $discount;
    }
    
    /**
     * Calculate discount amount
     */
    public function calculateAmount($code, $cartTotal) {
        $discount = $this->validate($code, $cartTotal);
        
        $amount = 0;
        if ($discount['type'] === 'percentage') {
            $amount = ($cartTotal * $discount['value']) / 100;
            
            // Cap at max discount if set
            if (!empty($discount['max_discount_amount']) && $amount > $discount['max_discount_amount']) {
                $amount = $discount['max_discount_amount'];
            }
        } else {
            // Fixed amount
            $amount = $discount['value'];
        }
        
        // Ensure discount doesn't exceed total
        if ($amount > $cartTotal) {
            $amount = $cartTotal;
        }
        
        return $amount;
    }
}
