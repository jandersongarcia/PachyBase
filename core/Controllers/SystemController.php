<?php

declare(strict_types=1);

namespace PachyBase\Controllers;

use PachyBase\Config;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;

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
                'host' => Config::get('DB_HOST'),
                'port' => Config::get('DB_PORT'),
                'database' => Config::get('DB_DATABASE'),
            ];

            try {
                $dsn = sprintf(
                    '%s:host=%s;port=%s;dbname=%s',
                    Config::get('DB_DRIVER', 'mysql'),
                    Config::get('DB_HOST', '127.0.0.1'),
                    Config::get('DB_PORT', '3306'),
                    Config::get('DB_DATABASE', 'pachybase')
                );
                new \PDO($dsn, Config::get('DB_USERNAME', 'root'), Config::get('DB_PASSWORD', ''));
                $data['database']['connected'] = true;
            } catch (\Exception $e) {
                $data['database']['connected'] = false;
                $data['database']['error'] = $e->getMessage();
            }
            // Test accessing the request via the new class
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
