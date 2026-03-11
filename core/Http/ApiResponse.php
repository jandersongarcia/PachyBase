<?php

declare(strict_types=1);

namespace PachyBase\Http;

final class ApiResponse
{
    private const CONTRACT_VERSION = '1.0';

    public static function success(mixed $data = null, array $meta = [], int $statusCode = 200): never
    {
        self::send([
            'success' => true,
            'data' => $data,
            'meta' => self::buildMeta($meta),
            'error' => null,
        ], $statusCode);
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
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if ($json === false) {
            http_response_code(500);

            echo json_encode([
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
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            exit;
        }

        echo $json;
        exit;
    }
}
