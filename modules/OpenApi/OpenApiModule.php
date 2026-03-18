<?php

declare(strict_types=1);

namespace PachyBase\Modules\OpenApi;

use PachyBase\Api\Controllers\OpenApiController;
use PachyBase\Http\Router;

final class OpenApiModule
{
    public function register(Router $router): void
    {
        $router->get('/openapi.json', [OpenApiController::class, 'document']);
    }
}
