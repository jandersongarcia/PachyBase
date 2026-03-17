---
id: contract-enforcement
title: Contract Enforcement
sidebar_position: 4
---

# Contract Enforcement

Phase 1 is only complete when the API contract is not just documented, but also enforced by the codebase and the test suite.

## Enforcement rules

- Every HTTP success response must be emitted by `core/Http/ApiResponse.php`.
- Every failure must be normalized by `core/Http/ErrorHandler.php`.
- Controllers, services, modules, routes, bootstrap code, and auth middleware must not call `echo`, `print`, `exit`, `header()`, `http_response_code()`, or `json_encode()` directly.
- New endpoints must preserve the public envelope with `success`, `data`, `meta`, and `error`.
- `meta.request_id`, `meta.timestamp`, `meta.method`, and `meta.path` are mandatory for every response.

## Test coverage

The contract is protected by:

- `tests/Http/ApiResponseTest.php`
- `tests/Http/ErrorHandlerTest.php`
- `tests/Api/HttpKernelTest.php`
- `tests/Architecture/ApiContractEnforcementTest.php`

## Validation commands

```bash
docker compose -f docker/docker-compose.yml exec php composer dump-autoload
docker compose -f docker/docker-compose.yml exec php vendor/bin/phpunit --testdox
```

## Recommended smoke checks

```bash
curl http://localhost:8080/
curl http://localhost:8080/missing
curl -X POST http://localhost:8080/
```
