<?php

declare(strict_types=1);

namespace PachyBase\Modules\System;

use PachyBase\Api\Controllers\SystemController;
use PachyBase\Http\Router;

final class SystemModule
{
    public function register(Router $router): void
    {
        $router->get('/', [SystemController::class, 'status']);
    }
}
