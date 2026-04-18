# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LibreMesh is a decentralized, peer-to-peer file storage system for shared hosting environments. It uses PHP, HTTP APIs, and cron jobs to create a distributed storage network with replication and eventual consistency.

**Version: Alpha 0.1 (Proof of Concept)**

**IMPORTANT**: This is a proof-of-concept. It lacks production hardening, robust consistency guarantees, and automated healing.

---

## Architecture

### Core Components

```
peerhost/           # LibreMesh node software (main codebase)
  api/               # HTTP API endpoints
    upload.php       # User file uploads
    download.php     # User file downloads
    peers.php        # Peer list exchange (gossip)
    upload_chunk.php # Node-to-node chunk transfer
    download_chunk.php # Node-to-node chunk retrieval
    metadata.php     # Metadata exchange
    analytics.php    # Gateway analytics queries
    capabilities.php # Node capability reporting

  cron/              # Background tasks (triggered via cron or HTTP)
    run_cron.php     # Entry point (task=? parameter)
    gossip_peers.php # Peer discovery
    gossip_metadata.php # Metadata sync
    check_peers.php  # Health + capability checks
    cleanup_data.php # Deletion + orphan cleanup
    archive_old_files.php # ZipArchive compression
    check_environment.php # PHP capability detection

  lib/               # Core library classes
    Node.php         # Main orchestrator - coordinates all operations
    Util.php         # HTTP client, JSON file I/O with flock()
    Storage.php      # Local file operations, archiving
    Metadata.php     # metadata.json CRUD
    Peers.php        # peers.json CRUD
    Analytics.php    # analytics.json CRUD (download counts, peer status, storage)

  config.php         # Network configuration (NETWORK_SECRET, NODE_ID, NODE_URL, SEED_NODES)

gateway/            # Separate download gateway application
  index.php          # Web entry point
  download.php       # File download handler
  lib/GatewayUtil.php
  lib/PeerStatus.php
  cron/update_peer_status.php

meshdrop/           # Simple upload/download web interface
vendor/manager.php  # TinyFileManager (unrelated file manager utility)
```

### Data Flow

1. **User Upload**: `upload.php` -> `Node::handleClientUpload()` -> store locally -> replicate to `REPLICATION_FACTOR - 1` peers via `upload_chunk.php`
2. **User Download**: `download.php` -> `Node::handleClientDownload()` -> serve local or fetch from peer via `download_chunk.php`
3. **Peer Discovery**: `gossip_peers` cron selects random peers, calls `peers.php`, merges results into `peers.json`
4. **Metadata Sync**: `gossip_metadata` cron exchanges file metadata with healthy peers
5. **Health Checks**: `check_peers` cron calls `capabilities.php` on all peers, updates `analytics.json` peer status

### State Files (in `data/`)

- `peers.json` - Array of known peer URLs
- `metadata.json` - File/chunk metadata with structure:
  ```json
  {
    "file_id_ABC": {
      "chunks": {
        "chunk_id_0": {
          "local_path": "/path/to/data/file_ABC_0.dat",
          "archive_path": "/path/to/data/archives/archive_ABC_0.zip",
          "archive_entry_name": "file_ABC_0.dat",
          "checksum": "sha256:...",
          "size": 12345,
          "state": "active|archived|deleted",
          "last_accessed": 1678886400,
          "stored_at": 1678800000,
          "deleted_at": null,
          "source_node": "node_XYZ"
        }
      },
      "overall_file_status": "active"
    }
  }
  ```
- `analytics.json` - Download counts, peer health status, disk usage

### Authentication

All node-to-node and gateway-to-node requests use the `X-Network-Secret` header with value `NETWORK_SECRET` (defined in `config.php`).

### Key Design Decisions

- **Chunk 0 replication**: Simple full-file replication as single chunk (chunk_id = "0"), no sharding
- **Eventually consistent**: No strong consistency - gossip propagates updates over time
- **Deletion workflow**: Mark `state: deleted` + `deleted_at` timestamp -> wait `DELETE_PROPAGATION_DELAY_HOURS` -> physically delete on `cleanup_data` cron
- **Archiving**: Files inactive > `ARCHIVE_THRESHOLD_DAYS` get ZipArchive compressed to `data/archives/`, extracted on demand
- **Peer selection**: For replication, select healthy peers based on `check_peers` status and capabilities

---

## Configuration (peerhost/config.php)

```php
NETWORK_SECRET      # Shared secret for all node-to-node auth
NODE_ID             # Unique identifier for this node
NODE_URL            # Public URL of this node (how peers reach it)
SEED_NODES          # Array of bootstrap peer URLs
REPLICATION_FACTOR  # Number of copies per file (default: 3)
ARCHIVE_THRESHOLD_DAYS  # Inactivity before archiving (default: 180)
DELETE_PROPAGATION_DELAY_HOURS # Wait before physical deletion (default: 48)
```

