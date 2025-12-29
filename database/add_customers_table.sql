-- Customers Table
-- For registered customers from frontend

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255),
  `phone` varchar(20),
  `billing_address` text,
  `shipping_address` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: Foreign key constraint for orders.user_id is optional
-- If you want to add it, make sure all existing orders.user_id values are NULL or valid customer IDs
-- ALTER TABLE `orders` 
-- ADD CONSTRAINT `fk_orders_customer` 
-- FOREIGN KEY (`user_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

