<?php

declare(strict_types=1);

namespace PachyBase\Release;

final class ProjectMetadata
{
    /**
     * @var array<string, string>
     */
    private static array $versionCache = [];

    public static function version(?string $basePath = null): string
    {
        $versionPath = self::versionPath($basePath);

        if (isset(self::$versionCache[$versionPath])) {
            return self::$versionCache[$versionPath];
        }

        if (!is_file($versionPath)) {
            return self::$versionCache[$versionPath] = '0.0.0-dev';
        }

        $version = trim((string) file_get_contents($versionPath));

        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version)) {
            return self::$versionCache[$versionPath] = '0.0.0-dev';
        }

        return self::$versionCache[$versionPath] = $version;
    }

    public static function reset(): void
    {
        self::$versionCache = [];
    }

    private static function versionPath(?string $basePath = null): string
    {
        $basePath ??= dirname(__DIR__, 2);

        return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'VERSION';
    }
}
