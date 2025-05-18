# LibreMesh
Decentralized File Storage on Shared Hosting (Proof of Concept)

LibreMesh is a proof-of-concept implementation for a decentralized file storage system designed to be easily deployed on standard shared hosting environments using PHP. It aims to provide a distributed way to store files with redundancy, leveraging common web hosting features like HTTP and cron jobs, while accounting for the variability in PHP environments.

**⚠️ IMPORTANT DISCLAIMER:** This project is a *basic framework* intended to demonstrate architectural concepts. It is **not production-ready**, lacks robust error handling, advanced security hardening, efficient large-scale data management, and guaranteed consistency. Deploy and use at your own risk, especially for sensitive data. Client-side encryption is highly recommended.

## Features

* **Shared Hosting Compatible:** Built primarily with PHP, HTTP requests, and cron jobs, making it deployable on most standard shared hosting packages.
* **Decentralized Peer Discovery:** Nodes automatically discover and connect to each other using a simple HTTP-based gossip protocol, bootstrapped by seed nodes.
* **Data Replication:** Supports storing multiple copies (replicas) of files across different nodes for redundancy.
* **Eventual Consistency:** Peer lists, metadata, and analytics propagate through the network over time.
* **File Deletion:** Supports logical marking for deletion, with eventual physical removal via background cleanup tasks.
* **Space Saving (Archiving):** Archives rarely used files via compression (`ZipArchive`) and unarchives them on demand. Adapts if the `zip` extension is missing on a node.
* **PHP Extension Redundancy:** Checks for available PHP extensions (`curl`, `zip`, `hashing`) and adapts its behavior and reports its capabilities to peers.
* **Basic Analytics:** Tracks file download counts (per node), peer health, and local storage usage on each node.
* **Analytics API:** Provides a secure API endpoint on each node for a separate gateway application to retrieve and aggregate analytics data.
* **Simple User API:** Basic HTTP endpoints for file upload and download by users.

## Getting Started

These instructions will get a single LibreMesh node running on your shared hosting. To form a network, you need to deploy this code on multiple hosting accounts and configure them to know about each other via `SEED_NODES`.

### Prerequisites

* A shared hosting account with:
    * PHP support (>= 7.4 recommended).
    * The ability to set up Cron Jobs.
    * `json` extension (usually standard).
    * `curl` extension (highly recommended for node-to-node communication).
    * `zip` extension (required for archiving feature).
    * `file_get_contents`, `file_put_contents`, `unlink`, `mkdir`, `scandir`, `flock` functions enabled.
    * Sufficient disk space (each node requires space for its data, plus replicas/archives).
* FTP/SFTP access to upload files.
* Access to your hosting control panel (cPanel, Plesk, etc.) to set up cron jobs.

### Installation Steps

1.  **Download Code:** Get the latest version of the LibreMesh codebase (from this repository once available, or use the code provided).
2.  **Upload Files:** Upload the entire directory structure (including `api/`, `cron/`, `lib/`, `index.php`, `config.php`, `.htaccess`) to a subdirectory on your shared hosting account (e.g., `public_html/libremesh/`).
3.  **Create Data Directories:** Create the `data/` and `data/archives/` directories inside your deployed location (e.g., `public_html/libremesh/data/`, `public_html/libremesh/data/archives/`).
    * **Security Best Practice:** If your hosting allows, create the `data/` directory *outside* your public web root (e.g., `/home/yourusername/libremesh_data/`) and update the `DATA_PATH` constant in `config.php` accordingly. This prevents direct web access to your stored files.
