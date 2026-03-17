---
id: openapi
title: OpenAPI
sidebar_position: 8
---

# OpenAPI

PachyBase exposes a generated OpenAPI 3.0.3 document at `/openapi.json`.

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

## Static generation

You can export the current runtime contract to a file:

```bash
./pachybase openapi:generate
./pachybase openapi:generate --output=docs-site/static/openapi.json
```

On Windows, use `.\pachybase.bat`.

This is useful when publishing docs, generating SDKs, or reviewing contract drift in CI.

Example:

```bash
curl http://localhost:8080/openapi.json
```
