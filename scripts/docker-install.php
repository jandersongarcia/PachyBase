<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';
$composePath = $rootPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';
const DOCKER_COMPOSE_EOL = "\n";

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    main($argv, $rootPath, $envPath, $composePath);
}

function main(array $argv, string $rootPath, string $envPath, string $composePath): void
{
    $writeOnly = in_array('--write-only', $argv, true) || in_array('--dry-run', $argv, true);

    ensureFileExists($envPath, '.env file not found.');
    ensureDirectoryExists(dirname($composePath), 'docker directory not found.');
    ensureCommandAvailable('docker --version', 'Docker');
    ensureCommandAvailable('docker compose version', 'Docker Compose');

    $config = validateDatabaseConfig(parseEnvFile($envPath));
    $compose = buildDockerCompose($config);
    $writeResult = writeDockerComposeFile($composePath, $compose);

    output(sprintf('docker/docker-compose.yml %s successfully.', $writeResult['status']));
    output(sprintf(
        'Database container configured for %s (%s).',
        $config['DB_DRIVER'],
        $config['DB_DATABASE']
    ));

    if ($writeOnly) {
        output('Compose sync completed. Containers were not started.');
        return;
    }

    $command = sprintf(
        'docker compose -f %s up -d',
        escapeshellarg($composePath)
    );

    runCommand($command, 'Unable to start Docker containers.');
    bootstrapDatabase($composePath);

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
        fail($label . ' is required to generate the Docker runtime configuration.');
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
            'DB_HOST must be "%s" when generating the Docker runtime configuration.',
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
    $databaseVolume = databaseVolumeName($config);

    return implode(DOCKER_COMPOSE_EOL, [
        'services:',
        '  web:',
        '    image: nginx:1.27-alpine',
        '    ports:',
        '      - "8080:80"',
        '    volumes:',
        '      - ../:/var/www/html',
        '      - ./nginx.conf:/etc/nginx/conf.d/default.conf',
        '    depends_on:',
        '      - php',
        '',
        '  php:',
        '    build:',
        '      context: ..',
        '      dockerfile: docker/Dockerfile',
        '    working_dir: /var/www/html',
        '    volumes:',
        '      - ../:/var/www/html',
        '    depends_on:',
        '      - db',
        '',
        '  db:',
        '    image: ' . $config['DB_IMAGE'],
        '    restart: unless-stopped',
        '    ports:',
        '      - "' . $config['DB_PORT'] . ':' . $config['DB_PORT'] . '"',
        '    environment:',
        $databaseService['environment'],
        '    volumes:',
        '      - ' . $databaseVolume . ':' . $config['DB_VOLUME_PATH'],
        '',
        'volumes:',
        '  ' . $databaseVolume . ':',
        '',
    ]);
}

/**
 * @return array{path: string, status: string}
 */
function writeDockerComposeFile(string $composePath, string $compose): array
{
    $normalizedCompose = str_replace(["\r\n", "\r"], DOCKER_COMPOSE_EOL, $compose);
    $normalizedCompose = rtrim($normalizedCompose, DOCKER_COMPOSE_EOL) . DOCKER_COMPOSE_EOL;
    $existing = is_file($composePath)
        ? str_replace(["\r\n", "\r"], DOCKER_COMPOSE_EOL, (string) file_get_contents($composePath))
        : null;

    if ($existing === $normalizedCompose) {
        return [
            'path' => $composePath,
            'status' => 'already synchronized',
        ];
    }

    file_put_contents($composePath, $normalizedCompose);

    return [
        'path' => $composePath,
        'status' => is_file($composePath) && $existing !== null ? 'synchronized' : 'generated',
    ];
}

function buildDatabaseService(array $config): array
{
    if ($config['DB_DRIVER'] === 'mysql') {
        $environment = [
            '      MYSQL_ROOT_PASSWORD: ' . yamlScalar(rootPassword($config)),
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

function rootPassword(array $config): string
{
    return $config['DB_PASSWORD'];
}

function databaseVolumeName(array $config): string
{
    return sprintf('db_%s_data', $config['DB_DRIVER']);
}

function runCommand(string $command, string $errorMessage): void
{
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        fail($errorMessage);
    }
}

function bootstrapDatabase(string $composePath): void
{
    $command = sprintf(
        'docker compose -f %s exec -T php php scripts/bootstrap-database.php',
        escapeshellarg($composePath)
    );

    runCommand($command, 'Unable to bootstrap the database schema and seeds.');
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
