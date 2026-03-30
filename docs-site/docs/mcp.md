---
id: mcp
title: MCP Adapter
sidebar_position: 11
---

# MCP Adapter

PachyBase now ships a native MCP stdio adapter so Claude, Codex, and other MCP-capable clients can talk to the live backend through tools instead of raw HTTP prompts.

## Start the server

Use the project CLI:

```bash
./pachybase mcp:serve
```

By default the adapter uses:

- `APP_URL` from `.env` as the PachyBase base URL
- `PACHYBASE_MCP_TOKEN` as the bearer token for protected CRUD tools

You can override the base URL at launch time:

```bash
./pachybase mcp:serve --base-url=http://localhost:8080
```

## Recommended environment

Create a scoped integration token first:

```bash
./pachybase auth:token:create "Claude MCP" \
  --scope=crud:read \
  --scope=crud:create \
  --scope=crud:update \
  --scope=entity:system-settings:read \
  --scope=entity:system-settings:update
```

Then export it before starting the adapter:

```bash
export PACHYBASE_MCP_TOKEN="<integration-token>"
./pachybase mcp:serve
```

On Windows PowerShell:

```powershell
$env:PACHYBASE_MCP_TOKEN="<integration-token>"
.\pachybase.bat mcp:serve
```

## Exposed tools

- `pachybase_get_schema`
- `pachybase_list_entities`
- `pachybase_describe_entity`
- `pachybase_list_records`
- `pachybase_get_record`
- `pachybase_create_record`
- `pachybase_replace_record`
- `pachybase_update_record`
- `pachybase_delete_record`

The CRUD tools call the same HTTP endpoints documented by OpenAPI and `/ai/schema`, so they inherit the same validation, auth checks, audit logging, and rate limiting behavior from the live backend.

## Integration notes

- The adapter speaks JSON-RPC over stdio and declares the MCP `tools` capability.
- Discovery tools can work without a bearer token if your runtime leaves the docs endpoints public.
- Protected CRUD tools should be used with a scoped token through `PACHYBASE_MCP_TOKEN`.
- Because the adapter wraps the live HTTP API, it is best used against a running PachyBase runtime instead of a stopped project directory.
