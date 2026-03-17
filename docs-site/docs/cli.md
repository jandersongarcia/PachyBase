---
id: cli
title: CLI
sidebar_position: 3
---

# CLI

PachyBase now ships with a project CLI so installation, Docker lifecycle, migrations, metadata inspection, CRUD sync, OpenAPI generation, and tests all follow one predictable command surface.

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

- `install`: prepare Docker, install dependencies, start the stack, and bootstrap the database
- `env:init`: create `.env` from `.env.example`
- `docker:install`: generate `docker/docker-compose.yml`, build the PHP image, and run Composer inside Docker
- `docker:up`: start the local stack
- `docker:down`: stop the local stack
- `migrate`: apply pending migrations
- `migrate:rollback`: roll back migrations
- `seed`: run pending seeders
- `entity:list`: inspect normalized entity metadata
- `crud:sync`: regenerate `config/CrudEntities.php` from the current schema
- `crud:generate`: alias of `crud:sync`
- `openapi:generate`: write a static OpenAPI document, by default to `build/openapi.json`
- `test`: run the PHPUnit suite inside the PHP container

## Typical flow

```bash
./pachybase env:init
./pachybase docker:install
./pachybase docker:up
./pachybase migrate
./pachybase seed
./pachybase entity:list
./pachybase crud:sync
./pachybase openapi:generate
./pachybase test
```

## Useful options

- `env:init --force`: overwrite the current `.env`
- `crud:sync --expose-new`: mark newly introspected entities as exposed
- `crud:sync --output=path/to/CrudEntities.php`: write the CRUD config somewhere else
- `openapi:generate --output=docs-site/static/openapi.json`: publish the generated specification to a custom path
