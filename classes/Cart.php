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
            $cartData = json_decode($_COOKIE[CART_COOKIE_NAME], true);
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
            
            // If image is missing or invalid, fetch from product
            if (empty($item['image']) || $item['image'] === 'null' || $item['image'] === 'undefined' || empty($item['slug'])) {
                $product = $this->product->getById($item['product_id']);
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
    private function getCartFromDB($userId) {
        $items = $this->db->fetchAll(
            "SELECT c.*, p.name, p.price, p.currency, p.sale_price, p.featured_image, p.stock_quantity, p.stock_status, p.slug
             FROM cart c
             INNER JOIN products p ON c.product_id = p.id
             WHERE c.user_id = ?",
            [$userId]
        );
        
        $cartItems = [];
        foreach ($items as $item) {
            // Get product image
            $productImage = '';
            if (!empty($item['featured_image'])) {
                $productImage = $item['featured_image'];
            } else {
                $product = $this->product->getById($item['product_id']);
                if ($product) {
                    $images = json_decode($product['images'] ?? '[]', true);
                    if (!empty($images[0])) {
                        $productImage = $images[0];
                    }
                }
            }
            
            // Convert to full URL if needed (but not for base64 data URIs)
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0 && strpos($productImage, 'data:') !== 0) {
                if (defined('UPLOAD_URL')) {
                    $productImage = UPLOAD_URL . '/' . $productImage;
                } else {
                    require_once __DIR__ . '/../includes/functions.php';
                    $baseUrl = getBaseUrl();
                    $productImage = $baseUrl . '/assets/images/uploads/' . $productImage;
                }
            }
            
            $cartItems[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'name' => $item['name'],
                'price' => $item['sale_price'] ?? $item['price'],
                'currency' => $item['currency'] ?? 'USD',
                'image' => $productImage,
                'slug' => $item['slug'] ?? ''
            ];
        }
        
        return $cartItems;
    }
    
    /**
     * Add item to cart
     */
    public function addItem($productId, $quantity = 1) {
        // Get product
        $product = $this->product->getById($productId);
        if (!$product) {
            error_log("Cart::addItem - Product ID $productId not found in database");
            throw new Exception("Product not found");
        }
        
        error_log("Cart::addItem - Product found: " . ($product['name'] ?? 'N/A') . " (ID: $productId)");
        error_log("Cart::addItem - Stock status: " . ($product['stock_status'] ?? 'N/A'));
        
        if ($product['stock_status'] !== 'in_stock') {
            error_log("Cart::addItem - Product ID $productId is out of stock");
            throw new Exception("Product is out of stock");
        }
        
        // Get current cart
        $cartItems = $this->getCart();
        
        // Check if item already exists
        $found = false;
        foreach ($cartItems as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        // Add new item if not found
        if (!$found) {
            // Get product image
            $productImage = '';
            if (!empty($product['featured_image'])) {
                $productImage = $product['featured_image'];
            } else {
                $images = json_decode($product['images'] ?? '[]', true);
                if (!empty($images[0])) {
                    $productImage = $images[0];
                }
            }
            
            // Convert to full URL if needed (but not for base64 data URIs)
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0 && strpos($productImage, 'data:') !== 0) {
                if (defined('UPLOAD_URL')) {
                    $productImage = UPLOAD_URL . '/' . $productImage;
                } else {
                    require_once __DIR__ . '/../includes/functions.php';
                    $baseUrl = getBaseUrl();
                    $productImage = $baseUrl . '/assets/images/uploads/' . $productImage;
                }
            }
            
            $cartItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'name' => $product['name'],
                'price' => $product['sale_price'] ?? $product['price'],
                'currency' => $product['currency'] ?? 'USD',
                'image' => $productImage,
                'slug' => $product['slug'] ?? ''
            ];
        }
        
        // Save to cookie
        $this->saveCartToCookie($cartItems);
        
        error_log("Cart::addItem - Cart items after save: " . count($cartItems) . " items");
        error_log("Cart::addItem - Returning cart: " . json_encode($cartItems));
        
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
    public function updateItem($productId, $quantity) {
        if ($quantity <= 0) {
            return $this->removeItem($productId);
        }
        
        $cartItems = $this->getCart();
        
        foreach ($cartItems as &$item) {
            if ($item['product_id'] == $productId) {
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
    public function removeItem($productId) {
        $cartItems = $this->getCart();
        
        $cartItems = array_filter($cartItems, function($item) use ($productId) {
            return $item['product_id'] != $productId;
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
                $this->db->execute(
                    "DELETE FROM cart WHERE user_id = ?",
                    [$loggedId]
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
            if (empty($cartItems)) {
                setcookie(CART_COOKIE_NAME, '', time() - 3600, '/');
            } else {
                setcookie(CART_COOKIE_NAME, $cartJson, time() + CART_COOKIE_EXPIRY, '/');
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
        // Clear existing cart
        $this->db->execute(
            "DELETE FROM cart WHERE user_id = ?",
            [$userId]
        );
        
        // Insert new items
        foreach ($cartItems as $item) {
            $this->db->insert(
                "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)",
                [$userId, $item['product_id'], $item['quantity']]
            );
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
            $found = false;
            foreach ($dbCart as &$dbItem) {
                if ($dbItem['product_id'] == $guestItem['product_id']) {
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

