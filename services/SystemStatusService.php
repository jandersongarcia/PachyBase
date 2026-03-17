<?php

declare(strict_types=1);

namespace PachyBase\Services;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Http\Request;
use RuntimeException;

final class SystemStatusService
{
    public function buildStatusPayload(Request $request): array
    {
        $data = [
            'name' => Config::get('APP_NAME', 'PachyBase'),
            'status' => 'running',
            'version' => '1.0.0',
        ];

        if (!Config::isProduction()) {
            $data['environment'] = Config::environment();
            $data['database'] = [
                'driver' => Config::get('DB_DRIVER'),
                'connected' => false,
                'adapter' => null,
            ];

            try {
                Connection::reset();
                $connection = Connection::getInstance();
                $connection->getPDO();
                $data['database']['adapter'] = AdapterFactory::make($connection)::class;
                $data['database']['connected'] = true;
            } catch (RuntimeException) {
                $data['database']['connected'] = false;
            }

            $data['request'] = [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
            ];
        }

        return $data;
    }
}
