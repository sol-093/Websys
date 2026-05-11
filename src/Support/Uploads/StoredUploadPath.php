<?php

declare(strict_types=1);

namespace Involve\Support\Uploads;

final class StoredUploadPath
{
    private string $relativePath;

    private string $absolutePath;

    private function __construct(string $relativePath, string $absolutePath)
    {
        $this->relativePath = $relativePath;
        $this->absolutePath = $absolutePath;
    }

    public static function fromPublicPath(?string $path, ?string $uploadRoot = null): ?self
    {
        $normalized = str_replace('\\', '/', trim((string) $path));
        if ($normalized === '' || str_contains($normalized, "\0")) {
            return null;
        }

        if (str_starts_with($normalized, 'public/uploads/')) {
            $normalized = 'uploads/' . substr($normalized, strlen('public/uploads/'));
        }

        if (!str_starts_with($normalized, 'uploads/')) {
            return null;
        }

        $relativePath = substr($normalized, strlen('uploads/'));
        if ($relativePath === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('/', $relativePath), static fn (string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..' || str_contains($part, ':')) {
                return null;
            }
        }

        if ($parts === []) {
            return null;
        }

        $root = rtrim($uploadRoot ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads', '/\\');
        $absolutePath = $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);

        return new self(implode('/', $parts), $absolutePath);
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function publicPath(): string
    {
        return 'uploads/' . $this->relativePath;
    }

    public function absolutePath(): string
    {
        return $this->absolutePath;
    }

    public function deleteIfFile(): bool
    {
        if (!is_file($this->absolutePath)) {
            return false;
        }

        return @unlink($this->absolutePath);
    }
}
