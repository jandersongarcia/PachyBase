<?php

declare(strict_types=1);

namespace PachyBase\Services\Observability;

use PachyBase\Http\Request;

final class RequestContext
{
    private static ?Request $current = null;

    public static function set(Request $request): void
    {
        self::$current = $request;
    }

    public static function current(): ?Request
    {
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
