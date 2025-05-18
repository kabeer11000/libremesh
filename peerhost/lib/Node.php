<?php
// lib/Node.php

require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Peers.php';
require_once __DIR__ . '/Metadata.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Analytics.php';

class Node {
    private $peers;
    private $metadata;
    private $analytics;
    private $storage;
    private $capabilities;

    public function __construct() {
        $this->peers = new Peers();
        $this->metadata = new Metadata();
        $this->storage = new Storage();
        $this->analytics = new Analytics();
        $this->capabilities = $this->detectCapabilities();

        // Ensure essential directories exist
         if (!is_dir(get_config('DATA_PATH'))) {
             mkdir(get_config('DATA_PATH'), 0777, true);
         }
         if (!is_dir(get_config('ARCHIVE_PATH'))) {
             mkdir(get_config('ARCHIVE_PATH'), 0777, true);
         }
    }

    public function getPeers() {
        return $this->peers->getAll();
    }

    public function addPeer($peerUrl) {
        return $this->peers->add($peerUrl);
    }

    public function removePeer($peerUrl) {
        return $this->peers->remove($peerUrl);
    }

    public function updatePeerStatus($peerUrl, $status, $capabilities = []) {
        return $this->analytics->updatePeerStatus($peerUrl, $status, $capabilities);
    }

    public function getMetadata() {
        return $this->metadata->getAll();
    }

    public function getMetadataForFile($fileId) {
        return $this->metadata->getFileMetadata($fileId);
    }

     public function updateFileMetadata($fileId, $chunkId, $data) {
         return $this->metadata->updateFileMetadata($fileId, $chunkId, $data);
     }

     public function markFileDeleted($fileId) {
         return $this->metadata->markFileDeleted($fileId);
     }

    public function getAnalytics() {
        return $this->analytics->getAll();
    }

     public function incrementDownloadCount($fileId) {
         return $this->analytics->incrementDownload($fileId);
     }

     public function updateStorageUsage() {
         return $this->analytics->updateStorageUsage();
     }


    public function getCapabilities() {
        return $this->capabilities;
    }

    private function detectCapabilities() {
        $caps = [
            'node_id' => get_config('NODE_ID'),
            'node_url' => get_config('NODE_URL'),
            'php_version' => PHP_VERSION,
            'curl' => extension_loaded('curl'),
            'archiving' => extension_loaded('zip'), // ZipArchive support
            'sqlite3' => extension_loaded('sqlite3'), // SQLite support
            'hashing' => [],
            'can_initiate_http' => extension_loaded('curl'), // Can make outgoing requests
        ];

        if (function_exists('sha256_file')) {
            $caps['hashing'][] = 'sha256';
        }
        if (function_exists('md5_file')) {
            $caps['hashing'][] = 'md5';
        }

        // Check for critical dependencies
        foreach (get_config('REQUIRED_EXTENSIONS') as $ext) {
             if (!extension_loaded($ext)) {
                 error_log("CRITICAL: Required extension '$ext' is missing. Node may not function.");
                 // Set a capability flag? Or just log and the node will fail?
                 // For now, relying on error log and function_exists checks later.
             }
        }

        return $caps;
    }

    /**
     * Uploads a file to this node's local storage.
     * @param string $fileId
     * @param string $chunkId
     * @param string $tempFilePath Path to the temporary uploaded file.
     * @param string $checksum Checksum of the data.
     * @param string $sourceNodeId ID of the node sending the data.
     * @return bool True on success.
     */
    public function storeDataLocally($fileId, $chunkId, $tempFilePath, $checksum, $sourceNodeId = 'client') {
        // Basic validation
        if (!file_exists($tempFilePath) || !is_readable($tempFilePath)) {
            error_log("Failed to store data locally: Temp file not found or not readable.");
            return false;
        }

        $expectedChecksum = Util::calculateFileChecksum($tempFilePath);
        if ($expectedChecksum === false || $expectedChecksum !== $checksum) {
            error_log("Checksum mismatch for fileId=$fileId, chunkId=$chunkId. Expected $checksum, Calculated $expectedChecksum.");
            // Optionally delete the temp file here
            @unlink($tempFilePath);
            return false;
        }

        $localPath = $this->storage->buildDataPath($fileId, $chunkId);
        if (!is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0777, true);
        }
        // echo $tempFilePath . ':' . $localPath;

