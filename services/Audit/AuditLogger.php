<?php

declare(strict_types=1);

namespace PachyBase\Services\Audit;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Config;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\AuthorizationException;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Observability\RequestMetrics;
use PachyBase\Utils\BooleanParser;
use Throwable;

final class AuditLogger
{
    public function enabled(): bool
    {
        return BooleanParser::fromMixed(Config::get('APP_AUDIT_LOG_ENABLED', false));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        string $event,
        Request $request,
        array $context = [],
        ?int $statusCode = null,
        ?string $category = null,
        string $level = 'info',
        string $outcome = 'success'
    ): void
    {
        $this->writeEntry(
            $event,
            $request,
            $context,
            $statusCode,
            $category ?? $this->inferCategory($event, $request),
            $level,
            $outcome
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logAuth(
        string $event,
        Request $request,
        array $context = [],
        ?int $statusCode = null,
        string $outcome = 'success',
        string $level = 'info'
    ): void
    {
        $this->writeEntry($event, $request, $context, $statusCode, 'auth', $level, $outcome);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logCrud(
        string $event,
        Request $request,
        array $context = [],
        ?int $statusCode = null,
        string $outcome = 'success',
        string $level = 'info'
    ): void
    {
        $this->writeEntry($event, $request, $context, $statusCode, 'crud', $level, $outcome);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logException(Throwable $exception, Request $request, int $statusCode, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        $category = $this->categoryFromException($exception, $request);
        $event = $category . '.request.failed';
        $level = $statusCode >= 500 ? 'error' : 'warning';

        $this->writeEntry(
            $event,
            $request,
            array_replace([
                'exception' => $exception::class,
                'error_code' => $this->exceptionCode($exception),
            ], $context),
            $statusCode,
            $category,
            $level,
            'failure',
            [
                'class' => $exception::class,
                'code' => $this->exceptionCode($exception),
                'message' => $exception->getMessage(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $error
     */
    private function writeEntry(
        string $event,
        Request $request,
        array $context,
        ?int $statusCode,
        string $category,
        string $level,
        string $outcome,
        ?array $error = null
    ): void {
        if (!$this->enabled()) {
            return;
        }

        $path = $this->logPath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            return;
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'category' => $category,
            'event' => $event,
            'level' => $level,
            'outcome' => $outcome,
            'request_id' => ApiResponse::requestId(),
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'status_code' => $statusCode,
            'resource' => $context['resource'] ?? null,
            'ip' => $this->resolveClientIp(),
            'user_agent' => trim((string) $request->header('User-Agent', '')),
            'principal' => $this->principalPayload($request->attribute('auth.principal')),
            'metrics' => RequestMetrics::snapshot(),
            'context' => $context,
        ];

        if ($error !== null) {
            $entry['error'] = $error;
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function logPath(): string
    {
        $path = trim((string) Config::get('APP_AUDIT_LOG_PATH', 'build/logs/audit.jsonl'));

        if ($path === '') {
            $path = 'build/logs/audit.jsonl';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function principalPayload(mixed $principal): ?array
    {
        if (!$principal instanceof AuthPrincipal) {
            return null;
        }

        return $principal->toArray();
    }

    private function resolveClientIp(): string
    {
        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string) ($parts[0] ?? ''));

            if ($candidate !== '') {
                return $candidate;
            }
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return $remoteAddr !== '' ? $remoteAddr : 'unknown';
    }

    private function inferCategory(string $event, Request $request): string
    {
        if (str_starts_with($event, 'auth.')) {
            return 'auth';
        }

        if (str_starts_with($event, 'crud.')) {
            return 'crud';
        }

        $path = $request->getPath();

        if (str_starts_with($path, '/api/auth')) {
            return 'auth';
        }

        if (str_starts_with($path, '/api/')) {
            return 'crud';
        }

        return 'audit';
    }

    private function categoryFromException(Throwable $exception, Request $request): string
    {
        if ($exception instanceof AuthenticationException || $exception instanceof AuthorizationException) {
            return 'auth';
        }

        $inferred = $this->inferCategory('error.request.failed', $request);

        return in_array($inferred, ['auth', 'crud'], true) ? $inferred : 'error';
    }

    private function exceptionCode(Throwable $exception): string
    {
        if (method_exists($exception, 'errorCode')) {
            return (string) $exception->errorCode();
        }

        $code = $exception->getCode();

        return is_scalar($code) ? (string) $code : 'UNKNOWN';
    }
}
