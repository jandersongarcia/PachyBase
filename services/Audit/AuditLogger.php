<?php

declare(strict_types=1);

namespace PachyBase\Services\Audit;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\AuthorizationException;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Observability\RequestMetrics;
use PachyBase\Utils\BooleanParser;
use PachyBase\Utils\Json;
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

        if (in_array($this->backend(), ['file', 'both'], true)) {
            $this->writeFileEntry($encoded);
        }

        if (in_array($this->backend(), ['database', 'both'], true)) {
            $this->writeDatabaseEntry($entry);
        }
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

    private function backend(): string
    {
        $backend = strtolower(trim((string) Config::get('APP_AUDIT_LOG_BACKEND', 'database')));

        return in_array($backend, ['database', 'file', 'both'], true) ? $backend : 'database';
    }

    private function writeFileEntry(string $encoded): void
    {
        $path = $this->logPath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            return;
        }

        file_put_contents($path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function writeDatabaseEntry(array $entry): void
    {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);
        $table = $adapter->quoteIdentifier('pb_audit_logs');
        $principal = is_array($entry['principal'] ?? null) ? $entry['principal'] : null;

        (new PdoQueryExecutor($connection->getPDO()))->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, category, event, level, outcome, request_id, method, path, status_code, resource, ip, user_agent, principal_json, metrics_json, context_json, error_json, occurred_at) VALUES (:tenant_id, :category, :event, :level, :outcome, :request_id, :method, :path, :status_code, :resource, :ip, :user_agent, :principal_json, :metrics_json, :context_json, :error_json, :occurred_at)',
                $table
            ),
            [
                'tenant_id' => isset($principal['tenant_id']) ? (int) $principal['tenant_id'] : ($entry['context']['tenant_id'] ?? null),
                'category' => $entry['category'] ?? null,
                'event' => $entry['event'] ?? null,
                'level' => $entry['level'] ?? null,
                'outcome' => $entry['outcome'] ?? null,
                'request_id' => $entry['request_id'] ?? null,
                'method' => $entry['method'] ?? null,
                'path' => $entry['path'] ?? null,
                'status_code' => $entry['status_code'] ?? null,
                'resource' => $entry['resource'] ?? null,
                'ip' => $entry['ip'] ?? null,
                'user_agent' => $entry['user_agent'] ?? null,
                'principal_json' => Json::encode($entry['principal'] ?? null),
                'metrics_json' => Json::encode($entry['metrics'] ?? null),
                'context_json' => Json::encode($entry['context'] ?? null),
                'error_json' => Json::encode($entry['error'] ?? null),
                'occurred_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
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
