---
sidebar_position: 8
---

# OpenAPI automático

PachyBase now exposes a generated OpenAPI 3.0.3 document at `/openapi.json`.

The document is built from the same runtime sources used by the API itself:

- registered HTTP routes
- auth middleware requirements
- CRUD entity exposure from `config/CrudEntities.php`
- database metadata introspected from the real tables
- request validation rules already enforced at runtime

## What is documented

- fixed endpoints such as `/`, `/api/auth/*`, and `/openapi.json`
- automatic CRUD endpoints expanded per exposed entity, for example `/api/system-settings` and `/api/api-tokens`
- request and response schemas
- standard error envelopes
- bearer authentication requirements
- tags for system, documentation, auth, and each exposed CRUD entity

## Why this matters

Because the OpenAPI document is generated from the active route map and entity metadata, it reflects the real API surface instead of a separate hand-written promise.

This improves:

- DX when exploring the API
- automatic client and SDK generation
- AI and tooling integrations
- contract visibility for integrators

## Current scope

- OpenAPI JSON endpoint: available
- Swagger UI / Redoc: not bundled yet

Example:

```bash
curl http://localhost:8080/openapi.json
```
