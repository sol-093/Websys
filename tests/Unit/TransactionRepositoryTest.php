<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Repositories\TransactionRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class TransactionRepositoryTest extends TestCase
{
    public function testCreateAndChangeRequestHelpers(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $repository = new TransactionRepository($db);
        $transactionId = $repository->create(4, 'income', 1500.25, 'Membership dues', '2026-05-09', 'uploads/receipts/dues.pdf');

        self::assertTrue($repository->existsForOrganization($transactionId, 4));
        self::assertFalse($repository->existsForOrganization($transactionId, 99));

        $updateRequestId = $repository->requestUpdate($transactionId, 4, 7, 'expense', 800.00, 'Venue correction', '2026-05-10');
        self::assertGreaterThan(0, $updateRequestId);
        self::assertTrue($repository->hasPendingChangeRequest($transactionId, 'update'));
        self::assertFalse($repository->hasPendingChangeRequest($transactionId, 'delete'));

        $deleteRequestId = $repository->requestDelete($transactionId, 4, 7);
        self::assertGreaterThan(0, $deleteRequestId);
        self::assertTrue($repository->hasPendingChangeRequest($transactionId, 'delete'));

        $requests = $repository->changeRequestsForOrganizationByRequester(4, 7);
        self::assertCount(2, $requests);
    }

    private function createSchema(PDO $db): void
    {
        $db->exec('CREATE TABLE financial_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT NOT NULL,
            transaction_date TEXT NOT NULL,
            receipt_path TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $db->exec('CREATE TABLE transaction_change_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            transaction_id INTEGER NOT NULL,
            organization_id INTEGER NOT NULL,
            requested_by INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            proposed_type TEXT NULL,
            proposed_amount REAL NULL,
            proposed_description TEXT NULL,
            proposed_transaction_date TEXT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NULL
        )');
    }
}
