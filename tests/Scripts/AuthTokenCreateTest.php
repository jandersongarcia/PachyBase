<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/auth-token-create.php';

class AuthTokenCreateTest extends TestCase
{
    public function testParseArgumentsSupportsRepeatedScopesAndOptionalFields(): void
    {
        $options = authTokenCreateParseArguments([
            'Codex Agent',
            '--scope=crud:read',
            '--scope=entity:system-settings:read',
            '--expires-at=2026-12-31T23:59:59Z',
            '--user-email=agent@example.com',
            '--json',
        ]);

        $this->assertSame('Codex Agent', $options['name']);
        $this->assertSame(['crud:read', 'entity:system-settings:read'], $options['scopes']);
        $this->assertSame('2026-12-31 23:59:59', $options['expires_at']);
        $this->assertSame('agent@example.com', $options['user_email']);
        $this->assertTrue($options['json']);
    }

    public function testParseArgumentsAllowsWildcardScopeShortcut(): void
    {
        $options = authTokenCreateParseArguments([
            'Full Access Agent',
            '--all-scopes',
            '--scope=crud:read',
        ]);

        $this->assertSame(['*'], $options['scopes']);
    }

    public function testParseArgumentsRequiresAtLeastOneScope(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provide at least one scope');

        authTokenCreateParseArguments(['Codex Agent']);
    }

    public function testWriteHumanReportIncludesSingleUseTokenWarning(): void
    {
        $stream = fopen('php://temp', 'w+');
        authTokenCreateWriteHumanReport([
            'token_id' => 12,
            'name' => 'Codex Agent',
            'token' => 'pbt_example',
            'token_prefix' => 'pbt_example',
            'expires_at' => null,
            'scopes' => ['crud:read'],
            'subject' => [
                'type' => 'integration',
                'user_id' => null,
                'email' => null,
                'name' => null,
                'role' => null,
            ],
        ], $stream);
        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->assertIsString($output);
        $this->assertStringContainsString('Integration token created successfully.', $output);
        $this->assertStringContainsString('Store this token now.', $output);
        $this->assertStringContainsString('pbt_example', $output);
    }
}
