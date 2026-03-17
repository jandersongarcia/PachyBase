---
id: docker-install
title: Docker Install
sidebar_position: 3
---

# Docker Install

PachyBase can provision its local Docker stack directly through Docker, without requiring Composer to be installed on the host machine.

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
DB_USERNAME=root
DB_PASSWORD=root
```

Supported database drivers:

- `mysql`
- `pgsql`

## Install flow

### Windows

```powershell
.\install.bat
```

### Linux

```bash
chmod +x install.sh
./install.sh
```

The installer performs these steps:

1. Validates the database configuration in `.env`.
2. Generates `docker/docker-compose.yml`.
3. Builds the PHP image with Composer available inside the container.
4. Runs `composer install` inside the PHP container.
5. Starts the containers with `docker compose up -d`.

## Configuration notes

The installer does not create `.env` automatically. Configure it manually before running the installer.

After installation, you can manage the stack with Docker Compose:

```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml down
```
