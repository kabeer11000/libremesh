<?php
// gateway/download.php - Handles download requests, selects node, and serves file

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/GatewayUtil.php';
require_once __DIR__ . '/lib/PeerStatus.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo "Method not allowed.";
    exit;
}

// Check for file_id parameter
$fileId = $_GET['file_id'] ?? null;
if (!$fileId) {
    http_response_code(400); // Bad Request
    echo "Missing file_id parameter.";
    exit;
}

$peerStatusManager = new PeerStatus();
$fileContent = false; // Will store the downloaded content

// --- Load Balancing: Select a Node ---
// Try getting a healthy node using round-robin
$selectedNodeUrl = $peerStatusManager->getNodeForDownload($fileId); // Pass fileId in case LoadBalancer uses it later

if ($selectedNodeUrl) {
    error_log("Gateway: Attempting download of file $fileId from selected node: " . $selectedNodeUrl);
    // --- Attempt Download from Selected Node ---
     // Use the utility function to call the *node's user download API*
    $fileContent = GatewayUtil::downloadFileFromNode($selectedNodeUrl, $fileId);

    // --- Handle Download Outcome ---
    if ($fileContent === null) {
        // downloadFileFromNode returned null, means the node returned 404 (File Not Found)
        error_log("Gateway: Node " . $selectedNodeUrl . " reported file $fileId not found (404).");
        // TODO: In a more robust system, you would try the *next* healthy node here.
        // For this basic version, if the first chosen node says 404, we report not found.
        $fileContent = false; // Treat as not found overall for now.

    } elseif ($fileContent === false) {
        // downloadFileFromNode returned false, means there was a cURL error or other non-404 HTTP error (401, 500, etc.)
        error_log("Gateway: Failed to download file $fileId from node " . $selectedNodeUrl . " due to request error.");
        // TODO: Try the next healthy node here if this one failed unexpectedly.
         // For this basic version, report a gateway error.

    } else {
        // Success! fileContent holds the raw file data. Headers were set by GatewayUtil.
         error_log("Gateway: Successfully downloaded file $fileId from node " . $selectedNodeUrl);
        // fileContent is the raw data, headers already set in GatewayUtil::downloadFileFromNode
    }

} else {
    error_log("Gateway: No healthy nodes available to attempt download of file $fileId.");
     // fileContent remains false
}


// --- Final Response to Client ---
if ($fileContent === false) {
    // No node available, or selected node failed/reported not found
    http_response_code(404); // Not Found
    echo "Error: File not found or no nodes available.";
} else {
    // File content successfully retrieved from a node and headers are set
    // Output the raw content received from the node
    echo $fileContent;
    // exit is called implicitly after echoing in PHP scripts
}

?>