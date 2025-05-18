<?php
// config.php
// !!! IMPORTANT: Replace with a unique, random string for YOUR network !!!
// This acts as a shared secret for node-to-node communication authentication.
define('NETWORK_SECRET', 'YOUR_VERY_LONG_AND_RANDOM_SHARED_SECRET_HERE');

// !!! IMPORTANT: Replace with a unique ID for THIS SPECIFIC NODE !!!
// This helps identify this node in the network. Could be a UUID or hostname.
define('NODE_ID', 'node_freeweb10-2.byetcluster.com_682976f6a7a2c'); // Simple example ID

// !!! IMPORTANT: Replace with the accessible URL for THIS NODE's root directory !!!
// Other nodes will use this URL to communicate with this node.
define('NODE_URL', 'https://libremesh-mesh0-root.infy.uk'); // Example URL

// !!! IMPORTANT: Initial list of known nodes in the network !!!
// New nodes use this list to discover the rest of the network via gossip.
// Include your own NODE_URL here once deployed. Add other nodes' URLs as they join.
define('SEED_NODES', [
    'https://libremesh-mesh0-root.infy.uk/',
    // Add URLs of other nodes here
]);

// Data Storage Paths
// Make sure these directories exist and are writable by your web server user.
// It's highly recommended to place the 'data' directory OUTSIDE your web root
// if your shared hosting allows it, for better security.
define('DATA_PATH', __DIR__ . '/data/');
define('ARCHIVE_PATH', DATA_PATH . 'archives/');
define('PEERS_FILE', DATA_PATH . 'peers.json');
define('METADATA_FILE', DATA_PATH . 'metadata.json');
define('ANALYTICS_FILE', DATA_PATH . 'analytics.json');

// Replication Settings
define('REPLICATION_FACTOR', 3); // How many copies of each file/chunk to keep (including the original node's copy if applicable)

// Archiving Settings
define('ARCHIVE_THRESHOLD_DAYS', 180); // Files not accessed in this many days will be archived
define('REARCHIVE_WINDOW_DAYS', 7); // If unarchived, re-archive if not accessed again within this many days

// Cron Settings
define('GOSSIP_PEERS_INTERVAL_MINUTES', 15); // How often to run peer gossip
define('GOSSIP_METADATA_INTERVAL_MINUTES', 30); // How often to run metadata gossip
define('CHECK_PEERS_INTERVAL_MINUTES', 60); // How often to check peer health/capabilities
define('CLEANUP_INTERVAL_HOURS', 24); // How often to run data cleanup (deletion, usage, re-archiving check)
define('ARCHIVE_INTERVAL_HOURS', 24); // How often to run archiving (can be same as cleanup)

// Security Settings
define('API_KEY_NAME', 'X-Network-Secret'); // HTTP header name for the shared secret

// PHP Environment/Capability Settings
define('MIN_PHP_VERSION', '7.4.0'); // Minimum required PHP version
define('REQUIRED_EXTENSIONS', ['json', 'curl']); // Extensions absolutely needed
define('OPTIONAL_EXTENSIONS', ['zip', 'sqlite3']); // Extensions used for extra features

// --- Advanced Settings (Usually don't need to change) ---
define('CHUNK_SIZE', 1024 * 1024 * 5); // File chunk size in bytes (5MB example). For simple replication, file isn't chunked here.
define('DELETE_PROPAGATION_DELAY_HOURS', 48); // How long to wait after a file is marked deleted before physical deletion.

// --- Error Reporting ---
ini_set('error_log', __DIR__ . '/php_error.log');
ini_set('display_errors', 1); // Turn off display errors in production
ini_set('log_errors', 1); // Log errors in production
error_reporting(E_ALL); // Report all errors
error_log("This is a runtime error log test.");

// Check/Create necessary directories
if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 777, true);
if (!is_dir(ARCHIVE_PATH)) mkdir(ARCHIVE_PATH, 777, true);

// Initialize state files if they don't exist
if (!file_exists(PEERS_FILE)) file_put_contents(PEERS_FILE, json_encode(SEED_NODES));
if (!file_exists(METADATA_FILE)) file_put_contents(METADATA_FILE, json_encode([])); // {file_id: {chunks: {chunk_id: {...}}, status: 'active', ...}}
if (!file_exists(ANALYTICS_FILE)) file_put_contents(ANALYTICS_FILE, json_encode([])); // {file_id: {downloads: N}, peer_status: {}, storage_usage: {}}

// --- Helper for accessing config from included files ---
function get_config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}
?>