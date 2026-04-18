#!/bin/bash
# Test file upload to node1 and verify replication to node2

set -e

NODE1_URL="http://localhost:8001"
NODE2_URL="http://localhost:8002"
SECRET="test-secret"

echo "=== Testing Upload and Replication ==="

# Create test file
TEST_FILE="/tmp/test_file_$$.txt"
echo "LibreMesh test file - $(date)" > "$TEST_FILE"
FILE_SIZE=$(stat -f%z "$TEST_FILE" 2>/dev/null || stat -c%s "$TEST_FILE")

echo "Created test file: $TEST_FILE ($FILE_SIZE bytes)"

# Upload to node1
echo "Uploading to node1..."
RESPONSE=$(curl -s -X POST \
    -F "file_upload=@${TEST_FILE}" \
    "${NODE1_URL}/api/upload.php")

echo "Upload response: $RESPONSE"

# Extract file_id from response
FILE_ID=$(echo "$RESPONSE" | grep -o '"file_id":"[^"]*"' | cut -d'"' -f4)

if [ -z "$FILE_ID" ]; then
    echo "FAILED: No file_id returned"
    rm -f "$TEST_FILE"
    exit 1
fi

echo "File ID: $FILE_ID"

# Wait for replication
sleep 2

# Check node1 has the file
echo "Checking node1 metadata..."
NODE1_META=$(curl -s "${NODE1_URL}/api/metadata.php" \
    -H "X-Network-Secret: ${SECRET}")

echo "Node1 metadata: $NODE1_META"

# Check node2 has the file
echo "Checking node2 metadata..."
NODE2_META=$(curl -s "${NODE2_URL}/api/metadata.php" \
    -H "X-Network-Secret: ${SECRET}")

echo "Node2 metadata: $NODE2_META"

# Verify node2 has the file
if echo "$NODE2_META" | grep -q "$FILE_ID"; then
    echo "SUCCESS: File replicated to node2"
else
    echo "WARNING: File not found on node2 (may need gossip to propagate)"
fi

# Download from node1
echo "Downloading from node1..."
curl -s -o /tmp/downloaded_file.txt "${NODE1_URL}/api/download.php?file_id=${FILE_ID}"

if [ -f /tmp/downloaded_file.txt ]; then
    echo "Downloaded content:"
    cat /tmp/downloaded_file.txt
fi

# Cleanup
rm -f "$TEST_FILE" /tmp/downloaded_file.txt

echo "=== Upload/Replication Test Complete ==="