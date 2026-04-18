# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with pre-release versions (e.g., `0.1.0-alpha.1`).

## [0.1.0-alpha.1] - 2026-04-17

### Added
- Initial alpha release
- Peer-to-peer file storage system for shared hosting environments
- Docker Compose setup for local multi-node development
- Pre-commit hooks for PHP linting and code style (PHP-CS-Fixer, PHPStan)
- Conventional commits enforcement via husky

### Components
- **peerhost**: Main LibreMesh node software with HTTP API
- **gateway**: Separate download gateway application
- **meshdrop**: Simple upload/download web interface

### Features
- Decentralized peer discovery via HTTP gossip protocol
- Data replication across nodes
- Eventual consistency via background sync
- File archiving via ZipArchive
- PHP capability detection and adaptation
- Basic analytics tracking