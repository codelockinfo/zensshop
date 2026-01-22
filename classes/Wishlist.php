<?php
/**
 * Wishlist Management Class
 * Handles wishlist operations with cookie and database storage
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Product.php';

class Wishlist {
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
     * Get wishlist items from cookie or session
     */
    public function getWishlist() {
        $wishlistItems = [];
        
        // Try to get from cookie first
        if (isset($_COOKIE[WISHLIST_COOKIE_NAME])) {
            $json = $_COOKIE[WISHLIST_COOKIE_NAME];
            
            // Try explicit decoding if needed
            $wishlistData = json_decode($json, true);
            
            if (!is_array($wishlistData)) {
                $wishlistData = json_decode(stripslashes($json), true);
            }
            
            if (!is_array($wishlistData)) {
                $wishlistData = json_decode(urldecode($json), true);
            }

            if (is_array($wishlistData) && !empty($wishlistData)) {
                $wishlistItems = $wishlistData;
            }
        }
        
        // If user is logged in, strictly use database
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $wishlistItems = $this->getWishlistFromDB($loggedId);
            // Sync cookie with DB so that local count remains accurate
            $this->saveWishlistToCookie($wishlistItems);
        }
        
        // Ensure all items have required fields and proper image URLs
        foreach ($wishlistItems as &$item) {
            // Ensure product_id exists
            if (empty($item['product_id'])) {
                continue;
            }
            
            // If image is missing or invalid, fetch from product
            if (empty($item['image']) || $item['image'] === 'null' || $item['image'] === 'undefined') {
                $productId = $item['product_id'];
                $product = $this->product->getByProductId($productId);

                // Fallback for old auto-increment IDs
                if (!$product && is_numeric($productId) && $productId < 1000000000) {
                    $product = $this->product->getById($productId);
                    if ($product) {
                        $item['product_id'] = $product['product_id'];
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
                    
                    // Ensure name and price are set
                    if (empty($item['name'])) {
                        $item['name'] = $product['name'];
                    }
                    if (empty($item['price'])) {
                        $item['price'] = $product['sale_price'] ?? $product['price'];
                    }
                    if (empty($item['slug'])) {
                        $item['slug'] = $product['slug'];
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
        $wishlistItems = array_filter($wishlistItems, function($item) {
            return !empty($item['product_id']) && !empty($item['name']);
        });
        $wishlistItems = array_values($wishlistItems); // Re-index
        
        return $wishlistItems;
    }
    
    /**
     * Get wishlist from database
     */
    private function getWishlistFromDB($userId) {
        $items = $this->db->fetchAll(
            "SELECT w.*, p.name, p.price, p.sale_price, p.featured_image, p.slug, p.rating, p.review_count, p.sku
             FROM wishlist w
             LEFT JOIN products p ON (w.product_id = p.product_id OR (w.product_id = p.id AND w.product_id < 1000000000))
             WHERE w.user_id = ?",
            [$userId]
        );
        
        $wishlistItems = [];
        foreach ($items as $item) {
            // Get product image
            $productImage = '';
            if (!empty($item['featured_image'])) {
                $productImage = $item['featured_image'];
            } else {
                $product = $this->product->getByProductId($item['product_id']);
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
            
            $wishlistItems[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['sale_price'] ?? $item['price'],
                'image' => $productImage,
                'slug' => $item['slug'],
                'rating' => $item['rating'] ?? 0,
                'review_count' => $item['review_count'] ?? 0,
                'sku' => $item['sku'],
                'featured_image' => $item['featured_image']
            ];
        }
        
        return $wishlistItems;
    }
    
    /**
     * Add item to wishlist
     */
    public function addItem($productId) {
        // Get product by 10-digit ID
        $product = $this->product->getByProductId($productId);
        if (!$product) {
            throw new Exception("Product not found");
        }
        
        // Get current wishlist
        $wishlistItems = $this->getWishlist();
        
        // Check if item already exists
        foreach ($wishlistItems as $item) {
            if ($item['product_id'] == $productId) {
                // Already in wishlist
                return $wishlistItems;
            }
        }
        
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
        
        // Add new item
        $wishlistItems[] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'price' => $product['sale_price'] ?? $product['price'],
            'image' => $productImage,
            'slug' => $product['slug'],
            'rating' => $product['rating'] ?? 0,
            'review_count' => $product['review_count'] ?? 0
        ];
        
        // Save to cookie
        $this->saveWishlistToCookie($wishlistItems);
        
        // Save to database if user is logged in
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            error_log("Saving wishlist to DB for user: " . $loggedId);
            $this->saveWishlistToDB($loggedId, $wishlistItems);
        } else {
            error_log("User not logged in, skipping DB save for wishlist");
        }
        
        return $wishlistItems;
    }

    /**
     * Get items for a specific user
     */
    public function getItems($userId) {
        return $this->getWishlistFromDB($userId);
    }
    
    /**
     * Remove item from wishlist
     */
    public function removeItem($productId) {
        $wishlistItems = $this->getWishlist();
        
        $wishlistItems = array_filter($wishlistItems, function($item) use ($productId) {
            return $item['product_id'] != $productId;
        });
        
        $wishlistItems = array_values($wishlistItems); // Re-index array
        
        $this->saveWishlistToCookie($wishlistItems);
        
        $loggedId = $_SESSION['customer_id'] ?? null;
        if ($loggedId) {
            $this->saveWishlistToDB($loggedId, $wishlistItems);
        }
        
        return $wishlistItems;
    }
    
    /**
     * Check if product is in wishlist
     */
    public function isInWishlist($productId) {
        $wishlistItems = $this->getWishlist();
        foreach ($wishlistItems as $item) {
            if ($item['product_id'] == $productId) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get wishlist count
     */
    public function getCount() {
        $wishlistItems = $this->getWishlist();
        return count($wishlistItems);
    }
    
    /**
     * Save wishlist to cookie
     */
    private function saveWishlistToCookie($wishlistItems) {
        $wishlistJson = json_encode($wishlistItems);
        // Only set cookie if headers haven't been sent yet
        if (!headers_sent()) {
            $secure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $secure = true;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $secure = true;
            }

            if (empty($wishlistItems)) {
                setcookie(WISHLIST_COOKIE_NAME, '', time() - 3600, '/', '', $secure, false);
            } else {
                setcookie(WISHLIST_COOKIE_NAME, $wishlistJson, time() + WISHLIST_COOKIE_EXPIRY, '/', '', $secure, false);
            }
        }
        // Update $_COOKIE for current request
        if (empty($wishlistItems)) {
            unset($_COOKIE[WISHLIST_COOKIE_NAME]);
        } else {
            $_COOKIE[WISHLIST_COOKIE_NAME] = $wishlistJson;
        }
    }
    
    /**
     * Save wishlist to database
     */
    private function saveWishlistToDB($userId, $wishlistItems) {
        // Clear existing wishlist
        $this->db->execute(
            "DELETE FROM wishlist WHERE user_id = ?",
            [$userId]
        );
        
        // Insert new items
        foreach ($wishlistItems as $item) {
            try {
                $this->db->insert(
                    "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                    [$userId, $item['product_id']]
                );
            } catch (Exception $e) {
                error_log("Error saving wishlist item to DB: " . $e->getMessage());
            }
        }
    }
    /**
     * Sync guest wishlist (cookie) with user wishlist (database) after login
     */
    public function syncWishlistAfterLogin($userId) {
        // Get guest items from cookie
        if (isset($_COOKIE[WISHLIST_COOKIE_NAME])) {
            $cookieItems = json_decode($_COOKIE[WISHLIST_COOKIE_NAME], true);
            if (is_array($cookieItems) && !empty($cookieItems)) {
                // Add each item to the database
                foreach ($cookieItems as $item) {
                    if (!empty($item['product_id'])) {
                        $this->db->insert(
                            "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                            [$userId, $item['product_id']]
                        );
                    }
                }
            }
        }
    }
}


