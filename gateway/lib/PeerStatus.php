<?php
// gateway/lib/PeerStatus.php - Handles loading/saving and selecting peer status for gateway

require_once __DIR__ . '/GatewayUtil.php';

class PeerStatus {
    private $peerStatus; // Structure: [node_url => ['status' => 'ok/offline/etc', 'timestamp' => int, 'capabilities' => []]]

    public function __construct() {
        $this->load();
    }

    private function load() {
        $this->peerStatus = GatewayUtil::readJsonFile(get_gateway_config('GATEWAY_PEER_STATUS_FILE')) ?? [];
         // Ensure it's an array and merge in seed nodes if they aren't present
         if (!is_array($this->peerStatus)) {
             $this->peerStatus = [];
             error_log("Gateway PeerStatus file was corrupt or not an array. Initialized empty.");
         }
         // Ensure all seed nodes are in the list, maybe marked 'unknown' initially
         foreach(get_gateway_config('GATEWAY_SEED_NODES') as $seed) {
             if (!isset($this->peerStatus[$seed])) {
                  $this->peerStatus[$seed] = ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
             }
         }
    }

    private function save() {
        return GatewayUtil::writeJsonFile(get_gateway_config('GATEWAY_PEER_STATUS_FILE'), $this->peerStatus);
    }

    /**
     * Gets the full peer status map.
     * @return array
     */
    public function getAll() {
        return $this->peerStatus;
    }

     /**
      * Updates the status and capabilities for a specific peer.
      * @param string $peerUrl
      * @param string $status
      * @param array $capabilities
      * @return bool
      */
    public function updateStatus($peerUrl, $status, $capabilities = []) {
        $this->load(); // Reload before updating

        if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
             error_log("Attempted to update status for invalid peer URL: " . $peerUrl);
            return false;
        }

        $this->peerStatus[$peerUrl] = [
            'status' => $status,
            'timestamp' => time(),
            'capabilities' => $capabilities,
        ];
        $success = $this->save();
         if (!$success) error_log("Gateway failed to save peer status after updating " . $peerUrl);
         return $success;
    }

     /**
      * Adds new peers discovered via gossip/analytics to the list.
      * Merges incoming peer list with known peers.
      * @param array $peerUrlsFromNode List of peers reported by one node.
      * @return int Number of new peers added to the gateway's list.
      */
     public function addDiscoveredPeers(array $peerUrlsFromNode) {
         $this->load(); // Reload once

         $initialCount = count($this->peerStatus);
         foreach($peerUrlsFromNode as $peerUrl) {
             if (filter_var($peerUrl, FILTER_VALIDATE_URL) && !isset($this->peerStatus[$peerUrl])) {
                 $this->peerStatus[$peerUrl] = ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
             } else {
                  if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
                     error_log("Gateway skipping invalid discovered peer URL: " . $peerUrl);
                 }
             }
         }
         $addedCount = count($this->peerStatus) - $initialCount;
         if ($addedCount > 0) {
             if (!$this->save()) {
                  error_log("Gateway failed to save peer status after adding multiple discovered peers.");
             }
         }
         return $addedCount;
     }


    /**
     * Selects a suitable node URL for downloading, using basic round-robin load balancing.
     * Prioritizes 'ok' nodes.
     * @param string|null $fileId Optional file ID (not used in simple load balancing, but could be for future locality).
     * @return string|false A node URL or false if no healthy nodes available.
     */
    public function getNodeForDownload($fileId = null) {
        $this->load(); // Ensure we have the latest status

        // Filter for nodes marked as 'ok' and capable of HTTP (should always be ok if capability check passed)
        $healthyNodes = array_filter($this->peerStatus, function($status) {
            return $status['status'] === 'ok' && ($status['capabilities']['can_initiate_http'] ?? false); // Check if they can even be contacted via HTTP
        });

        $healthyNodeUrls = array_keys($healthyNodes);
        if (empty($healthyNodeUrls)) {
            error_log("Gateway: No healthy nodes available for download.");
            return false; // No healthy nodes
        }

        // --- Basic Round-Robin Logic ---
        $lastIndexFile = get_gateway_config('GATEWAY_LAST_NODE_INDEX_FILE');
        $lastIndex = (int)@file_get_contents($lastIndexFile); // Read last used index

        // Ensure index is within bounds and increment/wrap
        if ($lastIndex < 0 || $lastIndex >= count($healthyNodeUrls)) {
            $lastIndex = 0;
        }

        $selectedNodeUrl = $healthyNodeUrls[$lastIndex];

        // Calculate the next index (simple round-robin)
        $nextIndex = ($lastIndex + 1) % count($healthyNodeUrls);

        // Save the next index (basic file locking)
        $handle = fopen($lastIndexFile, 'c+');
        if ($handle !== false) {
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $nextIndex);
                fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        } else {
            error_log("Gateway: Could not acquire lock or write to last node index file: " . $lastIndexFile);
        }
        // --- End Round-Robin Logic ---

        error_log("Gateway: Selected node for download: " . $selectedNodeUrl);
        return $selectedNodeUrl;
    }

     // TODO: Method to handle a node failing *during* a download attempt
     // If downloadFileFromNode returns false (error other than 404), this method
     // could try the *next* healthy node from the list.
     // This would require modifying download.php to loop through options.
}
?><?php
// gateway/lib/PeerStatus.php - Handles loading/saving and selecting peer status for gateway

