<?php

declare(strict_types=1);

namespace PachyBase\Http;

class Route
{
    public readonly string $method;
    public readonly string $path;
    /** @var callable|array */
    private $handler;
    public readonly string $pattern;
    private array $paramNames = [];
    /** @var string[] Middleware class names */
    private array $middlewares = [];


    public function __construct(string $method, string $path, callable|array $handler)
    {
        $this->method = strtoupper($method);
        $this->path = $path === '' ? '/' : $path;
        $this->handler = $handler;
        $this->compilePattern();
    }

    private function compilePattern(): void
    {
        $pattern = preg_replace_callback(
            '/{([a-zA-Z0-9_]+)}/',
            function ($matches) {
                $this->paramNames[] = $matches[1];
                return '([^/]+)';
            },
            $this->path
        );

        $this->pattern = '#^' . $pattern . '$#';
    }

    public function match(string $method, string $path): ?array
    {
        if ($this->method !== $method) {
            return null;
        }

        if (preg_match($this->pattern, $path, $matches)) {
            array_shift($matches); // Remove the full match

            $params = [];
            foreach ($this->paramNames as $index => $name) {
                $params[$name] = $matches[$index] ?? null;
            }

            return $params;
        }

        return null;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    public function getParamNames(): array
    {
        return $this->paramNames;
    }

    /**
     * Attach middleware classes to this route.
     * Each middleware must implement: handle(Request $request, callable $next): void
     *
     * @param string[] $middlewares
     */
    public function middleware(array $middlewares): static
    {
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    /** @return string[] */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}

