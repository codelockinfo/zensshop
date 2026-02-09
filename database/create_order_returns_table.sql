CREATE TABLE IF NOT EXISTS order_returns (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL DEFAULT 'cancel', -- 'cancel' or 'refund'
    order_id BIGINT NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    user_id BIGINT NOT NULL,
    store_id VARCHAR(50) DEFAULT NULL,
    
    -- Request Details
    reason VARCHAR(255) NOT NULL,
    comments TEXT DEFAULT NULL,
    status VARCHAR(50) DEFAULT 'requested', -- requested, approved, rejected, processed
    refund_amount DECIMAL(10,2) DEFAULT NULL, -- Only for refunds
    
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
    
    INDEX idx_return_user (user_id),
    INDEX idx_return_store (store_id),
    INDEX idx_return_number (order_number),
    INDEX idx_return_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
