---
id: cli
title: CLI
sidebar_position: 3
---

# CLI

PachyBase ships with a project CLI for both official tracks. Docker remains the primary path, and the same command surface also supports the host-managed alternative when `APP_RUNTIME=local`.

## Launchers

### Windows

```powershell
.\pachybase.bat help
```

### Linux

```bash
chmod +x pachybase
./pachybase help
```

## Commands

Lifecycle:

- `install`: sync `.env`, detect the active runtime, configure auth defaults, prepare the database, and build machine-readable artifacts
- `start`: start Docker services or the local PHP runtime based on `APP_RUNTIME`
- `stop`: stop the active runtime
- `doctor`: validate runtime posture, env consistency, Docker alignment, and release-sensitive settings
- `status`: return a quick health view for runtime, database, auth, and generated docs
- `test`: run the PHPUnit suite in Docker or locally, depending on the configured runtime

Environment:

- `env:sync`: create or extend `.env` from `.env.example` without discarding valid custom values
- `env:validate`: validate required variables and configuration consistency
- `app:key`: generate or regenerate the main application key

Docker:

- `docker:sync`: generate and synchronize `docker/docker-compose.yml`
- `docker:up`: start the project containers
- `docker:down`: stop and remove the project containers
- `docker:logs`: stream Docker logs for operational inspection

Database:

- `db:setup`: wait for the database and prepare the migration baseline
- `db:migrate`: apply pending migrations
- `db:rollback`: roll back the last migration batch
- `db:seed`: run configured seeders
- `db:fresh`: rebuild the development database from scratch

Scaffolding:

- `make:module`: create a base module class under `modules/`
- `make:entity`: register a new CRUD entity entry in `config/CrudEntities.php`
- `make:migration`: create a timestamped migration file
- `make:seed`: create a timestamped seed file
- `make:controller`: create an API-first JSON controller stub
- `make:service`: create a service stub in `services/`
- `make:middleware`: create middleware compatible with the HTTP pipeline
- `make:test`: create a unit or functional PHPUnit test stub
- `crud:generate`: expose new schema-driven CRUD entries or register one new entity quickly

Build and inspection:

- `auth:install`: configure the default auth secrets and optionally prepare auth persistence
- `entity:list`: inspect normalized entity metadata
- `crud:sync`: regenerate `config/CrudEntities.php` from the active schema
- `openapi:build`: write a static OpenAPI document, by default to `build/openapi.json`
- `ai:build`: write the AI-oriented schema document, by default to `build/ai-schema.json`
- `version`: print the current release version

## Typical flow

```bash
./pachybase install
./pachybase status
./pachybase entity:list
./pachybase crud:generate --expose-new
./pachybase openapi:build
./pachybase ai:build
./pachybase test
```

## Local installation equivalents

If you are not using Docker, set `APP_RUNTIME=local` and use the same CLI first. The direct host equivalents remain available when needed:

```bash
composer install
php scripts/env-validate.php
php scripts/bootstrap-database.php
php scripts/migrate.php up
php scripts/seed.php run
php scripts/status.php
php scripts/openapi-generate.php
php scripts/ai-build.php
vendor/bin/phpunit --testdox
```

## Useful options

- `env:sync --force`: overwrite the current `.env`
- `env:validate --json`: print the env validation report as JSON
- `app:key --force`: rotate the application key intentionally
- `crud:sync --expose-new`: mark newly introspected entities as exposed
- `crud:sync --output=path/to/CrudEntities.php`: write the CRUD config somewhere else
- `make:entity name --table=pb_name`: register a specific CRUD entity/table mapping
- `make:test Example --type=functional`: create a functional test skeleton
- `openapi:build --output=docs-site/static/openapi.json`: publish the generated specification to a custom path
- `ai:build --output=build/ai-schema.json`: publish the AI schema to a custom path
