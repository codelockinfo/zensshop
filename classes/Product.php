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
                LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                LEFT JOIN categories c ON pc.category_id = c.id 
                WHERE 1=1";
        $params = [];

        // Store ID filtering
        $storeId = $filters['store_id'] ?? null;
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }

        if ($storeId && $storeId !== 'DEFAULT') {
            $sql .= " AND (p.store_id = ? OR p.store_id IS NULL OR p.store_id = '0' OR p.store_id = '')";
            $params[] = $storeId;
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        } else {
             $sql .= " AND p.status = 'active'";
        }
        
        if (!empty($filters['category_id'])) {
            $catId = (int)$filters['category_id'];
            $sql .= " AND (
                p.category_id = ? 
                OR p.category_id LIKE ?
                OR p.category_id LIKE ?
                OR p.category_id LIKE ?
                OR p.category_id LIKE ?
                OR EXISTS (
                    SELECT 1 FROM product_categories pc2 
                    WHERE pc2.product_id = p.product_id AND pc2.category_id = ?
                )
            )";
            $params[] = $catId;
            $params[] = (string)$catId; // Exact match
            $params[] = "%," . $catId . ",%"; // CSV style
            $params[] = "%\"" . $catId . "\"%"; // JSON string style
            $params[] = "%:" . $catId . ",%"; // JSON-ish style
            $params[] = $catId;
        }
        
        if (!empty($filters['category_slug'])) {
            $sql .= " AND (
                EXISTS (
                    SELECT 1 FROM categories c3 WHERE c3.id = p.category_id AND c3.slug = ?
                ) 
                OR EXISTS (
                    SELECT 1 FROM categories c4 
                    WHERE c4.slug = ? AND (
                        p.category_id = CAST(c4.id AS CHAR)
                        OR p.category_id LIKE CONCAT('%\"', CAST(c4.id AS CHAR), '\"%')
                        OR p.category_id LIKE CONCAT('%:', CAST(c4.id AS CHAR), ',%')
                    )
                )
                OR EXISTS (
                    SELECT 1 FROM product_categories pc3 
                    INNER JOIN categories c2 ON pc3.category_id = c2.id
                    WHERE pc3.product_id = p.product_id AND c2.slug = ?
                )
            )";
            $params[] = $filters['category_slug'];
            $params[] = $filters['category_slug'];
            $params[] = $filters['category_slug'];
        }
        
        if (!empty($filters['status']) && empty($filters['store_id'])) { // Handled above if store_id present or not
             // Already handled
        }
        
        if (!empty($filters['featured'])) {
            $sql .= " AND p.featured = 1";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.brand LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['stock_status'])) {
            $sql .= " AND p.stock_status = ?";
            $params[] = $filters['stock_status'];
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND COALESCE(NULLIF(p.sale_price, 0), p.price) >= ?";
            $params[] = $filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND COALESCE(NULLIF(p.sale_price, 0), p.price) <= ?";
            $params[] = $filters['max_price'];
        }
        
        $sql .= " GROUP BY p.id";
        // Sorting
        $sortKey = trim($filters['sort'] ?? '');
        
        $allowedSorts = [
            'price ASC' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) ASC',
            'price DESC' => 'COALESCE(NULLIF(p.sale_price, 0), p.price) DESC',
            'name ASC' => 'p.name ASC',
            'name DESC' => 'p.name DESC',
            'name_ASC' => 'p.name ASC',
            'name_DESC' => 'p.name DESC',
            'rating DESC' => 'p.rating DESC',
            'created_at DESC' => 'p.created_at DESC'
        ];
        
        if (!empty($sortKey) && array_key_exists($sortKey, $allowedSorts)) {
            $sql .= " ORDER BY " . $allowedSorts[$sortKey];
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
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
     * Get products by category slug
     */
    public function getByCategory($categorySlug) {
        return $this->getAll(['category_slug' => $categorySlug]);
    }
    
    /**
     * Get product by ID
     */
    public function getById($id, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        $sql = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.id = ?";
        $params = [$id];
        if ($storeId) {
            $sql .= " AND (p.store_id = ? OR p.store_id IS NULL)";
            $params[] = $storeId;
        }
        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * Get product by 10-digit product_id
     */
    public function getByProductId($productId, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        $sql = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.product_id = ?";
        $params = [$productId];
        if ($storeId) {
            $sql .= " AND (p.store_id = ? OR p.store_id IS NULL)";
            $params[] = $storeId;
        }
        $result = $this->db->fetchOne($sql, $params);
        
        // If not found in current store, try globally (for removal operations mostly)
        if (!$result) {
             $globalSql = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.product_id = ?";
             $result = $this->db->fetchOne($globalSql, [$productId]);
        }
        
        return $result;
    }
    
    /**
     * Get product by slug
     */
    public function getBySlug($slug, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }

        $sql = "SELECT DISTINCT p.*, GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as category_names
             FROM products p 
             LEFT JOIN product_categories pc ON p.product_id = pc.product_id
             LEFT JOIN categories c ON pc.category_id = c.id 
             WHERE p.slug = ?";
        $params = [$slug];

        if ($storeId) {
            $sql .= " AND (p.store_id = ? OR p.store_id IS NULL)";
            $params[] = $storeId;
        }

        $sql .= " GROUP BY p.id";

        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * Create product with retry logic
     */
    public function create($data) {
        return $this->retryHandler->executeWithRetry(
            function() use ($data) {
                // Use transaction to ensure consistency
                $this->db->beginTransaction();
                
                try {
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

                // Prepare category data for storage in category_id column (as JSON if multiple)
                $categoryData = $primaryCategoryId;
                if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                     $categoryData = json_encode(array_values($data['category_ids']));
                }
                
                // Determine Store ID
                $storeId = $_SESSION['store_id'] ?? ($data['store_id'] ?? null);
                if (!$storeId && isset($_SESSION['user_email'])) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                    $storeId = $storeUser['store_id'] ?? null;
                }
                if (!$storeId) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
                    $storeId = $storeUser['store_id'] ?? null;
                }

                // Insert product with custom 10-digit product_id
                $productId = $this->db->insert(
                    "INSERT INTO products 
                    (product_id, name, slug, sku, description, short_description, category_id, price, currency, sale_price, 
                     cost_per_item, total_expense, stock_quantity, stock_status, images, featured_image, brand, status, featured, highlights, shipping_policy, return_policy, store_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $customProductId,  // 10-digit random product ID (e.g., 5654148741)
                        $data['name'],
                        $slug,
                        $data['sku'] ?? null,
                        $data['description'] ?? null,
                        $data['short_description'] ?? null,
                        $categoryData,
                        $data['price'] ?? 0,
                        $data['currency'] ?? 'INR',
                        $data['sale_price'] ?? null,
                        $data['cost_per_item'] ?? 0,
                        $data['total_expense'] ?? 0,
                        $data['stock_quantity'] ?? 0,
                        $data['stock_status'] ?? 'in_stock',
                        $imagesJson,
                        $featuredImage,
                        $data['brand'] ?? null,
                        $data['status'] ?? 'draft',
                        $data['featured'] ?? 0,
                        $data['highlights'] ?? null,
                        $data['shipping_policy'] ?? null,
                        $data['return_policy'] ?? null,
                        $storeId
                    ]
                );
                
                // Insert product categories (many-to-many)
                if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                    foreach ($data['category_ids'] as $categoryId) {
                        if ($categoryId) {
                            try {
                                $this->db->insert(
                                    "INSERT INTO product_categories (product_id, category_id, store_id) VALUES (?, ?, ?)",
                                    [$customProductId, $categoryId, $storeId]
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
                            "INSERT INTO product_categories (product_id, category_id, store_id) VALUES (?, ?, ?)",
                            [$customProductId, $primaryCategoryId, $storeId]
                        );
                    } catch (Exception $e) {
                        // Ignore duplicate key errors
                    }
                }
                
                // Handle variants if provided
                if (!empty($data['variants']) && is_array($data['variants'])) {
                    // Use the 10-digit product_id we just generated
                    $this->saveVariants($customProductId, $data['variants']);
                }
                
                $this->db->commit();
                return $productId;
                
                } catch (Exception $e) {
                    $this->db->rollback();
                    // Detect duplicate SKU early
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), '.sku') !== false) {
                        throw new Exception("A product with SKU '" . ($data['sku'] ?? '') . "' already exists.");
                    }
                    throw $e;
                }
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
                
                $allowedFields = ['name', 'sku', 'description', 'short_description', 'category_id', 'price', 'currency', 
                                 'sale_price', 'cost_per_item', 'total_expense', 'stock_quantity', 'stock_status', 'images', 'featured_image',
                                 'brand', 'status', 'featured', 'highlights', 'shipping_policy', 'return_policy'];
                
                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $fields[] = "{$field} = ?";
                        
                        if ($field === 'images' && is_array($data[$field])) {
                            $params[] = json_encode(array_values($data[$field]));
                        } elseif ($field === 'category_id') {
                            if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                                $params[] = json_encode(array_values($data['category_ids']));
                            } else {
                                $params[] = $data[$field] ?: null;
                            }
                        } elseif ($field === 'featured') {
                            // Ensure featured is an integer (0 or 1)
                            $params[] = (int)$data[$field];
                        } elseif ($field === 'sku' && $data[$field] === '') {
                             // Handle empty SKU -> NULL for unique constraint
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
                
                $storeId = $_SESSION['store_id'] ?? null;
                $this->db->execute($sql, $params);
                
                // Update product categories (many-to-many)
                if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                    // Fetch the 10-digit product_id because $id is the internal ID
                    $productInfo = $this->getById($id, $storeId);
                    $tenDigitProductId = $productInfo['product_id'] ?? null;
                    
                    if ($tenDigitProductId) {
                        // Delete existing relationships using the 10-digit product_id
                        $this->db->execute("DELETE FROM product_categories WHERE product_id = ?", [$tenDigitProductId]);
                        
                        // Insert new relationships
                        foreach ($data['category_ids'] as $categoryId) {
                            if ($categoryId) {
                                try {
                                    $this->db->insert(
                                        "INSERT INTO product_categories (product_id, category_id, store_id) VALUES (?, ?, ?)",
                                        [$tenDigitProductId, $categoryId, $storeId]
                                    );
                                } catch (Exception $e) {
                                    // Ignore duplicate key errors
                                }
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
        try {
            // Attempt Hard Delete
            
            // 1. Delete Foreign Key Dependencies (that might not be ON DELETE CASCADE)
            // Variants
            $storeId = $_SESSION['store_id'] ?? null;
            
            // Fetch the 10-digit product_id for other deletions
            $productInfo = $this->getById($id, $storeId);
            $tenDigitProductId = $productInfo['product_id'] ?? null;

            // Remove from homepage sections (Best Selling / Trending)
            if ($tenDigitProductId) {
                $this->db->execute("DELETE FROM section_best_selling_products WHERE product_id = ? AND store_id = ?", [$tenDigitProductId, $storeId]);
                $this->db->execute("DELETE FROM section_trending_products WHERE product_id = ? AND store_id = ?", [$tenDigitProductId, $storeId]);
            }
            
            $this->deleteVariants($id, $storeId);
            // Categories
            if ($tenDigitProductId) {
                $this->db->execute("DELETE FROM product_categories WHERE product_id = ?", [$tenDigitProductId]);
            }
            
            // 2. Delete Product
            $this->db->execute("DELETE FROM products WHERE id = ? AND store_id = ?", [$id, $storeId]);
            
            return true;
        } catch (Exception $e) {
            // Hard delete failed (likely due to Foreign Key constraint with orders)
            // Fallback to Soft Delete (Archive)
            error_log("Hard delete failed for product $id: " . $e->getMessage() . ". Falling back to soft delete.");
            
            return $this->db->execute(
                "UPDATE products SET status = 'archived', slug = CONCAT(slug, '-deleted-', UNIX_TIMESTAMP()) WHERE id = ? AND store_id = ?",
                [$id, $storeId]
            );
        }
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
        $storeId = $_SESSION['store_id'] ?? null;
        while ($this->db->fetchOne("SELECT id FROM products WHERE slug = ? AND store_id = ?", [$slug, $storeId])) {
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
        $storeId = $_SESSION['store_id'] ?? null;
        while ($existing = $this->db->fetchOne("SELECT id FROM products WHERE slug = ? AND id != ? AND store_id = ?", [$slug, $excludeId, $storeId])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Get best selling products
     */
    public function getBestSelling($limit = 6, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.status = 'active'";
        $params = [];

        if ($storeId) {
            $sql .= " AND p.store_id = ?";
            $params[] = $storeId;
        }

        $sql .= " ORDER BY p.review_count DESC, p.rating DESC, p.created_at DESC LIMIT ?";
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get trending products
     */
    public function getTrending($limit = 6, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }

        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.status = 'active' AND p.featured = 1";
        $params = [];

        if ($storeId) {
            $sql .= " AND p.store_id = ?";
            $params[] = $storeId;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT ?";
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Save product variants
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     */
    public function saveVariants($productId, $variantsData, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $log = "Saving variants for PID $productId. ";
        // Check if variants data exists
        if (empty($variantsData['variants']) || !is_array($variantsData['variants'])) {
            $log .= "No variants found or invalid format.\n";
            file_put_contents(__DIR__ . '/../admin/debug_variants.log', $log, FILE_APPEND);
            return;
        }
        
        $log .= "Count: " . count($variantsData['variants']) . ". ";
        $log .= "Data: " . json_encode($variantsData['variants']) . ". ";
        
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId, $storeId);
        $log .= "PID Value: $productIdValue. ";
        
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
                    (product_id, sku, price, sale_price, stock_quantity, stock_status, image, variant_attributes, is_default, store_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $productIdValue,  // Use 10-digit product_id
                        !empty($variant['sku']) ? trim($variant['sku']) : null,
                        $price,
                        $salePrice,
                        !empty($variant['stock_quantity']) ? intval($variant['stock_quantity']) : 0,
                        !empty($variant['stock_status']) ? $variant['stock_status'] : 'in_stock',
                        !empty($variant['image']) ? trim($variant['image']) : null,
                        json_encode($variant['attributes']),
                        !empty($variant['is_default']) ? 1 : 0,
                        $storeId
                    ]
                );
            }
            $log .= "Success.\n";
        } catch (Exception $e) {
            $log .= "Error: " . $e->getMessage() . "\n";
            // Log error but don't fail the product creation
            error_log("Could not save variants: " . $e->getMessage());
            // Don't throw - allow product to be created even if variants fail
        }
        file_put_contents(__DIR__ . '/../admin/debug_variants.log', $log, FILE_APPEND);
    }
    
    /**
     * Get product variants
     * @param int|string $productId - Can be auto-increment id or 10-digit product_id
     */
    public function getVariants($productId, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId, $storeId);
        
        try {
            // Get variants from product_variants table
            $sql = "SELECT * FROM product_variants WHERE product_id = ?";
            $params = [$productIdValue];

            if ($storeId) {
                $sql .= " AND store_id = ?";
                $params[] = $storeId;
            }

            $sql .= " ORDER BY is_default DESC, id ASC";
            $variants = $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            $variants = [];
        }
        
        // Reconstruct options from variant attributes
        $optionsMap = [];
        
        if (is_array($variants)) {
            foreach ($variants as &$variant) {
                if (isset($variant['variant_attributes'])) {
                    $attributes = json_decode($variant['variant_attributes'], true);
                    $variant['variant_attributes'] = is_array($attributes) ? $attributes : [];
                    
                    // Collect unique option names and values
                    foreach ($variant['variant_attributes'] as $name => $value) {
                        if (!isset($optionsMap[$name])) {
                            $optionsMap[$name] = [];
                        }
                        if (!in_array($value, $optionsMap[$name])) {
                            $optionsMap[$name][] = $value;
                        }
                    }
                } else {
                    $variant['variant_attributes'] = [];
                }
            }
        } else {
            $variants = [];
        }
        
        // Convert optionsMap to the expected options array format
        $options = [];
        foreach ($optionsMap as $name => $values) {
            $options[] = [
                'option_name' => $name,
                'option_values' => $values
            ];
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
    public function deleteVariants($productId, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        // Convert auto-increment id to 10-digit product_id if needed
        $productIdValue = $this->getProductIdValue($productId, $storeId);
        
        try {
            $this->db->execute(
                "DELETE FROM product_variants WHERE product_id = ? AND store_id = ?",
                [$productIdValue, $storeId]
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
    private function getProductIdValue($productId, $storeId = null) {
        if (!$storeId) {
            if (defined('CURRENT_STORE_ID')) {
                $storeId = CURRENT_STORE_ID;
            } elseif (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        // If it's already a 10-digit number (between 1000000000 and 9999999999), return as is
        if (is_numeric($productId) && $productId >= 1000000000 && $productId <= 9999999999) {
            return $productId;
        }
        
        // Otherwise, assume it's an auto-increment id and get the product_id
        $sql = "SELECT product_id FROM products WHERE (id = ? OR product_id = ?)";
        $params = [$productId, $productId];

        if ($storeId) {
            $sql .= " AND (store_id = ? OR store_id IS NULL)";
            $params[] = $storeId;
        }

        $product = $this->db->fetchOne($sql, $params);
        if ($product && !empty($product['product_id'])) {
            return $product['product_id'];
        }

        // If not found in current store, try searching globally (ignoring store_id) - fallback for wishlist deletions
        // This is safe because product_id is unique enough (10-digits) or ID is auto-increment PK
        $globalSql = "SELECT product_id FROM products WHERE (id = ? OR product_id = ?)";
        $globalParams = [$productId, $productId];
        $globalProduct = $this->db->fetchOne($globalSql, $globalParams);
        
        if ($globalProduct && !empty($globalProduct['product_id'])) {
            return $globalProduct['product_id'];
        }

        // Fallback: return as is (in case it's already the product_id)
        return $productId;
    }

    /**
     * Get products by multiple IDs
     */
    public function getByIds($ids, $preserveOrder = true, $storeId = null) {
        if (empty($ids)) {
            return [];
        }
        
        if (!$storeId) {
            if (function_exists('getCurrentStoreId')) {
                $storeId = getCurrentStoreId();
            } else {
                $storeId = $_SESSION['store_id'] ?? null;
            }
        }
        
        // Ensure IDs are integers
        $ids = array_map('intval', $ids);
        $idsStr = implode(',', $ids);
        
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id IN ($idsStr) AND p.status = 'active'";
        $params = [];

        if ($storeId) {
            $sql .= " AND p.store_id = ?";
            $params[] = $storeId;
        }
        
        if ($preserveOrder) {
            $sql .= " ORDER BY FIELD(p.id, $idsStr)";
        }
        
        return $this->db->fetchAll($sql, $params);
    }
}

