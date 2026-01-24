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

header('Content-Type: application/json');

if ($action === 'get_brands') {
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands'");
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
    
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands'");
    $brands = $result ? json_decode($result['setting_value'], true) : [];
    
    if (in_array($brand, $brands)) {
        echo json_encode(['success' => false, 'message' => 'Brand already exists']);
        exit;
    }
    
    $brands[] = $brand;
    $brandsJson = json_encode(array_values($brands));
    
    if ($result) {
        $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'Brands'", [$brandsJson]);
    } else {
        $db->execute("INSERT INTO site_settings (setting_key, setting_value) VALUES ('Brands', ?)", [$brandsJson]);
    }
    
    echo json_encode(['success' => true, 'brands' => $brands]);
} 
elseif ($action === 'remove_brand') {
    $data = json_decode(file_get_contents('php://input'), true);
    $brand = $data['brand'] ?? '';
    
    $result = $db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = 'Brands'");
    $brands = $result ? json_decode($result['setting_value'], true) : [];
    
    if (($key = array_search($brand, $brands)) !== false) {
        unset($brands[$key]);
    }
    
    $brandsJson = json_encode(array_values($brands));
    $db->execute("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'Brands'", [$brandsJson]);
    
    echo json_encode(['success' => true, 'brands' => array_values($brands)]);
} 
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
