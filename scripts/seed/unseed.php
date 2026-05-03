<?php

declare(strict_types=1);

require __DIR__ . '/../../src/core/db.php';

$pdo = db();

// List of tables to clear in order of dependency
$tables = [
    'transaction_change_requests',
    'financial_transactions',
    'organization_join_requests',
    'owner_assignments',
    'announcements',
    'organization_members',
    'organizations',
    'users'
];

fwrite(STDOUT, "Starting unseed process...\n");

$pdo->beginTransaction();

try {
    // 1. Clear database records
    foreach ($tables as $table) {
        // We use TRUNCATE for a clean slate, but DELETE is safer if you have shared data
        // For development, we'll use DELETE to respect existing DB structures
        $count = $pdo->exec("DELETE FROM $table");
        fwrite(STDOUT, "Cleared $count records from table: $table\n");
    }

    // 2. Clear physical receipt files
    $receiptDir = __DIR__ . '/../../public/uploads/receipts/';
    if (is_dir($receiptDir)) {
        $files = glob($receiptDir . '*'); 
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        fwrite(STDOUT, "Deleted physical receipt images from uploads folder.\n");
    }

    $pdo->commit();
    fwrite(STDOUT, "Successfully unseeded all data.\n");

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Unseeding failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}