<?php

declare(strict_types=1);

namespace PachyBase\Services\Mcp;

use RuntimeException;

final class HttpMcpBackendClient implements PachyBaseMcpBackendInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token = null
    ) {
    }

    public function getSchema(): array
    {
        return $this->request('GET', '/ai/schema');
    }

    public function listEntities(): array
    {
        return $this->request('GET', '/ai/entities');
    }

    public function describeEntity(string $entity): array
    {
        return $this->request('GET', '/ai/entity/' . rawurlencode($entity));
    }

    public function listRecords(string $entity, array $query): array
    {
        return $this->request('GET', '/api/' . rawurlencode($entity), null, $query);
    }

    public function getRecord(string $entity, string $id): array
    {
        return $this->request('GET', '/api/' . rawurlencode($entity) . '/' . rawurlencode($id));
    }

    public function createRecord(string $entity, array $payload): array
    {
        return $this->request('POST', '/api/' . rawurlencode($entity), $payload);
    }

    public function replaceRecord(string $entity, string $id, array $payload): array
    {
        return $this->request('PUT', '/api/' . rawurlencode($entity) . '/' . rawurlencode($id), $payload);
    }

    public function updateRecord(string $entity, string $id, array $payload): array
    {
        return $this->request('PATCH', '/api/' . rawurlencode($entity) . '/' . rawurlencode($id), $payload);
    }

    public function deleteRecord(string $entity, string $id): array
    {
        return $this->request('DELETE', '/api/' . rawurlencode($entity) . '/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: PachyBase-MCP/1.0',
        ];

        $content = null;
        if ($payload !== null) {
            $content = json_encode($payload, JSON_UNESCAPED_SLASHES);

            if ($content === false) {
                throw new RuntimeException('Failed to encode the JSON request body for the MCP adapter.');
            }

            $headers[] = 'Content-Type: application/json';
        }

        $token = trim((string) $this->token);
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header)
            ? $http_response_header
            : [];
        $statusCode = $this->statusCode($responseHeaders);

        if ($body === false) {
            throw new RuntimeException(sprintf('Failed to reach the PachyBase API at "%s".', $url));
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('The PachyBase API returned a non-JSON response for "%s %s".', $method, $path));
        }

        if ($statusCode >= 400) {
            $message = (string) ($decoded['error']['message'] ?? ('The PachyBase API returned HTTP ' . $statusCode . '.'));

            throw new RuntimeException($message, $statusCode);
        }

        return $decoded;
    }

    /**
     * @param array<int, string> $headers
     */
    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 200;
    }
}
