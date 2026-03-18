# Changelog

All notable changes to PachyBase will be documented in this file.

## [1.0.0-rc.1] - 2026-03-17

### Added

- Centralized project versioning through the root `VERSION` file.
- `doctor` plus the backward-compatible `release:check` alias to validate release-critical configuration.
- Release documentation: changelog, release notes, and publishing checklist.
- Docker build hardening with pinned runtime/tooling images and a repository `.dockerignore`.

### Changed

- System status and OpenAPI output now publish the shared release version.
- Docker generation now uses the repository root as build context consistently across setup flows.
- Example Compose files were aligned with the documented default MySQL setup and no longer publish the database port.
- `.env.example` now calls out production-sensitive values more clearly.
- Root project metadata now aligns with the release candidate positioning across public materials.
- Release archives now exclude `docs-site/`, and top-level docs now point to the published documentation site.
- Documentation and operational hints now prefer the canonical CLI command names: `env:sync`, `docker:sync`, and `openapi:build`.
- Shell wrappers now avoid booting the host CLI before `vendor/autoload.php` exists, which keeps the Docker quick start working in fresh clones where PHP is installed but dependencies are not yet present.

### Verified

- Docker environment preparation via `scripts/setup.ps1 -Mode docker-install`.
- Full containerized PHPUnit suite: `93 tests`, `429 assertions`.
- Containerized release doctor run against the active project configuration.
