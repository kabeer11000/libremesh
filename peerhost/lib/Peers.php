<?php
// lib/Peers.php

require_once __DIR__ . '/Util.php';

class Peers {
    private $peers;

    public function __construct() {
        $this->load();
    }

    private function load() {
        $this->peers = Util::readJsonFile(get_config('PEERS_FILE')) ?? [];
         // Ensure it's an array and contains the self URL
         if (!is_array($this->peers)) {
             $this->peers = [];
             error_log("Peers file was corrupt or not an array. Initialized empty peer list.");
         }
         $selfUrl = get_config('NODE_URL');
         if (!in_array($selfUrl, $this->peers)) {
              $this->peers[] = $selfUrl; // Always include self
         }
    }

    private function save() {
        // Ensure peer list is unique and contains self before saving
        $this->peers = array_unique($this->peers);
         $selfUrl = get_config('NODE_URL');
         if (!in_array($selfUrl, $this->peers)) {
              $this->peers[] = $selfUrl;
         }
        return Util::writeJsonFile(get_config('PEERS_FILE'), array_values($this->peers)); // Save as indexed array
    }

    /**
     * Get all known peer URLs.
     * @return array
     */
    public function getAll() {
        return $this->peers;
    }

    /**
     * Add a peer URL to the list if not already present.
     * @param string $peerUrl
     * @return bool True if the peer was added (was new), false otherwise.
     */
    public function add($peerUrl) {
        $this->load(); // Reload before adding

        if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
            error_log("Attempted to add invalid peer URL: " . $peerUrl);
            return false;
        }

        if (!in_array($peerUrl, $this->peers)) {
            $this->peers[] = $peerUrl;
            $success = $this->save();
             if (!$success) error_log("Failed to save peer list after adding " . $peerUrl);
            return $success; // Return true only if saved successfully
        }
        return false; // Peer already exists
    }

    /**
     * Remove a peer URL from the list.
     * @param string $peerUrl
     * @return bool True if the peer was removed (was present), false otherwise.
     */
    public function remove($peerUrl) {
        $this->load(); // Reload before removing

        $initialCount = count($this->peers);
        $this->peers = array_filter($this->peers, function($p) use ($peerUrl) {
            return $p !== $peerUrl;
        });
        $this->peers = array_values($this->peers); // Re-index array

        if (count($this->peers) < $initialCount) {
            $success = $this->save();
            if (!$success) error_log("Failed to save peer list after removing " . $peerUrl);
            return $success; // Return true only if removed and saved successfully
        }
        return false; // Peer was not present
    }

    /**
     * Add multiple peer URLs at once.
     * @param array $peerUrls
     * @return int Number of new peers added.
     */
     public function addMultiple(array $peerUrls) {
         $this->load(); // Reload once

         $initialCount = count($this->peers);
         foreach($peerUrls as $peerUrl) {
             if (filter_var($peerUrl, FILTER_VALIDATE_URL) && !in_array($peerUrl, $this->peers)) {
                 $this->peers[] = $peerUrl;
             } else {
                 if (!filter_var($peerUrl, FILTER_VALIDATE_URL)) {
                     error_log("Skipping invalid peer URL from list: " . $peerUrl);
                 }
             }
         }
         $this->peers = array_unique($this->peers); // Ensure uniqueness again
         $this->peers = array_values($this->peers); // Re-index

         $addedCount = count($this->peers) - $initialCount;
         if ($addedCount > 0) {
             if (!$this->save()) {
                  error_log("Failed to save peer list after adding multiple peers.");
             }
         }
         return $addedCount;
     }
}
?>