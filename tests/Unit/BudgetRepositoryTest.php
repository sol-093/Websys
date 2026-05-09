<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\BudgetRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class BudgetRepositoryTest extends TestCase
{
    public function testBudgetLifecycleAndLineUsage(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for budget repository tests.');
        }

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([7, 'Budget Owner']);

        $repository = new BudgetRepository($db);
        $budgetId = $repository->create(12, 7, 'May Budget', '2026-05-01', '2026-05-31', 5000.00);
        $lineId = $repository->createLineItem($budgetId, 'Events', 'Launch week', 1200.00);

        $db->prepare('INSERT INTO expense_requests (budget_line_item_id, amount, status) VALUES (?, ?, ?)')->execute([$lineId, 250.00, 'pending']);

        $line = $repository->lineItem($lineId);
        self::assertNotNull($line);
        self::assertSame(250.0, $line['pending_amount']);
        self::assertSame(950.0, $line['remaining_amount']);

        $repository->updateStatus($budgetId, 'active');

        $activeBudget = $repository->activeForOrganization(12, '2026-05-09');
        self::assertNotNull($activeBudget);
        self::assertSame('May Budget', $activeBudget['title']);

        $budgets = $repository->forOrganization(12);
        self::assertCount(1, $budgets);
        self::assertSame('Budget Owner', $budgets[0]['created_by_name']);
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            created_by INTEGER NULL,
            title TEXT NOT NULL,
            period_start TEXT NOT NULL,
            period_end TEXT NOT NULL,
            total_amount REAL NOT NULL,
            status TEXT NOT NULL,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE budget_line_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_id INTEGER NOT NULL,
            category_name TEXT NOT NULL,
            description TEXT NULL,
            allocated_amount REAL NOT NULL,
            spent_amount REAL NOT NULL DEFAULT 0,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE expense_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_line_item_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            status TEXT NOT NULL
        )');
    }
}