4.  **Set Directory Permissions:** Ensure the `data/` and `data/archives/` directories are writable by the web server process. Typically, setting permissions to `755` or `775` works, but consult your hosting provider's documentation if needed.
5.  **Configure `config.php`:**
    * Edit the `config.php` file.
    * **`NETWORK_SECRET`:** **CHANGE THIS** to a long, unique, random string shared *across all nodes in your LibreMesh network*. This acts as the network's password for node-to-node communication.
    * **`NODE_ID`:** **CHANGE THIS** to a unique identifier for this specific hosting node (e.g., a hostname, a unique UUID).
    * **`NODE_URL`:** **CHANGE THIS** to the full, public URL where you deployed the code (e.g., `https://<NODE>/libremesh/`). This is how other nodes will contact this one.
    * **`SEED_NODES`:** Update this array. Start by including your own `NODE_URL`. As you deploy more nodes, add their `NODE_URL`s to the `SEED_NODES` list on *all* nodes so they can discover each other.
    * Verify `DATA_PATH` and `ARCHIVE_PATH` are correct.
    * Review `REPLICATION_FACTOR`, archiving settings, and cron intervals and adjust as needed.
    * **Security:** In a non-development environment, ensure `display_errors` is off and `log_errors` is on in your PHP configuration or within `config.php`.
6.  **Initial State Files:** Access your `index.php` in a web browser once (e.g., `https://<NODE>/libremesh/`). This should trigger `config.php` to create the initial empty state files (`peers.json`, `metadata.json`, `analytics.json`) if they don't exist. Verify these files appear in your `data/` directory.
7.  **Setup Cron Jobs:**
    * Login to your hosting control panel and find the "Cron Jobs" section.
    * Add entries to execute the `cron/run_cron.php` script using `wget` or `curl`. You need multiple entries for different tasks:
        * **Check Environment:** Periodically checks PHP capabilities.
            `*/60 * * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=check_environment >/dev/null 2>&1` (e.g., hourly)
        * **Gossip Peers:** Discovers new peers and shares known ones.
            `*/15 * * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=gossip_peers >/dev/null 2>&1` (e.g., every 15 mins)
        * **Check Peers:** Checks peer health and capabilities.
            `*/60 * * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=check_peers >/dev/null 2>&1` (e.g., hourly)
        * **Gossip Metadata:** Synchronizes file/chunk metadata.
            `*/30 * * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=gossip_metadata >/dev/null 2>&1` (e.g., every 30 mins)
        * **Cleanup/Archiving:** Handles deletion, orphans, storage usage, and archives old files.
            `0 */24 * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=cleanup_data >/dev/null 2>&1` (e.g., daily at midnight)
            `0 */24 * * * /usr/bin/wget -O /dev/null https://<NODE>/libremesh/cron/run_cron.php?task=archive_old_files >/dev/null 2>&1` (can be same schedule or separate)
    * **Adjust paths and schedules:** Make sure `/usr/bin/wget` is correct for your host and adjust the `*/XX * * * *` timing as per your `config.php` intervals. `>/dev/null 2>&1` sends output and errors to nowhere, preventing potential emails on every run.
8.  **Verify:** Check your hosting logs for cron job execution and PHP `error_log` for any errors. After cron runs, check `data/peers.json`, `data/metadata.json`, and `data/analytics.json` to see if they are being updated.

## Usage

### Node Operators

* Deploy and configure the code as described above.
* Ensure cron jobs are running correctly.
* Monitor PHP error logs for issues.
* Check `index.php` occasionally for a basic overview of the node's status and known peers.

### Users (Developers)

Interact with the node's API endpoints using tools like `curl` or within your own applications:

* **Upload File:**
    ```bash
    curl -X POST -F "file_upload=@/path/to/your/file.txt" [https://your-node-url.com/api/upload.php](https://your-node-url.com/api/upload.php)
    ```
    * The response will include the `file_id` assigned by the network.
* **Download File:**
    ```bash
    curl -O [https://your-node-url.com/api/download.php?file_id=YOUR_FILE_ID](https://your-node-url.com/api/download.php?file_id=YOUR_FILE_ID)
    ```
    * Replace `YOUR_FILE_ID` with the ID obtained during upload.

### Analytics Gateway

The project includes an `/api/analytics.php` endpoint on each node, protected by the `NETWORK_SECRET`. A separate application (the "Gateway") needs to be built to:

