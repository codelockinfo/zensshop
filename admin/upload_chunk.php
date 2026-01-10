<?php
require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$uploadDir = __DIR__ . '/../assets/images/banner/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileName = $_POST['file_name'] ?? '';
    // Unique session ID for this upload to avoid collisions
    $uploadId = $_POST['upload_id'] ?? '';
    $chunkIndex = intval($_POST['chunk_index'] ?? 0);
    $totalChunks = intval($_POST['total_chunks'] ?? 0);
    
    if (!$fileName || !$uploadId) {
        echo json_encode(['error' => 'Missing metadata']);
        exit;
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($fileName));
    $tempPath = $uploadDir . 'temp_' . $uploadId . '_' . $safeName;
    
    if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
        $chunk = fopen($_FILES['chunk']['tmp_name'], 'rb');
        // 'ab' = Append Binary. If index 0, we can truncate but usually client handles sequential.
        // To be safe, if index 0, verify file doesn't exist or delete it?
        if ($chunkIndex === 0 && file_exists($tempPath)) {
            unlink($tempPath);
        }
        
        $handle = fopen($tempPath, 'ab'); 
        
        if ($chunk && $handle) {
            stream_copy_to_stream($chunk, $handle);
            fclose($chunk);
            fclose($handle);
            
            if ($chunkIndex === $totalChunks - 1) {
                // Finished
                $finalName = time() . '_' . $safeName;
                if (rename($tempPath, $uploadDir . $finalName)) {
                    echo json_encode(['status' => 'done', 'path' => 'assets/images/banner/' . $finalName]);
                } else {
                     echo json_encode(['error' => 'Rename failed']);
                }
            } else {
                echo json_encode(['status' => 'chunk_ok', 'index' => $chunkIndex]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'File write error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'No chunk uploaded']);
    }
}
?>
