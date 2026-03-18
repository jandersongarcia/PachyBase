---
id: local-install
title: Local Installation
sidebar_position: 4
---

# Local Installation

Local installation is the official alternative to the Docker-first setup. Choose this track when you want to run PHP, Composer, and the database directly on the host machine.

## What this track assumes

You provide and manage these dependencies yourself:

- PHP 8.2 or newer
- Composer 2
- MySQL 8 or PostgreSQL 15
- The PHP extensions used by the project runtime: `pdo_mysql`, `pdo_pgsql`, `mbstring`, `exif`, `pcntl`, `bcmath`, and `gd`

## Repository

- GitHub: [jandersongarcia/pachybase](https://github.com/jandersongarcia/pachybase)
- Download ZIP: [main.zip](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip)

## Clone option

```bash
git clone https://github.com/jandersongarcia/pachybase.git
cd pachybase
```

## ZIP option

1. Download [main.zip](https://github.com/jandersongarcia/pachybase/archive/refs/heads/main.zip).
2. Extract the project files.
3. Open the extracted folder.

## 1. Create `.env`

Create `.env` from `.env.example`, set `APP_RUNTIME=local`, and point it to your host-managed database.

```bash
cp .env.example .env
```

Example for local MySQL:

```env
APP_RUNTIME=local
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=root
DB_PASSWORD=change_this_password
```

Example for local PostgreSQL:

```env
APP_RUNTIME=local
DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pachybase
DB_USERNAME=postgres
DB_PASSWORD=change_this_password
```

## 2. Install PHP dependencies

```bash
composer install
```

## 3. Install and start the local runtime

Use the project CLI after Composer dependencies are present:

```bash
./pachybase install
```

This local CLI flow:

- validates `.env`
- configures `APP_KEY` and `AUTH_JWT_SECRET` when they are missing
- applies the default migrations and seeds
- generates `build/openapi.json` and `build/ai-schema.json`
- starts the built-in PHP server through `public/router.php`

## 4. Verify the runtime

```bash
./pachybase status
```

The local runtime stores its PID and log under `.pachybase/runtime/`.

After the server starts, the default URLs are:

- API base URL: `http://127.0.0.1:8080`
- OpenAPI document: `http://127.0.0.1:8080/openapi.json`
- AI schema: `http://127.0.0.1:8080/ai/schema`
- Development admin login: `admin@pachybase.local` / `pachybase123`

## Windows

In PowerShell:

```powershell
Copy-Item .env.example .env
composer install
# update APP_RUNTIME=local and your DB_* values
.\pachybase.bat install
.\pachybase.bat status
```

Direct host commands also remain available:

```powershell
Copy-Item .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

## Linux

In a shell:

```bash
cp .env.example .env
composer install
# update APP_RUNTIME=local and your DB_* values
./pachybase install
./pachybase status
```

Direct host commands also remain available:

```bash
cp .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

## Operational notes

- `./pachybase start`, `stop`, `status`, `doctor`, and `test` work in local mode when `APP_RUNTIME=local`.
- Direct PHP commands such as `php scripts/migrate.php up` and `php scripts/seed.php run` remain available for step-by-step maintenance.
- Run `./pachybase doctor` before sharing the environment or publishing a release candidate.
- Run tests locally with `./pachybase test` or `vendor/bin/phpunit --testdox`.
