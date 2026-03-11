<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';
$composePath = $rootPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';

main($argv, $rootPath, $envPath, $composePath);

function main(array $argv, string $rootPath, string $envPath, string $composePath): void
{
    $dryRun = in_array('--dry-run', $argv, true);

    ensureFileExists($envPath, '.env file not found.');
    ensureDirectoryExists(dirname($composePath), 'docker directory not found.');
    ensureCommandAvailable('docker --version', 'Docker');
    ensureCommandAvailable('docker compose version', 'Docker Compose');

    $config = validateDatabaseConfig(parseEnvFile($envPath));
    $compose = buildDockerCompose($config);

    file_put_contents($composePath, $compose);

    output('docker/docker-compose.yml generated successfully.');
    output(sprintf(
        'Database container configured for %s (%s).',
        $config['DB_DRIVER'],
        $config['DB_DATABASE']
    ));

    if ($dryRun) {
        output('Dry run enabled. Containers were not started.');
        return;
    }

    $command = sprintf(
        'docker compose -f %s up -d',
        escapeshellarg($composePath)
    );

    runCommand($command, 'Unable to start Docker containers.');

    output('PachyBase is running at http://localhost:8080');
}

function ensureFileExists(string $path, string $message): void
{
    if (!is_file($path)) {
        fail($message);
    }
}

function ensureDirectoryExists(string $path, string $message): void
{
    if (!is_dir($path)) {
        fail($message);
    }
}

function ensureCommandAvailable(string $command, string $label): void
{
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        fail($label . ' is required to run composer docker-install.');
    }
}

function parseEnvFile(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $values = [];

    if ($lines === false) {
        fail('Unable to read .env file.');
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $delimiter = strpos($trimmed, '=');
        if ($delimiter === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $delimiter));
        $value = trim(substr($trimmed, $delimiter + 1));

        if ($key === '') {
            continue;
        }

        $values[$key] = trim($value, "\"'");
    }

    return $values;
}

function validateDatabaseConfig(array $config): array
{
    $driver = strtolower(trim((string) ($config['DB_DRIVER'] ?? '')));

    $supportedDrivers = [
        'mysql' => [
            'port' => '3306',
            'host' => 'db',
            'image' => 'mysql:8',
            'volume_path' => '/var/lib/mysql',
        ],
        'pgsql' => [
            'port' => '5432',
            'host' => 'db',
            'image' => 'postgres:15',
            'volume_path' => '/var/lib/postgresql/data',
        ],
    ];

    if (!isset($supportedDrivers[$driver])) {
        fail('Unsupported DB_DRIVER. Use mysql or pgsql.');
    }

    $config['DB_DRIVER'] = $driver;
    $config['DB_HOST'] = trim((string) ($config['DB_HOST'] ?? $supportedDrivers[$driver]['host']));
    $config['DB_PORT'] = trim((string) ($config['DB_PORT'] ?? $supportedDrivers[$driver]['port']));
    $config['DB_DATABASE'] = trim((string) ($config['DB_DATABASE'] ?? ''));
    $config['DB_USERNAME'] = trim((string) ($config['DB_USERNAME'] ?? ''));
    $config['DB_PASSWORD'] = trim((string) ($config['DB_PASSWORD'] ?? ''));

    foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $field) {
        if ($config[$field] === '') {
            fail(sprintf('%s is required in the .env file.', $field));
        }
    }

    if (!ctype_digit($config['DB_PORT'])) {
        fail('DB_PORT must be a numeric value.');
    }

    if ((int) $config['DB_PORT'] < 1 || (int) $config['DB_PORT'] > 65535) {
        fail('DB_PORT must be between 1 and 65535.');
    }

    if ($config['DB_HOST'] !== $supportedDrivers[$driver]['host']) {
        fail(sprintf(
            'DB_HOST must be "%s" when using composer docker-install.',
            $supportedDrivers[$driver]['host']
        ));
    }

    if ($config['DB_PORT'] !== $supportedDrivers[$driver]['port']) {
        fail(sprintf(
            'DB_PORT must be %s for the %s Docker container.',
            $supportedDrivers[$driver]['port'],
            $driver
        ));
    }

    $config['DB_IMAGE'] = $supportedDrivers[$driver]['image'];
    $config['DB_VOLUME_PATH'] = $supportedDrivers[$driver]['volume_path'];

    return $config;
}

function buildDockerCompose(array $config): string
{
    $databaseService = buildDatabaseService($config);

    return implode(PHP_EOL, [
        'version: "3.9"',
        '',
        'services:',
        '  web:',
        '    image: nginx:latest',
        '    ports:',
        '      - "8080:80"',
        '    volumes:',
        '      - ../:/var/www/html',
        '      - ./nginx.conf:/etc/nginx/conf.d/default.conf',
        '    depends_on:',
        '      - php',
        '',
        '  php:',
        '    image: php:8.2-fpm',
        '    volumes:',
        '      - ../:/var/www/html',
        '    depends_on:',
        '      - db',
        '',
        '  db:',
        '    image: ' . $config['DB_IMAGE'],
        '    restart: unless-stopped',
        '    environment:',
        $databaseService['environment'],
        '    ports:',
        '      - "' . $config['DB_PORT'] . ':' . $config['DB_PORT'] . '"',
        '    volumes:',
        '      - db_data:' . $config['DB_VOLUME_PATH'],
        '',
        'volumes:',
        '  db_data:',
        '',
    ]);
}

function buildDatabaseService(array $config): array
{
    if ($config['DB_DRIVER'] === 'mysql') {
        $environment = [
            '      MYSQL_ROOT_PASSWORD: ' . yamlScalar($config['DB_PASSWORD']),
            '      MYSQL_DATABASE: ' . yamlScalar($config['DB_DATABASE']),
        ];

        if (strtolower($config['DB_USERNAME']) !== 'root') {
            $environment[] = '      MYSQL_USER: ' . yamlScalar($config['DB_USERNAME']);
            $environment[] = '      MYSQL_PASSWORD: ' . yamlScalar($config['DB_PASSWORD']);
        }

        return ['environment' => implode(PHP_EOL, $environment)];
    }

    return [
        'environment' => implode(PHP_EOL, [
            '      POSTGRES_DB: ' . yamlScalar($config['DB_DATABASE']),
            '      POSTGRES_USER: ' . yamlScalar($config['DB_USERNAME']),
            '      POSTGRES_PASSWORD: ' . yamlScalar($config['DB_PASSWORD']),
        ]),
    ];
}

function yamlScalar(string $value): string
{
    return '"' . addcslashes($value, "\\\"") . '"';
}

function runCommand(string $command, string $errorMessage): void
{
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        fail($errorMessage);
    }
}

function output(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
