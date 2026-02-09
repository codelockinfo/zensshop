<?php
/**
 * Cart Management Class
 * Handles cart operations with cookie and database storage
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Product.php';

class Cart {
    private $db;
    private $product;
    
    public $dbError = '';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->product = new Product();
        
        // Start session if not already started and headers not sent
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
    }
    
    /**
     * Get cart items from cookie or session
     */
    public function getCart() {
        $cartItems = [];
        
        // Get current store ID
        $currentStoreId = null;
        if (defined('CURRENT_STORE_ID')) {
            $currentStoreId = CURRENT_STORE_ID;
        } elseif (function_exists('getCurrentStoreId')) {
            $currentStoreId = getCurrentStoreId();
        } else {
            $currentStoreId = $_SESSION['store_id'] ?? null;
        }
        
        // Try to get from cookie first
        if (isset($_COOKIE[CART_COOKIE_NAME])) {
            $json = $_COOKIE[CART_COOKIE_NAME];
            
            // Try explicit decoding if needed
            $cartData = json_decode($json, true);
            
            if (!is_array($cartData)) {
                $cartData = json_decode(stripslashes($json), true);
            }
            
            if (!is_array($cartData)) {
                $cartData = json_decode(urldecode($json), true);
            }

            if (is_array($cartData)) {
                $cartItems = $cartData;
            }
        }
        
        // If user is logged in, strictly use database
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $dbCart = $this->getCartFromDB($loggedId, $currentStoreId);
            $cartItems = $dbCart;
            // Sync cookie with DB
            $this->saveCartToCookie($cartItems);
        }
        
        // Ensure all items have required fields and proper image URLs
        $validItems = [];
        foreach ($cartItems as $item) {
            // Ensure product_id exists
            if (empty($item['product_id'])) {
                continue;
            }
            
            $productId = $item['product_id'];
            $product = $this->product->getByProductId($productId, $currentStoreId);
            
            // Fallback for old auto-increment IDs
            if (!$product && is_numeric($productId) && $productId < 1000000000) {
                $product = $this->product->getById($productId, $currentStoreId);
                if ($product) {
                    $item['product_id'] = $product['product_id']; // Use 10-digit ID
                }
            }

            // STRICT FILTER: If product doesn't exist or belongs to another store, skip it
            if (!$product) {
                continue;
            }
            
            // RELAXED FILTER: If product belongs to another store, we still show it in guest cart
            // but we might want to log it or handle it differently if needed.
            // For now, let's allow it as long as the product exists.
            /*
            if ($currentStoreId && !empty($product['store_id']) && $product['store_id'] !== $currentStoreId) {
                continue;
            }
            */

            // Update fresh data from product table
            $item['name'] = $product['name'];
            $item['price'] = $product['sale_price'] ?? $product['price'];
            $item['base_price'] = $product['price'];
            $item['slug'] = $product['slug'] ?? '';
            $item['currency'] = $product['currency'] ?? 'USD';
            $item['stock_status'] = $product['stock_status'] ?? 'in_stock';
            $item['stock_quantity'] = $product['stock_quantity'] ?? 0;
            $item['is_taxable'] = $product['is_taxable'] ?? 0;
            $item['hsn_code'] = $product['hsn_code'] ?? null;
            $item['gst_percent'] = $product['gst_percent'] ?? 0.00;

            if (empty($item['image']) || $item['image'] === 'null' || $item['image'] === 'undefined' || !empty($product['featured_image'])) {
                if (!empty($product['featured_image'])) {
                    $item['image'] = $product['featured_image'];
                } else {
                    $images = json_decode($product['images'] ?? '[]', true);
                    if (!empty($images[0])) {
                        $item['image'] = $images[0];
                    }
                }
            }
            
            // Convert to full URL if needed
            require_once __DIR__ . '/../includes/functions.php';
            $item['image'] = getImageUrl($item['image'] ?? '');

            $validItems[] = $item;
        }
        
        return array_values($validItems);
    }
    
    /**
     * Get cart from database
     */
    private function getCartFromDB($userId, $storeId = null) {
        if (!$storeId && function_exists('getCurrentStoreId')) {
            $storeId = getCurrentStoreId();
        }

        $sql = "SELECT c.*, p.name, p.price, p.currency, p.sale_price, p.featured_image, p.stock_quantity, p.stock_status, p.slug,
                       p.is_taxable, p.hsn_code, p.gst_percent
              FROM cart c
              LEFT JOIN products p ON (c.product_id = p.product_id OR (c.product_id = p.id AND c.product_id < 1000000000))
              WHERE c.user_id = ?";
        $params = [$userId];

        if ($storeId) {
            $sql .= " AND (c.store_id = ? OR c.store_id IS NULL)";
            $params[] = $storeId;
        }

        $items = $this->db->fetchAll($sql, $params);
        
        $cartItems = [];
        foreach ($items as $item) {
            $variantAttributes = !empty($item['variant_attributes']) ? json_decode($item['variant_attributes'], true) : [];
            
            // Get base product data
            $productImage = $item['featured_image'];
            $price = $item['sale_price'] ?? $item['price'];
            $basePrice = $item['price'];
            $sku = $item['sku'] ?? '';
            
            // If variant attributes exist, try to get specific variant data
            $variant = null;
            if (!empty($variantAttributes)) {
                $productId = $item['product_id'];
                $variant = $this->db->fetchOne(
                    "SELECT * FROM product_variants WHERE product_id = ? AND variant_attributes = ?",
                    [$productId, json_encode($variantAttributes)]
                );
                
                // Fallback for old auto-increment IDs in cart table
                if (!$variant && is_numeric($productId) && $productId < 1000000000) {
                    $innerProd = $this->product->getById($productId);
                    if ($innerProd) {
                        $variant = $this->db->fetchOne(
                            "SELECT * FROM product_variants WHERE product_id = ? AND variant_attributes = ?",
                            [$innerProd['product_id'], json_encode($variantAttributes)]
                        );
                    }
                }
                
                if ($variant) {
                    if (!empty($variant['image'])) $productImage = $variant['image'];
                    if (!empty($variant['price'])) {
                        $price = $variant['sale_price'] ?: $variant['price'];
                        $basePrice = $variant['price'];
                    }
                    if (!empty($variant['sku'])) $sku = $variant['sku'];
                }
            }
            
            // Convert to full URL if needed
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0 && strpos($productImage, 'data:') !== 0) {
                require_once __DIR__ . '/../includes/functions.php';
                $productImage = getBaseUrl() . '/assets/images/uploads/' . $productImage;
            }
            
            $cartItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'name' => $item['name'],
                'price' => $price,
                'base_price' => $basePrice,
                'currency' => $item['currency'] ?? 'USD',
                'image' => $productImage,
                'slug' => $item['slug'] ?? '',
                'variant_attributes' => $variantAttributes,
                'sku' => $sku,
                'stock_quantity' => $variant ? ($variant['stock_quantity'] ?? 0) : ($item['stock_quantity'] ?? 0),
                'stock_status' => $variant ? ($variant['stock_status'] ?? 'in_stock') : ($item['stock_status'] ?? 'in_stock'),
                'is_taxable' => $item['is_taxable'] ?? 0,
                'hsn_code' => $item['hsn_code'] ?? null,
                'gst_percent' => $item['gst_percent'] ?? 0.00
            ];
        }
        
        return $cartItems;
    }
    
    /**
     * Add item to cart
     */
    /**
     * Helper to compare variant attributes robustly
     */
    private function attributesMatch($attr1, $attr2) {
        // Normalize
        if (is_string($attr1)) $attr1 = json_decode($attr1, true);
        if (!is_array($attr1)) $attr1 = [];
        
        if (is_string($attr2)) $attr2 = json_decode($attr2, true);
        if (!is_array($attr2)) $attr2 = [];
        
        // Handle empty cases matches
        if (empty($attr1) && empty($attr2)) return true;
        
        // Sort by key to ensure order doesn't matter
        ksort($attr1);
        ksort($attr2);
        
        // Strict comparison after sorting
        return $attr1 === $attr2;
    }

    /**
     * Add item to cart
     */
    public function addItem($productId, $quantity = 1, $attributes = []) {
        // Get product by 10-digit ID first
        $product = $this->product->getByProductId($productId);
        
        // If not found, try getting by standard ID (legacy support or if passed ID is PK)
        if (!$product && is_numeric($productId)) {
            $product = $this->product->getById($productId);
        }
        
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        if ($product['stock_status'] !== 'in_stock') {
            throw new Exception("Product is out of stock");
        }
        $cartItems = $this->getCart();
        
        // Check if item already exists with SAME attributes
        $found = false;
        foreach ($cartItems as &$item) {
            $itemAttrs = $item['variant_attributes'] ?? [];
            
            // Use robust helper
            if ($item['product_id'] == $product['product_id'] && $this->attributesMatch($itemAttrs, $attributes)) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        // Add new item if not found
        if (!$found) {
            // Default product data
            $productImage = $product['featured_image'];
            $price = $product['sale_price'] ?? $product['price'];
            $sku = $product['sku'] ?? '';
            
            // Try to find specific variant data
            if (!empty($attributes)) {
                $variant = $this->db->fetchOne(
                    "SELECT * FROM product_variants WHERE product_id = ? AND variant_attributes = ?",
                    [$product['product_id'], json_encode($attributes)]
                );
                
                if ($variant) {
                    if (!empty($variant['image'])) $productImage = $variant['image'];
                    if (!empty($variant['price'])) $price = $variant['sale_price'] ?: $variant['price'];
                    if (!empty($variant['sku'])) $sku = $variant['sku'];
                }
            }
            
            // Convert to full URL if needed
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0 && strpos($productImage, 'data:') !== 0) {
                require_once __DIR__ . '/../includes/functions.php';
                $productImage = getBaseUrl() . '/assets/images/uploads/' . $productImage;
            }
            
            $cartItems[] = [
                'product_id' => $product['product_id'],
                'quantity' => $quantity,
                'name' => $product['name'],
                'price' => $price,
                'currency' => $product['currency'] ?? 'USD',
                'image' => $productImage,
                'slug' => $product['slug'] ?? '',
                'variant_attributes' => $attributes,
                'sku' => $sku,
                'stock_quantity' => isset($variant) ? ($variant['stock_quantity'] ?? 0) : ($product['stock_quantity'] ?? 0),
                'stock_status' => isset($variant) ? ($variant['stock_status'] ?? 'in_stock') : ($product['stock_status'] ?? 'in_stock')
            ];
        }
        
        // Save to cookie
        $this->saveCartToCookie($cartItems);
        
        // Save to database if user is logged in
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $this->saveCartToDB($loggedId, $cartItems);
        }
        
        return $cartItems;
    }
    
    /**
     * Update item quantity
     */
    public function updateItem($productId, $quantity, $attributes = []) {
        if ($quantity <= 0) {
            return $this->removeItem($productId, $attributes);
        }
        
        // Normalize productId to 10-digit ID
        $product = $this->product->getByProductId($productId);
        if (!$product && is_numeric($productId)) {
            $product = $this->product->getById($productId);
        }
        if ($product) {
            $productId = $product['product_id'];
        }

        $cartItems = $this->getCart();
        
        foreach ($cartItems as &$item) {
            $itemAttrs = $item['variant_attributes'] ?? [];
            if ($item['product_id'] == $productId && $this->attributesMatch($itemAttrs, $attributes)) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        
        $this->saveCartToCookie($cartItems);
        
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $this->saveCartToDB($loggedId, $cartItems);
        }
        
        return $cartItems;
    }
    
    /**
     * Remove item from cart
     */
    public function removeItem($productId, $attributes = []) {
        // Normalize productId to 10-digit ID
        $product = $this->product->getByProductId($productId);
        if (!$product && is_numeric($productId)) {
            $product = $this->product->getById($productId);
        }
        if ($product) {
            $productId = $product['product_id'];
        }

        $cartItems = $this->getCart();
        
        $cartItems = array_filter($cartItems, function($item) use ($productId, $attributes) {
            $itemAttrs = $item['variant_attributes'] ?? [];
            // Match both ID and Attributes using robust helper
            // We return FALSE to remove it
            $isMatch = ($item['product_id'] == $productId && $this->attributesMatch($itemAttrs, $attributes));
            return !$isMatch;
        });
        
        $cartItems = array_values($cartItems); // Re-index array
        
        $this->saveCartToCookie($cartItems);
        
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $this->saveCartToDB($loggedId, $cartItems);
        }
        
        return $cartItems;
    }
    
    /**
     * Clear cart
     */
    public function clear() {
        // Clear cookie
        if (!headers_sent()) {
            setcookie(CART_COOKIE_NAME, '', time() - 3600, '/');
        }
        unset($_COOKIE[CART_COOKIE_NAME]);
        
        // Clear from database if user is logged in
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            try {
                $storeId = $_SESSION['store_id'] ?? null;
                $this->db->execute(
                    "DELETE FROM cart WHERE user_id = ? AND store_id = ?",
                    [$loggedId, $storeId]
                );
            } catch (Exception $e) {
                // Table might not exist, ignore
                error_log("Could not clear cart from database: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get cart total
     */
    public function getTotal() {
        $cartItems = $this->getCart();
        $total = 0;
        
        foreach ($cartItems as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        return $total;
    }
    
    /**
     * Get cart count
     */
    public function getCount() {
        $cartItems = $this->getCart();
        $count = 0;
        
        foreach ($cartItems as $item) {
            $count += $item['quantity'];
        }
        
        return $count;
    }

    /**
     * Get total tax amount
     */
    public function getTaxTotal() {
        $cartItems = $this->getCart();
        $taxTotal = 0;
        
        foreach ($cartItems as $item) {
            if (!empty($item['is_taxable']) && !empty($item['gst_percent'])) {
                $itemTotal = $item['price'] * $item['quantity'];
                $taxAmount = ($itemTotal * $item['gst_percent']) / 100;
                $taxTotal += $taxAmount;
            }
        }
        
        return $taxTotal;
    }
    
    /**
     * Save cart to cookie
     */
    private function saveCartToCookie($cartItems) {
        $cartJson = json_encode($cartItems);
        // Only set cookie if headers haven't been sent yet
        if (!headers_sent()) {
            $secure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $secure = true;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $secure = true;
            }

            if (empty($cartItems)) {
                setcookie(CART_COOKIE_NAME, '', time() - 3600, '/', '', $secure, false);
            } else {
                setcookie(CART_COOKIE_NAME, $cartJson, time() + CART_COOKIE_EXPIRY, '/', '', $secure, false);
            }
        }
        // Update $_COOKIE for current request
        if (empty($cartItems)) {
            unset($_COOKIE[CART_COOKIE_NAME]);
        } else {
            $_COOKIE[CART_COOKIE_NAME] = $cartJson;
        }
    }
    
    /**
     * Save cart to database
     */
    private function saveCartToDB($userId, $cartItems) {
        $this->dbError = '';
        try {
            // Clear existing cart (Store Specific)
            $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
            $this->db->execute(
                "DELETE FROM cart WHERE user_id = ? AND (store_id = ? OR store_id IS NULL)",
                [$userId, $storeId]
            );

            // Insert new items
            foreach ($cartItems as $item) {
                try {
                    $this->db->insert(
                        "INSERT INTO cart (user_id, product_id, quantity, variant_attributes, store_id) VALUES (?, ?, ?, ?, ?)",
                        [$userId, $item['product_id'], $item['quantity'], json_encode($item['variant_attributes'] ?? []), $storeId]
                    );
                } catch (Exception $e) {
                    $this->dbError .= "Item " . $item['product_id'] . " Error: " . $e->getMessage() . "; ";
                    error_log("Cart::saveCartToDB - Error inserting item: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->dbError .= "General Error: " . $e->getMessage();
            error_log("Cart::saveCartToDB - Error: " . $e->getMessage());
        }
    }

    /**
     * Sync guest cart with database after login
     */
    public function syncCartAfterLogin($userId) {
        $guestCart = [];
        if (isset($_COOKIE[CART_COOKIE_NAME])) {
            $json = $_COOKIE[CART_COOKIE_NAME];
            $cartData = json_decode($json, true);
            if (!is_array($cartData)) {
                $cartData = json_decode(stripslashes($json), true);
            }
            if (!is_array($cartData)) {
                $cartData = json_decode(urldecode($json), true);
            }
            if (is_array($cartData)) {
                $guestCart = $cartData;
            }
        }
        
        if (empty($guestCart)) return;
        
        // Get existing database cart
        $storeId = function_exists('getCurrentStoreId') ? getCurrentStoreId() : ($_SESSION['store_id'] ?? null);
        $dbCart = $this->getCartFromDB($userId, $storeId);
        
        // Merge guest cart into db cart
        foreach ($guestCart as $guestItem) {
            $guestAttrs = $guestItem['variant_attributes'] ?? [];
            $found = false;
            foreach ($dbCart as &$dbItem) {
                $dbAttrs = $dbItem['variant_attributes'] ?? [];
                if ($dbItem['product_id'] == $guestItem['product_id'] && $this->attributesMatch($dbAttrs, $guestAttrs)) {
                    // Update quantity instead of adding
                    // If db has 7 and guest has 1, total should be 8.
                    // But we must ensure getCartFromDB actually returned the items correctly.
                    $dbItem['quantity'] += $guestItem['quantity'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $dbCart[] = $guestItem;
            }
        }
        
        $this->saveCartToDB($userId, $dbCart);
        // Clear guest cart cookie after sync to prevent double-merging if called again
        $this->saveCartToCookie([]);
    }
}

