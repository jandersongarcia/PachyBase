<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class ApiContractEnforcementTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $forbiddenFunctionCalls = ['header', 'http_response_code'];

    public function testRuntimeLayersDoNotBypassApiResponseFormatter(): void
    {
        $root = dirname(__DIR__, 2);
        $directories = [
            'api',
            'auth',
            'config',
            'database',
            'modules',
            'public',
            'routes',
            'services',
            'utils',
            'core',
        ];

        $violations = [];

        foreach ($directories as $directory) {
            $path = $root . DIRECTORY_SEPARATOR . $directory;
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $realPath = $file->getRealPath();
                if ($realPath === false) {
                    continue;
                }

                if ($this->isWhitelistedRuntimeFile($realPath)) {
                    continue;
                }

                $violations = array_merge($violations, $this->findViolations($realPath));
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Runtime layers must not emit HTTP responses outside core/Http/ApiResponse.php.\n"
            . implode("\n", $violations)
        );
    }

    private function isWhitelistedRuntimeFile(string $path): bool
    {
        return str_ends_with(str_replace('\\', '/', $path), 'core/Http/ApiResponse.php');
    }

    /**
     * @return array<int, string>
     */
    private function findViolations(string $path): array
    {
        $code = file_get_contents($path);
        if ($code === false) {
            return ["Unable to read file: {$path}"];
        }

        $tokens = token_get_all($code);
        $violations = [];
        $line = 1;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (is_array($token)) {
                $line = $token[2];

                if ($token[0] === T_ECHO || $token[0] === T_EXIT || $token[0] === T_PRINT) {
                    $violations[] = "{$path}:{$line} uses direct output control outside ApiResponse.";
                    continue;
                }

                if ($token[0] !== T_STRING) {
                    continue;
                }

                $name = strtolower($token[1]);
                if (!in_array($name, $this->forbiddenFunctionCalls, true)) {
                    continue;
                }

                if ($this->isFunctionDeclaration($tokens, $index) || !$this->isFunctionCall($tokens, $index)) {
                    continue;
                }

                $violations[] = "{$path}:{$line} calls {$name}() outside ApiResponse.";
            }
        }

        return $violations;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function isFunctionDeclaration(array $tokens, int $index): bool
    {
        $previous = $this->previousSignificantToken($tokens, $index);

        return is_array($previous) && $previous[0] === T_FUNCTION;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function isFunctionCall(array $tokens, int $index): bool
    {
        $previous = $this->previousSignificantToken($tokens, $index);
        $next = $this->nextSignificantToken($tokens, $index);

        if ($next !== '(') {
            return false;
        }

        if (is_string($previous) && in_array($previous, ['->', '::', '\\'], true)) {
            return false;
        }

        if (is_array($previous) && in_array($previous[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NS_SEPARATOR], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function previousSignificantToken(array $tokens, int $index): mixed
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token)) {
                return $token;
            }

            return trim($token) !== '' ? $token : null;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function nextSignificantToken(array $tokens, int $index): mixed
    {
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($token)) {
                return $token;
            }

            return trim($token) !== '' ? $token : null;
        }

        return null;
    }
}

