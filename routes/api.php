<?php

declare(strict_types=1);

use PachyBase\Http\Router;
use PachyBase\Modules\Auth\AuthModule;
use PachyBase\Modules\Crud\CrudModule;
use PachyBase\Modules\OpenApi\OpenApiModule;
use PachyBase\Modules\System\SystemModule;

return static function (Router $router): void {
    (new SystemModule())->register($router);
    (new OpenApiModule())->register($router);
    (new AuthModule())->register($router);
    (new CrudModule())->register($router);
};
