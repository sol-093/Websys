<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - RUNTIME CONFIG
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Environment Readers
 * 2. App Settings
 * 3. Database Settings
 * 4. Mail and OAuth Settings
 *
 * EDIT GUIDE:
 * - Edit this file when adding config keys or env variable defaults.
 * ================================================
 */

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

$smtpUser = (string) $env('SMTP_USER', 'markcas039@gmail.com');
$smtpFrom = (string) $env('SMTP_FROM', $smtpUser !== '' ? $smtpUser : 'noreply@campus.local');

return [
    'timezone' => (string) $env('APP_TIMEZONE', 'Asia/Manila'),
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
    'app_name' => (string) $env('APP_NAME', 'INVOLVE'),
    'upload_dir' => dirname(__DIR__, 2) . '/uploads',
    'google_oauth' => [
        'client_id' => (string) $env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => (string) $env('GOOGLE_CLIENT_SECRET', ''),
    ],
    'smtp' => [
        'host' => (string) $env('SMTP_HOST', 'smtp.gmail.com'),
        'port' => (int) $env('SMTP_PORT', 587),
        'user' => $smtpUser,
        'pass' => (string) $env('SMTP_PASS', 'wsvy rmtm wpwg dxfg'),
        'from' => $smtpFrom,
        'from_name' => (string) $env('SMTP_FROM_NAME', (string) $env('APP_NAME', 'INVOLVE')),
    ],
    'base_url' => (string) $env('BASE_URL', (string) $env('APP_URL', '')),
];
