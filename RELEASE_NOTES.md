# Release Notes

## PachyBase 1.0.0-rc.1

This release candidate focuses on hardening rather than feature expansion. The goal is to make a fresh clone feel predictable for third parties, with fewer hidden assumptions around Docker, environment variables, release metadata, and operational checks.

### Highlights

- Release metadata is now centralized and exposed consistently through the runtime and OpenAPI document.
- The project CLI now includes `version` and `doctor`, while preserving legacy aliases such as `release:check`.
- Docker assets were reviewed to remove surprise-prone defaults such as unpinned tags and published database ports.
- The repository now includes first-class release documentation for publishing and verification.
- Source archives now exclude `docs-site/`, and the root project docs now point to the published documentation site.
- The documented install flows now match the actual CLI behavior for both `APP_RUNTIME=docker` and `APP_RUNTIME=local`.

### Upgrade notes

- Run `./pachybase doctor` after updating to confirm `.env`, auth, and Docker posture are still valid.
- Regenerate the Compose file after updating Docker assets: `./pachybase docker:sync`.
- If you rely on the generated Compose file, regenerate it from your local `.env` so the selected database driver stays aligned.
- Prefer the canonical command names `env:sync`, `docker:sync`, and `openapi:build`; the older aliases still work but are no longer the primary documentation surface.

### Validation summary

- Docker build and dependency installation completed successfully through the official setup flow.
- The full containerized PHPUnit suite passed without regressions.
- The release doctor passed on the active development configuration, with one expected warning about the missing development JWT override.
