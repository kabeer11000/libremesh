<?php
// gateway/cron/update_peer_status.php - Cron task for the gateway to update peer status

// Prevent execution via web browser (basic check)
if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== $_SERVER['SERVER_ADDR']) {
    http_response_code(403); // Forbidden
    echo "Access denied.";
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/GatewayUtil.php';
require_once __DIR__ . '/../lib/PeerStatus.php';

error_log("Gateway Cron: Running peer status update...");

$peerStatusManager = new PeerStatus();
$knownNodes = $peerStatusManager->getAll();

$checkedCount = 0;
$updatedCount = 0;
$newPeersDiscovered = 0;

// Iterate through all known nodes to get their status and capabilities
foreach ($knownNodes as $nodeUrl => $statusData) {
     error_log("Gateway Cron: Checking status of node: " . $nodeUrl);
    $checkedCount++;

    // Query the node's analytics API for its overall status and peer health view
    // This endpoint requires the NETWORK_SECRET
    $analyticsUrl = $nodeUrl . 'api/analytics.php?type=all'; // Or type=status and type=peer_health separately
    $response = GatewayUtil::requestNode($analyticsUrl, 'GET');

    $status = 'offline'; // Assume offline if request fails
    $capabilities = [];
    $discoveredPeerListFromNode = []; // Peers this node knows about

    if (is_array($response) && isset($response['success']) && $response['success'] && isset($response['data'])) {
        // Successfully got analytics data
        $status = 'ok'; // Node is reachable and analytics API works
        $capabilities = $response['data']['capabilities'] ?? []; // Get node's own capabilities
        // The 'peer_status' in the response tells us about *its* view of the network
        // We use this to discover new nodes
        $discoveredPeerListFromNode = array_keys($response['data']['peer_status'] ?? []);

         error_log("Gateway Cron: Status OK for " . $nodeUrl . ". Capabilities: " . print_r($capabilities, true));
         error_log("Gateway Cron: " . $nodeUrl . " knows about " . count($discoveredPeerListFromNode) . " peers.");

    } else {
         error_log("Gateway Cron: Failed to get status/analytics from " . $nodeUrl . ". Marking as offline.");
         $status = 'offline';
         $capabilities = []; // Clear capabilities if offline
         $discoveredPeerListFromNode = []; // Cannot trust its peer list if offline
    }

    // Update the status of this node in the gateway's local file
    if ($peerStatusManager->updateStatus($nodeUrl, $status, $capabilities)) {
         $updatedCount++;
    }

     // Add any new peers discovered from this node's analytics data
     // Filter out self from the list it sent
     $discoveredPeersWithoutSelf = array_filter($discoveredPeerListFromNode, function($peerUrl) use ($nodeUrl) {
          return $peerUrl !== $nodeUrl;
     });

     if (!empty($discoveredPeersWithoutSelf)) {
         $newPeersAdded = $peerStatusManager->addDiscoveredPeers($discoveredPeersWithoutSelf);
         $newPeersDiscovered += $newPeersAdded;
         if ($newPeersAdded > 0) {
             error_log("Gateway Cron: Discovered $newPeersAdded new peers from $nodeUrl.");
         }
     }
}

error_log("Gateway Cron: Peer status update complete. Checked $checkedCount nodes. Updated $updatedCount. Discovered $newPeersDiscovered new peers.");

exit(0); // Indicate success
?>