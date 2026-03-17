<?php

declare(strict_types=1);

namespace PachyBase\Utils;

use JsonException;
use RuntimeException;

final class Json
{
    public static function encode(mixed $value): string
    {
        try {
            return (string) \json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode JSON.', 500, $exception);
        }
    }
}
