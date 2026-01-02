-- Create product variants tables for Shopify-like variant system
-- This allows products to have multiple variants with different attributes (Size, Color, Material, etc.)

-- Product Variant Options Table (e.g., Size, Color, Material)
-- This stores the variant option types and their values

-- Product Variants Table
-- This stores individual variant combinations (e.g., Small-Red, Medium-Blue)
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `sku` varchar(100) UNIQUE,
  `price` decimal(10,2) DEFAULT NULL COMMENT 'Override product price if set',
  `sale_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
  `weight` decimal(8,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL COMMENT 'Variant-specific image',
  `variant_attributes` text COMMENT 'JSON object with option_name: option_value pairs, e.g., {"Size": "Small", "Color": "Red"}',
  `barcode` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Default variant to show',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `is_default` (`is_default`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

