<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\NotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryTest extends TestCase
{
    public function testRequestUpdatesAggregatesWorkflowItems(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $now = date('Y-m-d H:i:s');
        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([3, 'JPCS']);
        $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([3, 5, 'approved', $now, $now]);
        $db->prepare('INSERT INTO transaction_change_requests (organization_id, requested_by, status, action_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([3, 5, 'rejected', 'update', $now, $now]);
        $db->prepare('INSERT INTO owner_assignments (organization_id, student_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([3, 5, 'accepted', $now, $now]);
        $db->prepare('INSERT INTO security_notifications (user_id, event_type, event_data, created_at, sent_at) VALUES (?, ?, ?, ?, ?)')->execute([
            5,
            'budget_expense_request_approved',
            json_encode([
                'organization_name' => 'JPCS',
                'line_item_name' => 'Venue',
                'amount' => 1200,
            ], JSON_THROW_ON_ERROR),
            $now,
            $now,
        ]);

        $repository = new NotificationRepository($db);
        $updates = $repository->requestUpdates(5, 30, 10);

        self::assertCount(4, $updates);
        self::assertContains('Join Request', array_column($updates, 'kind'));
        self::assertContains('Finance Update Request', array_column($updates, 'kind'));
        self::assertContains('Organization Assignment', array_column($updates, 'kind'));
        self::assertContains('Budget Expense Request', array_column($updates, 'kind'));
        self::assertContains('approved', array_column($updates, 'status'));
        self::assertContains('rejected', array_column($updates, 'status'));
    }

    public function testRequestUpdatesRespectsUserAndLimit(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $now = date('Y-m-d H:i:s');
        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([3, 'JPCS']);
        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([4, 'Math Club']);
        $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([3, 5, 'approved', $now, $now]);
        $db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([4, 9, 'approved', $now, $now]);

        $repository = new NotificationRepository($db);
        $updates = $repository->requestUpdates(5, 30, 1);

        self::assertCount(1, $updates);
        self::assertSame('Organization: JPCS', $updates[0]['message']);
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE organization_join_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE transaction_change_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            requested_by INTEGER NOT NULL,
            status TEXT NOT NULL,
            action_type TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE owner_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE security_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            event_data TEXT NULL,
            created_at TEXT NOT NULL,
            sent_at TEXT NULL
        )');
    }
}
