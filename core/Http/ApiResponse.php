<?php

declare(strict_types=1);

namespace PachyBase\Http;

use PachyBase\Services\Observability\RequestMetrics;

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
    private static array $capturedHeaders = [];

    public static function enableCapture(): void
    {
        self::$captureEnabled = true;
        self::$capturedPayload = null;
        self::$capturedStatusCode = null;
        self::$capturedHeaders = [];
    }

    public static function disableCapture(): void
    {
        self::$captureEnabled = false;
        self::$capturedPayload = null;
        self::$capturedStatusCode = null;
        self::$capturedHeaders = [];
    }

    public static function captured(): array
    {
        return [
            'status_code' => self::$capturedStatusCode,
            'payload' => self::$capturedPayload,
            'headers' => self::$capturedHeaders,
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

    public static function document(array $document, int $statusCode = 200): never
    {
        self::sendDocument($document, $statusCode);
    }

    /**
     * @param array<int, string> $allowedMethods
     */
    public static function preflight(array $allowedMethods, array $meta = []): never
    {
        self::send(
            [
                'success' => true,
                'data' => [
                    'preflight' => true,
                    'allowed_methods' => array_values($allowedMethods),
                ],
                'meta' => self::buildMeta(array_replace([
                    'resource' => 'cors.preflight',
                ], $meta)),
                'error' => null,
            ],
            200,
            self::preflightHeaders($allowedMethods)
        );
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

    public static function requestId(): string
    {
        return self::resolveRequestId();
    }

    private static function buildMeta(array $meta): array
    {
        return array_replace([
            'contract_version' => self::CONTRACT_VERSION,
            'request_id' => self::requestId(),
            'timestamp' => gmdate('c'),
            'path' => self::resolvePath(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        ], $meta);
    }

    private static function resolveRequestId(): string
    {
        $cachedRequestId = $_SERVER['PACHYBASE_REQUEST_ID'] ?? '';
        if (is_string($cachedRequestId) && trim($cachedRequestId) !== '') {
            return trim($cachedRequestId);
        }

        $headerRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        if (is_string($headerRequestId) && trim($headerRequestId) !== '') {
            $_SERVER['PACHYBASE_REQUEST_ID'] = trim($headerRequestId);

            return $_SERVER['PACHYBASE_REQUEST_ID'];
        }

        try {
            $_SERVER['PACHYBASE_REQUEST_ID'] = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $_SERVER['PACHYBASE_REQUEST_ID'] = uniqid('req_', true);
        }

        return $_SERVER['PACHYBASE_REQUEST_ID'];
    }

    private static function resolvePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private static function send(array $payload, int $statusCode, array $headers = []): never
    {
        $headers = self::mergeHeaders(self::defaultHeaders(), self::corsHeaders(), $headers);

        if (self::$captureEnabled) {
            self::$capturedStatusCode = $statusCode;
            self::$capturedPayload = $payload;
            self::$capturedHeaders = $headers;

            throw new ResponseCaptured($statusCode, $payload, $headers);
        }

        http_response_code($statusCode);
        self::emitHeaders($headers);

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
                self::$capturedHeaders = $headers;

                throw new ResponseCaptured(500, $fallbackPayload, $headers);
            }

            http_response_code(500);
            echo json_encode($fallbackPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            exit;
        }

        echo $json;
        exit;
    }

    private static function sendDocument(array $document, int $statusCode, array $headers = []): never
    {
        $headers = self::mergeHeaders(self::defaultHeaders(), self::corsHeaders(), $headers);

        if (self::$captureEnabled) {
            self::$capturedStatusCode = $statusCode;
            self::$capturedPayload = $document;
            self::$capturedHeaders = $headers;

            throw new ResponseCaptured($statusCode, $document, $headers);
        }

        http_response_code($statusCode);
        self::emitHeaders($headers);

        $json = json_encode(
            $document,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if ($json === false) {
            self::error(
                'RESPONSE_ENCODING_ERROR',
                'The API response could not be encoded as JSON.',
                500,
                [],
                [],
                'server_error'
            );
        }

        echo $json;
        exit;
    }

    /**
     * @return array<string, string>
     */
    private static function defaultHeaders(): array
    {
        return array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Request-Id' => self::requestId(),
        ], RequestMetrics::responseHeaders());
    }

    /**
     * @return array<string, string>
     */
    private static function corsHeaders(): array
    {
        return CorsPolicy::fromConfig()->responseHeaders($_SERVER['HTTP_ORIGIN'] ?? null);
    }

    /**
     * @param array<int, string> $allowedMethods
     * @return array<string, string>
     */
    private static function preflightHeaders(array $allowedMethods): array
    {
        return CorsPolicy::fromConfig()->preflightHeaders(
            $_SERVER['HTTP_ORIGIN'] ?? null,
            $allowedMethods,
            $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? null
        );
    }

    /**
     * @param array<string, string> ...$headerSets
     * @return array<string, string>
     */
    private static function mergeHeaders(array ...$headerSets): array
    {
        $merged = [];

        foreach ($headerSets as $headers) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Vary') === 0 && isset($merged[$name])) {
                    $merged[$name] = self::mergeVaryHeader($merged[$name], $value);
                    continue;
                }

                $merged[$name] = $value;
            }
        }

        return $merged;
    }

    private static function mergeVaryHeader(string $current, string $next): string
    {
        $values = [];

        foreach ([$current, $next] as $headerValue) {
            foreach (explode(',', $headerValue) as $item) {
                $item = trim($item);

                if ($item === '' || in_array($item, $values, true)) {
                    continue;
                }

                $values[] = $item;
            }
        }

        return implode(', ', $values);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function emitHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }
    }
}
