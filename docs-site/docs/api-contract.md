---
id: api-contract
title: API Contract
sidebar_position: 2
---

# API Contract

PachyBase responses are designed to be predictable, structured, and unambiguous for both developers and AI agents.

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
    "type": "application_error",
    "message": "The request payload is invalid.",
    "details": []
  }
}
```

## Contract rules

- `success` is always a boolean.
- `data` is always present, even when its value is `null`.
- `meta` is always present and contains request-level metadata.
- `error` is always `null` on success and always an object on failure.
- Responses should never switch between JSON, HTML, and plain text depending on the failure mode.

## Current implementation

- `core/Http/ApiResponse.php` formats successful and failed responses.
- `core/Http/ErrorHandler.php` converts exceptions and PHP errors into the same contract.
- `public/index.php` already uses this shared response layer.
