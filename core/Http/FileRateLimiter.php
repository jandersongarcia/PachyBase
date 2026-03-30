<?php

declare(strict_types=1);

namespace PachyBase\Http;

use RuntimeException;

final class FileRateLimiter
{
    public function __construct(
        private readonly ?RateLimitPolicy $policy = null
    ) {
    }

    public function enforce(Request $request): void
    {
        $policy = $this->policy ?? RateLimitPolicy::fromConfig();

        if (!$policy->enabled() || $request->getMethod() === 'OPTIONS') {
            return;
        }

        $path = $policy->storagePath();
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            return;
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $contents = stream_get_contents($handle);
            $state = is_string($contents) && trim($contents) !== ''
                ? json_decode($contents, true)
                : [];

            if (!is_array($state)) {
                $state = [];
            }

            $now = time();
            $windowExpiresAt = $now + $policy->windowSeconds();
            $state = $this->purgeExpired($state, $now);
            $key = $this->clientKey($request);
            $bucket = $state[$key] ?? null;

            if (
                !is_array($bucket)
                || !isset($bucket['count'], $bucket['reset_at'])
                || (int) $bucket['reset_at'] <= $now
            ) {
                $bucket = [
                    'count' => 0,
                    'reset_at' => $windowExpiresAt,
                ];
            }

            if ((int) $bucket['count'] >= $policy->maxRequests()) {
                $retryAfter = max(1, (int) $bucket['reset_at'] - $now);

                throw new RuntimeException(
                    sprintf('Rate limit exceeded. Retry in %d second(s).', $retryAfter),
                    429
                );
            }

            $bucket['count'] = (int) $bucket['count'] + 1;
            $state[$key] = $bucket;

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function purgeExpired(array $state, int $now): array
    {
        $active = [];

        foreach ($state as $key => $bucket) {
            if (!is_array($bucket)) {
                continue;
            }

            $resetAt = (int) ($bucket['reset_at'] ?? 0);
            if ($resetAt <= $now) {
                continue;
            }

            $active[(string) $key] = $bucket;
        }

        return $active;
    }

    private function clientKey(Request $request): string
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if ($authorization !== '') {
            return 'auth:' . sha1($authorization);
        }

        return 'ip:' . sha1($this->resolveClientIp());
    }

    private function resolveClientIp(): string
    {
        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string) ($parts[0] ?? ''));

            if ($candidate !== '') {
                return $candidate;
            }
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return $remoteAddr !== '' ? $remoteAddr : 'unknown';
    }
}
