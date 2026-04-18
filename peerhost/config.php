<?php
// config.php
// Environment variables for Docker (with defaults for local development)

function getenv_or($key, $default) {
    $val = getenv($key);
    return $val !== false && $val !== '' ? $val : $default;
}

// !!! IMPORTANT: Replace with a unique, random string for YOUR network !!!
define('NETWORK_SECRET', getenv_or('NETWORK_SECRET', 'test-secret'));

// !!! IMPORTANT: Replace with a unique ID for THIS SPECIFIC NODE !!!
define('NODE_ID', getenv_or('NODE_ID', 'node-' . uniqid()));

// !!! IMPORTANT: Replace with the accessible URL for THIS NODE's root directory !!!
define('NODE_URL', getenv_or('NODE_URL', 'http://localhost/'));

// !!! IMPORTANT: Initial list of known nodes in the network !!!
$seed_env = getenv_or('SEED_NODES', '');
$default_seeds = ['http://localhost:8001/', 'http://localhost:8002/'];
define('SEED_NODES', $seed_env ? json_decode($seed_env, true) : $default_seeds);

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

// Firewall Bypass Settings (for hosts like InfinityFree that block non-browser requests)
define('FIREWALL_BYPASS_ENABLED', getenv_or('FIREWALL_BYPASS_ENABLED', false));
define('FIREWALL_BYPASS_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

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