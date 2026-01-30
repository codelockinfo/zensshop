<?php
/**
 * Reviews API
 * Handles review submission and retrieval
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Submit a new review
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            if (empty($data['product_id']) || empty($data['user_name']) || empty($data['user_email']) || empty($data['rating']) || empty($data['comment'])) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            $productId = intval($data['product_id']);
            $userName = trim($data['user_name']);
            $userEmail = filter_var(trim($data['user_email']), FILTER_VALIDATE_EMAIL);
            $rating = intval($data['rating']);
            $title = !empty($data['title']) ? trim($data['title']) : null;
            $comment = trim($data['comment']);
            
            // Validate rating
            if ($rating < 1 || $rating > 5) {
                echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
                exit;
            }
            
            // Validate email
            if (!$userEmail) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address']);
                exit;
            }
            
            // Check if product exists
            $product = $db->fetchOne("SELECT id FROM products WHERE id = ? AND status = 'active'", [$productId]);
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit;
            }
            
            // Determine Store ID
            $storeId = $_SESSION['store_id'] ?? null;
            if (!$storeId && isset($_SESSION['user_email'])) {
                 $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
                 $storeId = $storeUser['store_id'] ?? null;
            }
            if (!$storeId) {
                 $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE store_id IS NOT NULL LIMIT 1");
                 $storeId = $storeUser['store_id'] ?? null;
            }

            // Insert review
            $reviewId = $db->insert(
                "INSERT INTO reviews (product_id, user_name, user_email, rating, title, comment, status, store_id) 
                 VALUES (?, ?, ?, ?, ?, ?, 'approved', ?)",
                [$productId, $userName, $userEmail, $rating, $title, $comment, $storeId]
            );
            
            // Update product rating and review count
            $reviews = $db->fetchAll(
                "SELECT rating FROM reviews WHERE product_id = ? AND status = 'approved'",
                [$productId]
            );
            
            if (!empty($reviews)) {
                $totalRating = 0;
                foreach ($reviews as $review) {
                    $totalRating += $review['rating'];
                }
                $avgRating = $totalRating / count($reviews);
                $reviewCount = count($reviews);
                
                $db->execute(
                    "UPDATE products SET rating = ?, review_count = ? WHERE id = ?",
                    [$avgRating, $reviewCount, $productId]
                );
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Review submitted successfully',
                'review_id' => $reviewId
            ]);
            break;
            
        case 'GET':
            // Get reviews for a product
            $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
            $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
            
            if (!$productId) {
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                exit;
            }
            
            // Determine sort order
            $orderBy = 'ORDER BY created_at DESC';
            switch ($sortBy) {
                case 'oldest':
                    $orderBy = 'ORDER BY created_at ASC';
                    break;
                case 'highest':
                    $orderBy = 'ORDER BY rating DESC, created_at DESC';
                    break;
                case 'lowest':
                    $orderBy = 'ORDER BY rating ASC, created_at DESC';
                    break;
            }
            
            $reviews = $db->fetchAll(
                "SELECT id, user_name, user_email, rating, title, comment, created_at 
                 FROM reviews 
                 WHERE product_id = ? AND status = 'approved' 
                 $orderBy",
                [$productId]
            );
            
            echo json_encode([
                'success' => true,
                'reviews' => $reviews
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

