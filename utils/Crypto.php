<?php

declare(strict_types=1);

namespace PachyBase\Utils;

use PachyBase\Config;
use RuntimeException;

final class Crypto
{
    public static function encryptString(string $value): string
    {
        $key = self::encryptionKey();
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if (!is_string($ciphertext)) {
            throw new RuntimeException('Failed to encrypt the secret value.', 500);
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        return base64_encode($iv . $mac . $ciphertext);
    }

    public static function decryptString(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if (!is_string($decoded) || strlen($decoded) < 49) {
            throw new RuntimeException('The encrypted secret payload is invalid.', 500);
        }

        $key = self::encryptionKey();
        $iv = substr($decoded, 0, 16);
        $mac = substr($decoded, 16, 32);
        $ciphertext = substr($decoded, 48);
        $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $key, true);

        if (!hash_equals($expectedMac, $mac)) {
            throw new RuntimeException('The encrypted secret payload failed integrity verification.', 500);
        }

        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if (!is_string($plain)) {
            throw new RuntimeException('Failed to decrypt the secret value.', 500);
        }

        return $plain;
    }

    private static function encryptionKey(): string
    {
        $raw = trim((string) Config::get('APP_KEY', ''));

        if ($raw === '') {
            throw new RuntimeException('APP_KEY must be configured before managing project secrets.', 500);
        }

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);

            if (is_string($decoded) && $decoded !== '') {
                return hash('sha256', $decoded, true);
            }
        }

        return hash('sha256', $raw, true);
    }
}