---

## Common Tasks

### Trigger a cron task manually
```bash
curl "https://your-node.com/cron/run_cron.php?task=gossip_peers"
curl "https://your-node.com/cron/run_cron.php?task=check_peers"
curl "https://your-node.com/cron/run_cron.php?task=gossip_metadata"
curl "https://your-node.com/cron/run_cron.php?task=cleanup_data"
curl "https://your-node.com/cron/run_cron.php?task=archive_old_files"
```

### Upload a file (via curl)
```bash
curl -X POST -F "file_upload=@/path/to/file.txt" https://your-node.com/api/upload.php
```

### Download a file
```bash
curl -O https://your-node.com/api/download.php?file_id=YOUR_FILE_ID
```

### Inter-node chunk transfer (upload chunk to peer)
```bash
curl -X POST -F "file_id=FILE_ID" -F "chunk_id=0" -F "checksum=sha256:..." \
     -F "source_node_id=NODE_ID" -F "chunk_data=@/path/to/file.dat" \
     -H "X-Network-Secret: YOUR_SECRET" \
     https://peer-node.com/api/upload_chunk.php
```

### Inter-node chunk retrieval (download from peer)
```bash
curl -O -G -d "file_id=FILE_ID" -d "chunk_id=0" \
     -H "X-Network-Secret: YOUR_SECRET" \
     https://peer-node.com/api/download_chunk.php
```

### Test node capabilities
```bash
curl -H "X-Network-Secret: YOUR_SECRET" https://your-node.com/api/capabilities.php
```

### Get peer list
```bash
curl -H "X-Network-Secret: YOUR_SECRET" https://your-node.com/api/peers.php
```

### Get metadata from peer
```bash
curl -H "X-Network-Secret: YOUR_SECRET" "https://peer-node.com/api/metadata.php"
```

### Get analytics (from gateway)
```bash
curl -H "X-Network-Secret: YOUR_SECRET" "https://your-node.com/api/analytics.php?type=status"
```

---

## Gateway Application

The `gateway/` directory is a separate PHP application that:
- Discovers nodes via seed nodes
- Polls `analytics.php` on each node to aggregate network status
- Provides a user-facing download interface

