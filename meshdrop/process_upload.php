<?php
// process_upload.php - Receives file from HTML form and sends to a LibreMesh node API

// --- Configuration ---
// !!! IMPORTANT: Replace with the URL of a reachable LibreMesh node's upload API !!!
// This client only needs to know about *one* node to upload the file.
define('TARGET_NODE_UPLOAD_API', 'https://libremesh-mesh0-root.infy.uk/api/upload.php');

// --- Error Handling ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --------------------

?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Status</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
         .message { margin-top: 15px; padding: 10px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background-color: #ffeeba; color: #856404; border-color: #ffc107; }
    </style>
</head>
<body>

    <h1>Upload Status</h1>

<?php
// Check if the form was submitted and a file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_upload'])) {

    $file = $_FILES['file_upload'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'File upload failed with error code: ' . $file['error'];
        // You can add more specific error messages based on UPLOAD_ERR_XXX constants
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message .= ' (The uploaded file exceeds the upload_max_filesize directive in php.ini)';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message .= ' (The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message .= ' (The uploaded file was only partially uploaded)';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message .= ' (No file was uploaded)';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message .= ' (Missing a temporary folder)';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message .= ' (Failed to write file to disk)';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message .= ' (A PHP extension stopped the file upload)';
                break;
            default:
                $error_message .= ' (Unknown error)';
                break;
        }
        echo '<div class="message error">' . htmlspecialchars($error_message) . '</div>';

    } elseif ($file['size'] == 0) {
         echo '<div class="message error">Uploaded file is empty.</div>';

    } else {
        // File uploaded successfully to the client's temporary directory
        $tempFilePath = $file['tmp_name'];
        $originalFileName = $file['name']; // You might want to send original name too, but not required by current API

        // --- Use cURL to send the file to the LibreMesh node API ---

        if (!function_exists('curl_init')) {
             echo '<div class="message error">cURL PHP extension is not available on this client server. Cannot send file.</div>';
        } else {

            $cfile = null;
            // Use CURLFile for modern PHP (>= 5.5) - Recommended
            if (class_exists('CURLFile')) {
                $cfile = new CURLFile($tempFilePath, $file['type'], $originalFileName);
            } else {
                 // Fallback for older PHP - @ syntax (deprecated)
                 $cfile = '@' . $tempFilePath;
                 // Warning: This might require CURL_MIMEPOST in some older cURL versions or may not set Content-Type correctly.
                 echo '<div class="message info">Using deprecated @ syntax for cURL file upload. Consider upgrading PHP.</div>';
            }


            if ($cfile) {
                $postData = array(
                    // 'file_upload' MUST match the input name in upload_form.html and the expected key in api/upload.php
                    'file_upload' => $cfile,
                    // Add any other necessary fields here if the API expected them
                    // e.g., 'user_id' => 'some_user_id',
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, TARGET_NODE_UPLOAD_API);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as a string
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Set a reasonable timeout (e.g., 300 seconds)
                // Do NOT set Content-Type: multipart/form-data header manually when using CURLOPT_POSTFIELDS with file uploads,
                // cURL handles it correctly including the boundary.

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);

                curl_close($ch);
                echo $response;

                if ($response === false || $http_code >= 400) {
                    echo '<div class="message error">Error sending file to LibreMesh node API: ' . htmlspecialchars($curl_error) . ' (HTTP Code: ' . $http_code . ')</div>';
                    if ($response !== false) {
                         echo '<div class="message info">API Response Body: <pre>' . htmlspecialchars($response) . '</pre></div>';
                    }
                } else {
                    // Attempt to decode JSON response from the node API
                    $responseData = json_decode($response, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($responseData['success'])) {
                        if ($responseData['success']) {
                            echo '<div class="message success">File uploaded successfully to the network!</div>';
                            echo '<div class="message info">File ID: <code>' . htmlspecialchars($responseData['file_id'] ?? 'N/A') . '</code></div>';
                            echo '<div class="message info">Received by Node: <code>' . htmlspecialchars($responseData['node_id'] ?? 'N/A') . '</code></div>';
                            // Optional: Display replication status if provided
                            if (!empty($responseData['replication_status'])) {
                                 echo '<div class="message info">Replication Status: <pre>' . htmlspecialchars(print_r($responseData['replication_status'], true)) . '</pre></div>';
                            }
                        } else {
                            echo '<div class="message error">LibreMesh node API reported an error during upload.</div>';
                            echo '<div class="message info">API Response: <pre>' . htmlspecialchars($response) . '</pre></div>';
                        }
                    } else {
                        echo '<div class="message error">Received invalid or non-JSON response from LibreMesh node API.</div>';
                         echo '<div class="message info">HTTP Code: ' . $http_code . '</div>';
                        echo '<div class="message info">API Response Body: <pre>' . htmlspecialchars($response) . '</pre></div>';
                    }
                }
            }
        }

        // PHP automatically cleans up the temporary file in $_FILES after the script finishes.

    }

} else {
    // If accessed directly without POST
    echo '<div class="message info">Please use the upload form to submit a file.</div>';
}
?>

    <p><a href="upload_form.html">Back to Upload Form</a></p>

</body>
</html>