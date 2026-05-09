<?php

declare(strict_types=1);

namespace Involve\Support;

final class ApiRequest
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function requireMethod(string $method): void
    {
        if (self::method() !== strtoupper($method)) {
            JsonResponse::error('Method not allowed.', 405);
        }
    }

    public static function csrfToken(): string
    {
        return (string) (
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_CSRF']
            ?? $_POST['_csrf']
            ?? ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
