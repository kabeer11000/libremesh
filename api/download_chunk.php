<?php
// api/download_chunk.php - Node <-> Node: serves file data/chunk to another node

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow authenticated GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !Util::isAuthenticated()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access or invalid method']);
    exit;
}

// Check for required parameters
$fileId = $_GET['file_id'] ?? null;
$chunkId = $_GET['chunk_id'] ?? '0'; // Default to chunk 0 for simple replication

if (!$fileId || $chunkId === null) { // Check chunkId specifically against null if it's expected
     http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing file_id or chunk_id parameter.']);
    exit;
}

$node = new Node();

// Handle the request for the specific chunk
$chunkContent = $node->handleIncomingChunkRequest($fileId, $chunkId); // This method handles local retrieval/unarchiving

if ($chunkContent === false) {
    http_response_code(404); // Not Found (or internal error if logging indicates)
    echo json_encode(['error' => 'Chunk not found or not available on this node.']);
} else {
    // Success - serve the chunk content
    header('Content-Type: application/octet-stream'); // Generic binary type
    header('Content-Length: ' . strlen($chunkContent));
    // No Content-Disposition typically for internal chunk transfers

    echo $chunkContent; // Output the chunk content
    exit; // Terminate script
}
?>