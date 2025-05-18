<?php
// lib/Storage.php

class Storage {

    /**
     * Builds the expected local path for a file/chunk.
     * @param string $fileId
     * @param string $chunkId
     * @return string Absolute path.
     */
    public function buildDataPath($fileId, $chunkId) {
        // Use first few chars of fileId for directory structure to avoid huge single directories
        $dir = get_config('DATA_PATH') . substr($fileId, 0, 2) . '/' . substr($fileId, 2, 2) . '/';
        $filename = $fileId . '_' . $chunkId . '.dat'; // .dat extension just indicates data
        return $dir . $filename;
    }

    /**
     * Gets a list of all data files currently on disk based on directory structure.
     * Ignores archive directory for this check.
     * @param string $directory Optional subdirectory to scan.
     * @return array List of absolute file paths.
     */
    public function getAllLocalFiles($directory = null) {
        $allFiles = [];
        $basePath = $directory ?? get_config('DATA_PATH');

        // Prevent scanning archive dir as part of "live" files
         if ($basePath === get_config('DATA_PATH')) {
              $ignoreDirs = [get_config('ARCHIVE_PATH')];
         } else {
              $ignoreDirs = [];
         }


        if (!is_dir($basePath)) {
            return [];
        }

        $items = scandir($basePath);
        if ($items === false) {
             error_log("Failed to scan directory: " . $basePath);
             return [];
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $basePath . $item;

            if (is_dir($itemPath)) {
                 if (!in_array($itemPath . '/', $ignoreDirs) && !in_array($itemPath, $ignoreDirs)) {
                    $allFiles = array_merge($allFiles, $this->getAllLocalFiles($itemPath . '/')); // Recurse
                 }
            } elseif (is_file($itemPath)) {
                 // Filter out state files like peers.json, metadata.json, analytics.json from data files list
                 $excludedFiles = [
                     get_config('PEERS_FILE'),
                     get_config('METADATA_FILE'),
                     get_config('ANALYTICS_FILE'),
                     // Add other non-data files in DATA_PATH if any
                 ];
                 if (!in_array($itemPath, $excludedFiles)) {
                      $allFiles[] = $itemPath;
                 }
            }
        }
        return $allFiles;
    }

    /**
     * Unarchives a specific file entry from a zip archive.
     * @param string $fileId For logging/debugging.
     * @param string $chunkId For logging/debugging.
     * @param string $archivePath Path to the zip file.
     * @param string $entryName Name of the file inside the zip archive.
     * @return string|false Path to the extracted temporary file on success, false on failure.
     */
    public function unarchiveDataLocally($fileId, $chunkId, $archivePath, $entryName) {
        if (!extension_loaded('zip')) {
            error_log("Cannot unarchive: ZipArchive extension not available.");
            return false;
        }
         if (!file_exists($archivePath)) {
             error_log("Archive file not found: " . $archivePath);
             return false;
         }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) === TRUE) {
            // Extract to a temporary location
            $tempDir = sys_get_temp_dir(); // Use system temp directory
            $extractedPath = $tempDir . '/' . uniqid('unarchived_', true) . '_' . $entryName;

            if ($zip->extractTo($tempDir, $entryName)) {
                 $zip->close();
                 // Check if the extracted file exists at the expected temp path
                 if (file_exists($extractedPath)) {
                     error_log("Successfully extracted $entryName from $archivePath to $extractedPath");
                     return $extractedPath;
                 } else {
                      error_log("Failed to find extracted file at expected temp path: " . $extractedPath);
                      return false;
                 }

            } else {
                error_log("Failed to extract entry '$entryName' from archive: " . $archivePath . " Error: " . $zip->getStatusString());
                $zip->close();
                return false;
            }
        } else {
            error_log("Failed to open zip archive for unarchiving: " . $archivePath . " Error code: " . $zip->getStatusString()); // getStatusString in newer PHP
             // Handle different open errors like ZIP_ER_NOENT (file not found), etc.
            return false;
        }
    }

    // TODO: Implement logic to delete specific entry from a zip (hard in PHP ZipArchive)
    // OR simply delete the entire zip if all contained files are marked for deletion.
     public function deleteArchivedEntry($archivePath, $entryName) {
         error_log("Deleting specific entry from archive not fully supported by PHP ZipArchive without rewriting. Archive: $archivePath, Entry: $entryName");
         // Workaround: If this was the only file in the archive, delete the whole archive.
         // This requires knowing if the archive contained only this one file originally.
         // Complex. In this basic code, rely on cleanup_data deleting the whole .zip file
         // when the *last* file it contains is marked for deletion and propagation delay passes.
         return false; // Indicate not fully supported
     }
}
?>