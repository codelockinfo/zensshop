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
            $dbCart = $this->getCartFromDB($loggedId);
            $cartItems = $dbCart;
            // Sync cookie with DB
            $this->saveCartToCookie($cartItems);
        }
        
        // Ensure all items have required fields and proper image URLs
        foreach ($cartItems as &$item) {
            // Ensure product_id exists
            if (empty($item['product_id'])) {
                continue;
            }
            
            if (empty($item['image']) || $item['image'] === 'null' || $item['image'] === 'undefined' || empty($item['slug'])) {
                $productId = $item['product_id'];
                $product = $this->product->getByProductId($productId);
                
                // Fallback for old auto-increment IDs
                if (!$product && is_numeric($productId) && $productId < 1000000000) {
                    $product = $this->product->getById($productId);
                    if ($product) {
                        $item['product_id'] = $product['product_id']; // Use 10-digit ID from now on
                    }
                }

                if ($product) {
                    if (!empty($product['featured_image'])) {
                        $item['image'] = $product['featured_image'];
                    } else {
                        $images = json_decode($product['images'] ?? '[]', true);
                        if (!empty($images[0])) {
                            $item['image'] = $images[0];
                        }
                    }
                    
                    // Ensure name, price, and slug are set
                    if (empty($item['name'])) {
                        $item['name'] = $product['name'];
                    }
                    if (empty($item['price'])) {
                        $item['price'] = $product['sale_price'] ?? $product['price'];
                    }
                    if (empty($item['slug'])) {
                        $item['slug'] = $product['slug'] ?? '';
                    }
                    if (empty($item['currency'])) {
                        $item['currency'] = $product['currency'] ?? 'USD';
                    }
                }
            }
            
            // Convert to full URL if needed
            if (!empty($item['image']) && strpos($item['image'], 'http') !== 0 && strpos($item['image'], '/') !== 0 && strpos($item['image'], 'data:') !== 0) {
                if (defined('UPLOAD_URL')) {
                    $item['image'] = UPLOAD_URL . '/' . $item['image'];
                } else {
                    require_once __DIR__ . '/../includes/functions.php';
                    $baseUrl = getBaseUrl();
                    $item['image'] = $baseUrl . '/assets/images/uploads/' . $item['image'];
                }
            }
        }
        unset($item);
        
        // Remove invalid items
        $cartItems = array_filter($cartItems, function($item) {
            return !empty($item['product_id']) && !empty($item['name']);
        });
        $cartItems = array_values($cartItems); // Re-index
        
        return $cartItems;
    }
    
    /**
     * Get cart from database
     */
    private function getCartFromDB($userId, $storeId = null) {
        if (!$storeId) $storeId = $_SESSION['store_id'] ?? null;
        $items = $this->db->fetchAll(
            "SELECT c.*, p.name, p.price, p.currency, p.sale_price, p.featured_image, p.stock_quantity, p.stock_status, p.slug
             FROM cart c
             LEFT JOIN products p ON (c.product_id = p.product_id OR (c.product_id = p.id AND c.product_id < 1000000000))
             WHERE c.user_id = ? AND c.store_id = ?",
            [$userId, $storeId]
        );
        
        $cartItems = [];
        foreach ($items as $item) {
            $variantAttributes = !empty($item['variant_attributes']) ? json_decode($item['variant_attributes'], true) : [];
            
            // Get base product data
            $productImage = $item['featured_image'];
            $price = $item['sale_price'] ?? $item['price'];
            $sku = $item['sku'] ?? '';
            
            // If variant attributes exist, try to get specific variant data
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
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'name' => $item['name'],
                'price' => $price,
                'currency' => $item['currency'] ?? 'USD',
                'image' => $productImage,
                'slug' => $item['slug'] ?? '',
                'variant_attributes' => $variantAttributes,
                'sku' => $sku
            ];
        }
        
        return $cartItems;
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
            // Compare against both arg and proper ID to be safe, or just proper ID if we are sure items are normalized
            // Best to compare against the ID we intend to store
            if ($item['product_id'] == $product['product_id'] && $itemAttrs == $attributes) {
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
                'sku' => $sku
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
        
        $cartItems = $this->getCart();
        
        foreach ($cartItems as &$item) {
            $itemAttrs = $item['variant_attributes'] ?? [];
            if ($item['product_id'] == $productId && $itemAttrs == $attributes) {
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
        $cartItems = $this->getCart();
        
        $cartItems = array_filter($cartItems, function($item) use ($productId, $attributes) {
            $itemAttrs = $item['variant_attributes'] ?? [];
            return !($item['product_id'] == $productId && $itemAttrs == $attributes);
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
            // Determine Store ID
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId) {
                if (isset($_SESSION['user_email'])) {
                    $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                    $storeId = $storeUser['store_id'] ?? null;
                } elseif (isset($_SESSION['customer_email'])) {
                    $storeCustomer = $this->db->fetchOne("SELECT store_id FROM customers WHERE email = ?", [$_SESSION['customer_email']]);
                    $storeId = $storeCustomer['store_id'] ?? null;
                }
            }
            if (!$storeId) {
                // Try to get from users table
                try {
                     $storeUser = $this->db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
                     $storeId = $storeUser['store_id'] ?? null;
                } catch(Exception $ex) {}
            }

            // Clear existing cart
            $this->db->execute(
                "DELETE FROM cart WHERE user_id = ? AND store_id = ?",
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
            $cartData = json_decode($_COOKIE[CART_COOKIE_NAME], true);
            if (is_array($cartData)) {
                $guestCart = $cartData;
            }
        }
        
        if (empty($guestCart)) return;
        
        $dbCart = $this->getCartFromDB($userId);
        
        // Merge guest cart into db cart
        foreach ($guestCart as $guestItem) {
            $guestAttrs = $guestItem['variant_attributes'] ?? [];
            $found = false;
            foreach ($dbCart as &$dbItem) {
                $dbAttrs = $dbItem['variant_attributes'] ?? [];
                if ($dbItem['product_id'] == $guestItem['product_id'] && $dbAttrs == $guestAttrs) {
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
        $this->saveCartToCookie($dbCart);
    }
}

