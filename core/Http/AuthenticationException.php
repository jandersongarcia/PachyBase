<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

final class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message = 'Authentication is required to access this resource.',
        private readonly string $errorCode = 'AUTHENTICATION_REQUIRED'
    ) {
        parent::__construct($message, 401);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
