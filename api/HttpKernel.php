<?php

declare(strict_types=1);

namespace PachyBase\Api;

use PachyBase\Http\Request;
use PachyBase\Http\Router;
use RuntimeException;

final class HttpKernel
{
    public function __construct(
        private readonly string $basePath
    ) {
    }

    public function handle(): void
    {
        $request = Request::capture();
        $router = new Router();
        $registerRoutes = require $this->basePath . '/routes/api.php';

        if (!is_callable($registerRoutes)) {
            throw new RuntimeException('The API routes file must return a callable registrar.');
        }

        $registerRoutes($router);
        $router->dispatch($request);
    }
}
