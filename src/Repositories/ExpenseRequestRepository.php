<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class ExpenseRequestRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return $this->profile('expense_requests.all', function () use ($filters): array {
            $sql = $this->selectSql() . ' WHERE 1 = 1';
            $params = [];

            if (!empty($filters['organization_id'])) {
                $sql .= ' AND er.organization_id = ?';
                $params[] = (int) $filters['organization_id'];
            }

            if (!empty($filters['budget_id'])) {
                $sql .= ' AND er.budget_id = ?';
                $params[] = (int) $filters['budget_id'];
            }

            if (!empty($filters['requested_by'])) {
                $sql .= ' AND er.requested_by = ?';
                $params[] = (int) $filters['requested_by'];
            }

            if (!empty($filters['status']) && in_array((string) $filters['status'], ['pending', 'approved', 'rejected'], true)) {
                $sql .= ' AND er.status = ?';
                $params[] = (string) $filters['status'];
            }

            $limit = isset($filters['limit']) ? max(1, min(200, (int) $filters['limit'])) : 100;
            $sql .= ' ORDER BY er.created_at DESC, er.id DESC LIMIT ' . $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare($this->selectSql() . ' WHERE er.id = ? LIMIT 1');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    public function create(int $organizationId, int $budgetId, int $budgetLineItemId, int $requestedBy, float $amount, string $description, ?string $receiptPath = null): int
    {
        $receiptPath = trim((string) $receiptPath);

        $stmt = $this->db->prepare(
            'INSERT INTO expense_requests (organization_id, budget_id, budget_line_item_id, requested_by, amount, description, receipt_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $organizationId,
            $budgetId,
            $budgetLineItemId,
            $requestedBy,
            round($amount, 2),
            $description,
            $receiptPath !== '' ? $receiptPath : null,
            'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockForDecision(int $requestId): ?array
    {
        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = 'SELECT * FROM expense_requests WHERE id = ? LIMIT 1';
        if ($driver === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function createExpenseTransactionForRequest(array $request, int $requestId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path, expense_request_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $request['organization_id'],
            'expense',
            round((float) $request['amount'], 2),
            (string) $request['description'],
            date('Y-m-d'),
            $request['receipt_path'] !== null && $request['receipt_path'] !== '' ? (string) $request['receipt_path'] : null,
            $requestId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markApproved(int $requestId, int $reviewedBy, int $transactionId, string $adminNote): void
    {
        $stmt = $this->db->prepare("UPDATE expense_requests SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, transaction_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$adminNote, $reviewedBy, $transactionId, $requestId]);
    }

    public function markRejected(int $requestId, int $reviewedBy, string $adminNote): void
    {
        $stmt = $this->db->prepare("UPDATE expense_requests SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$adminNote, $reviewedBy, $requestId]);
    }

    public function incrementLineSpent(int $budgetLineItemId, float $amount): void
    {
        $stmt = $this->db->prepare('UPDATE budget_line_items SET spent_amount = spent_amount + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([round($amount, 2), $budgetLineItemId]);
    }

    private function selectSql(): string
    {
        return "SELECT er.*, o.name AS organization_name, b.title AS budget_title, bli.category_name AS line_item_name,
                       requester.name AS requested_by_name, reviewer.name AS reviewed_by_name
                FROM expense_requests er
                JOIN organizations o ON o.id = er.organization_id
                JOIN budgets b ON b.id = er.budget_id
                JOIN budget_line_items bli ON bli.id = er.budget_line_item_id
                LEFT JOIN users requester ON requester.id = er.requested_by
                LEFT JOIN users reviewer ON reviewer.id = er.reviewed_by";
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
