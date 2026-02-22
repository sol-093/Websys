<?php

declare(strict_types=1);

return [
    'db' => [
        // Use mysql for XAMPP by default.
        // Change to 'sqlite' if you prefer file-based DB.
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'websys_db',
        'username' => 'root',
        'password' => '',
        'sqlite_path' => __DIR__ . '/../storage/database.sqlite',
    ],
    'app_name' => 'Student Organization Management',
    'upload_dir' => __DIR__ . '/../public/uploads',
    'google_oauth' => [
        'client_id' => '',
        'client_secret' => '',
    ],
    'base_url' => '',
];
