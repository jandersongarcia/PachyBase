<?php

declare(strict_types=1);

namespace PachyBase\Controllers;

use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use RuntimeException;

class SystemController
{
    public function status(Request $request): void
    {
        $data = [
            'name' => Config::get('APP_NAME', 'PachyBase'),
            'status' => 'running',
            'version' => '1.0.0', // Arbitrary MVP version
        ];

        if (!Config::isProduction()) {
            $data['environment'] = Config::environment();
            $data['database'] = [
                'driver' => Config::get('DB_DRIVER'),
                'connected' => false,
            ];

            try {
                Connection::reset();
                Connection::getInstance()->getPDO();
                $data['database']['connected'] = true;
            } catch (RuntimeException) {
                $data['database']['connected'] = false;
            }

            $data['request'] = [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
            ];
        }

        ApiResponse::success($data, [
            'resource' => 'system.status',
        ]);
    }
}
