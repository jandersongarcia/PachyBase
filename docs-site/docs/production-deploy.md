---
title: Production Deploy
---

# Production Deploy

## Baseline

Before exposing PachyBase to third parties:

1. Set `APP_ENV=production`.
2. Generate and persist a strong `APP_KEY`.
3. Set a unique `AUTH_JWT_SECRET`.
4. Turn on database-backed audit logging.
5. Configure quotas for every provisioned project.
6. Run `doctor`, `http:smoke`, and `benchmark:local`.

## Recommended environment

```env
APP_ENV=production
APP_DEBUG=false
APP_AUDIT_LOG_ENABLED=true
APP_AUDIT_LOG_BACKEND=database
APP_RATE_LIMIT_ENABLED=true
APP_RATE_LIMIT_STORAGE=database
```

## Backups

- Use `project:backup` or `POST /api/platform/projects/{project}/backups` before destructive changes.
- Persist the `build/backups/` directory to durable storage.
- Periodically test `project:restore` in a non-production environment.

## Secrets

- `APP_KEY` is required for secret encryption and restore flows.
- Rotate project secrets through the operator plane rather than storing plaintext in `.env` files for each tenant.
- Restrict operator tokens to trusted automation and platform operators.

## Jobs and webhooks

- Run `jobs:work` on a schedule or as a lightweight worker loop in production.
- Monitor failed jobs through `GET /api/platform/jobs` and `GET /api/platform/operations/overview`.
- Use webhook signing secrets and verify `X-PachyBase-Signature` on receivers.
