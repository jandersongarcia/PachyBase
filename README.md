# PachyBase

PachyBase is an open-source, self-hosted backend foundation built with PHP for teams that want predictable JSON APIs, Docker-first local setup, automatic CRUD, and machine-readable contracts for both humans and AI tooling.

Current release candidate: `1.0.0-rc.1`

## Quick start

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
- OpenAPI document: `http://localhost:8080/openapi.json`
- AI schema: `http://localhost:8080/ai/schema`
- Development admin: `admin@pachybase.local` / `pachybase123`

Before exposing the project to third parties, run:

```bash
./pachybase doctor
```

## What is included today

- Predictable JSON API contract with a fixed success/error envelope
- Docker-first installation and project CLI
- MySQL and PostgreSQL support
- Migrations, seeds, and database bootstrap
- JWT access tokens and API tokens
- Automatic CRUD driven by `config/CrudEntities.php`
- Generated OpenAPI 3.0.3 document
- AI-friendly discovery endpoints
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

DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

Optional values include:

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

## Automatic CRUD, filters, and pagination

CRUD exposure is driven by `config/CrudEntities.php`.

The current runtime supports:

- pagination with `page` and `per_page`
- equality filters with `filter[field]=value`
- sorting with `sort=field` and `sort=-field`
- search with `search=term`

Example:

```bash
curl "http://localhost:8080/api/system-settings?filter[is_public]=1&sort=setting_key" \
  -H "Authorization: Bearer <access-token>"
```

## OpenAPI and AI endpoints

OpenAPI:

```bash
curl http://localhost:8080/openapi.json
./pachybase openapi:generate --output=build/openapi.json
```

AI-friendly endpoints:

```bash
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entities
curl http://localhost:8080/ai/entity/system-settings
```

## CLI

Main commands:

- `version`
- `install`
- `env:init`
- `doctor`
- `docker:install`
- `docker:up`
- `docker:down`
- `migrate`
- `migrate:rollback`
- `seed`
- `entity:list`
- `crud:sync`
- `crud:generate`
- `openapi:generate`
- `test`

## Documentation

Official docs source:

- English: [`docs-site/docs/`](docs-site/docs/)
- Portuguese: [`docs-site/i18n/pt-BR/docusaurus-plugin-content-docs/current/`](docs-site/i18n/pt-BR/docusaurus-plugin-content-docs/current/)

Recommended entry points:

- [Overview](docs-site/docs/intro.md)
- [Install](docs-site/docs/install.md)
- [Configuration](docs-site/docs/configuration.md)
- [Supported Databases](docs-site/docs/supported-databases.md)
- [API Contract](docs-site/docs/api-contract.md)
- [Authentication and Authorization](docs-site/docs/auth-security.md)
- [Automatic CRUD](docs-site/docs/automatic-crud.md)
- [Filters and Pagination](docs-site/docs/filters-pagination.md)
- [OpenAPI](docs-site/docs/openapi.md)
- [AI Endpoints](docs-site/docs/ai-endpoints.md)
- [CLI](docs-site/docs/cli.md)
- [Contributing](docs-site/docs/contributing.md)
- [Roadmap](docs-site/docs/roadmap.md)
- [Examples](docs-site/docs/examples.md)
- [Release Process](docs-site/docs/release-process.md)

Run the docs site locally:

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
```

## Contributing and roadmap

- Contribution guide: [CONTRIBUTING.md](CONTRIBUTING.md)
- Roadmap: [ROADMAP.md](ROADMAP.md)
- Changelog: [CHANGELOG.md](CHANGELOG.md)
- Release notes: [RELEASE_NOTES.md](RELEASE_NOTES.md)
- Publishing checklist: [PUBLISHING_CHECKLIST.md](PUBLISHING_CHECKLIST.md)
