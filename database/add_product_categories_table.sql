-- Create product_categories junction table for many-to-many relationship
-- This allows products to belong to multiple categories/collections

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_category` (`product_id`, `category_id`),
  KEY `product_id` (`product_id`),
  KEY `category_id` (`category_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing category_id data to product_categories table
INSERT INTO `product_categories` (`product_id`, `category_id`)
SELECT `id`, `category_id` 
FROM `products` 
WHERE `category_id` IS NOT NULL
ON DUPLICATE KEY UPDATE `product_id` = `product_id`;

-- Note: We keep category_id in products table for backward compatibility
-- But product_categories table is the primary source for category relationships


