<?php
// gateway/config.php - Configuration for the Download Gateway
// Supports environment variables for Docker/Kubernetes deployment

function getenv_or($key, $default) {
    $val = getenv($key);
    return $val !== false && $val !== '' ? $val : $default;
}

// !!! IMPORTANT: Initial list of known nodes in the network !!!
$seed_env = getenv_or('GATEWAY_SEED_NODES', '');
$default_seeds = ['http://localhost:8001/', 'http://localhost:8002/'];
define('GATEWAY_SEED_NODES', $seed_env ? json_decode($seed_env, true) : $default_seeds);

// !!! IMPORTANT: The NETWORK_SECRET used by your LibreMesh nodes !!!
define('GATEWAY_NETWORK_SECRET', getenv_or('GATEWAY_NETWORK_SECRET', 'test-secret'));

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