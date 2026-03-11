---
id: docker-install
title: Docker Install
sidebar_position: 3
---

# Docker Install

PachyBase can provision its local Docker stack directly from Composer.

Before running the Docker installer, get the source code from [GitHub](https://github.com/jandersongarcia/pachybase) or the [project ZIP download](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).

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

```bash
composer install
composer docker-install
```

The installer performs these steps:

1. Validates the database configuration in `.env`.
2. Generates `docker/docker-compose.yml`.
3. Configures the database container for the selected driver.
4. Starts the containers with `docker compose up -d`.

## Dry run

Use the dry-run mode to validate the configuration without starting containers:

```bash
composer docker-install -- --dry-run
```
