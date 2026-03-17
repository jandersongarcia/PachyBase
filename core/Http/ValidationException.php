<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<int, array<string, mixed>> $details
     */
    public function __construct(
        string $message = 'The request payload is invalid.',
        private readonly array $details = [],
        string $code = 'VALIDATION_ERROR'
    ) {
        parent::__construct($message, 422);
        $this->errorCode = $code;
    }

    private readonly string $errorCode;

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function details(): array
    {
        return $this->details;
    }
}
