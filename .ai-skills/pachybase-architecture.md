# PachyBase Architecture Skill

## Use this skill when

- the task changes runtime behavior
- the agent needs to know where a new class or edit belongs
- the request mentions routes, controllers, services, modules, auth, or database infrastructure

## Core map

- `public/`: front controller only
- `config/`: bootstrap and environment-backed configuration
- `routes/`: route registration entrypoints
- `modules/`: route composition by domain
- `api/`: HTTP kernel and controllers
- `services/`: business logic and orchestration
- `auth/`: authentication, authorization, and middleware
- `database/`: adapters, schema inspection, persistence, and query execution
- `core/Http/`: request, router, API responses, and shared error handling
- `tests/`: HTTP, auth, database, CLI, service, and script coverage

## Request lifecycle

1. `public/index.php` boots Composer and calls `PachyBase\Config\Bootstrap`.
2. `config/Bootstrap.php` loads `.env` and global error handling.
3. `api/HttpKernel.php` captures the request and loads `routes/api.php`.
4. `routes/api.php` registers modules such as `System`, `Auth`, and `Crud`.
5. Controllers delegate to services, auth, and database layers.
6. Responses should still leave through the shared API contract helpers.

## Placement rules

- Put routing composition in `modules/`, not in controllers.
- Put request orchestration in controllers, not domain rules.
- Put reusable business logic in `services/`.
- Put persistence concerns in `database/` or dedicated repositories.
- Put authentication and permission checks in `auth/` middleware and services.
- Preserve the JSON response envelope and error contract.

## Guardrails

- Prefer extending an existing module before creating a new cross-cutting abstraction.
- Keep docs, AI schema generation, and OpenAPI generation aligned when public behavior changes.
- Do not bypass the shared HTTP and auth layers with ad hoc response code.

## References

- `docs-site/docs/architecture.md`
- `docs-site/docs/api-contract.md`
- `docs-site/docs/auth-security.md`
- `docs-site/docs/testing.md`
