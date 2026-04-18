<?php
// meshdrop/download.php - Simple file download via LibreMesh network

// Network configuration for reaching LibreMesh nodes
// These URLs work from inside the Docker network via docker-compose aliases
define('SEED_NODES', [
    'http://node1.libremesh.local:80',
    'http://node2.libremesh.local:80',
]);
define('NETWORK_SECRET', 'test-secret');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

$fileId = $_GET['file_id'] ?? null;
if (!$fileId) {
    http_response_code(400);
    echo "Missing file_id parameter.";
    exit;
}

// Discover nodes and find one that has the file
$seedNodes = SEED_NODES;
$networkSecret = NETWORK_SECRET;
$selectedNodeUrl = null;
$fileContent = null;

foreach ($seedNodes as $nodeUrl) {
    // Query node for its full analytics to check health and capabilities
    $analyticsUrl = rtrim($nodeUrl, '/') . '/libremesh/api/analytics.php?type=status';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $analyticsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Network-Secret: ' . $networkSecret,
        'Expect:',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        continue;
    }

    $data = json_decode($response, true);
    if (!isset($data['success']) || $data['success'] !== true) {
        continue;
    }

    $capabilities = $data['status']['capabilities'] ?? [];
    $nodeStatus = $data['status'] ?? [];

    // Check if node is healthy enough to try
    if (!($capabilities['can_initiate_http'] ?? false)) {
        continue;
    }

    $selectedNodeUrl = rtrim($nodeUrl, '/');
    break;
}

if (!$selectedNodeUrl) {
    http_response_code(503);
    echo "Error: No healthy nodes available.";
    exit;
}

// Attempt download from selected node using user-facing download API
$downloadUrl = $selectedNodeUrl . '/libremesh/api/download.php?file_id=' . urlencode($fileId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $downloadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

$responseHeaders = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
    $len = strlen($header);
    $header = explode(':', $header, 2);
    if (count($header) < 2) {
        return $len;
    }
    $responseHeaders[strtolower(trim($header[0]))][] = trim($header[1]);
    return $len;
});

$fileContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 404) {
    http_response_code(404);
    echo "Error: File not found.";
    exit;
}

if ($httpCode >= 400 || $fileContent === false) {
    http_response_code(502);
    echo "Error: Failed to retrieve file from network.";
    exit;
}

// Pass through relevant headers from the node
foreach ($responseHeaders as $name => $values) {
    if (in_array($name, ['content-type', 'content-length', 'content-disposition', 'etag', 'last-modified'])) {
        foreach ($values as $value) {
            header("$name: $value");
        }
    }
}

if (!isset($responseHeaders['content-type'])) {
    header('Content-Type: application/octet-stream');
}
if (!isset($responseHeaders['content-disposition'])) {
    header('Content-Disposition: attachment; filename="' . basename($fileId) . '"');
}
if (!isset($responseHeaders['content-length'])) {
    header('Content-Length: ' . strlen($fileContent));
}

echo $fileContent;
exit;
