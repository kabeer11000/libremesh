<?php
// api/delete.php - Marks a file for deletion

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow GET and POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check for file_id parameter
$fileId = $_GET['file_id'] ?? $_POST['file_id'] ?? null;
if (!$fileId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing file_id parameter']);
    exit;
}

$node = new Node();
$success = $node->markFileDeleted($fileId);

if ($success) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'file_id' => $fileId,
        'message' => 'File marked for deletion',
    ]);
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'File not found or could not be marked for deletion',
    ]);
}
?>