require_once __DIR__ . '/GatewayUtil.php';

class PeerStatus {
    private $peerStatus; // Structure: [node_url => ['status' => 'ok/offline/etc', 'timestamp' => int, 'capabilities' => []]]

    public function __construct() {
        $this->load();
    }

    private function load() {
        $this->peerStatus = GatewayUtil::readJsonFile(get_gateway_config('GATEWAY_PEER_STATUS_FILE')) ?? [];
         // Ensure it's an array and merge in seed nodes if they aren't present
         if (!is_array($this->peerStatus)) {
             $this->peerStatus = [];
             error_log("Gateway PeerStatus file was corrupt or not an array. Initialized empty.");
         }
         // Ensure all seed nodes are in the list, maybe marked 'unknown' initially
         foreach(get_gateway_config('GATEWAY_SEED_NODES') as $seed) {
             if (!isset($this->peerStatus[$seed])) {
                  $this->peerStatus[$seed] = ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
             }
         }
    }

    private function save() {
        return GatewayUtil::writeJsonFile(get_gateway_config('GATEWAY_PEER_STATUS_FILE'), $this->peerStatus);
    }

    /**
     * Gets the full peer status map.
     * @return array
     */
    public function getAll() {
        return $this->peerStatus;
    }

     /**
      * Updates the status and capabilities for a specific peer.
      * @param string $peerUrl
      * @param string $status
      * @param array $capabilities
      * @return bool
      */
    public function updateStatus($peerUrl, $status, $capabilities = []) {
        $this->load(); // Reload before updating

        if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
             error_log("Attempted to update status for invalid peer URL: " . $peerUrl);
            return false;
        }

        $this->peerStatus[$peerUrl] = [
            'status' => $status,
            'timestamp' => time(),
            'capabilities' => $capabilities,
        ];
        $success = $this->save();
         if (!$success) error_log("Gateway failed to save peer status after updating " . $peerUrl);
         return $success;
    }

     /**
      * Adds new peers discovered via gossip/analytics to the list.
      * Merges incoming peer list with known peers.
      * @param array $peerUrlsFromNode List of peers reported by one node.
      * @return int Number of new peers added to the gateway's list.
      */
     public function addDiscoveredPeers(array $peerUrlsFromNode) {
         $this->load(); // Reload once

         $initialCount = count($this->peerStatus);
         foreach($peerUrlsFromNode as $peerUrl) {
             if (filter_var($peerUrl, FILTER_VALIDATE_URL) && !isset($this->peerStatus[$peerUrl])) {
                 $this->peerStatus[$peerUrl] = ['status' => 'unknown', 'timestamp' => 0, 'capabilities' => []];
             } else {
                  if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
                     error_log("Gateway skipping invalid discovered peer URL: " . $peerUrl);
                 }
             }
         }
         $addedCount = count($this->peerStatus) - $initialCount;
         if ($addedCount > 0) {
             if (!$this->save()) {
                  error_log("Gateway failed to save peer status after adding multiple discovered peers.");
             }
         }
         return $addedCount;
     }


    /**
     * Selects a suitable node URL for downloading, using basic round-robin load balancing.
     * Prioritizes 'ok' nodes.
     * @param string|null $fileId Optional file ID (not used in simple load balancing, but could be for future locality).
     * @return string|false A node URL or false if no healthy nodes available.
     */
    public function getNodeForDownload($fileId = null) {
        $this->load(); // Ensure we have the latest status

        // Filter for nodes marked as 'ok' and capable of HTTP (should always be ok if capability check passed)
        $healthyNodes = array_filter($this->peerStatus, function($status) {
            return $status['status'] === 'ok' && ($status['capabilities']['can_initiate_http'] ?? false); // Check if they can even be contacted via HTTP
        });

        $healthyNodeUrls = array_keys($healthyNodes);
        if (empty($healthyNodeUrls)) {
            error_log("Gateway: No healthy nodes available for download.");
            return false; // No healthy nodes
        }

        // --- Basic Round-Robin Logic ---
        $lastIndexFile = get_gateway_config('GATEWAY_LAST_NODE_INDEX_FILE');
        $lastIndex = (int)@file_get_contents($lastIndexFile); // Read last used index

        // Ensure index is within bounds and increment/wrap
        if ($lastIndex < 0 || $lastIndex >= count($healthyNodeUrls)) {
            $lastIndex = 0;
        }

        $selectedNodeUrl = $healthyNodeUrls[$lastIndex];

        // Calculate the next index (simple round-robin)
        $nextIndex = ($lastIndex + 1) % count($healthyNodeUrls);

        // Save the next index (basic file locking)
        $handle = fopen($lastIndexFile, 'c+');
        if ($handle !== false) {
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $nextIndex);
                fflush($handle);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        } else {
            error_log("Gateway: Could not acquire lock or write to last node index file: " . $lastIndexFile);
        }
        // --- End Round-Robin Logic ---

        error_log("Gateway: Selected node for download: " . $selectedNodeUrl);
        return $selectedNodeUrl;
    }

     // TODO: Method to handle a node failing *during* a download attempt
     // If downloadFileFromNode returns false (error other than 404), this method
     // could try the *next* healthy node from the list.
     // This would require modifying download.php to loop through options.
}
?>