        if (copy($tempFilePath, $localPath)) {
            // Update local metadata
            $metadata = [
                'file_id' => $fileId,
                'chunk_id' => $chunkId,
                'local_path' => $localPath, // Store absolute or relative? Relative might be better. Using absolute for now.
                'checksum' => $checksum,
                'size' => filesize($localPath),
                'state' => 'active', // Starts as active
                'last_accessed' => time(), // Initial access time
                'stored_at' => time(),
                'source_node' => $sourceNodeId, // Node that sent it to us
            ];
             $this->metadata->updateFileMetadata($fileId, $chunkId, $metadata);

            error_log("Successfully stored data locally: $localPath");
            return true;
        } else {
            error_log("Failed to move uploaded file to final destination: $localPath");
            return false;
        }
    }

     /**
      * Orchestrates uploading a file from a client and distributing it.
      * @param array $file $_FILES entry.
      * @return array|false File ID and success status or false on failure.
      */
     public function handleClientUpload($file) {
         // Basic file upload validation
         if ($file['error'] !== UPLOAD_ERR_OK) {
             error_log("File upload error: " . $file['error']);
             return false;
         }

         // Generate a unique ID for the file
         $fileId = uniqid('file_', true); // Example ID

         // Calculate checksum of the original uploaded file
         $checksum = Util::calculateFileChecksum($file['tmp_name']);
         if ($checksum === false) {
              error_log("Failed to calculate checksum for uploaded file.");
              @unlink($file['tmp_name']); // Clean up temp file
              return false;
         }

         // --- Replication Logic ---
         // In this simplified example, we're replicating the whole file as a single "chunk" (chunk_id = 0)
         $chunkId = '0';
         $originalTempPath = $file['tmp_name']; // Path to the uploaded file

         // Store the original upload on this node first
         // Need to copy the temp file because move_uploaded_file destroys it.
         $tempCopyPath = get_config('DATA_PATH') . basename($originalTempPath) . '_copy';
         if (!move_uploaded_file($originalTempPath, $tempCopyPath)) {
             error_log("Failed to make a temporary copy of the uploaded file.");
             // Original temp file is gone now anyway
             return false;
         }
         chmod($tempCopyPath, 0777); 


         // Store the copy locally
         $localStoreSuccess = $this->storeDataLocally($fileId, $chunkId, $tempCopyPath, $checksum, 'client');
         if (!$localStoreSuccess) {
              error_log("Failed to store original copy locally after upload.");
              @unlink($tempCopyPath); // Clean up temp copy
              return false;
         }
         @unlink($tempCopyPath); // Clean up temp copy after successful local store

         // Select peers for replication (excluding self)
         $allPeers = $this->peers->getAll();
         $peersToReplicate = [];
         $selfUrl = get_config('NODE_URL');

         // Filter out self and potentially filter by capabilities later
         $availablePeers = array_filter($allPeers, function($peerUrl) use ($selfUrl) {
             return $peerUrl !== $selfUrl;
         });

         // Select peers - simplistic random selection
         // Need at least REPLICATION_FACTOR - 1 other nodes
         if (count($availablePeers) < get_config('REPLICATION_FACTOR') - 1) {
             error_log("Not enough available peers (" . count($availablePeers) . ") for replication factor " . get_config('REPLICATION_FACTOR'));
             // Decide policy: fail upload? proceed with less replication? Proceeding for now.
             $peersToReplicate = $availablePeers;
         } else {
             // Select N-1 random unique peers
             $randomKeys = array_rand($availablePeers, get_config('REPLICATION_FACTOR') - 1);
             if (!is_array($randomKeys)) $randomKeys = [$randomKeys]; // Handle case where only 1 peer is selected
             foreach ($randomKeys as $key) {
                 $peersToReplicate[] = $availablePeers[$key];
             }
         }

         error_log("Replicating file $fileId to " . count($peersToReplicate) . " peers.");

         $replicationStatus = [];
         foreach ($peersToReplicate as $peerUrl) {
             // Send the original uploaded file content to the peer
             // Using CURLFile is the modern way for multipart uploads
             $cfile = new CURLFile($this->storage->buildDataPath($fileId, $chunkId), mime_content_type($this->storage->buildDataPath($fileId, $chunkId)), basename($this->storage->buildDataPath($fileId, $chunkId)));

             $postData = [
                 'file_data' => $cfile, // The file itself
                 'file_id' => $fileId,
                 'chunk_id' => $chunkId,
                 'checksum' => $checksum,
                 'source_node_id' => get_config('NODE_ID'),
                 // Add API_KEY_NAME header via curl_setopt
             ];

             $response = Util::requestPeer($peerUrl . 'api/upload_chunk.php', 'POST', $postData);

             if ($response !== false && isset($response['success']) && $response['success']) {
                 $replicationStatus[$peerUrl] = 'success';
                 error_log("Replication of $fileId to $peerUrl successful.");
             } else {
                 $replicationStatus[$peerUrl] = 'failed';
                 error_log("Replication of $fileId to $peerUrl failed. Response: " . print_r($response, true));
                 // TODO: Mark this replication as needed in analytics or metadata for healing later
             }
         }

         // Update local metadata with replication status (optional, more for tracking)
         // The metadata gossip will eventually inform us if other nodes received it.
         // $this->metadata->updateFileReplicationStatus($fileId, $chunkId, $replicationStatus);

         // TODO: Trigger immediate metadata gossip? Or rely on cron? Rely on cron for simplicity.

         return [
             'success' => $localStoreSuccess && count(array_filter($replicationStatus, function($s){ return $s === 'success'; })) >= get_config('REPLICATION_FACTOR') -1 , // Check if enough replicas were successful
             'file_id' => $fileId,
             'replication_status' => $replicationStatus,
         ];
     }

     /**
      * Serves a file to a client, fetching from peers if needed.
      * @param string $fileId
      * @return string|false File content or false on failure.
      */
     public function handleClientDownload($fileId) {
         // In this simple replication model, we just need one copy (chunk 0)
         $chunkId = '0';

         // 1. Check if this node has an active copy
         $localMetadata = $this->metadata->getChunkMetadata($fileId, $chunkId);

         if ($localMetadata && $localMetadata['state'] === 'active' && file_exists($localMetadata['local_path'])) {
             // Serve active local copy
             $this->incrementDownloadCount($fileId); // Increment count *on this node*
             $this->metadata->updateLastAccessed($fileId, $chunkId, time()); // Update access time
             // Trigger metadata gossip? Rely on cron.
             return file_get_contents($localMetadata['local_path']);
         }

          if ($localMetadata && $localMetadata['state'] === 'archived' && file_exists($localMetadata['archive_path'])) {
             // Serve archived local copy (unarchive first)
              error_log("Found archived copy of $fileId on this node. Unarchiving...");
             $extractedPath = $this->storage->unarchiveDataLocally($fileId, $chunkId, $localMetadata['archive_path'], $localMetadata['archive_entry_name']);

             if ($extractedPath && file_exists($extractedPath)) {
                 $this->incrementDownloadCount($fileId); // Increment count *on this node*
                 $this->metadata->updateLastAccessed($fileId, $chunkId, time()); // Update access time
                  // Optional: Change state back to active? Or let cron handle re-archiving?
                  // For simplicity, leave as archived, but update last_accessed.
                 $content = file_get_contents($extractedPath);
                 @unlink($extractedPath); // Clean up temporary extracted file
                 return $content;
             } else {
                 error_log("Failed to unarchive or find extracted file for $fileId on this node.");
                 // Fallback: try peers
             }
         }


         // 2. If no active/unarchivale local copy, try fetching from peers
         error_log("No active/unarchivale local copy of $fileId found. Querying peers...");
         $allPeers = $this->peers->getAll();
         $selfUrl = get_config('NODE_URL');
         $availablePeers = array_filter($allPeers, function($peerUrl) use ($selfUrl) {
             // Only try peers that can initiate HTTP and might have the data
             // A more advanced system would use gossiped metadata to find *which* peers have it.
             // For simplicity, try all peers that can do HTTP.
             $peerAnalytics = $this->analytics->getAll()['peer_status'][$peerUrl] ?? [];
             return $peerUrl !== $selfUrl && ($peerAnalytics['capabilities']['can_initiate_http'] ?? false);
         });

         // Shuffle peers for load balancing and trying different nodes
         shuffle($availablePeers);

         foreach ($availablePeers as $peerUrl) {
              error_log("Trying to fetch $fileId from peer: $peerUrl");
              // Request the specific chunk from the peer
              $url = $peerUrl . 'api/download_chunk.php?file_id=' . urlencode($fileId) . '&chunk_id=' . urlencode($chunkId);
              $response = Util::requestPeer($url, 'GET'); // Util::requestPeer adds auth header

              // Check if the response is likely file data (not JSON error) and not empty
              if ($response !== false && is_string($response) && $response !== '') {
                  error_log("Successfully fetched $fileId from peer $peerUrl.");
                   // We got the data. Should we store it locally for next time?
                   // This is complex (needs space, integrity check, metadata update, gossip). Omitted for now.
                   $this->incrementDownloadCount($fileId); // Increment count *on this node* (the one serving the client)
                   // We don't update last_accessed or state on the peer here.
                   return $response; // Serve the fetched data directly
              } else {
                  error_log("Failed to fetch $fileId from peer $peerUrl. Response was false, empty, or JSON error.");
              }
         }

         error_log("Failed to find or fetch $fileId from any available peer.");
         return false; // File not found or accessible
     }

     /**
      * Handles an incoming request for a file chunk from another node.
      * @param string $fileId
      * @param string $chunkId
      * @return string|false File chunk content or false on failure.
      */
     public function handleIncomingChunkRequest($fileId, $chunkId) {
         // Authenticate the incoming request
         if (!Util::isAuthenticated()) {
             error_log("Incoming chunk request unauthorized for $fileId/$chunkId.");
             return false; // Authentication failed
         }

         $localMetadata = $this->metadata->getChunkMetadata($fileId, $chunkId);

         if ($localMetadata && file_exists($localMetadata['local_path']) && $localMetadata['state'] === 'active') {
             // Serve active local copy to peer
              $this->incrementDownloadCount($fileId); // Increment count *on this node*
             $this->metadata->updateLastAccessed($fileId, $chunkId, time()); // Update access time
             // Trigger metadata gossip? Rely on cron.
             error_log("Serving active chunk $fileId/$chunkId to peer.");
             return file_get_contents($localMetadata['local_path']);
         }

         if ($localMetadata && file_exists($localMetadata['archive_path']) && $localMetadata['state'] === 'archived') {
             // Serve archived local copy to peer (unarchive first)
              error_log("Found archived copy of $fileId/$chunkId on this node for peer. Unarchiving...");
             $extractedPath = $this->storage->unarchiveDataLocally($fileId, $chunkId, $localMetadata['archive_path'], $localMetadata['archive_entry_name']);

             if ($extractedPath && file_exists($extractedPath)) {
                  $this->incrementDownloadCount($fileId); // Increment count *on this node*
                 $this->metadata->updateLastAccessed($fileId, $chunkId, time()); // Update access time
                 // Optional: Change state back to active? Or let cron handle re-archiving?
                 // For simplicity, leave as archived, but update last_accessed.
                 $content = file_get_contents($extractedPath);
                 @unlink($extractedPath); // Clean up temporary extracted file
                 error_log("Served unarchived chunk $fileId/$chunkId to peer.");
                 return $content;
             } else {
                 error_log("Failed to unarchive or find extracted file for $fileId/$chunkId for peer request.");
                 return false;
             }
         }

         error_log("Chunk $fileId/$chunkId not found or not active/archived on this node for peer request.");
         return false; // Not found or not available
     }


    // --- Cron Task Methods ---

    /**
     * Performs peer gossip.
     */
    public function doGossipPeers() {
        error_log("Running peer gossip...");
        $knownPeers = $this->peers->getAll();
        $selfUrl = get_config('NODE_URL');

        if (empty($knownPeers)) {
             error_log("No known peers, starting with seeds.");
             $knownPeers = get_config('SEED_NODERS'); // Start with seed nodes if peer list is empty
        }


        // Add self to the list if not present (important for self-checking)
        if (!in_array($selfUrl, $knownPeers)) {
            $knownPeers[] = $selfUrl;
            $this->peers->add($selfUrl); // Save self to peer list
        }


        // Select a few random peers to gossip with (excluding self)
        $peersToContact = array_filter($knownPeers, function($peerUrl) use ($selfUrl) {
            return $peerUrl !== $selfUrl;
        });

        // If no other peers, only add seeds to self if not present
        if(empty($peersToContact)){
             error_log("No other peers to contact. Ensuring seeds are in peer list.");
             $this->peers->add(get_config('SEED_NODES')); // Add all seeds
             return;
        }

        // Pick a random subset of peers to contact (e.g., 3 random peers)
        $numPeersToGossip = min(3, count($peersToContact));
        shuffle($peersToContact);
        $peersToContact = array_slice($peersToContact, 0, $numPeersToGossip);


        error_log("Contacting " . count($peersToContact) . " peers for gossip...");
        $newPeersDiscovered = 0;

        foreach ($peersToContact as $peerUrl) {
            error_log("Gossiping with: " . $peerUrl);
            $response = Util::requestPeer($peerUrl . 'api/peers.php', 'GET');

            if (is_array($response)) { // Expecting a JSON array of peer URLs
                 $newPeers = 0;
                 foreach ($response as $discoveredPeerUrl) {
                      if ($this->peers->add($discoveredPeerUrl)) {
                           $newPeersDiscovered++;
                           $newPeers++;
                      }
                 }
                 error_log("Discovered $newPeers new peers from $peerUrl.");

                 // Optional: Push our known peers back to the peer? (Push-pull model)
                 // Requires a /api/add_peers endpoint on the peer. Omitted for simplicity.

            } else {
                error_log("Gossip failed or unexpected response from $peerUrl.");
                 // Mark peer as potentially unhealthy? Handled by check_peers.
            }
        }
         error_log("Gossip complete. Discovered a total of $newPeersDiscovered new peers.");
    }

    /**
     * Performs metadata gossip (simplified).
     * Nodes exchange a summary or changes, not full metadata every time.
     * For simplicity, this just pulls *some* metadata from peers.
     */
    public function doGossipMetadata() {
        error_log("Running metadata gossip (simplified)...");
        $knownPeers = $this->peers->getAll();
         $selfUrl = get_config('NODE_URL');

        // Select a few random peers to get metadata from
        $peersToContact = array_filter($knownPeers, function($peerUrl) use ($selfUrl) {
            // Only gossip with peers known to be healthy and capable of HTTP
            $peerAnalytics = $this->analytics->getAll()['peer_status'][$peerUrl] ?? [];
            return $peerUrl !== $selfUrl && ($peerAnalytics['status'] ?? 'unknown') === 'ok' && ($peerAnalytics['capabilities']['can_initiate_http'] ?? false);
        });

        if(empty($peersToContact)){
             error_log("No healthy peers to gossip metadata with.");
             return;
        }

        $numPeersToGossip = min(3, count($peersToContact));
        shuffle($peersToContact);
        $peersToContact = array_slice($peersToContact, 0, $numPeersToGossip);

        error_log("Contacting " . count($peersToContact) . " peers for metadata...");

        foreach ($peersToContact as $peerUrl) {
            error_log("Getting metadata from: " . $peerUrl);
             // Request metadata (e.g., summary or recent changes).
             // For simplicity, let's say the endpoint returns metadata for a few random files it holds.
             $response = Util::requestPeer($peerUrl . 'api/metadata.php', 'GET'); // Can add parameters like ?summary=true or ?since=timestamp

            if (is_array($response)) { // Expecting a JSON object/array of metadata chunks
                // --- Metadata Merging Logic (Simplified) ---
                // This is complex! A real system needs timestamps, versioning, or CRDTs.
                // Simplification: Just add new metadata entries or potentially update
                // existing ones if the timestamp seems newer or the status is 'deleted'.
                $updatedEntries = 0;
                foreach ($response as $fileId => $fileMetadata) {
                     foreach ($fileMetadata['chunks'] ?? [] as $chunkId => $chunkData) {
                          // Example simple merge: if we don't have this chunk metadata, add it.
                          // If we have it, a real system would compare timestamps, checksums, etc.
                          // For this example, we just add if new. Deletion status needs special handling.
                           if (isset($chunkData['state']) && $chunkData['state'] === 'deleted') {
                               // If a peer says a chunk is deleted, mark it for deletion if we have it
                                $this->metadata->markChunkDeleted($fileId, $chunkId); // Needs implementation in Metadata.php
                                $updatedEntries++;
                           } elseif (!isset($this->metadata->getAll()[$fileId]['chunks'][$chunkId])) {
                                // If we don't have this chunk's metadata, add it.
                                // Note: This doesn't store the *data*, just the knowledge that a peer has it.
                                // The 'local_path' would be empty for these entries initially.
                                // A real system needs a separate way to track remote locations.
                                error_log("Learned about remote chunk: $fileId/$chunkId from $peerUrl");
                                // Storing remote metadata is complex. Omitting for this basic code.
                                // The 'peers.json' list combined with check_peers health is used implicitly for discovery.
                           }
                     }
                 }
                 // --- End Simplistic Merge ---

                error_log("Processed metadata from $peerUrl.");

            } else {
                error_log("Metadata gossip failed or unexpected response from $peerUrl.");
            }
        }
        error_log("Metadata gossip complete.");
    }


     /**
      * Checks health and capabilities of known peers.
      */
     public function doCheckPeers() {
         error_log("Running peer checks...");
         $knownPeers = $this->peers->getAll();
         $selfUrl = get_config('NODE_URL');
         $analytics = $this->analytics->getAll();

         // Check self status (always 'ok' if cron is running)
         $this->analytics->updatePeerStatus($selfUrl, 'ok', $this->capabilities);


         if (!($this->capabilities['can_initiate_http'] ?? false)) {
             error_log("Cannot initiate HTTP requests, skipping peer health checks.");
             return; // Cannot check peers without cURL
         }


         $peersToCheck = array_filter($knownPeers, function($peerUrl) use ($selfUrl) {
             return $peerUrl !== $selfUrl;
         });

         error_log("Checking health of " . count($peersToCheck) . " peers...");

         foreach ($peersToCheck as $peerUrl) {
             error_log("Checking peer: " . $peerUrl);
             $status = 'offline'; // Assume offline initially
             $capabilities = [];

             // Try to get capabilities and status
             $response = Util::requestPeer($peerUrl . 'api/capabilities.php', 'GET', null, 5); // Shorter timeout

             if ($response !== false && is_array($response) && isset($response['node_id'])) { // Expecting capability data
                 $status = 'ok';
                 $capabilities = $response;

                 // Optional: Perform code checksum check here if enabled and capable
                 // Requires /api/code_checksum.php and trusted hashes in config
                 /*
                 if (($this->capabilities['hashing'] ?? false) && ($capabilities['hashing'] ?? false)) {
                      // Check a few critical files... very basic
                      $criticalFiles = ['api/upload.php', 'api/download.php', 'cron/run_cron.php']; // Add more critical files
                       $mismatch = false;
                       foreach($criticalFiles as $file) {
                            $expectedHash = $this->getTrustedFileHash($file); // You need a function to get trusted hash from config
                            if ($expectedHash) {
                                 $hashResponse = Util::requestPeer($peerUrl . 'api/code_checksum.php?file=' . urlencode($file), 'GET', null, 5);
                                 if ($hashResponse !== false && is_array($hashResponse) && ($hashResponse['checksum'] ?? null) === $expectedHash) {
                                     // Match
                                 } else {
                                     error_log("Checksum mismatch for $file on peer $peerUrl");
                                     $mismatch = true;
                                     break; // Found a mismatch, no need to check more files on this peer
                                 }
                            }
                       }
                       if($mismatch) $status = 'checksum_mismatch';
                 }
                 */

             } else {
                 error_log("Peer $peerUrl is offline or returned invalid capabilities.");
                 $status = 'offline';
             }

             // Update peer status in analytics
             $this->analytics->updatePeerStatus($peerUrl, $status, $capabilities);
             error_log("Peer $peerUrl status: $status");
         }
          error_log("Peer checks complete.");
     }

     // Placeholder for trusted hash retrieval (needs implementation)
     private function getTrustedFileHash($filePath) {
         // Implement logic to read trusted hashes from config.php or a separate file
         // Example: return get_config('TRUSTED_FILE_HASHES')[$filePath] ?? null;
         return null; // Not implemented in this basic code
     }


    /**
     * Performs data cleanup (deletion, orphaned files, storage usage).
     * Could also trigger re-archiving check.
     */
    public function doCleanupData() {
        error_log("Running data cleanup...");
        $metadata = $this->metadata->getAll();
        $liveFiles = []; // Keep track of files that should exist based on metadata

        // 1. Identify files marked for deletion
        $deletedFiles = [];
        foreach ($metadata as $fileId => $fileMetadata) {
             foreach ($fileMetadata['chunks'] ?? [] as $chunkId => $chunkData) {
                  if (isset($chunkData['state']) && $chunkData['state'] === 'deleted') {
                      // Check if deletion timestamp is old enough
                      if (isset($chunkData['deleted_at']) && ($chunkData['deleted_at'] + get_config('DELETE_PROPAGATION_DELAY_HOURS') * 3600) < time()) {
                           $deletedFiles[] = ['file_id' => $fileId, 'chunk_id' => $chunkId, 'local_path' => $chunkData['local_path'] ?? null, 'archive_path' => $chunkData['archive_path'] ?? null];
                           error_log("Marking $fileId/$chunkId for physical deletion.");
                      } else {
                          // Not old enough, keep track they are still marked live in local view
                          if (isset($chunkData['local_path']) && file_exists($chunkData['local_path'])) {
                               $liveFiles[] = $chunkData['local_path'];
                          }
                           if (isset($chunkData['archive_path']) && file_exists($chunkData['archive_path'])) {
                               $liveFiles[] = $chunkData['archive_path']; // Keep archive path in live list too if state is deleted but not physically deleted yet
                          }
                      }
                  } else {
                      // Keep track of active/archived files that should exist
                      if (isset($chunkData['local_path']) && file_exists($chunkData['local_path'])) {
                           $liveFiles[] = $chunkData['local_path'];
                      }
                       if (isset($chunkData['archive_path']) && file_exists($chunkData['archive_path'])) {
                           $liveFiles[] = $chunkData['archive_path'];
                      }
                  }
             }
        }

        // 2. Physically delete marked files
        $deletedCount = 0;
        foreach ($deletedFiles as $fileInfo) {
             $success = false;
             // Try deleting the active file first
             if ($fileInfo['local_path'] && file_exists($fileInfo['local_path'])) {
                  $success = @unlink($fileInfo['local_path']);
                  if ($success) error_log("Physically deleted active file: " . $fileInfo['local_path']);
                  else error_log("Failed to physically delete active file: " . $fileInfo['local_path']);
             }
              // Then try deleting the archive file if it exists
             if ($fileInfo['archive_path'] && file_exists($fileInfo['archive_path'])) {
                  // Note: If multiple files are in one archive, only delete the archive if ALL are gone
                  // This is tricky! Simplification: Assume 1 file per archive for now.
                  $success = @unlink($fileInfo['archive_path']);
                   if ($success) error_log("Physically deleted archive file: " . $fileInfo['archive_path']);
                  else error_log("Failed to physically delete archive file: " . $fileInfo['archive_path']);
             }

             // TODO: After successful physical deletion, REMOVE the metadata entry completely.
             // This requires modifying Metadata.php and is complex for eventual consistency.
             // Omitted for this basic code. Metadata entries marked 'deleted' will persist.

             if ($success) $deletedCount++; // Increment if at least one path was deleted
        }
        error_log("Attempted physical deletion of $deletedCount marked file entries.");


        // 3. Identify and delete orphaned files (files on disk not in metadata)
        $allLocalFiles = $this->storage->getAllLocalFiles(); // Implement this in Storage.php
        $orphanedFiles = array_diff($allLocalFiles, $liveFiles);

        $orphanedCount = 0;
        foreach ($orphanedFiles as $orphanPath) {
             error_log("Deleting orphaned file: " . $orphanPath);
             if (@unlink($orphanPath)) {
                  $orphanedCount++;
             } else {
                  error_log("Failed to delete orphaned file: " . $orphanPath);
             }
        }
        error_log("Deleted $orphanedCount orphaned files.");


        // 4. Update storage usage analytics
        $this->updateStorageUsage();

        // 5. Trigger re-archiving check (part of archive_old_files or here)
         //$this->doArchiveOldFiles(true); // Pass true to indicate it's a re-archive check

         error_log("Data cleanup complete.");
    }

     /**
      * Performs archiving of old files.
      * @param bool $rearchiveCheck If true, only check for files recently unarchived.
      */
     public function doArchiveOldFiles($rearchiveCheck = false) {
         error_log("Running archiving process (rearchive check: " . ($rearchiveCheck ? 'Yes' : 'No') . ")...");

         if (!($this->capabilities['archiving'] ?? false)) {
             error_log("Skipping archiving: ZipArchive extension not available.");
             return;
         }

         // Define the threshold based on mode
         $thresholdDays = $rearchiveCheck ? get_config('REARCHIVE_WINDOW_DAYS') : get_config('ARCHIVE_THRESHOLD_DAYS');
         $cutOffTime = time() - ($thresholdDays * 24 * 3600);

         $metadata = $this->metadata->getAll();
         $archivedCount = 0;

         // Scan metadata for files to archive
         foreach ($metadata as $fileId => $fileMetadata) {
              foreach ($fileMetadata['chunks'] ?? [] as $chunkId => $chunkData) {
                  // Only consider active files on THIS node
                   if (($chunkData['state'] ?? 'active') === 'active' && isset($chunkData['local_path']) && file_exists($chunkData['local_path'])) {

                       $lastAccessed = $chunkData['last_accessed'] ?? $chunkData['stored_at'] ?? 0;

                       // Check if it meets the age criteria
                       $meetsCriteria = false;
                       if ($rearchiveCheck) {
                            // Re-archive check: Was it recently unarchived (state changed from archived)?
                            // This requires storing the previous state or a separate timestamp.
                            // Simplification: This part is complex and omitted.
                            // For this basic code, rearchiveCheck will likely do nothing unless you add state history.
                             error_log("Re-archive check logic not fully implemented.");
                             // TODO: Implement logic to find files recently set back to active.
                       } else {
                            // Standard archive check: Is it older than the main threshold?
                             if ($lastAccessed < $cutOffTime) {
                                  $meetsCriteria = true;
                             }
                       }


                       if ($meetsCriteria) {
                            error_log("Archiving $fileId/$chunkId (last accessed: " . date('Y-m-d', $lastAccessed) . ")");

                            // --- Archiving Logic ---
                            $originalPath = $chunkData['local_path'];
                            $archiveFileName = $fileId . '_' . $chunkId . '_' . time() . '.zip'; // Unique archive name
                            $archivePath = get_config('ARCHIVE_PATH') . $archiveFileName;
                            $archiveEntryName = basename($originalPath); // Name inside the zip

                            $zip = new ZipArchive;
                            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                                 if ($zip->addFile($originalPath, $archiveEntryName)) {
                                     $zip->close();

                                     // --- Verification ---
                                      $verifyZip = new ZipArchive;
                                     if ($verifyZip->open($archivePath) === TRUE) {
                                          $verifyZip->extractTo(sys_get_temp_dir(), $archiveEntryName);
                                          $verifyZip->close();
                                           $extractedVerifyPath = sys_get_temp_dir() . '/' . $archiveEntryName;
                                          if (file_exists($extractedVerifyPath) && Util::calculateFileChecksum($extractedVerifyPath) === $chunkData['checksum']) {
                                              @unlink($extractedVerifyPath); // Clean up
                                              // Verification successful! Delete original.
                                               if (@unlink($originalPath)) {
                                                    error_log("Original file deleted after archiving.");
                                                    // Update metadata
                                                    $this->metadata->updateFileMetadata($fileId, $chunkId, [
                                                        'state' => 'archived',
                                                        'local_path' => null, // Remove local path
                                                        'archive_path' => $archivePath,
                                                        'archive_entry_name' => $archiveEntryName,
                                                        'original_size' => $chunkData['size'], // Store original size
                                                    ]);
                                                    $archivedCount++;
                                                    // TODO: Trigger metadata gossip for this change? Rely on cron.
                                               } else {
                                                    error_log("Failed to delete original file after archiving: " . $originalPath);
                                                    // Revert? Leave original and archive? Need policy. Leaving both for now.
                                               }
                                          } else {
                                               error_log("Archived file checksum mismatch or verification failed for " . $archivePath);
                                              @unlink($archivePath); // Delete potentially bad archive
                                          }
                                     } else {
                                         error_log("Failed to open archive for verification: " . $archivePath);
                                          @unlink($archivePath); // Delete potentially bad archive
                                     }


                                 } else {
                                     error_log("Failed to add file to zip: " . $originalPath);
                                      @unlink($archivePath); // Delete empty or partial zip
                                 }
                            } else {
                                 error_log("Failed to create zip archive: " . $archivePath);
                            }
                            // --- End Archiving Logic ---
                       }
                  }
              }
         }

         error_log("Archiving complete. Archived $archivedCount files.");
     }


    /**
     * Detects and updates local node capabilities.
     */
    public function doCheckEnvironment() {
        error_log("Running environment check...");
         // Re-detect capabilities
         $this->capabilities = $this->detectCapabilities();

         // Update self status in analytics with new capabilities
         $this->analytics->updatePeerStatus(get_config('NODE_URL'), 'ok', $this->capabilities);

         error_log("Environment check complete. Capabilities updated.");
    }

    // --- Other Potential Cron Tasks (Not fully implemented) ---

    // public function doHealData() { ... } // Check for under-replicated files and copy from peers
    // public function doRebalanceData() { ... } // Move data to nodes with more space or better locality
}
?>