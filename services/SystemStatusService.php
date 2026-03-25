<?php

declare(strict_types=1);

namespace PachyBase\Services;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Http\Request;
use PachyBase\Release\ProjectMetadata;
use RuntimeException;

final class SystemStatusService
{
    public function buildStatusPayload(Request $request): array
    {
        $data = [
            'name' => Config::get('APP_NAME', 'PachyBase'),
            'status' => 'running',
            'version' => ProjectMetadata::version(),
        ];

        if (!Config::isProduction()) {
            $data['environment'] = Config::environment();
            $data['request'] = [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
            ];
        }

        return $data;
    }

    public function buildHealthPayload(Request $request, bool $includeDatabase = false): array
    {
        $data = [
            'name' => Config::get('APP_NAME', 'PachyBase'),
            'status' => 'ok',
            'version' => ProjectMetadata::version(),
            'checks' => [
                'application' => [
                    'status' => 'ok',
                ],
            ],
        ];

        if ($includeDatabase) {
            $database = $this->databaseHealth();
            $data['checks']['database'] = $database;

            if (($database['status'] ?? 'degraded') !== 'ok') {
                $data['status'] = 'degraded';
            }
        }

        if (!Config::isProduction()) {
            $data['environment'] = Config::environment();
            $data['request'] = [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseHealth(): array
    {
        $data = [
            'status' => 'degraded',
            'driver' => Config::get('DB_DRIVER'),
            'connected' => false,
            'adapter' => null,
        ];

        try {
            Connection::reset();
            $connection = Connection::getInstance();
            $connection->getPDO();
            $data['adapter'] = AdapterFactory::make($connection)::class;
            $data['connected'] = true;
            $data['status'] = 'ok';
        } catch (RuntimeException) {
            $data['connected'] = false;
        }

        return $data;
    }
}
