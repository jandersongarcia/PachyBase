---
id: architecture
title: Architecture
sidebar_position: 2
---

# Architecture

PachyBase now separates its runtime into explicit application layers instead of keeping everything inside `core/`.

## Layer map

- `public/`: front controller only. It delegates to the bootstrap and HTTP kernel.
- `config/`: environment-backed configuration and bootstrap wiring.
- `routes/`: route registration entrypoints.
- `api/`: HTTP kernel and API controllers.
- `modules/`: domain-oriented route composition.
- `services/`: business flows used by controllers.
- `database/`: connection, adapters, schema inspection, and persistence infrastructure.
- `auth/`: authentication services and middleware.
- `utils/`: reusable helpers.
- `core/Http/`: shared HTTP infrastructure, request capture, routing, API responses, and error handling.

## Request lifecycle

1. `public/index.php` loads Composer autoload and calls `PachyBase\Config\Bootstrap`.
2. `config/Bootstrap.php` loads `.env` values and registers the global error handler.
3. `api/HttpKernel.php` captures the current request and loads `routes/api.php`.
4. `routes/api.php` registers modules such as `modules/System/SystemModule.php`, `modules/Auth/AuthModule.php`, and `modules/Crud/CrudModule.php`.
5. Controllers in `api/Controllers/` delegate business logic to `services/` and `auth/`, including the automatic CRUD and security layers.
6. Responses still flow through `core/Http/ApiResponse.php` to preserve the contract.

## Current example

The root status endpoint is implemented through this chain:

- `routes/api.php`
- `modules/System/SystemModule.php`
- `api/Controllers/SystemController.php`
- `services/SystemStatusService.php`
- `database/Connection.php`

The automatic CRUD endpoints are implemented through this chain:

- `routes/api.php`
- `modules/Crud/CrudModule.php`
- `auth/Middleware/RequireBearerToken.php`
- `api/Controllers/CrudController.php`
- `services/Crud/EntityCrudService.php`
- `database/Metadata/EntityIntrospector.php`
- `database/Query/PdoQueryExecutor.php`

The auth endpoints are implemented through this chain:

- `routes/api.php`
- `modules/Auth/AuthModule.php`
- `auth/Middleware/RequireBearerToken.php` for protected routes
- `api/Controllers/AuthController.php`
- `auth/AuthService.php`
- `auth/AuthorizationService.php`
- `auth/JwtCodec.php`
- `auth/ApiTokenRepository.php` and `auth/RefreshTokenRepository.php`

## Validation

The architecture is covered by:

- `tests/Api/HttpKernelTest.php` for end-to-end route dispatch inside the application kernel.
- `tests/Auth/RequireBearerTokenTest.php` for the authentication layer.
- `tests/Api/AuthHttpKernelTest.php` for login, profile, API token, and protected route flows.
- `tests/Http/*` for request, router, response contract, and error handling behavior.

