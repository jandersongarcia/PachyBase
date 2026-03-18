<?php

declare(strict_types=1);

namespace PachyBase\Config;

use PachyBase\Api\HttpKernel;
use PachyBase\Http\ErrorHandler;

final class Bootstrap
{
    public static function boot(?string $basePath = null): HttpKernel
    {
        $basePath ??= dirname(__DIR__);

        AppConfig::load($basePath);
        ErrorHandler::register();

        return new HttpKernel($basePath);
    }
}
