<?php
/**
 * Image Upload API
 */

require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$baseUrl = getBaseUrl();

$auth = new Auth();
$auth->requireLogin();

// Upload directory
$uploadDir = __DIR__ . '/../../assets/images/products/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Create uploads subdirectory if needed
$uploadsDir = __DIR__ . '/../../assets/images/uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['image'])) {
            throw new Exception('No image file provided');
        }
        
        $file = $_FILES['image'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Validate file type
        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
            'video/x-m4v', 'video/mpeg', 'video/3gpp', 'video/avi', 'video/x-msvideo'
        ];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Fallback for generic binary types if extension is valid video
        if ($mimeType === 'application/octet-stream') {
             $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
             if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v', 'kv'])) {
                 $mimeType = 'video/' . $ext; // Fake it to pass validation if extension is whitelisted
                 if ($ext === 'mov') $mimeType = 'video/quicktime';
                 if ($ext === 'mp4') $mimeType = 'video/mp4';
             }
        }
        
        if (!in_array($mimeType, $allowedTypes) && strpos($mimeType, 'video/') !== 0 && strpos($mimeType, 'image/') !== 0) {
            throw new Exception('Invalid file type (' . $mimeType . '). Only Images and Videos are allowed.');
        }
        
        // Validate file size (max 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception('File size exceeds 50MB limit.');
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . uniqid() . '_' . time() . '.' . $extension;
        $destination = $uploadsDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to save uploaded file.');
        }
        
        // Return relative path
        $relativePath = $baseUrl . '/assets/images/uploads/' . $filename;
        
        echo json_encode([
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}


