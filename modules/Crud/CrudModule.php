<?php

declare(strict_types=1);

namespace PachyBase\Modules\Crud;

use PachyBase\Api\Controllers\CrudController;
use PachyBase\Auth\Middleware\RequireBearerToken;
use PachyBase\Http\Router;

final class CrudModule
{
    public function register(Router $router): void
    {
        $router->get('/api/{entity}', [CrudController::class, 'index'])->middleware([RequireBearerToken::class]);
        $router->get('/api/{entity}/{id}', [CrudController::class, 'show'])->middleware([RequireBearerToken::class]);
        $router->post('/api/{entity}', [CrudController::class, 'store'])->middleware([RequireBearerToken::class]);
        $router->put('/api/{entity}/{id}', [CrudController::class, 'replace'])->middleware([RequireBearerToken::class]);
        $router->patch('/api/{entity}/{id}', [CrudController::class, 'update'])->middleware([RequireBearerToken::class]);
        $router->delete('/api/{entity}/{id}', [CrudController::class, 'destroy'])->middleware([RequireBearerToken::class]);
    }
}
