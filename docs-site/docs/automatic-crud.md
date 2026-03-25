---
id: automatic-crud
title: Automatic CRUD
sidebar_position: 5
---

# Automatic CRUD

Phase 5 turns PachyBase metadata into usable REST endpoints without forcing the developer to hand-code every entity flow.

## Default enabled entities

The current runtime exposes CRUD automatically for:

- `system-settings` -> `pb_system_settings`
- `api-tokens` -> `pb_api_tokens`

These mappings are loaded through `config/CrudEntities.php` and consumed by `modules/Crud/CrudEntityRegistry.php`.

## Route surface

Each enabled entity receives:

- `GET /api/{entity}`
- `GET /api/{entity}/{id}`
- `POST /api/{entity}`
- `PUT /api/{entity}/{id}`
- `PATCH /api/{entity}/{id}`
- `DELETE /api/{entity}/{id}`

All CRUD routes are now protected by bearer authentication and authorize the caller per entity/action before executing the persistence flow.

## Declarative entity control

Phase 8 adds a declarative entity layer so the developer can change behavior without editing the CRUD core.

Each entity config can now:

- expose or hide the entity
- disable delete
- whitelist writable fields
- hide serialized fields
- set a custom `max_per_page`
- mark extra readonly fields
- declare extra validation rules
- register lightweight hooks such as `before_create`, `before_update`, `after_create`, `after_show`, `after_list_item`, and `after_delete`

## Request conventions

### Pagination

- `page`: 1-indexed page number, default `1`
- `per_page`: page size, default `15`, max `100`

### Filtering

Equality filters use:

```text
filter[field]=value
```

Richer filters use:

```text
filter[field][operator]=value
```

Supported operators:

- `eq`, `ne`
- `gt`, `gte`, `lt`, `lte` for numeric and date/time fields
- `in` for comma-separated values
- `contains` for case-insensitive text matches
- `null` with `true` or `false`

Example:

```text
/api/system-settings?filter[is_public]=1
/api/system-settings?filter[setting_key][contains]=site
```

### Sorting

Sorting uses `sort`:

- `sort=field` for ascending
- `sort=-field` for descending
- `sort=field,-other_field` for multiple columns

### Search

Basic search uses:

```text
search=term
```

Search runs against the entity searchable fields configured in the CRUD registry.

## Validation and field rules

The CRUD layer derives field rules from entity metadata and merges them with the entity config:

- readonly fields cannot be written
- required fields are enforced on create and replace
- disallowed fields are rejected before persistence
- nullable fields accept `null`
- scalar values are normalized by type when possible

Validation failures are returned as HTTP `422` with the standard `validation_error` contract.

## Protection model

The CRUD layer now expects a JWT access token or an API token on every request.

Authorization is scope-based and deny-by-default:

- `crud:read`, `crud:create`, `crud:update`, `crud:delete`
- `entity:{entity}:read`, `entity:{entity}:create`, `entity:{entity}:update`, `entity:{entity}:delete`
- wildcard grants like `entity:system-settings:*` or `*`

## Error behavior

Automatic CRUD now maps the main persistence cases to the public API contract:

- `404` when the entity or record does not exist
- `409` when the write conflicts with a unique constraint
- `422` when the payload, pagination, filters, or sort values are invalid
- `500` for unexpected database/runtime failures

## Implementation map

- `config/CrudEntities.php`: declarative entity behavior
- `modules/Crud/CrudEntityRegistry.php`: entity config loading and registry lookups
- `modules/Crud/CrudModule.php`: automatic route registration
- `api/Controllers/CrudController.php`: generic CRUD controller
- `services/Crud/EntityCrudService.php`: central CRUD orchestration
- `services/Crud/EntityCrudValidator.php`: payload normalization and validation
- `services/Crud/EntityCrudSerializer.php`: output serialization and type casting

## Validation

The automatic CRUD layer is covered by:

- integration tests for list, show, create, replace, patch, delete, validation, conflict, and not found
- kernel tests for route dispatch on the automatic CRUD endpoints
- full project test suite regression runs after enabling the new module
