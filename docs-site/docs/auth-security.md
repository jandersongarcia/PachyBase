---
id: auth-security
title: Authentication and Authorization
sidebar_position: 7
---

# Authentication and Authorization

Phase 7 adds a hybrid security layer to PachyBase without moving the project away from its lightweight core.

## Supported credentials

The runtime now accepts two bearer credential types:

- API tokens for server-to-server integrations and automations
- JWT access tokens for web and mobile clients

Refresh tokens are tracked separately in `pb_auth_sessions` and are used only by the auth endpoints.

## Route surface

Public auth routes:

- `POST /api/auth/login`
- `POST /api/auth/refresh`
- `POST /api/auth/revoke`

Protected auth routes:

- `GET /api/auth/me`
- `POST /api/auth/tokens`
- `DELETE /api/auth/tokens/{id}`

Protected CRUD routes:

- every `/api/{entity}` CRUD endpoint now requires a bearer credential
- sensitive auth and CRUD write operations can be appended to the audit log when `APP_AUDIT_LOG_ENABLED=true`

## Login and refresh flow

1. `POST /api/auth/login` validates the user credentials against `pb_users`.
2. On success, PachyBase issues:
   - a short-lived JWT access token
   - a refresh token backed by `pb_auth_sessions`
3. `POST /api/auth/refresh` rotates the refresh session and returns a new access token pair.
4. `POST /api/auth/revoke` can revoke:
   - a refresh token sent in the payload
   - the current authenticated JWT session
   - the current authenticated API token

## Scope model

Authorization is deny-by-default when a route or action requires permission.

The current scope conventions include:

- `crud:read`
- `crud:create`
- `crud:update`
- `crud:delete`
- `entity:{entity}:read`
- `entity:{entity}:create`
- `entity:{entity}:update`
- `entity:{entity}:delete`
- `auth:tokens:create`
- `auth:tokens:revoke`
- `auth:manage`

Wildcard scopes are supported through grants such as:

- `entity:system-settings:*`
- `crud:*`
- `*`

## Bootstrap user

The local bootstrap seeds one default admin user for development and smoke testing:

- email: `admin@pachybase.local`
- password: `pachybase123`

Override it before `composer db:bootstrap` with:

- `AUTH_BOOTSTRAP_ADMIN_EMAIL`
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`
- `AUTH_BOOTSTRAP_ADMIN_NAME`

## Environment configuration

The auth layer reads these environment variables:

- `AUTH_JWT_SECRET`
- `AUTH_JWT_ISSUER`
- `AUTH_ACCESS_TTL_MINUTES`
- `AUTH_REFRESH_TTL_DAYS`
- `AUTH_BOOTSTRAP_ADMIN_EMAIL`
- `AUTH_BOOTSTRAP_ADMIN_PASSWORD`
- `AUTH_BOOTSTRAP_ADMIN_NAME`

In development, PachyBase falls back to a local JWT secret when `AUTH_JWT_SECRET` is missing. In production, the secret must be explicitly configured.

For publicly exposed environments, also review:

- `APP_RATE_LIMIT_ENABLED`
- `APP_RATE_LIMIT_MAX_REQUESTS`
- `APP_RATE_LIMIT_WINDOW_SECONDS`
- `APP_AUDIT_LOG_ENABLED`
- `APP_AUDIT_LOG_PATH`

## Example requests

Login:

```bash
curl -X POST http://localhost:8080/api/auth/login \
  --data-urlencode email=admin@pachybase.local \
  --data-urlencode password=pachybase123
```

Inspect the authenticated principal:

```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <access-token>"
```

Create an API token scoped to one CRUD entity:

```bash
curl -X POST http://localhost:8080/api/auth/tokens \
  -H "Authorization: Bearer <access-token>" \
  --data-urlencode name="Deploy Token" \
  --data-urlencode "scopes[0]=entity:system-settings:read"
```

Create an integration token without interactive login:

```bash
./pachybase auth:token:create "Codex Agent" \
  --scope=crud:read \
  --scope=entity:system-settings:read
```

By default this creates a userless service-to-service token. Add `--user-email=admin@pachybase.local` to bind it to an existing active user.

## Implementation map

- `modules/Auth/AuthModule.php`
- `api/Controllers/AuthController.php`
- `auth/AuthService.php`
- `auth/AuthorizationService.php`
- `auth/BearerTokenAuthenticator.php`
- `auth/JwtCodec.php`
- `auth/Middleware/RequireBearerToken.php`
- `auth/UserRepository.php`
- `auth/ApiTokenRepository.php`
- `auth/RefreshTokenRepository.php`

## Validation

The auth layer is covered by:

- unit tests for JWT encoding/decoding and scope authorization
- integration tests for login, refresh, API token issuance, and revocation
- kernel tests for login, `/api/auth/me`, API token creation, and protected CRUD access
- the full project regression suite
