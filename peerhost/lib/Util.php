<?php
// lib/Util.php

class Util {

    /**
     * Makes an authenticated HTTP request to a peer.
     * @param string $url The URL to request.
     * @param string $method HTTP method (GET, POST).
     * @param array|string $data Data for POST requests.
     * @param int $timeout Timeout in seconds.
     * @return array|false Response body (JSON decoded) or false on failure.
     */
    public static function requestPeer($url, $method = 'GET', $data = null, $timeout = 10) {
        if (!function_exists('curl_init')) {
            // Cannot make requests without cURL
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Prevent infinite redirects

        // Add authentication header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            get_config('API_KEY_NAME') . ': ' . get_config('NETWORK_SECRET'),
            'Expect:', // Prevents Expect: 100-continue header issues
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($data)) {
                 // Check for file uploads specifically
                 $is_multipart = false;
                 foreach ($data as $key => $value) {
                     if (is_string($value) && strpos($value, '@') === 0 && file_exists(substr($value, 1))) {
                         // Using deprecated '@' syntax, might need CurlFile for modern PHP/cURL
                         error_log("Warning: Using deprecated '@' syntax for cURL file upload. Consider CurlFile.");
                         $is_multipart = true; // Using @ implies multipart/form-data
                     } elseif ($value instanceof CURLFile) {
                         $is_multipart = true;
                         break;
                     }
                 }

                 if ($is_multipart) {
                      // Let cURL set Content-Type for multipart
                      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                 } else {
                      // Assume JSON or form-urlencoded
                      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Default to form-urlencoded
                      // If sending JSON: curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', get_config('API_KEY_NAME') . ': ' . get_config('NETWORK_SECRET')]);
                 }

            } else {
                 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $http_code >= 400) {
            error_log("cURL Error to $url: " . curl_error($ch) . " (HTTP Code: $http_code)");
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        // Attempt to decode JSON response
        $decoded_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded_response;
        }

        // Return raw response if not JSON
        return $response;
    }

    /**
     * Basic authentication check for incoming requests.
     * @return bool True if authenticated, false otherwise.
     */
    public static function isAuthenticated() {
        $secret_header = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', get_config('API_KEY_NAME')))] ?? '';
        return $secret_header === get_config('NETWORK_SECRET');
    }

    /**
     * Reads JSON data from a file with basic flocking.
     * @param string $filePath
     * @return array|null Decoded JSON data or null on failure/empty file.
     */
    public static function readJsonFile($filePath) {
        if (!file_exists($filePath)) {
            return null; // Or [] if expecting array
        }
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            error_log("Failed to open file for reading: " . $filePath);
            return null;
        }
        $data = null;
        if (flock($handle, LOCK_SH)) { // Acquire a shared lock
            $content = file_get_contents($filePath);
            if ($content !== false && $content !== '') {
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                     error_log("JSON decode error in " . $filePath . ": " . json_last_error_msg());
                     $data = null; // Treat as failure if JSON is invalid
                }
            }
            flock($handle, LOCK_UN); // Release the lock
        } else {
            error_log("Could not acquire shared lock on " . $filePath);
        }
        fclose($handle);
        return $data;
    }

     /**
     * Writes JSON data to a file with basic flocking.
     * @param string $filePath
     * @param array $data The data to encode and write.
     * @return bool True on success, false on failure.
     */
    public static function writeJsonFile($filePath, $data) {
        $json_data = json_encode($data, JSON_PRETTY_PRINT);
        if ($json_data === false) {
             error_log("JSON encode error for " . $filePath . ": " . json_last_error_msg());
             return false;
        }

        $handle = fopen($filePath, 'c+'); // Use c+ to create if not exists, truncate to 0 if exists
        if ($handle === false) {
            error_log("Failed to open file for writing: " . $filePath);
            return false;
        }
        $success = false;
        if (flock($handle, LOCK_EX)) { // Acquire an exclusive lock
            ftruncate($handle, 0); // Truncate the file to zero size
            rewind($handle); // Rewind to the beginning of the file
            if (fwrite($handle, $json_data) !== false) {
                $success = true;
            } else {
                 error_log("Failed to write to file: " . $filePath);
            }
            fflush($handle); // Ensure all buffered data is written
            flock($handle, LOCK_UN); // Release the lock
        } else {
            error_log("Could not acquire exclusive lock on " . $filePath);
        }
        fclose($handle);
        // Simple check: does the file now exist and have content? (Optional, but adds robustness)
         if ($success && file_exists($filePath) && filesize($filePath) > 0) {
             return true;
         }
         return false;
    }

    /**
     * Calculates checksum of a file using available algorithms.
     * @param string $filePath
     * @return string|false Checksum string or false on failure or no supported algorithm.
     */
    public static function calculateFileChecksum($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        if (function_exists('sha256_file')) {
            return 'sha256:' . sha256_file($filePath);
        } elseif (function_exists('md5_file')) {
            error_log("SHA256 not available, falling back to MD5 for " . $filePath);
            return 'md5:' . md5_file($filePath);
        }
        error_log("No supported hashing function available for " . $filePath);
        return false;
    }

    /**
     * Formats bytes into a human-readable string.
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>