<?php

declare(strict_types=1);

namespace PachyBase\Http;

final class ApiResponse
{
    private const CONTRACT_VERSION = '1.0';
    private const DEFAULT_ERROR_MESSAGES = [
        400 => 'The request could not be processed.',
        401 => 'Authentication is required to access this resource.',
        403 => 'You do not have permission to access this resource.',
        404 => 'The requested resource was not found.',
        405 => 'The HTTP method is not allowed for this resource.',
        408 => 'The request timed out before completion.',
        409 => 'The request could not be completed due to a conflict.',
        422 => 'The request payload is invalid.',
        429 => 'Too many requests were sent in a short period.',
        500 => 'An unexpected internal error occurred.',
    ];
    private static bool $captureEnabled = false;
    private static ?array $capturedPayload = null;
    private static ?int $capturedStatusCode = null;

    public static function enableCapture(): void
    {
        self::$captureEnabled = true;
        self::$capturedPayload = null;
        self::$capturedStatusCode = null;
    }

    public static function disableCapture(): void
    {
        self::$captureEnabled = false;
        self::$capturedPayload = null;
        self::$capturedStatusCode = null;
    }

    public static function captured(): array
    {
        return [
            'status_code' => self::$capturedStatusCode,
            'payload' => self::$capturedPayload,
        ];
    }

    public static function success(mixed $data = null, array $meta = [], int $statusCode = 200): never
    {
        self::send([
            'success' => true,
            'data' => $data,
            'meta' => self::buildMeta($meta),
            'error' => null,
        ], $statusCode);
    }

    /**
     * Return a paginated list response.
     *
     * @param array<mixed> $items     The current page items.
     * @param int          $total     Total number of records across all pages.
     * @param int          $page      Current page number (1-indexed).
     * @param int          $perPage   Number of items per page.
     * @param array        $meta      Additional meta fields to merge.
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage,
        array $meta = []
    ): never {
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        self::send([
            'success' => true,
            'data' => $items,
            'meta' => self::buildMeta(array_merge($meta, [
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'from'         => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
                    'to'           => $total > 0 ? min($page * $perPage, $total) : null,
                ],
            ])),
            'error' => null,
        ], 200);
    }

    public static function error(
        string $code,
        string $message,
        int $statusCode,
        array $details = [],
        array $meta = [],
        string $type = 'application_error'
    ): never {
        self::send([
            'success' => false,
            'data' => null,
            'meta' => self::buildMeta($meta),
            'error' => [
                'code' => $code,
                'type' => $type,
                'message' => $message,
                'details' => array_values($details),
            ],
        ], $statusCode);
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    public static function validationError(
        array $details,
        string $message = 'The request payload is invalid.',
        string $code = 'VALIDATION_ERROR',
        array $meta = []
    ): never {
        self::error($code, $message, 422, $details, $meta, 'validation_error');
    }

    public static function authenticationError(
        string $message = 'Authentication is required to access this resource.',
        string $code = 'AUTHENTICATION_REQUIRED',
        array $details = [],
        array $meta = []
    ): never {
        self::error($code, $message, 401, $details, $meta, 'authentication_error');
    }

    public static function authorizationError(
        string $message = 'You do not have permission to access this resource.',
        string $code = 'INSUFFICIENT_PERMISSIONS',
        array $details = [],
        array $meta = []
    ): never {
        self::error($code, $message, 403, $details, $meta, 'authorization_error');
    }

    public static function defaultMessageForStatus(int $statusCode): string
    {
        return self::DEFAULT_ERROR_MESSAGES[$statusCode] ?? self::DEFAULT_ERROR_MESSAGES[500];
    }

    private static function buildMeta(array $meta): array
    {
        return array_replace([
            'contract_version' => self::CONTRACT_VERSION,
            'request_id' => self::resolveRequestId(),
            'timestamp' => gmdate('c'),
            'path' => self::resolvePath(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        ], $meta);
    }

    private static function resolveRequestId(): string
    {
        $headerRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if (is_string($headerRequestId) && trim($headerRequestId) !== '') {
            return trim($headerRequestId);
        }

        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('req_', true);
        }
    }

    private static function resolvePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private static function send(array $payload, int $statusCode): never
    {
        if (self::$captureEnabled) {
            self::$capturedStatusCode = $statusCode;
            self::$capturedPayload = $payload;

            throw new ResponseCaptured($statusCode, $payload);
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if ($json === false) {
            $fallbackPayload = [
                'success' => false,
                'data' => null,
                'meta' => [
                    'contract_version' => self::CONTRACT_VERSION,
                    'request_id' => self::resolveRequestId(),
                    'timestamp' => gmdate('c'),
                    'path' => self::resolvePath(),
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                ],
                'error' => [
                    'code' => 'RESPONSE_ENCODING_ERROR',
                    'type' => 'server_error',
                    'message' => 'The API response could not be encoded as JSON.',
                    'details' => [],
                ],
            ];

            if (self::$captureEnabled) {
                self::$capturedStatusCode = 500;
                self::$capturedPayload = $fallbackPayload;

                throw new ResponseCaptured(500, $fallbackPayload);
            }

            http_response_code(500);
            echo json_encode($fallbackPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            exit;
        }

        echo $json;
        exit;
    }
}
