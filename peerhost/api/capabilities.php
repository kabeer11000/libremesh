<?php
// api/capabilities.php - Node <-> Node / Gateway: report capabilities

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Allow authenticated GET requests from peers/gateway
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !Util::isAuthenticated()) {
     // Could also allow POST from check_peers to update capabilities?
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access or invalid method']);
    exit;
}

$node = new Node();
$capabilities = $node->getCapabilities();

http_response_code(200); // OK
echo json_encode($capabilities);
?>