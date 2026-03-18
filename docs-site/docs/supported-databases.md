---
id: supported-databases
title: Supported Databases
---

# Supported Databases

PachyBase officially supports two database drivers today:

- `mysql`
- `pgsql`

These two drivers are supported across connection handling, schema inspection, migrations, seeds, automatic CRUD metadata, Docker setup, and automated tests.

## MySQL

- Driver value: `DB_DRIVER=mysql`
- Default Docker image: `mysql:8`
- Default container host: `db`
- Default port: `3306`
- Storage path used by Docker: `/var/lib/mysql`

Example:

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

## PostgreSQL

- Driver value: `DB_DRIVER=pgsql`
- Default Docker image: `postgres:15`
- Default container host: `db`
- Default port: `5432`
- Optional schema variable: `DB_SCHEMA`, default `public`
- Storage path used by Docker: `/var/lib/postgresql/data`

Example:

```env
DB_DRIVER=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
DB_SCHEMA=public
```

## Behavior guarantees

- Adapter selection is automatic from `DB_DRIVER`
- Schema metadata is normalized before the CRUD and OpenAPI layers consume it
- Migrations and seeds use the same adapter abstraction as the runtime
- Docker install only generates stacks for the officially supported drivers

## Not supported yet

These engines should be treated as unsupported until explicit project support lands:

- SQLite
- SQL Server
- Oracle
- MariaDB as a separate compatibility target
