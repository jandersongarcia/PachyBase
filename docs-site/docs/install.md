---
id: install
title: Install
sidebar_position: 2
---

# Install

PachyBase ships with two official installation tracks:

- Docker installation: the primary and fastest path for most teams
- Local installation: the official manual path for teams running PHP, Composer, and the database on the host

Choose the path that matches your environment. Both tracks are supported on Windows and Linux.

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

## Quick decision guide

Choose [Install with Docker](./docker-install.md) if you want:

- the fastest supported setup
- the default onboarding flow
- project commands through `./pachybase` or `.\pachybase.bat`
- database provisioning handled for you

Choose [Local Installation](./local-install.md) if you want:

- PHP, Composer, and the database managed directly on the host
- no Docker dependency for development
- direct control over the runtime and database services while keeping the same project CLI

## Path 1: Install with Docker

This is the main track and the recommended starting point.

```bash
cp .env.example .env
./pachybase install
./pachybase doctor
```

On Windows, replace `./pachybase` with `.\pachybase.bat`.

Read the full guide: [Install with Docker](./docker-install.md)

## Path 2: Local Installation

This is the official alternative track for teams that do not want the runtime inside Docker.

Typical flow:

```bash
cp .env.example .env
composer install
# update APP_RUNTIME=local and your DB_* values
./pachybase install
./pachybase status
```

Direct host commands remain available when you want to operate one step at a time:

```bash
cp .env.example .env
composer install
php scripts/bootstrap-database.php
php -S 127.0.0.1:8080 -t public public/router.php
```

Read the full guide: [Local Installation](./local-install.md)

## Release readiness

Before sharing the environment with other developers or publishing a release candidate, run the runtime checks that match your chosen track:

- Docker track: `./pachybase doctor`
- Local track: `./pachybase doctor` (or `php scripts/doctor.php` when you want the direct PHP entrypoint)
