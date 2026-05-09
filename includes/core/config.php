<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - RUNTIME CONFIG
 * ================================================
 *
 * SECTION MAP:
 * 1. Environment Readers
 * 2. App Settings
 * 3. Database Settings
 * 4. Mail and OAuth Settings
 *
 * WORK GUIDE:
 * - Edit this file when adding config keys or env variable defaults.
 * ================================================
 */

$loadEnvFile = static function (string $path): void {
    static $loadedPaths = [];

    if (isset($loadedPaths[$path]) || !is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $separatorPos = strpos($trimmed, '=');
        if ($separatorPos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $separatorPos));
        $value = trim(substr($trimmed, $separatorPos + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    $loadedPaths[$path] = true;
};

$loadEnvFile(dirname(__DIR__, 2) . '/.env');

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return $value === false ? $default : $value;
};

$envBool = static function (string $key, bool $default = false) use ($env): bool {
    $value = $env($key);
    if ($value === null || $value === '') {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
};

$smtpUser = trim((string) $env('SMTP_USER', ''));
$smtpFrom = trim((string) $env('SMTP_FROM', $smtpUser !== '' ? $smtpUser : 'noreply@campus.local'));
$settingsPath = dirname(__DIR__, 2) . '/config/settings.php';
$settings = is_file($settingsPath) ? require $settingsPath : [];
$branding = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];

return [
    'timezone' => (string) $env('APP_TIMEZONE', 'Asia/Manila'),
    'environment' => (string) $env('APP_ENV', 'production'),
    'db' => [
        // Local env override with XAMPP-friendly fallback defaults.
        'driver' => (string) $env('DB_DRIVER', 'mysql'),
        'host' => (string) $env('DB_HOST', (string) $env('MYSQLHOST', '127.0.0.1')),
        'port' => (int) $env('DB_PORT', (int) $env('MYSQLPORT', 3306)),
        'database' => (string) $env('DB_DATABASE', (string) $env('MYSQLDATABASE', (string) $env('MYSQL_DATABASE', 'websysdb'))),
        'username' => (string) $env('DB_USERNAME', (string) $env('MYSQLUSER', 'root')),
        'password' => (string) $env('DB_PASSWORD', (string) $env('MYSQLPASSWORD', '')),
        'bootstrap_database' => $envBool('DB_BOOTSTRAP_DATABASE', true),
        'sqlite_path' => (string) $env('DB_SQLITE_PATH', dirname(__DIR__, 2) . '/storage/database.sqlite'),
    ],
    'app_name' => (string) $env('APP_NAME', (string) ($branding['app_name'] ?? 'INVOLVE')),
    'settings' => $settings,
    'cache' => [
        'enabled' => $envBool('CACHE_ENABLED', true),
        'path' => (string) $env('CACHE_PATH', dirname(__DIR__, 2) . '/storage/cache'),
    ],
    'performance' => [
        'query_profiler_enabled' => $envBool('QUERY_PROFILER_ENABLED', false),
        'slow_query_ms' => (int) $env('SLOW_QUERY_MS', 150),
    ],
    'upload_dir' => dirname(__DIR__, 2) . '/uploads',
    'google_oauth' => [
        'client_id' => (string) $env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => (string) $env('GOOGLE_CLIENT_SECRET', ''),
    ],
    'smtp' => [
        'host' => (string) $env('SMTP_HOST', 'smtp.gmail.com'),
        'port' => (int) $env('SMTP_PORT', 587),
        'user' => $smtpUser,
        'pass' => trim((string) $env('SMTP_PASS', '')),
        'from' => $smtpFrom,
        'from_name' => (string) $env('SMTP_FROM_NAME', (string) ($branding['mail_from_name'] ?? (string) $env('APP_NAME', 'INVOLVE'))),
    ],
    'base_url' => (string) $env('BASE_URL', (string) $env('APP_URL', '')),
];
