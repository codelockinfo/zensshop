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
        
        // If user is logged in, sync with database
        if (isset($_SESSION['user_id'])) {
            $dbCart = $this->getCartFromDB($_SESSION['user_id']);
            if (!empty($dbCart)) {
                $cartItems = $dbCart;
                // Update cookie
                $this->saveCartToCookie($cartItems);
            }
        }
        
        // Ensure all items have required fields and proper image URLs
        foreach ($cartItems as &$item) {
            // Ensure product_id exists
            if (empty($item['product_id'])) {
                continue;
            }
            
            // If image is missing or invalid, fetch from product
            if (empty($item['image']) || $item['image'] === 'null' || $item['image'] === 'undefined') {
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
                    
                    // Ensure name and price are set
                    if (empty($item['name'])) {
                        $item['name'] = $product['name'];
                    }
                    if (empty($item['price'])) {
                        $item['price'] = $product['sale_price'] ?? $product['price'];
                    }
                }
            }
            
            // Convert to full URL if needed
            if (!empty($item['image']) && strpos($item['image'], 'http') !== 0 && strpos($item['image'], '/') !== 0) {
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
            "SELECT c.*, p.name, p.price, p.sale_price, p.featured_image, p.stock_quantity, p.stock_status
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
            
            // Convert to full URL if needed
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0) {
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
                'image' => $productImage
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
            throw new Exception("Product not found");
        }
        
        if ($product['stock_status'] !== 'in_stock') {
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
            
            // Convert to full URL if needed
            if (!empty($productImage) && strpos($productImage, 'http') !== 0 && strpos($productImage, '/') !== 0) {
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
                'image' => $productImage
            ];
        }
        
        // Save to cookie
        $this->saveCartToCookie($cartItems);
        
        // Save to database if user is logged in
        if (isset($_SESSION['user_id'])) {
            $this->saveCartToDB($_SESSION['user_id'], $cartItems);
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
        
        if (isset($_SESSION['user_id'])) {
            $this->saveCartToDB($_SESSION['user_id'], $cartItems);
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
        
        if (isset($_SESSION['user_id'])) {
            $this->saveCartToDB($_SESSION['user_id'], $cartItems);
        }
        
        return $cartItems;
    }
    
    /**
     * Clear cart
     */
    public function clear() {
        // Only clear cookie if headers haven't been sent yet
        if (!headers_sent()) {
            setcookie(CART_COOKIE_NAME, '', time() - 3600, '/');
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->db->execute(
                "DELETE FROM cart WHERE user_id = ?",
                [$_SESSION['user_id']]
            );
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
            setcookie(CART_COOKIE_NAME, $cartJson, time() + CART_COOKIE_EXPIRY, '/');
        }
        // Always update $_COOKIE for current request
        $_COOKIE[CART_COOKIE_NAME] = $cartJson;
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
                "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = ?",
                [$userId, $item['product_id'], $item['quantity'], $item['quantity']]
            );
        }
    }
}

