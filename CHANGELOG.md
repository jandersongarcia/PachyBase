# Changelog

All notable changes to PachyBase will be documented in this file.

## [1.0.0-rc.1] - 2026-03-17

### Added

- Centralized project versioning through the root `VERSION` file.
- `doctor` and `release:check` commands to validate release-critical configuration.
- Release documentation: changelog, release notes, and publishing checklist.
- Docker build hardening with pinned runtime/tooling images and a repository `.dockerignore`.

### Changed

- System status and OpenAPI output now publish the shared release version.
- Docker generation now uses the repository root as build context consistently across setup flows.
- Example Compose files were aligned with the documented default MySQL setup and no longer publish the database port.
- `.env.example` now calls out production-sensitive values more clearly.

### Verified

- Docker environment preparation via `scripts/setup.ps1 -Mode docker-install`.
- Full containerized PHPUnit suite: `93 tests`, `429 assertions`.
- Containerized release doctor run against the active project configuration.
