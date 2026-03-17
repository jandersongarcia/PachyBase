# Release Notes

## PachyBase 1.0.0-rc.1

This release candidate focuses on hardening rather than feature expansion. The goal is to make a fresh clone feel predictable for third parties, with fewer hidden assumptions around Docker, environment variables, release metadata, and operational checks.

### Highlights

- Release metadata is now centralized and exposed consistently through the runtime and OpenAPI document.
- The project CLI now includes `version`, `doctor`, and `release:check` to improve day-zero developer experience.
- Docker assets were reviewed to remove surprise-prone defaults such as unpinned tags and published database ports.
- The repository now includes first-class release documentation for publishing and verification.

### Upgrade notes

- Run `./pachybase doctor` after updating to confirm `.env`, auth, and Docker posture are still valid.
- Rebuild the PHP image after updating Docker assets: `./pachybase docker:install`.
- If you rely on the generated Compose file, regenerate it from your local `.env` so the selected database driver stays aligned.

### Validation summary

- Docker build and dependency installation completed successfully through the official setup flow.
- The full containerized PHPUnit suite passed without regressions.
- The release doctor passed on the active development configuration, with one expected warning about the missing development JWT override.
