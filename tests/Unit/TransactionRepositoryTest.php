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
        $db->prepare('INSERT INTO organizations (id, name) VALUES (?, ?)')->execute([4, 'JPCS']);

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

        $adminRequests = $repository->changeRequestList('pending', 10, 0);
        self::assertSame(2, $adminRequests['total']);
        self::assertSame('JPCS', $adminRequests['items'][0]['organization_name']);

        $reports = $repository->listWithOrganization('Membership', 10, 0);
        self::assertSame(1, $reports['total']);
        self::assertSame('JPCS', $reports['items'][0]['organization_name']);

        $ownerList = $repository->listForOrganization(4, 10, 0);
        self::assertSame(1, $ownerList['total']);
        self::assertSame('Membership dues', $ownerList['items'][0]['description']);

        $pendingUpdate = $repository->pendingChangeRequest($updateRequestId);
        self::assertNotNull($pendingUpdate);
        $repository->applyApprovedChangeRequest($pendingUpdate);
        $repository->markChangeRequestDecision($updateRequestId, 'approved', 'Approved update');

        $updated = $db->query('SELECT type, amount, description, transaction_date FROM financial_transactions WHERE id = ' . $transactionId)->fetch();
        self::assertSame('expense', $updated['type']);
        self::assertSame(800.0, (float) $updated['amount']);
        self::assertSame('Venue correction', $updated['description']);
        self::assertSame('2026-05-10', $updated['transaction_date']);
        self::assertNull($repository->pendingChangeRequest($updateRequestId));

        $repository->markChangeRequestDecision($deleteRequestId, 'rejected', 'Keep record');
        self::assertNull($repository->pendingChangeRequest($deleteRequestId));
    }

    public function testApprovedDeleteChangeRequestRemovesTransaction(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($db);

        $repository = new TransactionRepository($db);
        $transactionId = $repository->create(4, 'expense', 450.00, 'Snacks', '2026-05-09');
        $deleteRequestId = $repository->requestDelete($transactionId, 4, 7);

        $pendingDelete = $repository->pendingChangeRequest($deleteRequestId);
        self::assertNotNull($pendingDelete);
        $repository->applyApprovedChangeRequest($pendingDelete);
        $repository->markChangeRequestDecision($deleteRequestId, 'approved', 'Remove duplicate');

        self::assertFalse($repository->existsForOrganization($transactionId, 4));
        self::assertNull($repository->pendingChangeRequest($deleteRequestId));
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
            admin_note TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NULL
        )');

        $db->prepare('INSERT INTO users (id, name) VALUES (?, ?)')->execute([7, 'Owner']);
    }
}
