# PachyBase

<p align="center">
  <img src="assets/logo.png" width="200" alt="PachyBase Logo">
</p>

# PachyBase

PachyBase is an open-source, self-hosted backend foundation built with PHP, focused on predictable JSON APIs, modular architecture, and simple local development through Docker.

The project is designed for developers who want more control over their backend stack, infrastructure, and deployment flow without depending on external BaaS platforms.

## Highlights

- API-first approach
- Predictable JSON response structure
- Modular and extensible architecture
- Self-hosted and Docker-ready
- Support for different database drivers
- PHP 8+ oriented structure
- Clean and maintainable codebase

## Standard API Response

All responses should follow the project standard:

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

Error responses keep the same outer structure:

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

### Contract goals

- `success` is always a boolean.
- `data` always exists, even when `null`.
- `meta` always exists and carries machine-readable request context.
- `error` is always `null` on success or a fixed object on failure.
- The API never mixes plain text, HTML, and JSON for different failure modes.

### Current implementation

- [`public/index.php`](./public/index.php) now responds through a centralized JSON contract.
- [`core/Http/ApiResponse.php`](./core/Http/ApiResponse.php) is the single response formatter.
- [`core/Http/ErrorHandler.php`](./core/Http/ErrorHandler.php) converts PHP errors and exceptions into the same API structure.

## Docker install

Configure the database connection in [`.env`](./.env):

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=root
DB_PASSWORD=root
```

Then run:

```bash
composer install
composer docker-install
```

The `docker-install` script validates the database settings, generates `docker/docker-compose.yml`, and starts the Docker containers with the selected database engine.
