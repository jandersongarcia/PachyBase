---
id: intro
title: Overview
slug: /
sidebar_position: 1
---

# PachyBase

PachyBase is a self-hosted PHP backend foundation focused on predictable JSON APIs, Docker-first local setup, and machine-readable contracts that are safe for humans and AI to consume.

## What a new developer can do today

- Install the stack quickly with Docker or manually on the host
- Configure the app through `.env` and declarative PHP config files
- Run automatic CRUD for exposed entities without hand-writing controllers
- Authenticate with JWT access tokens or API tokens
- Publish OpenAPI and AI-friendly schema documents from the live runtime
- Run tests, inspect entities, sync CRUD config, and bootstrap the database from the CLI

## Current principles

- Predictable API responses with a fixed outer contract
- Docker-first onboarding with an official local installation alternative
- Clear extension points for routing, modules, and CRUD generation
- Documentation that can be consumed in English and Brazilian Portuguese

## Quick start (Docker)

```bash
cp .env.example .env
./pachybase install
```

On Windows, use `Copy-Item .env.example .env` and `.\pachybase.bat install`.

After the stack is ready:

- API base URL: `http://localhost:8080`
- OpenAPI document: `http://localhost:8080/openapi.json`
- AI schema: `http://localhost:8080/ai/schema`
- Development admin login: `admin@pachybase.local` / `pachybase123`

For the full installation decision tree, start with [Install](./install.md). The Docker path is the primary track, and [Local Installation](./local-install.md) is the official manual alternative.

## Documentation map

### Product and setup

- [Install](./install.md)
- [Install with Docker](./docker-install.md)
- [Local Installation](./local-install.md)
- [Configuration](./configuration.md)
- [Supported Databases](./supported-databases.md)
- [Architecture](./architecture.md)

### API and integrations

- [API Contract](./api-contract.md)
- [Authentication and Authorization](./auth-security.md)
- [Automatic CRUD](./automatic-crud.md)
- [Filters and Pagination](./filters-pagination.md)
- [OpenAPI](./openapi.md)
- [AI Endpoints](./ai-endpoints.md)

### Tooling and maintenance

- [CLI](./cli.md)
- [Testing](./testing.md)
- [Examples](./examples.md)
- [Contributing](./contributing.md)
- [Roadmap](./roadmap.md)

## Run the docs site locally

```bash
npm install
npm run start
```

By default, the documentation opens in English. Use the locale selector in the top navigation to switch to `pt-BR`.
