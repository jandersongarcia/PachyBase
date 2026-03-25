# Release Notes

## PachyBase 1.0.0-rc.3

This release candidate turns PachyBase into a minimal but usable BaaS foundation. The focus is fast project onboarding, operator-grade backup and secret management, tenant-level jobs and storage primitives, and enough documentation for a third party to adopt the platform without manual hand-holding.

### Highlights

- Operator plane endpoints now provision projects, issue bootstrap credentials, manage project secrets, create backups, restore tenant state, and expose a basic operational overview.
- Tenant plane endpoints now cover async jobs, webhook registration and delivery tracking, and local file storage with quota enforcement.
- The CLI now includes project lifecycle commands for provision, backup, restore, and job execution.
- Documentation now covers the BaaS platform surface, production deploy posture, and agent/client integration templates.
- A minimal JavaScript SDK and starter templates were added for Codex, Claude, and MCP-based clients.

### Upgrade notes

- Run `php scripts/migrate.php up` or `./pachybase db:migrate` to create the new platform tables.
- If you use project bootstrap tokens, rotate any pre-release tokens and reissue them so they include the latest tenant scopes.
- Persist `build/backups/` and `build/storage/` in environments where backup recovery and file storage must survive container replacement.
- Keep `APP_KEY` stable across deploys; project secret encryption and restore flows depend on it.

### Validation summary

- The platform migration applied successfully on the active PostgreSQL runtime.
- Focused auth, CRUD, and platform HTTP kernel tests passed after the platform cut.
- The full containerized PHPUnit suite passed without regressions: `161 tests`, `703 assertions`.

## PachyBase 1.0.0-rc.2

This release candidate closes the critical installation and runtime gaps that were still visible in clean environments. The focus is deterministic Docker bootstrap, lower-overhead production serving for generated contracts, and a tighter baseline for PHP runtime behavior.

### Highlights

- Production can now serve `openapi.json` and `ai-schema.json` directly from generated static artifacts, with lightweight runtime fallbacks.
- Health checks are split between a cheap `/health` probe and `/health/deep` for readiness checks that include database access.
- The Docker CLI flow now keeps `install`, `start`, and `compose-sync` consistent, and generated `docker-compose.yml` output is deterministic.
- PHP runtime defaults now enable OPcache, add PHP-FPM tuning, and preserve metadata cache integrity under concurrent access.
- Clean bootstrap was exercised in isolated MySQL and PostgreSQL environments and now completes successfully for both drivers.

### Upgrade notes

- Run `./pachybase doctor` after updating to confirm `.env`, auth, and Docker posture are still valid.
- Regenerate the Compose file after updating Docker assets: `./pachybase compose-sync`.
- If you probe service health from an orchestrator, use `/health` for liveness and `/health/deep` only where database readiness is required.
- Prefer the canonical command names `env:sync`, `docker:sync`, and `openapi:build`; the older aliases still work but are no longer the primary documentation surface.

### Validation summary

- Docker build and dependency installation completed successfully through the official setup flow.
- The full containerized PHPUnit suite passed without regressions.
- The release doctor passed on the active development configuration, with one expected warning about the missing development JWT override.
