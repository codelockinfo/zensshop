<?php
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

$auth = new Auth();
if (!$auth->isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

// Determine Store ID
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId && isset($_SESSION['user_email'])) {
     $storeUser = $db->fetchOne("SELECT store_id FROM users WHERE email = ?", [$_SESSION['user_email']]);
     $storeId = $storeUser['store_id'] ?? null;
}

header('Content-Type: application/json');

if ($action === 'get_brands') {
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands' AND store_id = ?", [$storeId]);
    $brands = $result ? json_decode($result['setting_value'], true) : [];
    echo json_encode(['success' => true, 'brands' => $brands]);
} 
elseif ($action === 'add_brand') {
    $data = json_decode(file_get_contents('php://input'), true);
    $brand = trim($data['brand'] ?? '');
    
    if (empty($brand)) {
        echo json_encode(['success' => false, 'message' => 'Brand name is required']);
        exit;
    }
    
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands' AND store_id = ?", [$storeId]);
    $brands = $result ? json_decode($result['setting_value'], true) : [];
    
    if (in_array($brand, $brands)) {
        echo json_encode(['success' => false, 'message' => 'Brand already exists']);
        exit;
    }
    
    $brands[] = $brand;
    $brandsJson = json_encode(array_values($brands));
    
    if ($result) {
        $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'Brands' AND store_id = ?", [$brandsJson, $storeId]);
    } else {
        $db->execute("INSERT INTO site_settings (setting_key, setting_value, store_id) VALUES ('Brands', ?, ?)", [$brandsJson, $storeId]);
    }
    
    echo json_encode(['success' => true, 'brands' => $brands]);
} 
elseif ($action === 'remove_brand') {
    $data = json_decode(file_get_contents('php://input'), true);
    $brand = $data['brand'] ?? '';
    
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands' AND store_id = ?", [$storeId]);
    $brands = $result ? json_decode($result['setting_value'], true) : [];
    
    if (($key = array_search($brand, $brands)) !== false) {
        unset($brands[$key]);
    }
    
    $brandsJson = json_encode(array_values($brands));
    $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'Brands' AND store_id = ?", [$brandsJson, $storeId]);
    
    echo json_encode(['success' => true, 'brands' => array_values($brands)]);
} 
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