It maintains `data/gateway_peers_status.json` and uses its own `GATEWAY_NETWORK_SECRET` (must match node's `NETWORK_SECRET`).

---

## Protocols

### Peer Discovery (Gossip)
- **Endpoint**: `GET /api/peers.php`
- Pull-based, eventually consistent
- `gossip_peers` cron selects random subset of peers, fetches their peer lists, merges into local `peers.json`
- Bootstrapped from `SEED_NODES` in config.php

### Metadata Synchronization (Gossip)
- **Endpoint**: `GET /api/metadata.php`
- Pull-based exchange of file/chunk metadata with healthy peers
- `gossip_metadata` cron runs periodically
- Conflict resolution is simplistic (last-write-wins via timestamps)

### Data Transfer
- **Upload**: `POST /api/upload_chunk.php` - node-to-node chunk replication
- **Download**: `GET /api/download_chunk.php` - node-to-node chunk retrieval
- Both authenticated with `X-Network-Secret`

### Control & Status
- **Capabilities**: `GET /api/capabilities.php` - reports PHP version, extensions, features
- **Analytics**: `GET /api/analytics.php?type=status|peer_health|downloads` - local stats and peer status

---

## File Lifecycle

1. **Upload**: Entry node stores locally, replicates to `R-1` peers via `upload_chunk.php`
2. **Replicate**: Receiving peers validate checksum, store locally, update `metadata.json`
3. **Active**: File accessible on R nodes, `last_accessed` updated on download
4. **Inactivity**: If `last_accessed` > `ARCHIVE_THRESHOLD_DAYS`, `archive_old_files` cron may compress
5. **Archive**: ZipArchive compression if node has `zip` extension; original deleted, `archive_path` stored
6. **Access Archived**: On download request, file extracted from archive to temp, served, temp cleaned up
7. **Delete Request**: Mark `state: deleted` with `deleted_at` timestamp via future `/api/delete.php`
8. **Cleanup**: `cleanup_data` cron physically deletes files where `deleted_at` > `DELETE_PROPAGATION_DELAY_HOURS`

---

## Fault Tolerance & Limitations

- **Node Failure**: If node goes offline, `check_peers` detects it. Data still accessible from other R-1 replicas.
- **Network Partitioning**: Groups of nodes may diverge temporarily; gossip restores consistency on reconnection.
- **Data Loss**: If node lost and replicas fall below R, file becomes inaccessible (no automated healing).
- **Eventually Consistent**: No guarantee all nodes have same view at any moment.
- **Shared Hosting Limits**: Cron-based updates are not real-time; CPU/memory/disk I/O limits apply.
- **No Strong Integrity**: No Merkle tree or similar for distributed integrity verification.

---

## Security Considerations

- **Authentication**: `NETWORK_SECRET` shared among all nodes prevents arbitrary API access
- **Data Access**: Download APIs are unauthenticated in basic model - files are public to anyone with File ID
- **Data Confidentiality**: No encryption at rest or in transit by default. **Client-side encryption recommended.**
- **Hosting Dependency**: Security depends on hosting provider; `DATA_PATH` should be outside web root

---

## Known Limitations

- No automated healing when nodes go offline
- Metadata conflict resolution is simplistic (last-write-wins)
- Gossip can become a bottleneck at scale
- No user authentication on upload/download endpoints
- No end-to-end encryption - client-side encryption recommended
- No M-of-N sharding implementation
- No Vector Clocks or CRDT-based conflict resolution
- No Merkle tree integrity verification

---

## Future Work

- M-of-N Sharding implementation
- Sophisticated Metadata Conflict Resolution (Vector Clocks or CRDTs)
- Automated Data Healing and Repair Protocol
- Advanced Peer Selection (considering load, latency, geography)
- Secure Deletion Protocol (ensuring R copies marked deleted before physical removal)
- User Management and Access Control (AuthN/AuthZ for uploads/downloads)
- End-to-end Data Integrity Verification (Merkle Trees)
- Incentive Layer (for contributing storage/bandwidth)

---

## Commit Hygiene

When a commit resolves or fixes a GitHub issue, the commit message MUST include the issue reference (e.g., `fix: resolve memory leak in peer discovery (#1)` or `closes #1`). This allows GitHub to auto-close the issue when the commit is merged.

---

## Core Principles

- **Simplicity first**: Every change as simple as possible. Minimal code impact.
- **No laziness**: Find root causes. No temporary fixes.
- **Minimal impact**: Only touch what's necessary. Avoid introducing bugs.
- **No emojis**: In code, commits, or documentation. Remove any found while working.
- **No attribution**: Never add "Generated with Claude Code" or Co-Authored-By anywhere.
- **DRY is critical**: Flag repetition aggressively.
- **Explicit over clever**: Readable code over clever shortcuts.

---

## Communication Style

- Be direct and concise. Skip fluff and preamble.
- Give opinionated recommendations with reasoning.
- After major sections of work, pause and ask for feedback before moving on.
- Verify before marking done — ask "would a senior engineer approve this?"

---

## Autonomous Releases

When user says "/ship" or "ship it":
1. Run `git log --oneline <last-tag>..HEAD` to get commits since last release
2. Determine bump type: `fix:` → patch, `feat:` → minor, `BREAKING CHANGE:` → major
3. Calculate next version (e.g., `0.1.0-alpha.1` → `0.1.0-alpha.2` for patch)
4. Update `CHANGELOG.md` with new version section at top, categorize commits
5. Update `VERSION` file with new version
6. Commit: `git add CHANGELOG.md VERSION && git commit -m "release: v{version}"`
7. Create and push tag: `git tag v{version} && git push && git push --tags`
8. GitHub Actions release workflow triggers automatically → builds Docker images + creates release

Version bumping rules:
- `fix:` commits since last tag → patch bump
- `feat:` commits since last tag → minor bump
- `BREAKING CHANGE:` in any commit → major bump
- Alpha versions keep `-alpha` suffix (e.g., `0.1.0-alpha.1` → `0.1.0-alpha.2`)

---

## Docker Cleanup

After successful testing or commits involving Docker:
1. Stop running containers: `docker compose down`
2. Remove stopped containers, networks, and dangling images: `docker system prune -f`
3. Remove build cache: `docker builder prune -f`
4. Remove any dangling volumes: `docker volume prune -f`

Do NOT leave Docker containers or images hanging after tests or builds complete.

---

## Engineering Preferences

- Well-tested code is non-negotiable; better too many tests than too few.
- No `any` types in TypeScript/PHP without explicit justification.
- PHP: strict types where possible, typed return signatures.

---

## File Naming Conventions

- PHP classes: `ClassName.php` with class `ClassName`
- Data files stored as JSON with pretty print
- Chunk files on disk: `{file_id}_{chunk_id}.dat`
- Archive files: `{file_id}_{chunk_id}_{timestamp}.zip`