<?php

declare(strict_types=1);

use PachyBase\Modules\Ai\AiModule;
use PachyBase\Http\Router;
use PachyBase\Modules\Auth\AuthModule;
use PachyBase\Modules\Crud\CrudModule;
use PachyBase\Modules\OpenApi\OpenApiModule;
use PachyBase\Modules\Platform\PlatformModule;
use PachyBase\Modules\System\SystemModule;

return static function (Router $router): void {
    (new SystemModule())->register($router);
    (new OpenApiModule())->register($router);
    (new AiModule())->register($router);
    (new AuthModule())->register($router);
    (new CrudModule())->register($router);
    (new PlatformModule())->register($router);
};
