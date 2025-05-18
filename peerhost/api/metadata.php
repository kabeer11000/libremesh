<?php
// api/metadata.php - Node <-> Node: exchange metadata for gossip

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow authenticated requests
if (!Util::isAuthenticated()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$node = new Node();

// --- Metadata Gossip Logic (Simplified) ---
// In a real system, this would involve exchanging recent changes,
// vector clocks, or Merkle trees for efficient merging.
// For this basic code, we can return:
// 1. A summary of files we hold (e.g., list of file/chunk IDs)
// 2. Metadata for a random subset of files we hold.
// 3. Potentially accept incoming metadata updates via POST.

// For simplified GET gossip, let's return metadata for a few random files we hold.
$allMetadata = $node->getMetadata();
$fileIds = array_keys($allMetadata);
$metadataToShare = [];

if (!empty($fileIds)) {
     shuffle($fileIds);
     $fileIdsToShare = array_slice($fileIds, 0, min(10, count($fileIds))); // Share up to 10 random files metadata

     foreach($fileIdsToShare as $fileId) {
         $metadataToShare[$fileId] = $allMetadata[$fileId];
     }
}


// TODO: Implement POST handling to receive metadata updates from peers during gossip
/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // Receive metadata updates from a peer
     $incomingMetadata = json_decode(file_get_contents('php://input'), true);
     if (is_array($incomingMetadata)) {
          // Call a method in Node/Metadata to merge incoming metadata
          // $node->mergeIncomingMetadata($incomingMetadata); // Needs implementation
          echo json_encode(['success' => true, 'message' => 'Metadata received (merge logic omitted).']);
     } else {
          http_response_code(400);
          echo json_encode(['error' => 'Invalid metadata format received.']);
     }
     exit;
}
*/


http_response_code(200); // OK
echo json_encode($metadataToShare); // Return metadata for a random subset
?>