<?php
// api/upload_chunk.php - Node <-> Node: receives file data/chunk from another node

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow authenticated POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Util::isAuthenticated()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access or invalid method']);
    exit;
}

// Check for required parameters
$fileId = $_POST['file_id'] ?? null;
$chunkId = $_POST['chunk_id'] ?? null;
$checksum = $_POST['checksum'] ?? null;
$sourceNodeId = $_POST['source_node_id'] ?? 'unknown'; // ID of the node sending

// Check if file data was actually uploaded in the request (using the 'file_data' name from Node.php)
if (empty($_FILES['file_data']['tmp_name']) || !$fileId || !$chunkId || !$checksum) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing file data or required parameters (file_id, chunk_id, checksum).']);
    exit;
}

$node = new Node();

// Store the received data chunk locally
$storeSuccess = $node->storeDataLocally($fileId, $chunkId, $_FILES['file_data']['tmp_name'], $checksum, $sourceNodeId);

// Note: move_uploaded_file is used inside storeDataLocally, which cleans up the temp file.

if ($storeSuccess) {
    http_response_code(200); // OK
    echo json_encode(['success' => true, 'message' => 'Chunk received and stored.']);
} else {
    http_response_code(500); // Internal Server Error
    // Error logging is done inside storeDataLocally
    echo json_encode(['success' => false, 'error' => 'Failed to store chunk locally (checksum mismatch or disk error).']);
}
?>