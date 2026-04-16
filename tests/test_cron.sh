#!/bin/bash
# Test cron tasks on a node

set -e

NODE_URL="${1:-http://localhost:8001/libremesh}"
SECRET="test-secret"

echo "=== Testing Cron Tasks on ${NODE_URL} ==="

# Test check_environment
echo -e "\n--- Testing check_environment ---"
RESPONSE=$(curl -s "${NODE_URL}/cron/run_cron.php?task=check_environment")
echo "Response: $RESPONSE"

# Test check_peers
echo -e "\n--- Testing check_peers ---"
RESPONSE=$(curl -s "${NODE_URL}/cron/run_cron.php?task=check_peers")
echo "Response: $RESPONSE"

# Test gossip_peers
echo -e "\n--- Testing gossip_peers ---"
RESPONSE=$(curl -s "${NODE_URL}/cron/run_cron.php?task=gossip_peers")
echo "Response: $RESPONSE"

# Test gossip_metadata
echo -e "\n--- Testing gossip_metadata ---"
RESPONSE=$(curl -s "${NODE_URL}/cron/run_cron.php?task=gossip_metadata")
echo "Response: $RESPONSE"

# Test capabilities endpoint
echo -e "\n--- Testing capabilities.php ---"
RESPONSE=$(curl -s "${NODE_URL}/api/capabilities.php" \
    -H "X-Network-Secret: ${SECRET}")
echo "Capabilities: $RESPONSE"

# Test analytics endpoint
echo -e "\n--- Testing analytics.php ---"
RESPONSE=$(curl -s "${NODE_URL}/api/analytics.php?type=status" \
    -H "X-Network-Secret: ${SECRET}")
echo "Analytics: $RESPONSE"

echo -e "\n=== Cron Test Complete ==="