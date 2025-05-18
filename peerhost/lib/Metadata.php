<?php
// lib/Metadata.php

require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Storage.php'; // Needed for building paths

class Metadata {
    private $metadata;

    public function __construct() {
        $this->load();
    }

    private function load() {
        $this->metadata = Util::readJsonFile(get_config('METADATA_FILE')) ?? [];
         // Ensure basic structure if file was empty
         if (!is_array($this->metadata)) {
              $this->metadata = [];
              error_log("Metadata file was corrupt or not an array. Initialized empty metadata.");
         }
    }

    private function save() {
        return Util::writeJsonFile(get_config('METADATA_FILE'), $this->metadata);
    }

    public function getAll() {
        return $this->metadata;
    }

    public function getFileMetadata($fileId) {
        return $this->metadata[$fileId] ?? null;
    }

     public function getChunkMetadata($fileId, $chunkId) {
         return $this->metadata[$fileId]['chunks'][$chunkId] ?? null;
     }


    /**
     * Updates metadata for a specific file chunk.
     * This is called when data is stored locally or when receiving gossip about remote data/state.
     * @param string $fileId
     * @param string $chunkId
     * @param array $data Key-value pairs to update/add for this chunk. Includes state, paths, checksum, etc.
     * @return bool True on success.
     */
    public function updateFileMetadata($fileId, $chunkId, $data) {
        $this->load(); // Reload before update to get latest state (handles concurrent writes somewhat)

        if (!isset($this->metadata[$fileId])) {
            $this->metadata[$fileId] = ['chunks' => []];
        }
        if (!isset($this->metadata[$fileId]['chunks'][$chunkId])) {
             $this->metadata[$fileId]['chunks'][$chunkId] = [];
        }

        // Merge the provided data into the existing metadata for this chunk
        $this->metadata[$fileId]['chunks'][$chunkId] = array_merge($this->metadata[$fileId]['chunks'][$chunkId], $data);

         // --- Simplified Conflict Resolution ---
         // If merging metadata during gossip, more complex logic is needed here
         // to handle cases where two nodes have conflicting states or timestamps.
         // Example: If receiving metadata from a peer, compare 'last_accessed' or 'stored_at' timestamps
         // and only update if the incoming data is newer for a specific field,
         // or if the state is 'deleted' (which overrides). This is complex!
         // For this code, `array_merge` means the last call to this function wins if keys conflict.

        $success = $this->save();
         if (!$success) error_log("Failed to save metadata after updating $fileId/$chunkId");
         return $success;
    }

    /**
     * Marks a file (all its chunks) for deletion in the local metadata.
     * @param string $fileId
     * @return bool True on success.
     */
    public function markFileDeleted($fileId) {
        $this->load();

        if (isset($this->metadata[$fileId])) {
            $deleteTime = time();
            foreach ($this->metadata[$fileId]['chunks'] ?? [] as $chunkId => &$chunkData) {
                $chunkData['state'] = 'deleted';
                $chunkData['deleted_at'] = $deleteTime;
            }
             unset($chunkData); // Unset reference

            $success = $this->save();
             if (!$success) error_log("Failed to save metadata after marking $fileId deleted.");
             return $success;
        } else {
            error_log("Attempted to mark non-existent file $fileId for deletion.");
            return false; // File not found in metadata
        }
    }

     /**
      * Marks a specific chunk for deletion in the local metadata.
      * @param string $fileId
      * @param string $chunkId
      * @return bool
      */
     public function markChunkDeleted($fileId, $chunkId) {
         $this->load();
         if (isset($this->metadata[$fileId]['chunks'][$chunkId])) {
             $this->metadata[$fileId]['chunks'][$chunkId]['state'] = 'deleted';
             $this->metadata[$fileId]['chunks'][$chunkId]['deleted_at'] = time();
              $success = $this->save();
             if (!$success) error_log("Failed to save metadata after marking $fileId/$chunkId deleted.");
             return $success;
         } else {
             error_log("Attempted to mark non-existent chunk $fileId/$chunkId for deletion.");
             return false;
         }
     }


     /**
      * Updates the last accessed timestamp for a chunk.
      * @param string $fileId
      * @param string $chunkId
      * @param int $timestamp Unix timestamp.
      * @return bool
      */
     public function updateLastAccessed($fileId, $chunkId, $timestamp) {
         $this->load();
         if (isset($this->metadata[$fileId]['chunks'][$chunkId])) {
             $this->metadata[$fileId]['chunks'][$chunkId]['last_accessed'] = $timestamp;
             // Optional: If state was archived, setting last_accessed could imply setting state back to active
             // This requires more complex logic and potential re-archiving later.
             // For simplicity, just update the timestamp. The archive job checks age vs. state.

             $success = $this->save();
             if (!$success) error_log("Failed to save metadata after updating last_accessed for $fileId/$chunkId");
             return $success;
         } else {
             error_log("Attempted to update last_accessed for non-existent chunk $fileId/$chunkId.");
             return false;
         }
     }

     // TODO: Method to remove a chunk's metadata completely (used after physical deletion)
     // public function removeChunkMetadata($fileId, $chunkId) { ... }
}
?>