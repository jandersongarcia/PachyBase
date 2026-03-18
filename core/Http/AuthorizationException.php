<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

final class AuthorizationException extends RuntimeException
{
    public function __construct(
        string $message = 'You do not have permission to access this resource.',
        private readonly string $errorCode = 'INSUFFICIENT_PERMISSIONS'
    ) {
        parent::__construct($message, 403);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
