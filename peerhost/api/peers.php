<?php
// api/peers.php - Node <-> Node: exchange peer list for gossip

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow authenticated requests from other nodes
if (!Util::isAuthenticated()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$node = new Node();
$peers = $node->getPeers();

// Return the list of known peers
http_response_code(200); // OK
echo json_encode($peers);
?>