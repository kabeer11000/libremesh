<?php
// gateway/config.php - Configuration for the Download Gateway

// !!! IMPORTANT: Initial list of known nodes in the network !!!
// The gateway uses this list to start discovering and checking node status.
// Include the URL of at least one deployed LibreMesh node. Add others as they join.
define('GATEWAY_SEED_NODES', [
    'https://your-first-node-url.com/libremesh/',
    // Add URLs of other LibreMesh nodes here
]);

// !!! IMPORTANT: The NETWORK_SECRET used by your LibreMesh nodes !!!
// The gateway needs this to query the nodes' analytics API.
define('GATEWAY_NETWORK_SECRET', 'YOUR_VERY_LONG_AND_RANDOM_SHARED_SECRET_HERE'); // Must match NETWORK_SECRET in node's config.php

// Data Storage Paths for the Gateway
define('GATEWAY_DATA_PATH', __DIR__ . '/data/');
define('GATEWAY_PEER_STATUS_FILE', GATEWAY_DATA_PATH . 'gateway_peers_status.json');
define('GATEWAY_LAST_NODE_INDEX_FILE', GATEWAY_DATA_PATH . 'last_node_index.txt');


// Cron Settings for the Gateway
define('GATEWAY_PEER_UPDATE_INTERVAL_MINUTES', 10); // How often the gateway should update peer status

// Security Settings for Node API Calls
define('GATEWAY_API_KEY_NAME', 'X-Network-Secret'); // Must match API_KEY_NAME in node's config.php

// --- Helper for accessing config from included files ---
function get_gateway_config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Check/Create necessary directories
if (!is_dir(get_gateway_config('GATEWAY_DATA_PATH'))) {
    mkdir(get_gateway_config('GATEWAY_DATA_PATH'), 0775, true);
}

// Initialize state files if they don't exist
if (!file_exists(get_gateway_config('GATEWAY_PEER_STATUS_FILE'))) {
    $initial_status = [];
    foreach (get_gateway_config('GATEWAY_SEED_NODES') as $seed) {
        $initial_status[$seed] = ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
    }
    file_put_contents(get_gateway_config('GATEWAY_PEER_STATUS_FILE'), json_encode($initial_status));
}
if (!file_exists(get_gateway_config('GATEWAY_LAST_NODE_INDEX_FILE'))) {
    file_put_contents(get_gateway_config('GATEWAY_LAST_NODE_INDEX_FILE'), '0');
}

?>