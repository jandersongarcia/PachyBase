---
id: ai-endpoints
title: AI Endpoints
---

# AI Endpoints

PachyBase exposes machine-oriented discovery endpoints so frontend generators, agents, and automation tooling can understand the live backend contract without reverse engineering the codebase.

## Endpoint surface

- `GET /ai/schema`: full machine-readable schema document
- `GET /ai/entities`: compact list of exposed entities
- `GET /ai/entity/{name}`: one exposed entity contract

## What the documents include

- exposed CRUD entities and their collection/item paths
- field metadata such as type, nullable, readonly, writable, and defaults
- pagination, filter, sort, and search capabilities
- available CRUD operations and required scopes
- OpenAPI compatibility metadata and component references

## Relationship with OpenAPI

The AI endpoints do not replace OpenAPI. They complement it by publishing higher-level semantics that are convenient for generators and LLM workflows:

- human-stable entity names
- writable vs readonly guidance
- filters and pagination as structured capabilities
- scope expectations per CRUD operation

## Example requests

```bash
curl http://localhost:8080/ai/schema
curl http://localhost:8080/ai/entities
curl http://localhost:8080/ai/entity/system-settings
```

## When to use which document

- Use `/openapi.json` for client generation and transport-level schemas
- Use `/ai/schema` when a tool needs a compact map of the whole product
- Use `/ai/entity/{name}` when a workflow needs just one entity contract
