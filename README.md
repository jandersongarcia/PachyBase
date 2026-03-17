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

### Contract goals

- `success` is always a boolean.
- `data` always exists, even when `null`.
- `meta` always exists and carries machine-readable request context.
- `error` is always `null` on success or a fixed object on failure.
- The API never mixes plain text, HTML, and JSON for different failure modes.

### Official contract conventions

- Required metadata: `meta.request_id`, `meta.timestamp`, `meta.method`, and `meta.path`.
- Pagination responses expose `meta.pagination.total`, `per_page`, `current_page`, `last_page`, `from`, and `to`.
- Validation failures use HTTP `422`, `error.code = "VALIDATION_ERROR"`, `error.type = "validation_error"`, and `error.details` as a list of field objects shaped like `field`, `code`, and `message`.
- Authentication failures use HTTP `401` and `error.type = "authentication_error"` for missing, invalid, or expired credentials.
- Authorization failures use HTTP `403` and `error.type = "authorization_error"` when the caller is authenticated but lacks permission.
- New endpoints must return through `core/Http/ApiResponse.php`; controllers and middleware should not build JSON payloads manually.

### Current implementation

- [`public/index.php`](./public/index.php) responds through a centralized JSON contract and acts as the front controller.
- [`core/Http/Router.php`](./core/Http/Router.php) provides a simple and fast routing engine.
- [`core/Http/Request.php`](./core/Http/Request.php) abstracts incoming HTTP requests safely.
- [`core/Http/ApiResponse.php`](./core/Http/ApiResponse.php) is the single response formatter.
- [`core/Http/ErrorHandler.php`](./core/Http/ErrorHandler.php) converts PHP errors and exceptions into the same API structure.
- [`docs-site/docs/api-contract.md`](./docs-site/docs/api-contract.md) is the canonical public contract specification.

## Routing and Controllers

Routes are registered in `public/index.php` using the `$router` instance. 
You can map routes directly to Controller classes within the `core/Controllers/` namespace.

Example:

```php
use PachyBase\Controllers\SystemController;

$router->get('/status', [SystemController::class, 'status']);
```

And your Controller method receives the abstracted `Request` object:

```php
namespace PachyBase\Controllers;

use PachyBase\Http\Request;
use PachyBase\Http\ApiResponse;

class SystemController
{
    public function status(Request $request): void
    {
        // Example reading a query param: ?verbose=true
        $isVerbose = $request->query('verbose', false);
        
        // Example reading JSON body on POST
        // $data = $request->json('field_name');

        ApiResponse::success(['system' => 'ok']);
    }
}
```

## Installation

PachyBase only requires Docker and Docker Compose on the host machine. Composer runs inside the PHP container during setup.

Before installation, create [`.env`](./.env) from [`.env.example`](./.env.example) and fill in the database settings. This step is mandatory because `DB_DRIVER` defines which database container will be generated in `docker/docker-compose.yml`. The supported drivers are `mysql` and `pgsql`:

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=pachybase
DB_USERNAME=root
DB_PASSWORD=root
```

Example:

```bash
cp .env.example .env
```

### Windows

After configuring `.env`, run this from PowerShell or Command Prompt in the project root:

```bash
.\install.bat
```

### Linux

After configuring `.env`, run this from a shell in the project root:

```bash
chmod +x install.sh
./install.sh
```

Both installers perform these steps:
1. Validate Docker and Docker Compose availability.
2. Read the database settings from `.env`.
3. Generate `docker/docker-compose.yml` from the database settings.
4. Build the PHP image with Composer available inside Docker.
5. Run `composer install` inside the PHP container.
6. Start the containers with `docker compose up -d`.

After installation, you can manage the stack with:

```bash
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml down
docker compose -f docker/docker-compose.yml logs -f
```

Once running, PachyBase is accessible at **`http://localhost:8080`**.
