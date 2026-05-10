<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\AuditRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuditRepositoryTest extends TestCase
{
    public function testAuditListSearchesActionEntityAndUser(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([5, 'Admin User']);
        $db->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([5, 'organization.create', 'organization', 9, 'Created org', '2026-05-09 10:00:00']);

        $repository = new AuditRepository($db);
        $result = $repository->list('organization', 10, 0);

        self::assertSame(1, $result['total']);
        self::assertSame('Admin User', $result['items'][0]['user_name']);
        self::assertSame('organization.create', $result['items'][0]['action']);

        $trail = $repository->userTrail(5, '2026-05-01 00:00:00', 10);
        self::assertCount(1, $trail);
        self::assertSame('organization', $trail[0]['entity_type']);
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            action TEXT NOT NULL,
            entity_type TEXT NULL,
            entity_id INTEGER NULL,
            details TEXT NULL,
            ip_address TEXT NULL,
            user_agent TEXT NULL,
            created_at TEXT NOT NULL
        )');
    }
}
