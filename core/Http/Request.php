<?php

declare(strict_types=1);

namespace PachyBase\Http;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private array $body;
    private array $attributes = [];

    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        array $body = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->body = $body;
    }

    public static function capture(
        ?array $server = null,
        ?array $query = null,
        ?array $post = null,
        ?array $headers = null,
        ?string $rawBody = null
    ): self
    {
        $server ??= $_SERVER;
        $query ??= $_GET;
        $post ??= $_POST;

        $method = $server['REQUEST_METHOD'] ?? 'GET';

        $uri = $server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers ??= function_exists('getallheaders') ? getallheaders() : [];
        if (empty($headers)) {
            foreach ($server as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }

            foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $name => $headerName) {
                if (isset($server[$name])) {
                    $headers[$headerName] = $server[$name];
                }
            }
        }

        $body = [];
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

        if (str_contains(strtolower((string) $contentType), 'application/json')) {
            $input = $rawBody ?? file_get_contents('php://input');
            if ($input) {
                try {
                    $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw new \RuntimeException('Invalid JSON request body.', 400);
                }

                if (!is_array($decoded)) {
                    throw new \RuntimeException('JSON request body must decode to an object or array.', 400);
                }

                $body = $decoded;
            }
        } else {
            $body = $post;
        }

        return new self($method, $path, $query, $headers, $body);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);
        foreach ($this->headers as $header => $value) {
            if (strtolower((string) $header) === $key) {
                return $value;
            }
        }
        return $default;
    }

    public function attribute(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->attributes;
        }

        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }
}
