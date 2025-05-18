<?php
// api/analytics.php - Gateway <-> Node: retrieve analytics

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Util.php';
require_once __DIR__ . '/../lib/Node.php';

// Only allow authenticated GET requests (e.g., from the Gateway)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !Util::isAuthenticated()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access or invalid method']);
    exit;
}

$node = new Node();
$analytics = $node->getAnalytics();

$type = $_GET['type'] ?? 'all';
$fileId = $_GET['file_id'] ?? null;

$response = ['success' => true];

switch ($type) {
    case 'status':
        $response['status'] = [
            'node_id' => get_config('NODE_ID'),
            'node_url' => get_config('NODE_URL'),
            'storage_usage' => $analytics['storage_usage'] ?? [],
             'capabilities' => $node->getCapabilities(),
             'last_check_in' => $analytics['last_check_in'] ?? null, // Needs to be tracked in cron
        ];
        break;
    case 'peer_health':
        $response['peer_status'] = $analytics['peer_status'] ?? [];
        break;
    case 'downloads':
        if ($fileId) {
            // Return downloads for a specific file/chunk
            $response['download_counts'] = [];
             foreach($analytics['download_counts'] ?? [] as $key => $count) {
                 list($fId, $cId) = explode('_', $key);
                 if ($fId === $fileId) {
                     $response['download_counts'][$key] = $count;
                 }
             }
        } else {
            // Return all download counts on this node
            $response['download_counts'] = $analytics['download_counts'] ?? [];
        }
        break;
    case 'all':
    default:
        $response['data'] = $analytics;
        $response['data']['capabilities'] = $node->getCapabilities(); // Include capabilities in 'all'
        break;
}

http_response_code(200); // OK
echo json_encode($response);
?>