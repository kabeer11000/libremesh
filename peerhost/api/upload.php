<?php
// api/upload.php - Handles user file uploads

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Basic check if file was uploaded via HTTP POST
if (empty($_FILES['file_upload']['tmp_name'])) {
     http_response_code(400); // Bad Request
    echo json_encode(['error' => 'No file uploaded or invalid file input name. Use "file_upload".']);
    exit;
}

$node = new Node();

// Handle the upload and distribution
$uploadResult = $node->handleClientUpload($_FILES['file_upload']);

if ($uploadResult === false) {
     http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'File upload and distribution failed.']);
} else {
    http_response_code(200); // OK
    echo json_encode([
        'success' => $uploadResult['success'],
        'message' => $uploadResult['success'] ? 'File uploaded and scheduled for distribution.' : 'File upload failed or could not achieve full replication.',
        'file_id' => $uploadResult['file_id'],
        'replication_status' => $uploadResult['replication_status'], // Status per peer
        'node_id' => get_config('NODE_ID'), // Tell client which node they hit
    ]);
}
?>