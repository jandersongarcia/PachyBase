<?php

declare(strict_types=1);

namespace PachyBase\Cli;

use RuntimeException;

final class EnvironmentFileManager
{
    public function __construct(
        private readonly string $basePath
    ) {
    }

    /**
     * @return array{path: string, status: string, added: array<int, string>}
     */
    public function syncFromTemplate(bool $force = false): array
    {
        $templatePath = $this->templatePath();
        $targetPath = $this->envPath();

        if (!is_file($templatePath)) {
            throw new RuntimeException('.env.example was not found.');
        }

        if (!is_file($targetPath) || $force) {
            if (!copy($templatePath, $targetPath)) {
                throw new RuntimeException('Failed to create .env from .env.example.');
            }

            return [
                'path' => $targetPath,
                'status' => $force ? 'overwritten' : 'created',
                'added' => array_keys($this->readValuesFrom($templatePath)),
            ];
        }

        $existingValues = $this->readValues();
        $templateValues = $this->readValuesFrom($templatePath);
        $missingKeys = array_values(
            array_filter(
                array_keys($templateValues),
                static fn(string $key): bool => !array_key_exists($key, $existingValues)
            )
        );

        if ($missingKeys === []) {
            return [
                'path' => $targetPath,
                'status' => 'unchanged',
                'added' => [],
            ];
        }

        $contents = (string) file_get_contents($targetPath);
        $append = ['# Added by pachybase env:sync'];

        foreach ($missingKeys as $key) {
            $append[] = $key . '=' . ($templateValues[$key] ?? '');
        }

        file_put_contents($targetPath, rtrim($contents) . PHP_EOL . PHP_EOL . implode(PHP_EOL, $append) . PHP_EOL);

        return [
            'path' => $targetPath,
            'status' => 'updated',
            'added' => $missingKeys,
        ];
    }

    /**
     * @return array{errors: array<int, string>, warnings: array<int, string>, values: array<string, string>}
     */
    public function validate(): array
    {
        $values = $this->readValues();
        $errors = [];
        $warnings = [];

        foreach (['APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_RUNTIME', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $field) {
            if (trim((string) ($values[$field] ?? '')) === '') {
                $errors[] = sprintf('%s is required.', $field);
            }
        }

        $appEnv = strtolower(trim((string) ($values['APP_ENV'] ?? '')));
        if ($appEnv !== '' && !in_array($appEnv, ['development', 'production'], true)) {
            $errors[] = 'APP_ENV must be development or production.';
        }

        $runtime = strtolower(trim((string) ($values['APP_RUNTIME'] ?? 'docker')));
        if (!in_array($runtime, ['docker', 'local'], true)) {
            $errors[] = 'APP_RUNTIME must be docker or local.';
        }

        $appDebug = strtolower(trim((string) ($values['APP_DEBUG'] ?? '')));
        if ($appDebug !== '' && !in_array($appDebug, ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true)) {
            $errors[] = 'APP_DEBUG must be a boolean-like value.';
        }

        $driver = strtolower(trim((string) ($values['DB_DRIVER'] ?? '')));
        if ($driver !== '' && !in_array($driver, ['mysql', 'pgsql'], true)) {
            $errors[] = 'DB_DRIVER must be mysql or pgsql.';
        }

        $port = trim((string) ($values['DB_PORT'] ?? ''));
        if ($port !== '' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
            $errors[] = 'DB_PORT must be a number between 1 and 65535.';
        }

        if (trim((string) ($values['APP_KEY'] ?? '')) === '') {
            $warnings[] = 'APP_KEY is not configured.';
        }

        if (trim((string) ($values['AUTH_JWT_SECRET'] ?? '')) === '') {
            $warnings[] = 'AUTH_JWT_SECRET is not configured.';
        }

        if (trim((string) ($values['APP_URL'] ?? '')) === '') {
            $warnings[] = 'APP_URL is not configured. The CLI will infer http://localhost:8080.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'values' => $values,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function readValues(): array
    {
        return $this->readValuesFrom($this->envPath());
    }

    public function getValue(string $key, ?string $default = null): ?string
    {
        $values = $this->readValues();

        return $values[$key] ?? $default;
    }

    public function runtimeMode(): string
    {
        $runtime = strtolower(trim((string) $this->getValue('APP_RUNTIME', 'docker')));

        return in_array($runtime, ['docker', 'local'], true) ? $runtime : 'docker';
    }

    public function appUrl(): string
    {
        $appUrl = trim((string) $this->getValue('APP_URL', ''));

        if ($appUrl !== '') {
            return $appUrl;
        }

        $host = trim((string) $this->getValue('APP_HOST', '127.0.0.1'));
        $port = trim((string) $this->getValue('APP_PORT', '8080'));

        return sprintf('http://%s:%s', $host, $port);
    }

    public function envExists(): bool
    {
        return is_file($this->envPath());
    }

    public function setValue(string $key, string $value): void
    {
        $path = $this->envPath();

        if (!is_file($path)) {
            $templatePath = $this->templatePath();
            if (is_file($templatePath) && !copy($templatePath, $path)) {
                throw new RuntimeException('Failed to create .env from .env.example.');
            }
        }

        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $normalizedLine = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $normalizedLine, $contents);
        } else {
            $contents = rtrim($contents) . PHP_EOL . $normalizedLine . PHP_EOL;
        }

        file_put_contents($path, $contents);
    }

    public function envPath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . '.env';
    }

    public function templatePath(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . '.env.example';
    }

    /**
     * @return array<string, string>
     */
    private function readValuesFrom(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Unable to read environment file "%s".', $path));
        }

        $values = [];

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
}
