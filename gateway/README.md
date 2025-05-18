/gateway/ (or wherever you deploy it)
├── index.php           (Simple entry point/search form)
├── download.php        (Handles file download requests)
├── config.php          (Gateway configuration)
├── data/               (MUST be writable by PHP)
│   ├── gateway_peers_status.json (Stores status and capabilities of known nodes)
│   └── last_node_index.txt (Simple file for round-robin)
├── lib/
│   ├── GatewayUtil.php   (Utility functions for the gateway)
│   └── PeerStatus.php    (Handles loading/saving peer status)
└── cron/
    └── update_peer_status.php (Cron task to refresh peer status)