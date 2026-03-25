# Release Notes

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
