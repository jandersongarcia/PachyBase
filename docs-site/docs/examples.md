---
id: examples
title: Examples
---

# Examples

These examples are meant to get a new developer productive fast without needing private onboarding.

## 1. Install and boot the project

```bash
cp .env.example .env
./pachybase install
```

## 2. Authenticate and inspect the current user

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"admin@pachybase.local\",\"password\":\"pachybase123\"}"
```

Take the returned `access_token` and call:

```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <access-token>"
```

## 3. Read a paginated CRUD collection

```bash
curl "http://localhost:8080/api/system-settings?page=1&per_page=10&sort=setting_key" \
  -H "Authorization: Bearer <access-token>"
```

## 4. Filter a CRUD collection

```bash
curl "http://localhost:8080/api/system-settings?filter[is_public]=1" \
  -H "Authorization: Bearer <access-token>"
```

## 5. Create a CRUD record

```bash
curl -X POST http://localhost:8080/api/system-settings \
  -H "Authorization: Bearer <access-token>" \
  -H "Content-Type: application/json" \
  -d "{\"setting_key\":\"homepage.title\",\"setting_value\":\"PachyBase\",\"value_type\":\"string\",\"is_public\":true}"
```

## 6. Export OpenAPI and inspect AI metadata

```bash
./pachybase openapi:generate --output=build/openapi.json
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entity/system-settings
```
