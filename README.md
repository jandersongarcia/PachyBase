# PachyBase

PachyBase is an open-source, self-hosted backend foundation built with PHP for teams that want predictable JSON APIs, Docker-first local setup, automatic CRUD, and machine-readable contracts for both humans and AI tooling.

Current stage: release candidate `1.0.0-rc.2`

## Quick start (Docker)

```bash
cp .env.example .env
./pachybase install
```

On Windows:

```powershell
Copy-Item .env.example .env
.\pachybase.bat install
```

After installation:

- API base URL: `http://localhost:8080`
- Database port on the host: `3306` for MySQL or `5432` for PostgreSQL
- OpenAPI document: `http://localhost:8080/openapi.json`
- AI schema: `http://localhost:8080/ai/schema`
- MCP adapter: `./pachybase mcp:serve`
- Development admin: `admin@pachybase.local` / `pachybase123`

Before exposing the project to third parties, run:

```bash
./pachybase doctor
./pachybase http:smoke
./pachybase benchmark:local
./pachybase acceptance:check
```

## Installation paths

PachyBase documents two official installation paths:

- Docker-first quick start for the fastest supported setup
- Local installation for teams that want PHP, Composer, and the database directly on the host while keeping the same project CLI

Documentation entry points:

- Install overview: <https://jandersongarcia.github.io/pachybase/install>
- Install with Docker: <https://jandersongarcia.github.io/pachybase/docker-install>
- Local installation: <https://jandersongarcia.github.io/pachybase/local-install>

## What is included today

- Predictable JSON API contract with a fixed success/error envelope
- Docker-first installation and project CLI
- MySQL and PostgreSQL support
- Migrations, seeds, and database bootstrap
- JWT access tokens and API tokens
- Automatic CRUD driven by `config/CrudEntities.php`
- Generated OpenAPI 3.0.3 document
- AI-friendly discovery endpoints
- MCP stdio adapter for agent tooling
- acceptance smoke check for HTTP and MCP release validation
- dedicated HTTP smoke checks and a versioned local benchmark baseline
- PHPUnit regression suite

## Runtime surface

Core routes:

- `GET /`
- `GET /openapi.json`
- `GET /ai/schema`
- `GET /ai/entities`
- `GET /ai/entity/{name}`

Authentication routes:

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/revoke`
- `GET /api/auth/me`
- `POST /api/auth/tokens`
- `DELETE /api/auth/tokens/{id}`

Automatic CRUD routes:

- `GET /api/{entity}`
- `GET /api/{entity}/{id}`
- `POST /api/{entity}`
- `PUT /api/{entity}/{id}`
- `PATCH /api/{entity}/{id}`
- `DELETE /api/{entity}/{id}`

## Configuration

PachyBase reads runtime settings from `.env`.

Required values:

```env
APP_NAME=PachyBase
APP_ENV=development
APP_DEBUG=true
APP_RUNTIME=docker
APP_HOST=127.0.0.1
APP_PORT=8080
APP_URL=http://localhost:8080

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

When `APP_RUNTIME=docker`, PachyBase keeps `DB_HOST=db` for the application container, and also publishes the database port on the host machine. External tools should connect to the server IP or DNS name using `DB_PORT`.

Optional values include:

- `APP_KEY`
- `APP_CORS_ALLOWED_ORIGINS`
- `APP_CORS_ALLOWED_HEADERS`
- `APP_CORS_EXPOSED_HEADERS`
- `APP_CORS_ALLOW_CREDENTIALS`
- `APP_CORS_MAX_AGE`
- `APP_RATE_LIMIT_ENABLED`
- `APP_RATE_LIMIT_MAX_REQUESTS`
- `APP_RATE_LIMIT_WINDOW_SECONDS`
- `APP_RATE_LIMIT_STORAGE_PATH`
- `APP_AUDIT_LOG_ENABLED`
- `APP_AUDIT_LOG_PATH`
- `DB_SCHEMA` for PostgreSQL
- `AUTH_JWT_SECRET`
- `AUTH_JWT_ISSUER`
- `AUTH_ACCESS_TTL_MINUTES`
- `AUTH_REFRESH_TTL_DAYS`
- `AUTH_BOOTSTRAP_ADMIN_EMAIL`
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`
- `AUTH_BOOTSTRAP_ADMIN_NAME`

Declarative CRUD behavior lives in [`config/CrudEntities.php`](config/CrudEntities.php).

## Supported databases

Officially supported drivers:

- `mysql`
- `pgsql`

Docker generation, adapter selection, schema inspection, migrations, seeds, CRUD metadata, OpenAPI generation, and tests are all aligned around these two drivers.

## API contract

Every response follows the same envelope:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "contract_version": "1.0",
    "request_id": "b0bb2f930d4b4f5ab9e2d1f7b74b9df6",
    "timestamp": "2026-03-11T03:00:00+00:00",
    "path": "/",
    "method": "GET"
  },
  "error": null
}
```

Validation, authentication, authorization, conflict, and server failures keep the same outer structure.

## Authentication

PachyBase supports:

- JWT access tokens for web/mobile clients
- refresh tokens through `pb_auth_sessions`
- API tokens for server-to-server access

Protected endpoints expect `Authorization: Bearer <token>`.

## Browser Integration and CORS

PachyBase now supports automatic `OPTIONS` preflight handling and configurable CORS for browser-based apps.

To enable cross-origin access, define the allowed origins in `.env`:

```env
APP_CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:5173
APP_CORS_ALLOWED_HEADERS=Authorization,Content-Type,X-Requested-With,X-Request-Id
APP_CORS_EXPOSED_HEADERS=
APP_CORS_ALLOW_CREDENTIALS=false
APP_CORS_MAX_AGE=600
```

