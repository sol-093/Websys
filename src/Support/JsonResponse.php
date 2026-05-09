<?php

declare(strict_types=1);

namespace Involve\Support;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function send(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo self::encode($payload);
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(array $data = [], int $status = 200): never
    {
        self::send(['ok' => true] + $data, $status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(string $message, int $status, array $details = []): never
    {
        self::send(['ok' => false, 'error' => $message] + $details, $status);
    }
}
