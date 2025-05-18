<?php
// index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Util.php';
require_once __DIR__ . '/lib/Node.php';

$node = new Node();
$capabilities = $node->getCapabilities();
$analytics = $node->getAnalytics();
$peers = $node->getPeers();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Decentralized Storage Node (<?= get_config('NODE_ID') ?>)</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        h2 { margin-top: 30px; }
        code { background-color: #f4f4f4; padding: 2px 4px; border-radius: 4px; }
        pre { background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { border-collapse: collapse; margin-top: 10px; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-ok { color: green; font-weight: bold; }
        .status-offline { color: red; font-weight: bold; }
        .status-unknown { color: orange; }
    </style>
</head>
<body>
    <h1>Decentralized Storage Node</h1>
    <p>Node ID: <code><?= get_config('NODE_ID') ?></code></p>
    <p>Node URL: <code><?= get_config('NODE_URL') ?></code></p>
    <p>Network Secret (first 8 chars): <code><?= substr(get_config('NETWORK_SECRET'), 0, 8) ?>...</code></p>
    <p>Replication Factor: <code><?= get_config('REPLICATION_FACTOR') ?></code></p>
    <p>Data Path: <code><?= get_config('DATA_PATH') ?></code></p>

    <h2>Node Capabilities</h2>
    <table>
        <tr><th>Feature</th><th>Available</th></tr>
        <tr><td>cURL (HTTP Communication)</td><td><?= $capabilities['curl'] ? '<span class="status-ok">Yes</span>' : '<span class="status-offline">No (Severe Limitation)</span>' ?></td></tr>
        <tr><td>Zip Archive (Archiving)</td><td><?= $capabilities['archiving'] ? '<span class="status-ok">Yes</span>' : '<span class="status-unknown">No (Archiving Disabled)</span>' ?></td></tr>
        <tr><td>SHA256 Hashing</td><td><?= in_array('sha256', $capabilities['hashing']) ? '<span class="status-ok">Yes</span>' : '<span class="status-unknown">No (Using weaker or no hash)</span>' ?></td></tr>
        <tr><td>MD5 Hashing</td><td><?= in_array('md5', $capabilities['hashing']) ? '<span class="status-ok">Yes</span>' : '<span class="status-offline">No (Hashing Disabled)</span>' ?></td></tr>
         <tr><td>SQLite (if used)</td><td><?= $capabilities['sqlite3'] ? '<span class="status-ok">Yes</span>' : '<span class="status-unknown">No (SQLite features disabled)</span>' ?></td></tr>
    </table>

    <h2>Network Status (Local View)</h2>
    <p>Total Known Peers: <?= count($peers) ?></p>
    <table>
        <tr><th>Peer URL</th><th>Status</th><th>Last Seen (Approx)</th><th>Capabilities (Partial)</th></tr>
        <?php foreach ($peers as $peerUrl): ?>
            <?php
            $peerAnalytics = $analytics['peer_status'][$peerUrl] ?? ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
            $statusClass = 'status-' . $peerAnalytics['status'];
            $lastSeen = $peerAnalytics['timestamp'] > 0 ? date('Y-m-d H:i:s', $peerAnalytics['timestamp']) : 'Never';
            $peerCaps = !empty($peerAnalytics['capabilities']) ? implode(', ', array_keys(array_filter($peerAnalytics['capabilities']))) : 'N/A';
             ?>
            <tr>
                <td><code><?= htmlspecialchars($peerUrl) ?></code></td>
                <td class="<?= $statusClass ?>"><?= $peerAnalytics['status'] ?></td>
                <td><?= $lastSeen ?></td>
                 <td><?= htmlspecialchars($peerCaps) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

     <h2>Local Analytics</h2>
     <p>Disk Usage: <?= $analytics['storage_usage']['percentage'] ?? 'N/A' ?>% (<?= Util::formatBytes($analytics['storage_usage']['used'] ?? 0) ?> / <?= Util::formatBytes($analytics['storage_usage']['total'] ?? 0) ?>)</p>
     <h3>Local File Download Counts (Served by this node)</h3>
     <?php if (!empty($analytics['download_counts'])): ?>
        <table>
            <tr><th>File/Chunk ID</th><th>Downloads (on this node)</th></tr>
            <?php foreach ($analytics['download_counts'] as $fileId => $count): ?>
                <tr>
                    <td><code><?= htmlspecialchars($fileId) ?></code></td>
                    <td><?= $count ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No files served by this node yet.</p>
    <?php endif; ?>

    <h2>API Endpoints</h2>
    <p><code><?= get_config('NODE_URL') ?>api/upload.php</code> (POST file) - User Upload</p>
    <p><code><?= get_config('NODE_URL') ?>api/download.php?file_id=...</code> (GET) - User Download</p>
    <p><code><?= get_config('NODE_URL') ?>api/peers.php</code> (POST with secret) - Node Gossip</p>
    <p><code><?= get_config('NODE_URL') ?>api/upload_chunk.php</code> (POST with secret) - Node Internal Upload</p>
    <p><code><?= get_config('NODE_URL') ?>api/download_chunk.php?file_id=...&chunk_id=...</code> (GET with secret) - Node Internal Download</p>
    <p><code><?= get_config('NODE_URL') ?>api/metadata.php</code> (POST with secret) - Node Metadata Gossip</p>
    <p><code><?= get_config('NODE_URL') ?>api/analytics.php?type=...</code> (GET with secret) - Analytics Access</p>
    <p><code><?= get_config('NODE_URL') ?>api/capabilities.php</code> (GET/POST with secret) - Node Capabilities</p>


    <h2>Cron Jobs Setup</h2>
    <p>Configure cron jobs via your shared hosting control panel to run these scripts periodically using <code>wget</code> or <code>curl</code>. Replace <code>YOUR_NODE_URL</code> with your actual node URL.</p>
    <pre>
# Example Cron entries (adjust frequency as needed)
# Check environment hourly
*/60 * * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=check_environment >/dev/null 2>&1

# Gossip peers every 15 minutes
*/15 * * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=gossip_peers >/dev/null 2>&1

# Check peer health/capabilities every hour
*/60 * * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=check_peers >/dev/null 2>&1

# Gossip metadata every 30 minutes
*/30 * * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=gossip_metadata >/dev/null 2>&1

# Cleanup and Archiving (run less frequently, e.g., daily)
0 */<?= get_config('CLEANUP_INTERVAL_HOURS') ?> * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=cleanup_data >/dev/null 2>&1
# If archiving is separate or needs different schedule
# 0 */<?= get_config('ARCHIVE_INTERVAL_HOURS') ?> * * * /usr/bin/wget -O /dev/null <?= get_config('NODE_URL') ?>cron/run_cron.php?task=archive_old_files >/dev/null 2>&1
    </pre>
    <p>Make sure the cron command (`/usr/bin/wget` in example) is correct for your hosting.</p>

</body>
</html>