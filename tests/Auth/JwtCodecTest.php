<?php

declare(strict_types=1);

namespace Tests\Auth;

use PachyBase\Config;
use PachyBase\Http\AuthenticationException;
use PachyBase\Auth\JwtCodec;
use PHPUnit\Framework\TestCase;

class JwtCodecTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testEncodesAndDecodesJwtPayload(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'AUTH_JWT_SECRET' => 'test-secret',
            'AUTH_JWT_ISSUER' => 'PachyBase Tests',
        ]);

        $codec = new JwtCodec();
        $token = $codec->encode([
            'iss' => 'PachyBase Tests',
            'typ' => 'access',
            'uid' => 9,
            'exp' => time() + 300,
        ]);
        $payload = $codec->decode($token);

        $this->assertSame(9, $payload['uid']);
        $this->assertSame('access', $payload['typ']);
    }

    public function testRejectsExpiredJwt(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'AUTH_JWT_SECRET' => 'test-secret',
            'AUTH_JWT_ISSUER' => 'PachyBase Tests',
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The JWT token has expired.');

        (new JwtCodec())->decode((new JwtCodec())->encode([
            'iss' => 'PachyBase Tests',
            'typ' => 'access',
            'uid' => 9,
            'exp' => time() - 10,
        ]));
    }
}
