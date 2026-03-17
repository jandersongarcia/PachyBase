<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function get(string $path, callable|array $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): Route
    {
        $route = new Route($method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            // First: check if the path matches
            if (!preg_match($route->pattern, $path, $matches)) {
                continue;
            }

            $pathMatched = true;

            // Then: check if the method matches
            if ($route->method !== $method) {
                continue;
            }

            // Both path and method match — build params and run pipeline
            array_shift($matches);
            $params = [];
            foreach ($route->getParamNames() as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }

            $this->runPipeline($route, $request, $params);
            return;
        }

        if ($pathMatched) {
            throw new \RuntimeException(\sprintf('Method %s Not Allowed', $method), 405);
        }

        throw new \RuntimeException(\sprintf('Route not found: %s', $path), 404);
    }

    private function runPipeline(Route $route, Request $request, array $params): void
    {
        $middlewares = $route->getMiddlewares();
        $handler = fn() => $this->execute($route->getHandler(), $request, $params);

        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (callable $next, string $middlewareClass) use ($request): callable {
                return function () use ($request, $middlewareClass, $next): void {
                    $instance = new $middlewareClass();
                    $instance->handle($request, $next);
                };
            },
            $handler
        );

        $pipeline();
    }

    private function execute(callable|array $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $request, ...array_values($params));
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $method)) {
                    $controller->$method($request, ...array_values($params));
                    return;
                }
            }
        }

        throw new RuntimeException('Invalid route handler');
    }
}
