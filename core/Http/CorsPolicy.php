<?php

declare(strict_types=1);

namespace PachyBase\Http;

use PachyBase\Config;
use PachyBase\Utils\BooleanParser;

final readonly class CorsPolicy
{
    /**
     * @param array<int, string> $allowedOrigins
     * @param array<int, string> $allowedHeaders
     * @param array<int, string> $exposedHeaders
     */
    public function __construct(
        private array $allowedOrigins,
        private array $allowedHeaders,
        private array $exposedHeaders,
        private bool $allowCredentials,
        private int $maxAge
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            self::parseList((string) Config::get('APP_CORS_ALLOWED_ORIGINS', '')),
            self::parseList((string) Config::get('APP_CORS_ALLOWED_HEADERS', 'Authorization, Content-Type, X-Requested-With, X-Request-Id')),
            self::parseList((string) Config::get('APP_CORS_EXPOSED_HEADERS', 'X-Request-Id, Server-Timing, X-Response-Time-Ms, X-Query-Time-Ms, X-Introspection-Time-Ms')),
            BooleanParser::fromMixed(Config::get('APP_CORS_ALLOW_CREDENTIALS', false)),
            max(0, (int) Config::get('APP_CORS_MAX_AGE', 600))
        );
    }

    public function enabled(): bool
    {
        return $this->allowedOrigins !== [];
    }

    public function allowsOrigin(?string $origin): bool
    {
        $origin = trim((string) $origin);

        if (!$this->enabled() || $origin === '') {
            return false;
        }

        return $this->allowsAnyOrigin() || in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * @return array<string, string>
     */
    public function responseHeaders(?string $origin): array
    {
        $origin = trim((string) $origin);

        if (!$this->allowsOrigin($origin)) {
            return [];
        }

        $headers = [
            'Access-Control-Allow-Origin' => $this->resolvedOriginValue($origin),
        ];

        if (($headers['Access-Control-Allow-Origin'] ?? '') !== '*') {
            $headers['Vary'] = 'Origin';
        }

        if ($this->allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        if ($this->exposedHeaders !== []) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $this->exposedHeaders);
        }

        return $headers;
    }

    /**
     * @param array<int, string> $allowedMethods
     * @return array<string, string>
     */
    public function preflightHeaders(?string $origin, array $allowedMethods, ?string $requestedHeaders = null): array
    {
        $headers = $this->responseHeaders($origin);

        if ($headers === []) {
            return [];
        }

        $methods = [];
        foreach (array_merge($allowedMethods, ['OPTIONS']) as $method) {
            $method = strtoupper(trim((string) $method));

            if ($method === '' || in_array($method, $methods, true)) {
                continue;
            }

            $methods[] = $method;
        }

        if ($methods !== []) {
            $headers['Access-Control-Allow-Methods'] = implode(', ', $methods);
        }

        if ($this->allowedHeaders === ['*']) {
            $requestedHeaderList = self::normalizeRequestedHeaders($requestedHeaders);

            if ($requestedHeaderList !== '') {
                $headers['Access-Control-Allow-Headers'] = $requestedHeaderList;
            }
        } elseif ($this->allowedHeaders !== []) {
            $headers['Access-Control-Allow-Headers'] = implode(', ', $this->allowedHeaders);
        }

        $headers['Access-Control-Max-Age'] = (string) $this->maxAge;
        $headers['Vary'] = self::appendVary(
            $headers['Vary'] ?? '',
            ['Access-Control-Request-Method', 'Access-Control-Request-Headers']
        );

        return $headers;
    }

    private function allowsAnyOrigin(): bool
    {
        return $this->allowedOrigins === ['*'];
    }

    private function resolvedOriginValue(string $origin): string
    {
        if ($this->allowsAnyOrigin() && !$this->allowCredentials) {
            return '*';
        }

        return $origin;
    }

    /**
     * @param array<int, string> $values
     */
    private static function appendVary(string $currentValue, array $values): string
    {
        $items = self::parseList($currentValue);

        foreach ($values as $value) {
            if (!in_array($value, $items, true)) {
                $items[] = $value;
            }
        }

        return implode(', ', $items);
    }

    /**
     * @return array<int, string>
     */
    private static function parseList(string $value): array
    {
        $items = [];

        foreach (explode(',', $value) as $item) {
            $item = trim($item);

            if ($item === '' || in_array($item, $items, true)) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function normalizeRequestedHeaders(?string $requestedHeaders): string
    {
        return implode(', ', self::parseList((string) $requestedHeaders));
    }
}
