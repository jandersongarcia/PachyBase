<?php

declare(strict_types=1);

namespace PachyBase\Modules\Ai;

use PachyBase\Api\Controllers\AiController;
use PachyBase\Http\Router;

final class AiModule
{
    public function register(Router $router): void
    {
        $router->get('/ai/schema', [AiController::class, 'schema']);
        $router->get('/ai-schema.json', [AiController::class, 'schemaFile']);
        $router->get('/ai/entities', [AiController::class, 'entities']);
        $router->get('/ai/entity/{name}', [AiController::class, 'entity']);
    }
}
