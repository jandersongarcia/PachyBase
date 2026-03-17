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
                self::errorMessage('A fatal error interrupted the request.'),
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
        if ($exception instanceof ValidationException) {
            ApiResponse::validationError(
                $exception->details(),
                self::publicMessage($exception->getMessage(), 422),
                $exception->errorCode()
            );
        }

        if ($exception instanceof AuthenticationException) {
            ApiResponse::authenticationError(
                self::publicMessage($exception->getMessage(), 401),
                $exception->errorCode()
            );
        }

        if ($exception instanceof AuthorizationException) {
            ApiResponse::authorizationError(
                self::publicMessage($exception->getMessage(), 403),
                $exception->errorCode()
            );
        }

        $code = $exception->getCode();
        $statusCode = (is_int($code) && $code >= 100 && $code <= 599) ? $code : 500;

        $errorCode = match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            408 => 'REQUEST_TIMEOUT',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            default => 'INTERNAL_SERVER_ERROR',
        };

        $type = match ($statusCode) {
            401 => 'authentication_error',
            403 => 'authorization_error',
            422 => 'validation_error',
            default => $statusCode >= 500 ? 'server_error' : 'application_error',
        };

        ApiResponse::error(
            $errorCode,
            self::publicMessage($exception->getMessage(), $statusCode),
            $statusCode,
            self::debugDetails(
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception::class,
                $exception->getTrace()
            ),
            [],
            $type
        );
    }

    private static function errorMessage(string $developmentMessage): string
    {
        return self::debugEnabled()
            ? $developmentMessage
            : 'An unexpected internal error occurred.';
    }

    private static function publicMessage(string $developmentMessage, int $statusCode): string
    {
        if ($statusCode >= 500) {
            return self::errorMessage($developmentMessage);
        }

        if (trim($developmentMessage) === '') {
            return ApiResponse::defaultMessageForStatus($statusCode);
        }

        return $developmentMessage;
    }

    private static function debugDetails(
        string $message,
        string $file,
        int $line,
        ?string $exceptionClass = null,
        array $trace = []
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

        if ($trace !== []) {
            $details['trace'] = $trace;
        }

        return [$details];
    }

    private static function debugEnabled(): bool
    {
        return Config::debugEnabled();
    }
}
