<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\AnnouncementRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AnnouncementRepositoryTest extends TestCase
{
    public function testCreateActivePinUnpinAndDelete(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([2, 'JPCS']);

        $repository = new AnnouncementRepository($db);
        $firstId = $repository->create(2, 'General Assembly', 'Meet at 3 PM', 'Event', 30, '2026-06-01 00:00:00');
        $secondId = $repository->create(2, 'Dues Reminder', 'Settle before Friday', null, 30, '2026-06-01 00:00:00');

        $active = $repository->activeWithOrganization('2026-05-09 00:00:00');
        self::assertCount(2, $active);
        self::assertSame('JPCS', $active[0]['organization_name']);

        $list = $repository->activeList('2026-05-09 00:00:00', 'Dues', 10, 0);
        self::assertSame(1, $list['total']);
        self::assertSame('Dues Reminder', $list['items'][0]['title']);

        self::assertTrue($repository->exists($firstId));
        $repository->pinExclusive($secondId);
        self::assertSame($secondId, (int) $db->query('SELECT id FROM announcements WHERE is_pinned = 1')->fetchColumn());

        $repository->unpin($secondId);
        self::assertSame(0, (int) $db->query('SELECT COUNT(*) FROM announcements WHERE is_pinned = 1')->fetchColumn());

        $repository->deleteForOrganization($firstId, 2);
        self::assertFalse($repository->exists($firstId));
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            label TEXT NULL,
            duration_days INTEGER NOT NULL DEFAULT 30,
            expires_at TEXT NULL,
            is_pinned INTEGER NOT NULL DEFAULT 0,
            pinned_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }
}
