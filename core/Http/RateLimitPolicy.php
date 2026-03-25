<?php

declare(strict_types=1);

namespace PachyBase\Http;

use PachyBase\Config;
use PachyBase\Utils\BooleanParser;

final readonly class RateLimitPolicy
{
    public function __construct(
        private bool $enabled,
        private int $maxRequests,
        private int $windowSeconds,
        private string $storagePath
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            BooleanParser::fromMixed(Config::get('APP_RATE_LIMIT_ENABLED', false)),
            max(1, (int) Config::get('APP_RATE_LIMIT_MAX_REQUESTS', 120)),
            max(1, (int) Config::get('APP_RATE_LIMIT_WINDOW_SECONDS', 60)),
            self::resolveStoragePath((string) Config::get('APP_RATE_LIMIT_STORAGE_PATH', 'build/runtime/rate-limit.json'))
        );
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function maxRequests(): int
    {
        return $this->maxRequests;
    }

    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    private static function resolveStoragePath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            $trimmed = 'build/runtime/rate-limit.json';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmed) === 1 || str_starts_with($trimmed, '/') || str_starts_with($trimmed, '\\')) {
            return $trimmed;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed);
    }
}
