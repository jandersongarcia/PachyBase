<?php

declare(strict_types=1);

namespace PachyBase\Services\Observability;

final class RequestMetrics
{
    private static ?int $requestStartedAtNs = null;
    private static int $queryCount = 0;
    private static float $queryTimeMs = 0.0;
    private static int $introspectionCount = 0;
    private static float $introspectionTimeMs = 0.0;

    public static function start(?int $startedAtNs = null): void
    {
        self::$requestStartedAtNs = $startedAtNs ?? hrtime(true);
        self::$queryCount = 0;
        self::$queryTimeMs = 0.0;
        self::$introspectionCount = 0;
        self::$introspectionTimeMs = 0.0;
    }

    public static function reset(): void
    {
        self::$requestStartedAtNs = null;
        self::$queryCount = 0;
        self::$queryTimeMs = 0.0;
        self::$introspectionCount = 0;
        self::$introspectionTimeMs = 0.0;
    }

    public static function recordQuery(float $durationMs): void
    {
        self::ensureStarted();
        self::$queryCount++;
        self::$queryTimeMs += max(0.0, $durationMs);
    }

    public static function recordIntrospection(float $durationMs): void
    {
        self::ensureStarted();
        self::$introspectionCount++;
        self::$introspectionTimeMs += max(0.0, $durationMs);
    }

    /**
     * @return array{
     *   request_time_ms: float,
     *   query_time_ms: float,
     *   query_count: int,
     *   introspection_time_ms: float,
     *   introspection_count: int
     * }
     */
    public static function snapshot(?int $finishedAtNs = null): array
    {
        return [
            'request_time_ms' => self::rounded(self::requestTimeMs($finishedAtNs)),
            'query_time_ms' => self::rounded(self::$queryTimeMs),
            'query_count' => self::$queryCount,
            'introspection_time_ms' => self::rounded(self::$introspectionTimeMs),
            'introspection_count' => self::$introspectionCount,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function responseHeaders(?int $finishedAtNs = null): array
    {
        $snapshot = self::snapshot($finishedAtNs);

        return [
            'Server-Timing' => sprintf(
                'app;dur=%s, db;dur=%s;desc="queries:%d", introspection;dur=%s;desc="runs:%d"',
                self::headerValue($snapshot['request_time_ms']),
                self::headerValue($snapshot['query_time_ms']),
                $snapshot['query_count'],
                self::headerValue($snapshot['introspection_time_ms']),
                $snapshot['introspection_count']
            ),
            'X-Response-Time-Ms' => self::headerValue($snapshot['request_time_ms']),
            'X-Query-Time-Ms' => self::headerValue($snapshot['query_time_ms']),
            'X-Introspection-Time-Ms' => self::headerValue($snapshot['introspection_time_ms']),
        ];
    }

    private static function ensureStarted(): void
    {
        if (self::$requestStartedAtNs === null) {
            self::start();
        }
    }

    private static function requestTimeMs(?int $finishedAtNs = null): float
    {
        if (self::$requestStartedAtNs === null) {
            return 0.0;
        }

        $endedAtNs = $finishedAtNs ?? hrtime(true);

        return max(0.0, ($endedAtNs - self::$requestStartedAtNs) / 1_000_000);
    }

    private static function rounded(float $value): float
    {
        return round($value, 2);
    }

    private static function headerValue(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
