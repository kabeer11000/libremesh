<?php
// api/download.php - Handles user file downloads

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check for file_id parameter
$fileId = $_GET['file_id'] ?? null;
if (!$fileId) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing file_id parameter.']);
    exit;
}

$node = new Node();

// Handle the download, potentially fetching from peers
$fileContent = $node->handleClientDownload($fileId);

if ($fileContent === false) {
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'File not found or could not be retrieved from the network.']);
} else {
    // Success - serve the file content
    // Basic headers - need proper mime type detection
    header('Content-Type: application/octet-stream'); // Generic binary type
    header('Content-Disposition: attachment; filename="' . basename($fileId) . '"'); // Force download with fileId as name
    header('Content-Length: ' . strlen($fileContent));
    // Add more headers for caching, etc.

    echo $fileContent; // Output the file content
    exit; // Terminate script after sending file
}
?>