---
id: filters-pagination
title: Filters and Pagination
---

# Filters and Pagination

Automatic CRUD collections expose one consistent query interface for paging, filtering, sorting, and search.

## Pagination parameters

- `page`: 1-based page number, default `1`
- `per_page`: page size, default `15`

Each entity can lower the maximum page size through `config/CrudEntities.php`. When `per_page` exceeds the entity limit, PachyBase returns a structured `422` validation response.

## Pagination response

Paginated results place metadata in `meta.pagination`:

```json
{
  "meta": {
    "pagination": {
      "total": 25,
      "per_page": 10,
      "current_page": 2,
      "last_page": 3,
      "from": 11,
      "to": 20
    }
  }
}
```

## Field filters

Use equality filters with `filter[field]=value`:

```text
/api/system-settings?filter[is_public]=1
```

Allowed filter fields come from the entity configuration and can be inspected through both OpenAPI and `/ai/entity/{name}`.

## Sorting

Use `sort` with one or more comma-separated fields:

- `sort=setting_key`
- `sort=-created_at`
- `sort=setting_key,-id`

Descending order uses a `-` prefix.

## Search

Use `search=term` to search across the entity searchable fields:

```text
/api/system-settings?search=app
```

Searchable fields are controlled by `config/CrudEntities.php`.

## Examples

```bash
curl "http://localhost:8080/api/system-settings?page=2&per_page=10"
curl "http://localhost:8080/api/system-settings?filter[is_public]=1&sort=setting_key"
curl "http://localhost:8080/api/system-settings?search=app"
```

## Failure behavior

Invalid pagination, filters, or sort values return the standard API contract with:

- HTTP `422`
- `error.type = "validation_error"`
- structured `error.details`
