<?php

declare(strict_types=1);

namespace PachyBase\Http;

use PachyBase\Config;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);

        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $exception): void {
            self::renderException($exception);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($error['type'], $fatalErrors, true)) {
                return;
            }

            ApiResponse::error(
                'FATAL_ERROR',
                'A fatal error interrupted the request.',
                500,
                self::debugDetails(
                    $error['message'],
                    $error['file'],
                    $error['line']
                ),
                [],
                'server_error'
            );
        });
    }

    public static function renderException(Throwable $exception): never
    {
        ApiResponse::error(
            'INTERNAL_SERVER_ERROR',
            self::userMessage(),
            500,
            self::debugDetails(
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception::class
            ),
            [],
            'server_error'
        );
    }

    private static function userMessage(): string
    {
        return self::debugEnabled()
            ? 'The request failed with an internal exception.'
            : 'An unexpected internal error occurred.';
    }

    private static function debugDetails(
        string $message,
        string $file,
        int $line,
        ?string $exceptionClass = null
    ): array {
        if (!self::debugEnabled()) {
            return [];
        }

        $details = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ];

        if ($exceptionClass !== null) {
            $details['exception'] = $exceptionClass;
        }

        return [$details];
    }

    private static function debugEnabled(): bool
    {
        $value = Config::get('APP_DEBUG', false);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
