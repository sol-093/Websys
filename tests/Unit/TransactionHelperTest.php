<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/core/helpers.php';

final class TransactionHelperTest extends TestCase
{
    public function testWithTransactionCommitsSuccessfulWork(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for transaction helper tests.');
        }

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE samples (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        withTransaction($db, static function () use ($db): void {
            $db->prepare('INSERT INTO samples (name) VALUES (?)')->execute(['committed']);
        });

        self::assertSame('committed', $db->query('SELECT name FROM samples')->fetchColumn());
    }

    public function testWithTransactionRollsBackFailedWork(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for transaction helper tests.');
        }

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE samples (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        try {
            withTransaction($db, static function () use ($db): void {
                $db->prepare('INSERT INTO samples (name) VALUES (?)')->execute(['rolled-back']);
                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM samples')->fetchColumn());
    }
}
