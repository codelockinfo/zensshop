<?php
/**
 * Products API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Product.php';
require_once __DIR__ . '/../../classes/RetryHandler.php';

$auth = new Auth();
$auth->requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$product = new Product();
$retryHandler = new RetryHandler();

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            if ($id) {
                $item = $product->getById($id);
                echo json_encode(['success' => true, 'product' => $item]);
            } else {
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'category_id' => $_GET['category_id'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                $products = $product->getAll($filters);
                echo json_encode(['success' => true, 'products' => $products]);
            }
            break;
            
        case 'POST':
            $data = $input;
            $productId = $retryHandler->executeWithRetry(
                function() use ($product, $data) {
                    return $product->create($data);
                },
                'Create Product',
                ['data' => $data]
            );
            echo json_encode(['success' => true, 'product_id' => $productId, 'message' => 'Product created successfully']);
            break;
            
        case 'PUT':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Product ID is required');
            }
            $data = $input;
            unset($data['id']);
            $retryHandler->executeWithRetry(
                function() use ($product, $id, $data) {
                    return $product->update($id, $data);
                },
                'Update Product',
                ['id' => $id, 'data' => $data]
            );
            echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            break;
            
        case 'DELETE':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('Product ID is required');
            }
            $product->delete($id);
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

