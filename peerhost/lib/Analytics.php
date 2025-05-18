<?php
// lib/Analytics.php

require_once __DIR__ . '/Util.php';

class Analytics {
    private $analytics;

    public function __construct() {
        $this->load();
    }

    private function load() {
        $this->analytics = Util::readJsonFile(get_config('ANALYTICS_FILE')) ?? [];
         // Ensure basic structure
         if (!is_array($this->analytics)) {
              $this->analytics = [];
              error_log("Analytics file was corrupt or not an array. Initialized empty analytics.");
         }
        $this->analytics['download_counts'] = $this->analytics['download_counts'] ?? [];
        $this->analytics['peer_status'] = $this->analytics['peer_status'] ?? [];
        $this->analytics['storage_usage'] = $this->analytics['storage_usage'] ?? [];
    }

    private function save() {
        return Util::writeJsonFile(get_config('ANALYTICS_FILE'), $this->analytics);
    }

    public function getAll() {
        return $this->analytics;
    }

    /**
     * Increments download count for a file/chunk on this node.
     * @param string $fileId
     * @param string $chunkId Defaults to '0' for simple replication.
     * @return bool
     */
    public function incrementDownload($fileId, $chunkId = '0') {
        $this->load(); // Reload before updating
        $key = $fileId . '_' . $chunkId;
        $this->analytics['download_counts'][$key] = ($this->analytics['download_counts'][$key] ?? 0) + 1;
        $success = $this->save();
         if (!$success) error_log("Failed to save analytics after incrementing download for " . $key);
         return $success;
    }

    /**
     * Updates the status of a known peer.
     * @param string $peerUrl
     * @param string $status e.g., 'ok', 'offline', 'checksum_mismatch', 'unknown'
     * @param array $capabilities Peer's reported capabilities.
     * @return bool
     */
    public function updatePeerStatus($peerUrl, $status, $capabilities = []) {
        $this->load(); // Reload before updating
        $this->analytics['peer_status'][$peerUrl] = [
            'status' => $status,
            'timestamp' => time(),
            'capabilities' => $capabilities, // Store the last known capabilities
        ];
        $success = $this->save();
        if (!$success) error_log("Failed to save analytics after updating peer status for " . $peerUrl);
        return $success;
    }

    /**
     * Updates local storage usage statistics.
     * @return bool
     */
    public function updateStorageUsage() {
        // Use DATA_PATH as the base for usage calculation
        $total = @disk_total_space(get_config('DATA_PATH'));
        $free = @disk_free_space(get_config('DATA_PATH'));

        $this->load(); // Reload before updating

        if ($total !== false && $free !== false) {
             $used = $total - $free;
             $percentage = ($total > 0) ? round(($used / $total) * 100, 2) : 0;
             $this->analytics['storage_usage'] = [
                 'total' => $total,
                 'free' => $free,
                 'used' => $used,
                 'percentage' => $percentage,
                 'timestamp' => time(),
             ];
        } else {
            error_log("Failed to get disk space info for " . get_config('DATA_PATH'));
             $this->analytics['storage_usage'] = [
                 'total' => 0, 'free' => 0, 'used' => 0, 'percentage' => 'N/A', 'timestamp' => time()
             ]; // Reset or mark as N/A
        }

        $success = $this->save();
         if (!$success) error_log("Failed to save analytics after updating storage usage.");
         return $success;
    }
}
?>