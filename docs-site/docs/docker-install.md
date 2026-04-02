---
id: docker-install
title: Install with Docker
sidebar_position: 3
---

# Install with Docker

Docker is the primary installation track for PachyBase. It provisions the local stack without requiring Composer to be installed on the host machine.

Before running the Docker installer, get the source code from [GitHub](https://github.com/jandersongarcia/pachybase) or the [project ZIP download](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).

## Required manual step

Create `.env` from `.env.example` and fill in the database settings before running the installer. `DB_DRIVER` defines whether the generated stack will use MySQL or PostgreSQL.

```bash
cp .env.example .env
```

## Required `.env` values

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=pachybase
DB_PASSWORD=change_this_password
```

Supported database drivers:

- `mysql`
- `pgsql`

## Preferred install flow

Use the project CLI first. It is the documented surface that matches the current runtime behavior.

### Windows

```powershell
Copy-Item .env.example .env
.\pachybase.bat install
```

### Linux

```bash
cp .env.example .env
chmod +x pachybase
./pachybase install
```

The CLI install flow performs these steps:

1. Syncs `.env` from `.env.example` when needed and validates the resulting configuration.
2. Configures `APP_KEY` and auth defaults when they are still missing.
3. Generates `docker/docker-compose.yml`.
4. Starts the Docker runtime.
5. Waits for the database, applies migrations, and runs seeds.
6. Generates `build/openapi.json` and `build/ai-schema.json`.

The generated Compose file also publishes the database port on the host (`3306` for MySQL or `5432` for PostgreSQL). The app container still uses `DB_HOST=db`, while external database clients should use the machine IP or DNS name together with `DB_PORT`.

## Legacy setup wrappers

`install.sh` and `scripts/setup.ps1` remain available when you explicitly want the lower-level Docker setup wrappers. They still build the PHP image, install Composer dependencies in the container, generate `docker/docker-compose.yml`, start the stack, and bootstrap the database, but the CLI remains the canonical documented entrypoint.

## Why this is the primary track

- It is the fastest supported setup.
- The project CLI is designed around this path.
- It keeps PHP, Composer, and the database aligned with the documented environment.
- It minimizes host-machine differences across contributors.

## Configuration notes

The installer does not create `.env` automatically. Configure it manually before running the installer.

After installation, you can manage the stack with the project CLI or with Docker Compose directly:

```bash
./pachybase status
./pachybase docker:logs
./pachybase stop
./pachybase start
```

```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml down
```

You can also re-run the database bootstrap manually when needed:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/bootstrap-database.php
```

Before sharing the environment with other developers or publishing a release, run `./pachybase doctor`.
