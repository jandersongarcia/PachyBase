---
id: intro
title: Overview
slug: /
sidebar_position: 1
---

# PachyBase

PachyBase is a self-hosted PHP backend foundation focused on predictable JSON APIs, Docker-first local setup, and machine-readable contracts that are safe for humans and AI to consume.

## What the project does today

- Loads application and database configuration from `.env`.
- Exposes a health/status endpoint through `public/index.php`.
- Uses a centralized JSON response contract for success and failure.
- Installs Docker services from Composer based on the configured database driver.

## Current principles

- Predictable API responses with a fixed outer contract.
- Simple local development with Docker.
- Clear extension points for routing, modules, and CRUD generation.
- Documentation that can be consumed in English and Brazilian Portuguese.

## Documentation map

- [Install](./install.md)
- [API Contract](./api-contract.md)
- [Libraries](./libraries.md)
- [Docker Install](./docker-install.md)

## Run the docs site locally

```bash
npm install
npm run start
```

By default, the documentation opens in English. Use the locale selector in the top navigation to switch to `pt-BR`.
