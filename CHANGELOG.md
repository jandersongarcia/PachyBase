# Changelog

All notable changes to PachyBase will be documented in this file.

## [1.0.0-rc.3] - 2026-03-25

### Added

- Minimal BaaS operator plane for project provisioning, project backups and restore, per-project secrets, and an operations overview surface.
- Tenant-scoped async jobs, webhook registration and delivery tracking, and local file storage primitives.
- Initial JavaScript SDK plus templates for Codex, Claude, and MCP client integrations.
- Production, platform, and agent-template documentation for third-party onboarding without manual support.

### Changed

- CLI now exposes `project:provision`, `project:backup`, `project:restore`, and `jobs:work` as first-class platform commands.
- Runtime route registration now includes the platform module alongside the existing auth, CRUD, OpenAPI, and AI surfaces.
- Tenant bootstrap is now self-healing for the `default` workspace, including baseline system settings required by auth and CRUD tests.

### Fixed

- Project bootstrap tokens now include the scopes required to manage project secrets from the tenant plane.
- Backup restore now clears residual async jobs and webhook delivery state before replaying tenant data.

### Verified

- Containerized migration run for the new platform primitives.
- Full containerized PHPUnit suite: `161 tests`, `703 assertions`.

## [1.0.0-rc.2] - 2026-03-25

### Added

- Lightweight `/health` and database-backed `/health/deep` endpoints for clearer liveness versus readiness checks.
- Static production serving for `openapi.json` and `ai-schema.json`, backed by the generated build artifacts.

### Changed

- Docker lifecycle commands now keep `install`, `start`, and `compose-sync` aligned around deterministic `docker-compose.yml` generation.
- PHP runtime defaults now enable OPcache, tune PHP-FPM, and harden the persistent file metadata cache for production workloads.

### Fixed

- Fresh bootstrap is now validated end to end for both MySQL and PostgreSQL.
- MySQL bootstrap no longer breaks on grouped `ALTER TABLE` clauses or implicit transaction commits around DDL.

## [1.0.0-rc.1] - 2026-03-17

### Added

- Centralized project versioning through the root `VERSION` file.
- `doctor` plus the backward-compatible `release:check` alias to validate release-critical configuration.
- Release documentation: changelog, release notes, and publishing checklist.
- Docker build hardening with pinned runtime/tooling images and a repository `.dockerignore`.

### Changed

- System status and OpenAPI output now publish the shared release version.
- Docker generation now uses the repository root as build context consistently across setup flows.
- Example Compose files were aligned with the documented default MySQL setup and now publish the database port for host/external access.
- `.env.example` now calls out production-sensitive values more clearly.
- Root project metadata now aligns with the release candidate positioning across public materials.
- Release archives now exclude `docs-site/`, and top-level docs now point to the published documentation site.
- Documentation and operational hints now prefer the canonical CLI command names: `env:sync`, `docker:sync`, and `openapi:build`.
- Shell wrappers now avoid booting the host CLI before `vendor/autoload.php` exists, which keeps the Docker quick start working in fresh clones where PHP is installed but dependencies are not yet present.

### Verified

- Docker environment preparation via `scripts/setup.ps1 -Mode docker-install`.
- Full containerized PHPUnit suite: `93 tests`, `429 assertions`.
- Containerized release doctor run against the active project configuration.
