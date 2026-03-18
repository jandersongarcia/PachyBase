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
- Review exposed entities in `config/CrudEntities.php`
- Rotate the bootstrap admin credentials before first public use

## Where to change behavior

- App/runtime behavior: `.env` and `config/AppConfig.php`
- Authentication behavior: `.env` and `config/AuthConfig.php`
- CRUD HTTP surface: `config/CrudEntities.php`
- Route composition: `routes/api.php` and `modules/`
