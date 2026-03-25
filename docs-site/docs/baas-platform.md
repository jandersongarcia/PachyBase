---
title: BaaS Platform
---

# BaaS Platform

PachyBase now includes a minimal operator plane for onboarding, project operations, backups, secrets, jobs, file storage, and webhooks.

## Operator plane

Use an authenticated bearer token from your operator workspace to manage projects:

- `GET /api/platform/projects`
- `GET /api/platform/projects/{project}`
- `POST /api/platform/projects`
- `GET /api/platform/projects/{project}/backups`
- `POST /api/platform/projects/{project}/backups`
- `POST /api/platform/projects/{project}/restore`
- `GET /api/platform/projects/{project}/secrets`
- `GET /api/platform/projects/{project}/secrets/{key}`
- `PUT /api/platform/projects/{project}/secrets/{key}`
- `DELETE /api/platform/projects/{project}/secrets/{key}`
- `GET /api/platform/operations/overview`

Project configuration continues to use the standard tenant-scoped `system-settings` entity.

## Provision a project

```bash
curl -X POST http://localhost:8080/api/platform/projects \
  -H "Authorization: Bearer <operator-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme",
    "slug": "acme",
    "admin_email": "admin@acme.example",
    "quotas": {
      "max_requests_per_month": 500000,
      "max_storage_bytes": 1073741824
    },
    "settings": {
      "region": "us-east-1",
      "support_email": "ops@acme.example"
    }
  }'
```

The response returns:

- project metadata
- bootstrap admin credentials
- a bootstrap project token
- the tenant header name for multi-project routing

## Backups and restore

Create a backup:

```bash
curl -X POST http://localhost:8080/api/platform/projects/acme/backups \
  -H "Authorization: Bearer <operator-token>" \
  -H "Content-Type: application/json" \
  -d '{"label":"before-import"}'
```

Restore a backup:

```bash
curl -X POST http://localhost:8080/api/platform/projects/acme/restore \
  -H "Authorization: Bearer <operator-token>" \
  -H "Content-Type: application/json" \
  -d '{"backup_id": 12}'
```

Backups persist a JSON snapshot plus a file under `build/backups/<project-slug>/`.

## Secrets

Store a secret:

```bash
curl -X PUT http://localhost:8080/api/platform/projects/acme/secrets/stripe_api_key \
  -H "Authorization: Bearer <operator-token>" \
  -H "Content-Type: application/json" \
  -d '{"value":"sk_live_xxx"}'
```

Secrets are encrypted with `APP_KEY` before being written to the database.

## Tenant features

Tenant-scoped features use the project token or project admin credentials:

- `GET /api/platform/jobs`
- `POST /api/platform/jobs`
- `POST /api/platform/jobs/run`
- `GET /api/platform/webhooks`
- `POST /api/platform/webhooks`
- `POST /api/platform/webhooks/{id}/test`
- `GET /api/platform/webhook-deliveries`
- `GET /api/platform/storage`
- `POST /api/platform/storage`
- `GET /api/platform/storage/{id}/download`

Storage uploads use base64 payloads so the API can remain transport-simple from CLI and agent clients.
