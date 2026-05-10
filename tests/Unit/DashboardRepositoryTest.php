<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Involve\Repositories\DashboardRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DashboardRepositoryTest extends TestCase
{
    public function testDashboardReadModels(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);
        $this->seedData($db);

        $repository = DashboardRepository::fromConnection($db);

        $aggregates = $repository->aggregateSections([1]);
        self::assertCount(1, $aggregates['summary']);
        self::assertSame(1000.0, (float) $aggregates['summary'][0]['total_income']);
        self::assertSame(350.0, (float) $aggregates['summary'][0]['total_expense']);
        self::assertSame(1, $aggregates['pending_expense_count']);
        self::assertSame(1, $aggregates['active_budget_count']);
        self::assertCount(1, $aggregates['line_usage']);

        $monthly = $repository->monthlyKpi(new DateTimeImmutable('2026-05-01'));
        self::assertIsArray($monthly);
        self::assertSame(1000.0, (float) $monthly['income_current']);
        self::assertSame(350.0, (float) $monthly['expense_current']);

        $activity = $repository->recentActivity('2026-05-01 00:00:00', '2026-05-01');
        self::assertCount(3, $activity);

        $activityList = $repository->activityList('2026-05-01', 2, 0);
        self::assertSame(3, $activityList['total']);
        self::assertCount(2, $activityList['items']);
        self::assertSame('JPCS', $activityList['items'][0]['organization_name']);

        $trends = $repository->monthlyTrendRows('sqlite');
        self::assertCount(1, $trends);
        self::assertSame('2026-05', $trends[0]['month']);

        $assignments = $repository->pendingAssignmentsForStudent(7);
        self::assertCount(1, $assignments);
        self::assertSame('JPCS', $assignments[0]['organization_name']);

        self::assertSame(1, $repository->pendingTransactionRequestCount());
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            owner_id INTEGER NULL,
            logo_path TEXT NULL,
            logo_crop_x REAL NOT NULL DEFAULT 50,
            logo_crop_y REAL NOT NULL DEFAULT 50,
            logo_zoom REAL NOT NULL DEFAULT 1
        )');

        $db->exec('CREATE TABLE financial_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            transaction_date TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE expense_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            budget_line_item_id INTEGER NULL,
            amount REAL NOT NULL,
            status TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            status TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE budget_line_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER NOT NULL,
            allocated_amount REAL NOT NULL,
            spent_amount REAL NOT NULL
        )');

        $db->exec('CREATE TABLE announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE owner_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE transaction_change_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL
        )');
    }

    private function seedData(PDO $db): void
    {
        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([1, 'JPCS']);
        $db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([1, 'income', 1000.00, 'Dues', '2026-05-09', '2026-05-09 09:00:00']);
        $db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([1, 'expense', 350.00, 'Snacks', '2026-05-10', '2026-05-10 10:00:00']);
        $db->prepare('INSERT INTO budgets (id, organization_id, status) VALUES (?, ?, ?)')->execute([5, 1, 'active']);
        $db->prepare('INSERT INTO budget_line_items (id, budget_id, allocated_amount, spent_amount) VALUES (?, ?, ?, ?)')->execute([9, 5, 1000.00, 350.00]);
        $db->prepare('INSERT INTO expense_requests (organization_id, budget_line_item_id, amount, status) VALUES (?, ?, ?, ?)')->execute([1, 9, 100.00, 'pending']);
        $db->prepare('INSERT INTO announcements (organization_id, title, created_at) VALUES (?, ?, ?)')->execute([1, 'Meeting', '2026-05-09 08:00:00']);
        $db->prepare('INSERT INTO owner_assignments (organization_id, student_id, status, created_at) VALUES (?, ?, ?, ?)')->execute([1, 7, 'pending', '2026-05-09 07:00:00']);
        $db->prepare('INSERT INTO transaction_change_requests (status) VALUES (?)')->execute(['pending']);
    }
}
