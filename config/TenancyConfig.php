<?php

declare(strict_types=1);

namespace PachyBase\Config;

use PachyBase\Config;

final class TenancyConfig
{
    public static function headerName(): string
    {
        $header = trim((string) Config::get('TENANCY_HEADER', 'X-Tenant-Id'));

        return $header !== '' ? $header : 'X-Tenant-Id';
    }

    public static function defaultSlug(): string
    {
        $slug = strtolower(trim((string) Config::get('TENANCY_DEFAULT_SLUG', 'default')));

        return $slug !== '' ? $slug : 'default';
    }

    public static function defaultName(): string
    {
        $name = trim((string) Config::get('TENANCY_DEFAULT_NAME', 'Default Workspace'));

        return $name !== '' ? $name : 'Default Workspace';
    }
}
