<?php

declare(strict_types=1);

namespace PachyBase\Auth;

final readonly class AuthPrincipal
{
    /**
     * @param array<int, string> $scopes
     */
    public function __construct(
        public string $provider,
        public string $subjectType,
        public string|int $subjectId,
        public ?int $userId,
        public array $scopes,
        public ?int $tenantId = null,
        public ?string $tenantSlug = null,
        public ?int $sessionId = null,
        public ?int $tokenId = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $role = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'tenant_slug' => $this->tenantSlug,
            'session_id' => $this->sessionId,
            'token_id' => $this->tokenId,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'scopes' => $this->scopes,
        ];
    }
}
