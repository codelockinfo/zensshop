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
            $wishlistData = json_decode($_COOKIE[WISHLIST_COOKIE_NAME], true);
            if (is_array($wishlistData)) {
                $wishlistItems = $wishlistData;
            }
        }
        
        // If user is logged in, sync with database
        if (isset($_SESSION['user_id'])) {
            $dbWishlist = $this->getWishlistFromDB($_SESSION['user_id']);
            if (!empty($dbWishlist)) {
                $wishlistItems = $dbWishlist;
                // Update cookie
                $this->saveWishlistToCookie($wishlistItems);
            }
        }
        
        // Ensure all items have required fields and proper image URLs
        foreach ($wishlistItems as &$item) {
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
                    if (empty($item['slug'])) {
                        $item['slug'] = $product['slug'];
                    }
                }
            }
            
            // Convert to full URL if needed
            if (!empty($item['image']) && strpos($item['image'], 'http') !== 0 && strpos($item['image'], '/') !== 0 && strpos($item['image'], 'data:') !== 0) {
                $item['image'] = '/oecom/assets/images/uploads/' . $item['image'];
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
            "SELECT w.*, p.name, p.price, p.sale_price, p.featured_image, p.slug, p.rating, p.review_count
             FROM wishlist w
             INNER JOIN products p ON w.product_id = p.id
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
                $productImage = '/oecom/assets/images/uploads/' . $productImage;
            }
            
            $wishlistItems[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['sale_price'] ?? $item['price'],
                'image' => $productImage,
                'slug' => $item['slug'],
                'rating' => $item['rating'] ?? 0,
                'review_count' => $item['review_count'] ?? 0
            ];
        }
        
        return $wishlistItems;
    }
    
    /**
     * Add item to wishlist
     */
    public function addItem($productId) {
        // Get product
        $product = $this->product->getById($productId);
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
            $productImage = '/oecom/assets/images/uploads/' . $productImage;
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
        if (isset($_SESSION['user_id'])) {
            $this->saveWishlistToDB($_SESSION['user_id'], $wishlistItems);
        }
        
        return $wishlistItems;
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
        
        if (isset($_SESSION['user_id'])) {
            $this->saveWishlistToDB($_SESSION['user_id'], $wishlistItems);
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
            setcookie(WISHLIST_COOKIE_NAME, $wishlistJson, time() + WISHLIST_COOKIE_EXPIRY, '/');
        }
        // Always update $_COOKIE for current request
        $_COOKIE[WISHLIST_COOKIE_NAME] = $wishlistJson;
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
            $this->db->insert(
                "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP",
                [$userId, $item['product_id']]
            );
        }
    }
}

