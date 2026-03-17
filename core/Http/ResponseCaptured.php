<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

final class ResponseCaptured extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly array $payload
    ) {
        parent::__construct('API response captured for testing.', $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