1.  Maintain a list of known LibreMesh nodes (possibly by querying `/api/peers.php` or `/api/analytics.php?type=peer_health`).
2.  Query the `/api/analytics.php` endpoint on individual nodes (using the `NETWORK_SECRET`) to retrieve:
    * Node status and capabilities (`type=status`)
    * Peer health status (`type=peer_health`)
    * File download counts on that specific node (`type=downloads` or `type=downloads&file_id=...`)
3.  Aggregate the data from multiple nodes (e.g., sum download counts for a file across all nodes that have a copy).
4.  Provide a user interface to display the network status, node list, file locations (inferred from nodes reporting data), and aggregated download counts.

This Gateway application is **not included** in this codebase and needs to be developed separately.

## Project Status & Limitations

This is a **proof-of-concept**. Key limitations include:

* **No Strong Consistency:** Metadata merging is simplistic; conflicts may occur, potentially leading to temporary inconsistencies or requiring manual intervention.
* **Simplified Replication/No Sharding:** Only supports simple full file replication (as chunk 0). True content-addressable storage, M-of-N sharding, and complex healing are not implemented.
* **Basic Security:** Authentication uses a single shared secret. No user-specific access control, encryption, or strong protection against sophisticated attacks on the node's code or data files if the hosting account is compromised.
* **Reliability:** Highly dependent on cron job execution and the stability of individual shared hosting providers. Healing and data repair are not implemented.
* **Performance:** PHP is request-driven; background tasks rely on cron, adding latency to network updates. File operations and archiving can be resource-intensive.
* **Scalability:** Gossip and metadata synchronization mechanisms may become bottlenecks in larger networks.
* **No Discovery Service:** Relies on manually configured seed nodes for initial connection.

This codebase is intended as a starting point for understanding the challenges and potential approaches to building decentralized systems on constrained environments like shared hosting.

## Contributing

This project is a proof-of-concept and not actively seeking feature contributions for a production system on shared hosting due to the inherent limitations. However, feedback, suggestions for improvement *within the shared hosting constraints*, or insights into PHP-based distributed patterns are welcome.

## License

This project is licensed under the MIT License. See the LICENSE file for details (Note: The actual LICENSE file content is not generated here, but you should create one with the standard MIT license text).
                             

## Project Structure
```
/ (web root or subdirectory)
├── index.php
├── config.php
├── .htaccess (optional, for hiding data/cron/lib)
├── api/
│   ├── upload.php          (User uploads files here)
│   ├── download.php        (User downloads files from here)
│   ├── peers.php           (Node <-> Node: exchange peer list)
│   ├── upload_chunk.php    (Node <-> Node: send file data/chunk)
│   ├── download_chunk.php  (Node <-> Node: request file data/chunk)
│   ├── metadata.php        (Node <-> Node: exchange metadata)
│   ├── analytics.php       (Gateway <-> Node: retrieve analytics)
│   └── capabilities.php    (Node <-> Node / Gateway: report capabilities)
├── cron/
│   ├── run_cron.php        (Main cron entry point)
│   ├── gossip_peers.php    (Cron Task: discover and manage peers)
│   ├── gossip_metadata.php (Cron Task: synchronize metadata)
│   ├── check_peers.php     (Cron Task: check peer health/capabilities)
│   ├── cleanup_data.php    (Cron Task: delete marked/orphaned files, track usage)
│   ├── archive_old_files.php (Cron Task: archive old files)
│   └── check_environment.php (Cron Task: detect node capabilities)
├── lib/
│   ├── Node.php            (Core Node Class/Functions)
│   ├── Util.php            (Utility functions: HTTP, File Locking, JSON)
│   ├── Storage.php         (Local Storage Ops: read, write, delete, archive)
│   ├── Metadata.php        (Metadata Ops: load, save, update)
│   ├── Peers.php           (Peer List Ops: load, save, manage)
│   └── Analytics.php       (Analytics Ops: load, save, update)
└── data/                   (MUST be writable by PHP, outside web root if possible)
    ├── peers.json
    ├── metadata.json
    ├── analytics.json
    └── archives/           (MUST be writable by PHP)
```
