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

    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (empty($headers)) {
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }

        $body = [];
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        
        if (str_contains(strtolower((string) $contentType), 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        } else {
            $body = $_POST;
        }

        return new self($method, $path, $_GET, $headers, $body);
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
}
