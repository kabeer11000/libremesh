<?php
// cron/run_cron.php - Main entry point for cron jobs

// Prevent execution via web browser (basic check)
if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== $_SERVER['SERVER_ADDR']) {
    // Could add more checks like command line execution only
    // Or require a secret token in the cron URL
    http_response_code(403); // Forbidden
    echo "Access denied.";
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Node.php';

$task = $_GET['task'] ?? null; // Get task from query parameter

if (!$task) {
    echo "Error: No task specified.";
    exit;
}

$node = new Node();

// Execute the requested task method on the Node object
$methodName = 'do' . str_replace('_', '', ucwords($task, '_')); // e.g., 'gossip_peers' -> 'doGossipPeers'

if (method_exists($node, $methodName)) {
    echo "Executing task: " . $task . "\n";
    $node->$methodName(); // Call the method
    echo "Task '" . $task . "' finished.\n";

    // Update last check-in timestamp in analytics after any cron task runs
    $analytics = $node->getAnalytics();
    $analytics['last_check_in'] = time();
    Util::writeJsonFile(get_config('ANALYTICS_FILE'), $analytics); // Save analytics update


} else {
    echo "Error: Unknown task '" . $task . "'.\n";
    exit(1); // Indicate error
}

exit(0); // Indicate success
?>