Notes:

- Leave `APP_CORS_ALLOWED_ORIGINS` empty to keep CORS disabled
- The runtime automatically answers browser preflight requests for known routes
- Allowed methods are derived from the registered route surface for each path
- If you use wildcard origins with credentials enabled, PachyBase echoes the request origin instead of returning `*`

## Automatic CRUD, filters, and pagination

CRUD exposure is driven by `config/CrudEntities.php`.

The current runtime supports:

- pagination with `page` and `per_page`
- equality and operator filters with `filter[field]=value` and `filter[field][operator]=value`
- sorting with `sort=field` and `sort=-field`
- search with `search=term`

Example:

```bash
curl "http://localhost:8080/api/system-settings?filter[is_public]=1&sort=setting_key" \
  -H "Authorization: Bearer <access-token>"
```

## Operational hardening

PachyBase can now enforce a simple file-backed rate limit and append an audit trail for sensitive auth and CRUD write operations.

```env
APP_RATE_LIMIT_ENABLED=true
APP_RATE_LIMIT_MAX_REQUESTS=120
APP_RATE_LIMIT_WINDOW_SECONDS=60
APP_AUDIT_LOG_ENABLED=true
APP_AUDIT_LOG_PATH=build/logs/audit.jsonl
```

Responses also expose the current request identifier in the `X-Request-Id` header.

For minimal observability, every HTTP response now also exposes:

- `Server-Timing`
- `X-Response-Time-Ms`
- `X-Query-Time-Ms`
- `X-Introspection-Time-Ms`

Structured logs for auth, CRUD, and error flows are written to `APP_AUDIT_LOG_PATH` when audit logging is enabled.

## OpenAPI and AI endpoints

OpenAPI:

```bash
curl http://localhost:8080/openapi.json
./pachybase openapi:build --output=build/openapi.json
```

AI-friendly endpoints:

```bash
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entities
curl http://localhost:8080/ai/entity/system-settings
```

Integration token for agents:

```bash
./pachybase auth:token:create "Codex Agent" --scope=crud:read --scope=entity:system-settings:read
```

Optional user binding:

```bash
./pachybase auth:token:create "Claude Agent" --scope=crud:read --user-email=admin@pachybase.local
```

## CLI

Lifecycle:

- `install`
- `start`
- `stop`
- `doctor`
- `http:smoke`
- `benchmark:local`
- `status`
- `test`

Environment:

- `env:sync`
- `env:validate`
- `app:key`

Docker:

- `docker:sync`
- `docker:up`
- `docker:down`
- `docker:logs`

Database:

- `db:setup`
- `db:migrate`
- `db:rollback`
- `db:seed`
- `db:fresh`

Scaffolding:

- `make:module`
- `make:entity`
- `make:migration`
- `make:seed`
- `make:controller`
- `make:service`
- `make:middleware`
- `make:test`
- `crud:generate`

Build and inspection:

- `auth:install`
- `auth:token:create`
- `entity:list`
- `crud:sync`
- `openapi:build`
- `ai:build`
- `version`

Legacy aliases such as `env:init`, `docker:install`, `release:check`, and `openapi:generate` still resolve for backward compatibility, but the canonical command names are the ones listed above.

## Documentation

Published docs:

- English: <https://jandersongarcia.github.io/pachybase/>
- Portuguese: <https://jandersongarcia.github.io/pachybase/pt-BR/>

The `docs-site/` workspace remains in the Git repository for documentation authoring, but it is excluded from release archives to keep the distributed package focused on the runtime.

Recommended entry points:

- [Overview](https://jandersongarcia.github.io/pachybase/)
- [Install](https://jandersongarcia.github.io/pachybase/install)
- [Install with Docker](https://jandersongarcia.github.io/pachybase/docker-install)
- [Local Installation](https://jandersongarcia.github.io/pachybase/local-install)
- [Configuration](https://jandersongarcia.github.io/pachybase/configuration)
- [Supported Databases](https://jandersongarcia.github.io/pachybase/supported-databases)
- [API Contract](https://jandersongarcia.github.io/pachybase/api-contract)
- [Authentication and Authorization](https://jandersongarcia.github.io/pachybase/auth-security)
- [Automatic CRUD](https://jandersongarcia.github.io/pachybase/automatic-crud)
- [Filters and Pagination](https://jandersongarcia.github.io/pachybase/filters-pagination)
- [OpenAPI](https://jandersongarcia.github.io/pachybase/openapi)
- [AI Endpoints](https://jandersongarcia.github.io/pachybase/ai-endpoints)
- [CLI](https://jandersongarcia.github.io/pachybase/cli)
- [Contributing](https://jandersongarcia.github.io/pachybase/contributing)
- [Roadmap](https://jandersongarcia.github.io/pachybase/roadmap)
- [Examples](https://jandersongarcia.github.io/pachybase/examples)
- [Release Process](https://jandersongarcia.github.io/pachybase/release-process)

To work on the docs site locally from the Git repository:

```bash
cd docs-site
npm install
npm run start
```

## Testing

Preferred command:

```bash
./pachybase test
```

Or directly:

```bash
docker compose -f docker/docker-compose.yml run --rm php vendor/bin/phpunit --testdox
```

Release readiness check:

```bash
./pachybase doctor
./pachybase http:smoke
./pachybase benchmark:local
```

## Contributing and roadmap

- Contribution guide: [CONTRIBUTING.md](CONTRIBUTING.md)
- Roadmap: [ROADMAP.md](ROADMAP.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Release notes: [RELEASE_NOTES.md](RELEASE_NOTES.md)
- Publishing checklist: [PUBLISHING_CHECKLIST.md](PUBLISHING_CHECKLIST.md)
