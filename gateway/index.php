<?php
// gateway/index.php - Simple File Download Gateway Entry Point

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/PeerStatus.php'; // Optional, for displaying status

$peerStatusManager = new PeerStatus();
$knownPeers = $peerStatusManager->getAll(); // For displaying known nodes


?>
<!DOCTYPE html>
<html>
<head>
    <title>LibreMesh Download Gateway</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ccc; max-width: 500px; }
        input[type="text"] { width: 80%; padding: 8px; margin-bottom: 10px; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-ok { color: green; font-weight: bold; }
        .status-offline { color: red; font-weight: bold; }
        .status-unknown { color: orange; }
    </style>
</head>
<body>

    <h1>LibreMesh Download Gateway</h1>

    <p>Enter the File ID you wish to download from the network.</p>

    <form action="download.php" method="get">
        <label for="file_id">File ID:</label>
        <br>
        <input type="text" name="file_id" id="file_id" required placeholder="Enter File ID">
        <input type="submit" value="Download File">
    </form>

    <h2>Known Nodes (Gateway's View)</h2>
     <p>This list is updated periodically by the gateway's cron job.</p>
     <?php if (!empty($knownPeers)): ?>
        <table>
            <tr><th>Node URL</th><th>Gateway Status</th><th>Last Checked (Approx)</th><th>Capabilities (Partial)</th></tr>
            <?php foreach ($knownPeers as $peerUrl => $statusData): ?>
                <?php
                $statusClass = 'status-' . ($statusData['status'] ?? 'unknown');
                $lastChecked = ($statusData['timestamp'] ?? 0) > 0 ? date('Y-m-d H:i:s', $statusData['timestamp']) : 'Never';
                $capabilities = !empty($statusData['capabilities']) ? implode(', ', array_keys(array_filter($statusData['capabilities']))) : 'N/A';
                ?>
                <tr>
                    <td><code><?= htmlspecialchars($peerUrl) ?></code></td>
                    <td class="<?= $statusClass ?>"><?= htmlspecialchars($statusData['status'] ?? 'unknown') ?></td>
                    <td><?= $lastChecked ?></td>
                     <td><?= htmlspecialchars($capabilities) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Gateway has not discovered any nodes yet. Ensure GATEWAY_SEED_NODES are set correctly in config.php and the gateway cron is running.</p>
    <?php endif; ?>


</body>
</html>