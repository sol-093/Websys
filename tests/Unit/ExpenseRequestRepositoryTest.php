<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\ExpenseRequestRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ExpenseRequestRepositoryTest extends TestCase
{
    public function testFetchCreateAndDecisionPrimitives(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([3, 'JPCS']);
        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([5, 'Owner']);
        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([9, 'Admin']);
        $db->prepare('INSERT INTO budgets (id, organization_id, title) VALUES (?, ?, ?)')->execute([11, 3, 'May Budget']);
        $db->prepare('INSERT INTO budget_line_items (id, budget_id, category_name, spent_amount) VALUES (?, ?, ?, ?)')->execute([17, 11, 'Events', 0]);

        $repository = new ExpenseRequestRepository($db);
        $requestId = $repository->create(3, 11, 17, 5, 480.50, 'Venue rental', 'uploads/receipts/sample.pdf');

        $request = $repository->find($requestId);
        self::assertNotNull($request);
        self::assertSame('JPCS', $request['organization_name']);
        self::assertSame('Owner', $request['requested_by_name']);

        $transactionId = $repository->createExpenseTransactionForRequest($repository->lockForDecision($requestId) ?? [], $requestId);
        $repository->markApproved($requestId, 9, $transactionId, 'Looks good');
        $repository->incrementLineSpent(17, 480.50);

        $approved = $repository->all(['status' => 'approved', 'organization_id' => 3]);
        self::assertCount(1, $approved);
        self::assertSame('Admin', $approved[0]['reviewed_by_name']);
        self::assertSame(480.5, (float) $db->query('SELECT spent_amount FROM budget_line_items WHERE id = 17')->fetchColumn());
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE organizations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE budgets (
            id INTEGER PRIMARY KEY,
            organization_id INTEGER NOT NULL,
            title TEXT NOT NULL
        )');

        $db->exec('CREATE TABLE budget_line_items (
            id INTEGER PRIMARY KEY,
            budget_id INTEGER NOT NULL,
            category_name TEXT NOT NULL,
            spent_amount REAL NOT NULL DEFAULT 0,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE expense_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            budget_id INTEGER NOT NULL,
            budget_line_item_id INTEGER NOT NULL,
            requested_by INTEGER NOT NULL,
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            receipt_path TEXT NULL,
            status TEXT NOT NULL,
            admin_note TEXT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            transaction_id INTEGER NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NULL
        )');

        $db->exec('CREATE TABLE financial_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            transaction_date TEXT NOT NULL,
            receipt_path TEXT NULL,
            expense_request_id INTEGER NULL
        )');
    }
}
