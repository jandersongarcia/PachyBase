<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class TemporaryHttpServer
{
    /**
     * @var resource|null
     */
    private $process = null;

    private string $directory;
    private string $baseUrl;

    private function __construct(string $directory, string $baseUrl, $process)
    {
        $this->directory = $directory;
        $this->baseUrl = $baseUrl;
        $this->process = $process;
    }

    public static function start(): self
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-http-' . bin2hex(random_bytes(4));

        if (!mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create the temporary HTTP server directory.');
        }

        $routerPath = $directory . DIRECTORY_SEPARATOR . 'router.php';
        $logPath = $directory . DIRECTORY_SEPARATOR . 'server.log';
        file_put_contents($routerPath, <<<'PHP'
<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
header('Content-Type: application/json');

if ($path === '/healthz') {
    http_response_code(200);
    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/ok') {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'body' => json_decode(file_get_contents('php://input') ?: '{}', true),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/fail') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'forced'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
PHP
        );

        $server = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);

        if ($server === false) {
            throw new RuntimeException(sprintf('Unable to reserve a local port: %s', $errorMessage));
        }

        $address = stream_socket_get_name($server, false);
        fclose($server);

        if (!is_string($address) || !str_contains($address, ':')) {
            throw new RuntimeException('Unable to determine the local server address.');
        }

        [, $port] = explode(':', $address);
        $baseUrl = 'http://127.0.0.1:' . $port;
        $command = sprintf('"%s" -S 127.0.0.1:%s -t "%s" "%s"', PHP_BINARY, $port, $directory, $routerPath);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $directory);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the temporary HTTP server.');
        }

        fclose($pipes[0]);

        $instance = new self($directory, $baseUrl, $process);
        $instance->waitUntilReady();

        return $instance;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            @proc_terminate($this->process);
            @proc_close($this->process);
            $this->process = null;
        }

        $this->deleteDirectory($this->directory);
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $response = @file_get_contents($this->baseUrl . '/healthz');

            if ($response !== false) {
                return;
            }

            usleep(100_000);
        }

        $this->stop();

        throw new RuntimeException('Temporary HTTP server did not become ready in time.');
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
