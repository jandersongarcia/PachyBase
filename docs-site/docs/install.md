---
id: install
title: Install
sidebar_position: 2
---

# Install

PachyBase can be installed on Windows and Linux with Docker and Docker Compose only. Composer is executed inside the PHP container during setup.

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

## Required manual step

Before running the installer, create `.env` from `.env.example` and fill in the database settings. This step is mandatory because `DB_DRIVER` determines which database container will be generated during setup.

```bash
cp .env.example .env
```

## Windows

After configuring `.env`, run this from PowerShell or Command Prompt in the project root:

```powershell
.\install.bat
```

## Linux

After configuring `.env`, run this from a shell in the project root:

```bash
chmod +x install.sh
./install.sh
```

## Next step

The platform installers perform the same setup flow:

1. Reads the database settings from `.env`.
2. Generates `docker/docker-compose.yml` from the database settings.
3. Builds the PHP image with Composer available inside Docker.
4. Runs `composer install` inside the PHP container.
5. Starts the containers.
6. Waits for the database to become ready.
7. Runs the default migrations and seeds automatically.

After the installer finishes, the local environment already includes:

- the migration control table
- the seed control table
- the base system tables
- the default initial settings seed

To rebuild the full local environment without manual database work:

```bash
docker compose -f docker/docker-compose.yml down -v
./install.sh
```

After the source code is available locally, continue with [Docker Install](./docker-install.md) for more details about the Docker flow.
