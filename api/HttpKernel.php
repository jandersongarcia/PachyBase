<?php

declare(strict_types=1);

namespace PachyBase\Api;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\CorsPolicy;
use PachyBase\Http\FileRateLimiter;
use PachyBase\Http\Request;
use PachyBase\Http\Router;
use PachyBase\Services\Observability\RequestContext;
use PachyBase\Services\Observability\RequestMetrics;
use RuntimeException;

final class HttpKernel
{
    public function __construct(
        private readonly string $basePath
    ) {
    }

    public function handle(): void
    {
        RequestMetrics::reset();
        RequestMetrics::start();
        $request = Request::capture();
        RequestContext::set($request);
        $router = new Router();
        $registerRoutes = require $this->basePath . '/routes/api.php';

        if (!is_callable($registerRoutes)) {
            throw new RuntimeException('The API routes file must return a callable registrar.');
        }

        $registerRoutes($router);
        $this->handleCorsPreflight($request, $router);
        $this->enforceRateLimit($request);
        $router->dispatch($request);
    }

    private function handleCorsPreflight(Request $request, Router $router): void
    {
        if ($request->getMethod() !== 'OPTIONS') {
            return;
        }

        $policy = CorsPolicy::fromConfig();
        $origin = trim((string) $request->header('Origin', ''));

        if (!$policy->enabled() || $origin === '') {
            return;
        }

        $allowedMethods = $router->allowedMethodsForPath($request->getPath());

        if ($allowedMethods === []) {
            throw new RuntimeException(sprintf('Route not found: %s', $request->getPath()), 404);
        }

        if (!$policy->allowsOrigin($origin)) {
            throw new RuntimeException(sprintf('CORS origin not allowed: %s', $origin), 403);
        }

        $requestedMethod = strtoupper(trim((string) $request->header('Access-Control-Request-Method', '')));

        if ($requestedMethod !== '' && !in_array($requestedMethod, $allowedMethods, true)) {
            throw new RuntimeException(sprintf('Method %s Not Allowed', $requestedMethod), 405);
        }

        ApiResponse::preflight($allowedMethods);
    }

    private function enforceRateLimit(Request $request): void
    {
        (new FileRateLimiter())->enforce($request);
    }
}
