# Release Notes

## PachyBase 1.0.0-rc.4

This release candidate closes a set of release-preparation gaps around agent onboarding, Docker ergonomics, and public documentation consistency. The focus is making PachyBase easier to hand to third-party agents and contributors without leaking local-only scaffolding or leaving stale setup references behind.

### Highlights

- PachyBase now ships a public `.ai-skills/` directory with starter skills for architecture, CRUD workflows, runtime checks, and MCP-based agent integration.
- The README and agent-template docs now explain how agents should use `.ai-skills/` instead of relying on private local scaffolding.
- Generated Docker Compose services now receive deterministic container names based on `APP_NAME`.
- Public release-facing docs now avoid broken links to the removed root `CONTRIBUTING.md` and the removed `install.bat` wrapper.

### Upgrade notes

- If you maintain agent onboarding material outside the repository, update any `skills/` references to `.ai-skills/`.
- Regenerate `docker/docker-compose.yml` after updating if you want the new container names to appear in local Docker tooling.
- If your Windows setup instructions still mention `install.bat`, switch them to `.\pachybase.bat install` or `scripts/setup.ps1` for lower-level Docker setup.

### Validation summary

- `./pachybase doctor` passed with `19` checks and no warnings.
- `./pachybase acceptance:check` passed for OpenAPI, AI discovery, and MCP smoke flows, with one expected warning because no protected CRUD acceptance token was provided.
- `./pachybase openapi:build` generated `build/openapi.json` with `18` paths and `29` schemas.
- `./pachybase ai:build` generated `build/ai-schema.json` with `2` exposed entities.
- After running `./pachybase db:migrate` and `./pachybase db:seed` on the active PostgreSQL runtime, the full test suite passed: `161 tests`, `703 assertions`.

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
