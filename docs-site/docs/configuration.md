---
id: configuration
title: Configuration
---

# Configuration

PachyBase uses `.env` as the runtime source of truth and a small set of PHP config files for application behavior that should stay versioned with the codebase.

## Configuration layers

- `.env`: environment-specific values such as app mode, database connection, and auth secrets
- `config/AppConfig.php`: application environment and debug helpers
- `config/AuthConfig.php`: JWT, refresh TTL, and bootstrap admin defaults
- `config/CrudEntities.php`: declarative CRUD exposure, filters, writable fields, sorting, and validation rules

## Required `.env` values

```env
APP_NAME=PachyBase
APP_ENV=development
APP_DEBUG=true

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

## Optional database values

- `DB_SCHEMA`: PostgreSQL schema name. Defaults to `public`.

## Optional auth values

- `AUTH_JWT_SECRET`: required in production; in development a local fallback is used
- `AUTH_JWT_ISSUER`: token issuer name
- `AUTH_ACCESS_TTL_MINUTES`: access token TTL, default `15`
- `AUTH_REFRESH_TTL_DAYS`: refresh token TTL, default `30`
- `AUTH_BOOTSTRAP_ADMIN_EMAIL`: development bootstrap admin email
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`: development bootstrap admin password
- `AUTH_BOOTSTRAP_ADMIN_NAME`: development bootstrap admin display name

## Optional CORS values

- `APP_CORS_ALLOWED_ORIGINS`: comma-separated allowed origins. Leave empty to keep CORS disabled.
- `APP_CORS_ALLOWED_HEADERS`: comma-separated allow-list for browser preflight headers
- `APP_CORS_EXPOSED_HEADERS`: comma-separated response headers that browsers may read
- `APP_CORS_ALLOW_CREDENTIALS`: `true` to allow credentialed cross-origin requests
- `APP_CORS_MAX_AGE`: browser preflight cache duration in seconds, default `600`

When CORS is enabled, PachyBase automatically handles `OPTIONS` preflight requests for known routes and derives the allowed methods from the registered route surface.

## Optional rate limit values

- `APP_RATE_LIMIT_ENABLED`: `true` to enable request throttling
- `APP_RATE_LIMIT_MAX_REQUESTS`: maximum requests per window, default `120`
- `APP_RATE_LIMIT_WINDOW_SECONDS`: throttling window size in seconds, default `60`
- `APP_RATE_LIMIT_STORAGE_PATH`: file used to persist counters, default `build/runtime/rate-limit.json`

The current implementation uses a lightweight file-backed fixed window keyed by bearer token when present, or by client IP otherwise.

## Optional audit values

- `APP_AUDIT_LOG_ENABLED`: `true` to append audit entries for sensitive auth and CRUD write operations
- `APP_AUDIT_LOG_PATH`: JSONL file path for audit entries, default `build/logs/audit.jsonl`

Each audit entry includes `timestamp`, `request_id`, `method`, `path`, client IP, principal metadata when available, and a small action-specific context payload.

## CRUD configuration

`config/CrudEntities.php` is where the automatic CRUD surface is curated. Each entity can define:

- `slug` and backing `table`
- whether the entity is publicly exposed by the automatic CRUD module
- allowed, hidden, and readonly fields
- searchable, filterable, and sortable fields
- default sort and `max_per_page`
- validation rules and lightweight lifecycle hooks

## Recommended production checklist

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Define `AUTH_JWT_SECRET`
- Enable `APP_RATE_LIMIT_ENABLED`
- Enable `APP_AUDIT_LOG_ENABLED`
- Review exposed entities in `config/CrudEntities.php`
- Rotate the bootstrap admin credentials before first public use

## Where to change behavior

- App/runtime behavior: `.env` and `config/AppConfig.php`
- Authentication behavior: `.env` and `config/AuthConfig.php`
- CRUD HTTP surface: `config/CrudEntities.php`
- Route composition: `routes/api.php` and `modules/`
