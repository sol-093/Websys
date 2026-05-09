<?php

declare(strict_types=1);

namespace Involve\Support\Cache;

final class FileCache
{
    public function __construct(
        private readonly string $directory,
        private readonly bool $enabled = true
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }

        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return $default;
        }

        $payload = @unserialize((string) file_get_contents($path), ['allowed_classes' => false]);
        if (!is_array($payload) || ($payload['key'] ?? '') !== $key) {
            @unlink($path);
            return $default;
        }

        $expiresAt = $payload['expires_at'] ?? null;
        if (is_int($expiresAt) && $expiresAt < time()) {
            @unlink($path);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        if (!$this->enabled) {
            return;
        }

        $payload = [
            'key' => $key,
            'expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : null,
            'value' => $value,
        ];

        file_put_contents($this->pathFor($key), serialize($payload), LOCK_EX);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $missing = new \stdClass();
        $value = $this->get($key, $missing);
        if ($value !== $missing) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    public function forget(string $key): void
    {
        $path = $this->pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function forgetByPrefix(string $prefix): void
    {
        foreach (glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $path) {
            $payload = @unserialize((string) file_get_contents($path), ['allowed_classes' => false]);
            if (is_array($payload) && str_starts_with((string) ($payload['key'] ?? ''), $prefix)) {
                @unlink($path);
            }
        }
    }

    private function pathFor(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }
}
