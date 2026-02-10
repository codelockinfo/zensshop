<?php
require_once __DIR__ . '/../../classes/Auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

header('Content-Type: application/json');

// CKEditor 5 Simple Upload Adapter expects:
// Success: { urls: { default: "http://..." } }
// Error: { error: { message: "..." } }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload'])) {
    $uploadDir = __DIR__ . '/../../assets/images/blogs/content/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $file = $_FILES['upload'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $name = time() . '_' . uniqid() . '.' . $ext;
            $target = $uploadDir . $name;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                require_once __DIR__ . '/../../includes/functions.php';
                $baseUrl = getBaseUrl();
                $basePath = parse_url($baseUrl, PHP_URL_PATH);
                // Ensure basePath is just / or /subdir (no trailing slash)
                $basePath = rtrim($basePath ?? '', '/');
                
                echo json_encode([
                    'url' => $basePath . '/assets/images/blogs/content/' . $name
                ]);
            } else {
                echo json_encode(['error' => ['message' => 'Failed to move uploaded file.']]);
            }
        } else {
            echo json_encode(['error' => ['message' => 'Invalid file type. Only images allowed.']]);
        }
    } else {
        echo json_encode(['error' => ['message' => 'Upload error: ' . $file['error']]]);
    }
} else {
    echo json_encode(['error' => ['message' => 'No file uploaded.']]);
}
?>
