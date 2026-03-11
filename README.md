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

- [`public/index.php`](./public/index.php) responds through a centralized JSON contract and acts as the front controller.
- [`core/Http/Router.php`](./core/Http/Router.php) provides a simple and fast routing engine.
- [`core/Http/Request.php`](./core/Http/Request.php) abstracts incoming HTTP requests safely.
- [`core/Http/ApiResponse.php`](./core/Http/ApiResponse.php) is the single response formatter.
- [`core/Http/ErrorHandler.php`](./core/Http/ErrorHandler.php) converts PHP errors and exceptions into the same API structure.

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

## Docker install

Configure the database connection in [`.env`](./.env). It supports `mysql` and `pgsql`:

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

The `docker-install` script is a smart automation tool that:
1. Validates your database settings.
2. Generates a custom `docker/Dockerfile` to compile PHP 8.2 with the exact PDO extensions required by your driver.
3. Configures an Nginx container (`docker/nginx.conf`) to handle URL rewriting.
4. Generates a tailored `docker/docker-compose.yml` and starts the containers.

Once running, PachyBase is accessible at **`http://localhost:8080`**.
