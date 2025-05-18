<?php
// gateway/lib/GatewayUtil.php - Utility functions for the Gateway

class GatewayUtil {

     /**
     * Makes an authenticated HTTP request to a node API endpoint.
     * @param string $url The URL to request (should include /api/...).
     * @param string $method HTTP method (GET, POST).
     * @param array|string $data Data for POST requests.
     * @param int $timeout Timeout in seconds.
     * @return array|false Response body (JSON decoded) or false on failure.
     */
    public static function requestNode($url, $method = 'GET', $data = null, $timeout = 15) {
        if (!function_exists('curl_init')) {
            // Cannot make requests without cURL on the gateway server
            error_log("Gateway Error: cURL extension not available.");
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Prevent infinite redirects

        // Add authentication header using the shared secret
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            get_gateway_config('GATEWAY_API_KEY_NAME') . ': ' . get_gateway_config('GATEWAY_NETWORK_SECRET'),
            'Expect:', // Prevents Expect: 100-continue header issues
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($data)) {
                 curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Default to form-urlencoded
                 // If sending JSON: curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', get_gateway_config('GATEWAY_API_KEY_NAME') . ': ' . get_gateway_config('GATEWAY_NETWORK_SECRET')]);
            } else {
                 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $http_code >= 400) {
            // Log errors but don't necessarily return false for all >= 400 codes
            // A 404 might just mean the file isn't on that node.
            error_log("Gateway cURL Error to $url: " . curl_error($ch) . " (HTTP Code: $http_code)");
             // For 401/403/500 errors, treat as failure
             if ($http_code === 401 || $http_code === 403 || $http_code >= 500) {
                 curl_close($ch);
                 return false;
             }
             // For 404, 400, etc., return the response or an indicator
             // For now, return the raw response string for non-fatal HTTP errors
        }

        curl_close($ch);

        // Attempt to decode JSON response
        $decoded_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded_response;
        }

        // Return raw response string if not JSON (e.g., file content from download_chunk)
        return $response;
    }

    /**
     * Makes an authenticated request to a node's *user-facing* download API
     * and returns the raw file content.
     * @param string $nodeUrl Base URL of the target node.
     * @param string $fileId The file ID to download.
     * @return string|false Raw file content on success, false on failure (including 404).
     */
     public static function downloadFileFromNode($nodeUrl, $fileId) {
         if (!function_exists('curl_init')) {
              error_log("Gateway Download Error: cURL extension not available.");
             return false;
         }

         $url = $nodeUrl . 'api/download.php?file_id=' . urlencode($fileId);

         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get content as return value
         curl_setopt($ch, CURLOPT_TIMEOUT, 600); // Longer timeout for downloads
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
         curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

         // Pass through user's browser headers that might be relevant (e.g., Range) - Basic example
         $requestHeaders = [];
          foreach ($_SERVER as $name => $value) {
              if (substr($name, 0, 5) == 'HTTP_') {
                  $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                  // Only pass through relevant headers, avoid passing security headers back to node download API
                  if (in_array($headerName, ['Range', 'If-Modified-Since', 'If-None-Match'])) {
                       $requestHeaders[] = "$headerName: $value";
                  }
              }
          }
          // Do not pass authentication secret header here! This is the *user* download API.
         if (!empty($requestHeaders)) {
              curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
         }


         // Get headers from the response to pass back to the client
         $responseHeaders = [];
         curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
             $len = strlen($header);
             $header = explode(':', $header, 2);
             if (count($header) < 2) // ignore invalid headers
                 return $len;
             $responseHeaders[strtolower(trim($header[0]))][] = trim($header[1]);
             return $len;
         });


         $fileContent = curl_exec($ch);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         $curl_error = curl_error($ch);

         curl_close($ch);

         if ($fileContent === false || $http_code >= 400) {
              error_log("Gateway Download Error from $nodeUrl for file $fileId: " . $curl_error . " (HTTP Code: $http_code)");
              // 404 means file not found on that node (or network issue)
              if ($http_code === 404) return null; // Use null to indicate file not found on this node
              return false; // Other errors (401, 500, curl error)
         }

         // Set headers received from the node before returning content
         foreach ($responseHeaders as $name => $values) {
             // Only set relevant headers, avoid duplicates if PHP sets them automatically
             if (!in_array($name, ['content-type', 'content-length', 'content-disposition', 'etag', 'last-modified', 'accept-ranges', 'content-range'])) {
                  continue; // Skip common headers PHP will handle or unwanted ones
             }
             // Handle Content-Disposition specially if needed, or trust the node's header
             foreach($values as $value) {
                 header("$name: $value");
             }
         }

         // Set default Content-Type if the node didn't provide one
         if (!isset($responseHeaders['content-type'])) {
              header('Content-Type: application/octet-stream');
         }
          // Set default Content-Disposition if the node didn't provide one (using fileId)
          if (!isset($responseHeaders['content-disposition'])) {
              header('Content-Disposition: attachment; filename="' . basename($fileId) . '"');
          }
          // Set Content-Length if the node didn't provide one (based on fetched content)
          if (!isset($responseHeaders['content-length'])) {
               header('Content-Length: ' . strlen($fileContent));
          }


         return $fileContent; // Return the raw file content
     }
}

?>