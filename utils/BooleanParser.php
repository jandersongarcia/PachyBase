<?php

declare(strict_types=1);

namespace PachyBase\Utils;

final class BooleanParser
{
    public static function fromMixed(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }
}
