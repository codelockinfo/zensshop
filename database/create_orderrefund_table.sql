CREATE TABLE IF NOT EXISTS order_refunds (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    user_id BIGINT NOT NULL,
    store_id VARCHAR(50) DEFAULT NULL,
    
    -- Refund Details
    refund_reason VARCHAR(255) NOT NULL,
    refund_comment TEXT DEFAULT NULL,
    refund_status VARCHAR(50) DEFAULT 'requested', -- requested, approved, rejected, processed
    refund_amount DECIMAL(10,2) DEFAULT NULL, -- request specific amount or full
    
    -- Snapshot of Order Details
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    
    -- Payment & Shipping Snapshot
    payment_method VARCHAR(50),
    total_amount DECIMAL(10,2),
    tracking_number VARCHAR(100) DEFAULT NULL,
    
    -- Items Snapshot (JSON is easiest for archiving)
    items_snapshot JSON DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_refund_user (user_id),
    INDEX idx_order_refund_store (store_id),
    INDEX idx_order_refund_number (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
