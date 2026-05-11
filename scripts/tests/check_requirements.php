<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$warnings = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    echo ($condition ? '[OK] ' : '[FAIL] ') . $message . PHP_EOL;
    if (!$condition) {
        $failures[] = $message;
    }
};

$warn = static function (bool $condition, string $message) use (&$warnings): void {
    echo ($condition ? '[OK] ' : '[WARN] ') . $message . PHP_EOL;
    if (!$condition) {
        $warnings[] = $message;
    }
};

echo 'INVOLVE environment preflight' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' (' . PHP_BINARY . ')' . PHP_EOL . PHP_EOL;

$check(PHP_VERSION_ID >= 80200, 'PHP version is 8.2 or newer');

foreach (['pdo', 'fileinfo', 'gd', 'mbstring', 'openssl'] as $extension) {
    $check(extension_loaded($extension), 'PHP extension loaded: ' . $extension);
}

$pdoDrivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];
$check(in_array('mysql', $pdoDrivers, true) || in_array('sqlite', $pdoDrivers, true), 'PDO has at least one supported driver: mysql or sqlite');
$warn(in_array('mysql', $pdoDrivers, true), 'PDO MySQL driver is available for production-like local testing');
$warn(in_array('sqlite', $pdoDrivers, true), 'PDO SQLite driver is available for CI/integration tests');

foreach ([
    'uploads',
    'uploads/users',
    'uploads/organizations',
    'uploads/receipts',
    'storage/cache',
] as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    $check(is_dir($path), 'Directory exists: ' . $directory);
    $check(is_writable($path), 'Directory is writable: ' . $directory);
}

foreach ([
    'uploads/.htaccess',
    'uploads/users/.htaccess',
    'uploads/organizations/.htaccess',
    'uploads/receipts/.htaccess',
] as $guard) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $guard);
    $check(is_file($path), 'Upload execution guard exists: ' . $guard);
}

echo PHP_EOL;
if ($warnings !== []) {
    echo count($warnings) . ' warning(s):' . PHP_EOL;
    foreach ($warnings as $warning) {
        echo '- ' . $warning . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . ' requirement check(s) failed.' . PHP_EOL);
    exit(1);
}

echo 'Environment preflight passed.' . PHP_EOL;
