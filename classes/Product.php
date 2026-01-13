<?php
/**
 * Product Management Class
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RetryHandler.php';

class Product {
    private $db;
    private $retryHandler;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->retryHandler = new RetryHandler();
    }
    
    /**
     * Get all products with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
                FROM products p 
                LEFT JOIN product_categories pc ON p.id = pc.product_id
                LEFT JOIN categories c ON pc.category_id = c.id 
                WHERE p.status = 'active'";
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM product_categories pc2 
                WHERE pc2.product_id = p.id AND pc2.category_id = ?
            )";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['category_slug'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM product_categories pc2 
                INNER JOIN categories c2 ON pc2.category_id = c2.id
                WHERE pc2.product_id = p.id AND c2.slug = ?
            )";
            $params[] = $filters['category_slug'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['featured'])) {
            $sql .= " AND p.featured = 1";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $limit = (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $offset = (int)$filters['offset'];
                $sql .= " LIMIT {$offset}, {$limit}";
            } else {
                $sql .= " LIMIT {$limit}";
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get product by ID
     */
    public function getById($id) {
        return $this->db->fetchOne(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE p.id = ?",
            [$id]
        );
    }
    
    /**
     * Get product by slug
     */
    public function getBySlug($slug) {
        return $this->db->fetchOne(
            "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
             FROM products p 
             LEFT JOIN product_categories pc ON p.id = pc.product_id
             LEFT JOIN categories c ON pc.category_id = c.id 
             WHERE p.slug = ?
             GROUP BY p.id",
            [$slug]
        );
    }
    
    /**
     * Create product with retry logic
     */
    public function create($data) {
        return $this->retryHandler->executeWithRetry(
            function() use ($data) {
                // Generate unique 10-digit product ID (e.g., 5654148741)
                $customProductId = $this->generateUniqueProductId();
                
                // Generate slug from name
                $slug = $this->generateSlug($data['name']);
                
                // Handle images
                $images = [];
                if (!empty($data['images'])) {
                    $images = is_array($data['images']) ? $data['images'] : json_decode($data['images'], true);
                }
                $imagesJson = json_encode($images);
                $featuredImage = !empty($images[0]) ? $images[0] : null;
                
                // Get primary category_id (first one if multiple)
                $primaryCategoryId = null;
                if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                    $primaryCategoryId = $data['category_ids'][0];
                } elseif (!empty($data['category_id'])) {
                    $primaryCategoryId = $data['category_id'];
                }
                
                // Insert product with custom 10-digit product_id
                $productId = $this->db->insert(
                    "INSERT INTO products 
                    (product_id, name, slug, sku, description, short_description, category_id, price, currency, sale_price, 
                     stock_quantity, stock_status, images, featured_image, gender, brand, status, featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $customProductId,  // 10-digit random product ID (e.g., 5654148741)
                        $data['name'],
                        $slug,
                        $data['sku'] ?? null,
                        $data['description'] ?? null,
                        $data['short_description'] ?? null,
                        $primaryCategoryId,
                        $data['price'] ?? 0,
                        $data['currency'] ?? 'INR',
                        $data['sale_price'] ?? null,
                        $data['stock_quantity'] ?? 0,
                        $data['stock_status'] ?? 'in_stock',
                        $imagesJson,
                        $featuredImage,
                        $data['gender'] ?? 'unisex',
                        $data['brand'] ?? null,
                        $data['status'] ?? 'draft',
                        $data['featured'] ?? 0
                    ]
                );
                
                // Insert product categories (many-to-many)
                if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                    foreach ($data['category_ids'] as $categoryId) {
                        if ($categoryId) {
                            try {
                                $this->db->insert(
                                    "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)",
                                    [$productId, $categoryId]
                                );
                            } catch (Exception $e) {
                                // Ignore duplicate key errors
                            }
                        }
                    }
                } elseif ($primaryCategoryId) {
                    // Fallback: use primary category
                    try {
                        $this->db->insert(
                            "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)",
                            [$productId, $primaryCategoryId]
                        );
                    } catch (Exception $e) {
                        // Ignore duplicate key errors
                    }
                }
                
                // Handle variants if provided
                if (!empty($data['variants']) && is_array($data['variants'])) {
                    // Get the 10-digit product_id to use in variants table
                    $productRecord = $this->db->fetchOne("SELECT product_id FROM products WHERE id = ?", [$productId]);
                    if ($productRecord && !empty($productRecord['product_id'])) {
                        $this->saveVariants($productRecord['product_id'], $data['variants']);
                    }
                }
                
                return $productId;
            },
            'Create Product',
            ['data' => $data]
        );
    }
    
    /**
     * Update product with retry logic
     */
    public function update($id, $data) {
        return $this->retryHandler->executeWithRetry(
            function() use ($id, $data) {
                // Build update query dynamically
                $fields = [];
                $params = [];
                
                $allowedFields = ['name', 'description', 'short_description', 'category_id', 'price', 'currency', 
                                 'sale_price', 'stock_quantity', 'stock_status', 'images', 'featured_image',
                                 'gender', 'brand', 'status', 'featured'];
                
                // Handle category_id (use first category if multiple)
                if (isset($data['category_ids']) && is_array($data['category_ids']) && !empty($data['category_ids'])) {
                    $data['category_id'] = $data['category_ids'][0];
                }
                
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $fields[] = "{$field} = ?";
                        
                        if ($field === 'images' && is_array($data[$field])) {
                            $params[] = json_encode($data[$field]);
                        } elseif ($field === 'featured') {
                            // Ensure featured is an integer (0 or 1)
                            $params[] = (int)$data[$field];
                        } elseif ($field === 'category_id' && ($data[$field] === null || $data[$field] === '')) {
                            // Handle NULL category_id properly
                            $params[] = null;
                        } else {
                            $params[] = $data[$field];
                        }
                    }
                }
                
                // Update slug if name changed
                if (isset($data['name'])) {
                    $fields[] = "slug = ?";
                    $params[] = $this->generateSlugForUpdate($data['name'], $id);
                }
                
                if (empty($fields)) {
                    return true;
                }
                
                // Validate that we have the correct number of parameters
                $params[] = $id;
                
                // Build SQL query
                $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
                
                // Validate SQL and params count match
                $placeholders = substr_count($sql, '?');
                if ($placeholders !== count($params)) {
                    error_log("SQL Parameter Mismatch: SQL has {$placeholders} placeholders but " . count($params) . " parameters provided. SQL: {$sql}");
                    throw new Exception("SQL parameter count mismatch");
                }
                
                $this->db->execute($sql, $params);
                
                // Update product categories (many-to-many)
                if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                    // Delete existing relationships
                    $this->db->execute("DELETE FROM product_categories WHERE product_id = ?", [$id]);
                    
                    // Insert new relationships
                    foreach ($data['category_ids'] as $categoryId) {
                        if ($categoryId) {
                            try {
                                $this->db->insert(
                                    "INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)",
                                    [$id, $categoryId]
                                );
                            } catch (Exception $e) {
                                // Ignore duplicate key errors
                            }
                        }
                    }
                }
                
                return true;
            },
            'Update Product',
            ['id' => $id, 'data' => $data]
        );
    }
    
    /**
     * Delete product
     */
    public function delete($id) {
        // Soft delete to prevent foreign key constraint violations with orders
        // We also modify the slug to free it up for future use
        return $this->db->execute(
            "UPDATE products SET status = 'archived', slug = CONCAT(slug, '-deleted-', UNIX_TIMESTAMP()) WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Generate unique 10-digit product ID
     * Returns a random 10-digit number like: 5654148741
     */
    private function generateUniqueProductId() {
        $maxAttempts = 100; // Prevent infinite loop
        $attempts = 0;
        
        do {
            // Generate 10-digit random number (1000000000 to 9999999999)
            // This ensures exactly 10 digits, like: 5654148741
            $productId = mt_rand(1000000000, 9999999999);
            
            // Check if it already exists in database
            $existing = $this->db->fetchOne(
                "SELECT id FROM products WHERE product_id = ?",
                [$productId]
            );
            
            $attempts++;
            
            // If not found, return the unique ID
            if (!$existing) {
                return $productId;
            }
            
            // If we've tried too many times, throw an error
            if ($attempts >= $maxAttempts) {
                throw new Exception('Unable to generate unique product ID after ' . $maxAttempts . ' attempts');
            }
        } while (true);
    }
    
    private function generateSlug($name) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while ($this->db->fetchOne("SELECT id FROM products WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Generate URL-friendly slug for update (exclude current product)
     */
    private function generateSlugForUpdate($name, $excludeId) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness (excluding current product)
        $originalSlug = $slug;
        $counter = 1;
        while ($existing = $this->db->fetchOne("SELECT id FROM products WHERE slug = ? AND id != ?", [$slug, $excludeId])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Get best selling products
     */
    public function getBestSelling($limit = 6) {
        return $this->db->fetchAll(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE p.status = 'active' 
             ORDER BY p.review_count DESC, p.rating DESC, p.created_at DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Get trending products
     */
    public function getTrending($limit = 6) {
        return $this->db->fetchAll(
            "SELECT p.*, c.name as category_name 
             FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             WHERE p.status = 'active' AND p.featured = 1 
             ORDER BY p.created_at DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Save product variants
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     */
    public function saveVariants($productId, $variantsData) {
        // Check if variants data exists
        if (empty($variantsData['variants']) || !is_array($variantsData['variants'])) {
            return;
        }
        
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId);
        
        // Save variant options to product_variant_options table (if table exists)
        if (!empty($variantsData['options']) && is_array($variantsData['options'])) {
            try {
                foreach ($variantsData['options'] as $index => $option) {
                    if (empty($option['name']) || empty($option['values'])) {
                        continue;
                    }
                    
                    $this->db->insert(
                        "INSERT INTO product_variant_options (product_id, option_name, option_values, display_order) 
                         VALUES (?, ?, ?, ?)",
                        [
                            $productIdValue,
                            $option['name'],
                            json_encode($option['values']),
                            $index
                        ]
                    );
                }
            } catch (Exception $e) {
                // Table might not exist, log and continue (this is optional)
                error_log("Could not save variant options (table may not exist): " . $e->getMessage());
            }
        }
        
        // Save individual variants to product_variants table
        try {
            foreach ($variantsData['variants'] as $variant) {
                if (empty($variant['attributes'])) {
                    continue;
                }
                
                // Convert price and sale_price to proper format
                $price = null;
                if (!empty($variant['price']) && is_numeric($variant['price'])) {
                    $price = floatval($variant['price']);
                }
                
                $salePrice = null;
                if (!empty($variant['sale_price']) && is_numeric($variant['sale_price'])) {
                    $salePrice = floatval($variant['sale_price']);
                }
                
                $this->db->insert(
                    "INSERT INTO product_variants 
                    (product_id, sku, price, sale_price, stock_quantity, stock_status, image, variant_attributes, is_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $productIdValue,  // Use 10-digit product_id
                        !empty($variant['sku']) ? trim($variant['sku']) : null,
                        $price,
                        $salePrice,
                        !empty($variant['stock_quantity']) ? intval($variant['stock_quantity']) : 0,
                        !empty($variant['stock_status']) ? $variant['stock_status'] : 'in_stock',
                        !empty($variant['image']) ? trim($variant['image']) : null,
                        json_encode($variant['attributes']),
                        !empty($variant['is_default']) ? 1 : 0
                    ]
                );
            }
        } catch (Exception $e) {
            // Log error but don't fail the product creation
            error_log("Could not save variants: " . $e->getMessage());
            // Don't throw - allow product to be created even if variants fail
        }
    }
    
    /**
     * Get product variants
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     */
    public function getVariants($productId) {
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId);
        
        try {
            // Get variant options
            $options = $this->db->fetchAll(
                "SELECT * FROM product_variant_options WHERE product_id = ? ORDER BY display_order",
                [$productIdValue]
            );
        } catch (Exception $e) {
            // Table might not exist or query failed
            $options = [];
        }
        
        try {
            // Get variants
            $variants = $this->db->fetchAll(
                "SELECT * FROM product_variants WHERE product_id = ? ORDER BY is_default DESC, id ASC",
                [$productIdValue]
            );
        } catch (Exception $e) {
            // Table might not exist or query failed
            $variants = [];
        }
        
        // Decode JSON fields
        if (is_array($options)) {
            foreach ($options as &$option) {
                if (isset($option['option_values'])) {
                    $decoded = json_decode($option['option_values'], true);
                    $option['option_values'] = is_array($decoded) ? $decoded : [];
                } else {
                    $option['option_values'] = [];
                }
            }
        } else {
            $options = [];
        }
        
        if (is_array($variants)) {
            foreach ($variants as &$variant) {
                if (isset($variant['variant_attributes'])) {
                    $decoded = json_decode($variant['variant_attributes'], true);
                    $variant['variant_attributes'] = is_array($decoded) ? $decoded : [];
                } else {
                    $variant['variant_attributes'] = [];
                }
            }
        } else {
            $variants = [];
        }
        
        return [
            'options' => $options,
            'variants' => $variants
        ];
    }
    
    /**
     * Delete product variants
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     */
    public function deleteVariants($productId) {
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId);
        
        try {
            $this->db->execute(
                "DELETE FROM product_variant_options WHERE product_id = ?",
                [$productIdValue]
            );
        } catch (Exception $e) {
            // Table might not exist, ignore the error
            error_log("Could not delete variant options (table may not exist): " . $e->getMessage());
        }
        
        try {
            $this->db->execute(
                "DELETE FROM product_variants WHERE product_id = ?",
                [$productIdValue]
            );
        } catch (Exception $e) {
            // Table might not exist, ignore the error
            error_log("Could not delete variants (table may not exist): " . $e->getMessage());
        }
    }
    
    /**
     * Get 10-digit product_id value from either auto-increment id or product_id
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     * @return string|int - Returns the 10-digit product_id
     */
    private function getProductIdValue($productId) {
        // If it's already a 10-digit number (between 1000000000 and 9999999999), return as is
        if (is_numeric($productId) && $productId >= 1000000000 && $productId <= 9999999999) {
            return $productId;
        }
        
        // Otherwise, assume it's an auto-increment id and get the product_id
        $product = $this->db->fetchOne("SELECT product_id FROM products WHERE id = ?", [$productId]);
        if ($product && !empty($product['product_id'])) {
            return $product['product_id'];
        }
        
        // Fallback: return as is (in case it's already the product_id)
        return $productId;
    }
}

