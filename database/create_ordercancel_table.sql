CREATE TABLE IF NOT EXISTS ordercancel (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    user_id BIGINT NOT NULL,
    store_id VARCHAR(50) DEFAULT NULL,
    
    -- Cancellation Details
    cancel_reason VARCHAR(255) NOT NULL,
    cancel_comment TEXT DEFAULT NULL,
    cancel_status VARCHAR(50) DEFAULT 'requested', -- requested, approved, rejected
    
    -- Snapshot of Order Details
    customer_name VARCHAR(100),
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    shipping_address JSON DEFAULT NULL,
    
    -- Payment & Shipping Snapshot
    payment_method VARCHAR(50),
    payment_status VARCHAR(50),
    total_amount DECIMAL(10,2),
    tracking_number VARCHAR(100) DEFAULT NULL,
    
    -- Items Snapshot
    items_snapshot JSON DEFAULT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_cancel_user (user_id),
    INDEX idx_order_cancel_store (store_id),
    INDEX idx_order_cancel_number (order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
