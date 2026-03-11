<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function get(string $path, callable|array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): self
    {
        $this->routes[] = new Route($method, $path, $handler);
        return $this;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        $methodMatched = false;

        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);

            if ($params !== null) {
                $this->execute($route->getHandler(), $request, $params);
                return;
            }
            
            if (preg_match($route->pattern, $path)) {
                $methodMatched = true;
            }
        }

        if ($methodMatched) {
            throw new \RuntimeException(\sprintf('Method %s Not Allowed', $method), 405);
        }

        throw new \RuntimeException(\sprintf('Route not found: %s', $path), 404);
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
