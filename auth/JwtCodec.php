<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Config\AuthConfig;
use PachyBase\Http\AuthenticationException;
use PachyBase\Utils\Json;

final class JwtCodec
{
    /**
     * @param array<string, mixed> $claims
     */
    public function encode(array $claims): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode(Json::encode($header)),
            $this->base64UrlEncode(Json::encode($claims)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, AuthConfig::jwtSecret(), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new AuthenticationException('The JWT token is invalid.', 'INVALID_TOKEN');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = $this->decodeSegment($encodedHeader);
        $payload = $this->decodeSegment($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new AuthenticationException('The JWT token algorithm is invalid.', 'INVALID_TOKEN');
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $encodedHeader . '.' . $encodedPayload,
            AuthConfig::jwtSecret(),
            true
        );

        if (!hash_equals($expectedSignature, $signature)) {
            throw new AuthenticationException('The JWT token signature is invalid.', 'INVALID_TOKEN');
        }

        $now = time();
        $expiresAt = isset($payload['exp']) ? (int) $payload['exp'] : null;

        if ($expiresAt === null || $expiresAt < $now) {
            throw new AuthenticationException('The JWT token has expired.', 'TOKEN_EXPIRED');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new AuthenticationException('The JWT token is not yet valid.', 'INVALID_TOKEN');
        }

        if (($payload['iss'] ?? null) !== AuthConfig::issuer()) {
            throw new AuthenticationException('The JWT token issuer is invalid.', 'INVALID_TOKEN');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (!is_array($decoded)) {
            throw new AuthenticationException('The JWT token payload is invalid.', 'INVALID_TOKEN');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new AuthenticationException('The JWT token payload is invalid.', 'INVALID_TOKEN');
        }

        return $decoded;
    }
}
