---
id: api-contract
title: API Contract
sidebar_position: 2
---

# API Contract

PachyBase responses are designed to be predictable, structured, and unambiguous for both developers and AI agents. This document is the official public contract for every current and future HTTP endpoint.

## Response envelope

Every response must expose the same top-level envelope:

- `success`: required boolean.
- `data`: required value. It is `null` on failures.
- `meta`: required object with request-level metadata.
- `error`: `null` on success and a structured object on failure.

## Success response

```json
{
  "success": true,
  "data": {},
  "meta": {
    "contract_version": "1.0",
    "request_id": "b0bb2f930d4b4f5ab9e2d1f7b74b9df6",
    "timestamp": "2026-03-11T03:00:00+00:00",
    "path": "/",
    "method": "GET"
  },
  "error": null
}
```

## Error response

```json
{
  "success": false,
  "data": null,
  "meta": {
    "contract_version": "1.0",
    "request_id": "b0bb2f930d4b4f5ab9e2d1f7b74b9df6",
    "timestamp": "2026-03-11T03:00:00+00:00",
    "path": "/users",
    "method": "POST"
  },
  "error": {
    "code": "VALIDATION_ERROR",
    "type": "validation_error",
    "message": "The request payload is invalid.",
    "details": [
      {
        "field": "email",
        "code": "required",
        "message": "The email field is required."
      }
    ]
  }
}
```

## Required metadata

Every `meta` object must include:

- `contract_version`
- `request_id`
- `timestamp`
- `method`
- `path`

Additional metadata may be included as long as these keys remain stable.

## Pagination convention

Paginated collections must place pagination data in `meta.pagination`:

```json
{
  "pagination": {
    "total": 25,
    "per_page": 10,
    "current_page": 2,
    "last_page": 3,
    "from": 11,
    "to": 20
  }
}
```

## Error conventions

Structured errors must expose:

- `error.code`: stable machine-readable identifier.
- `error.type`: one of `validation_error`, `authentication_error`, `authorization_error`, `application_error`, or `server_error`.
- `error.message`: user-facing summary for the failure.
- `error.details`: list of structured detail objects. Use an empty list when there are no additional details.

## Validation convention

Validation failures must use HTTP `422`.

- `error.code`: `VALIDATION_ERROR` unless a narrower validation code is documented.
- `error.type`: `validation_error`.
- `error.message`: `The request payload is invalid.` unless a more specific validation summary is needed.
- `error.details`: list of field-level objects with `field`, `code`, and `message`.

## Authentication and authorization conventions

Authentication failures must use HTTP `401` when credentials are missing, invalid, or expired.

- `error.type`: `authentication_error`
- Recommended default code: `AUTHENTICATION_REQUIRED`

Authorization failures must use HTTP `403` when the caller is authenticated but does not have permission.

- `error.type`: `authorization_error`
- Recommended default code: `INSUFFICIENT_PERMISSIONS`

## Enforcement rules

- `success` is always a boolean.
- `data` is always present, even when its value is `null`.
- `meta` is always present and contains request-level metadata.
- `error` is always `null` on success and always an object on failure.
- Responses never switch between JSON, HTML, and plain text depending on the failure mode.
- New endpoints must return through `core/Http/ApiResponse.php`.
- Contract tests must be added for every new endpoint shape.
- Runtime layers outside `core/Http/ApiResponse.php` must not emit raw HTTP output directly.

## Current implementation

- `core/Http/ApiResponse.php` formats successful and failed responses.
- `core/Http/ErrorHandler.php` converts exceptions and PHP errors into the same contract.
- `public/index.php` delegates request handling to the modular bootstrap and kernel.
- `routes/api.php` centralizes HTTP route registration.
- `api/Controllers/` and `services/` keep endpoint orchestration separate from business logic.
- `tests/Architecture/ApiContractEnforcementTest.php` blocks manual output logic in runtime layers.
