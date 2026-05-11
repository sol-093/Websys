<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class TransactionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWithOrganization(string $fromDate, int $limit = 8): array
    {
        return $this->profile('transactions.recent_with_organization', function () use ($fromDate, $limit): array {
            $stmt = $this->db->prepare('SELECT t.*, o.name AS organization_name, o.logo_path AS organization_logo_path, o.logo_crop_x AS organization_logo_crop_x, o.logo_crop_y AS organization_logo_crop_y, o.logo_zoom AS organization_logo_zoom
                FROM financial_transactions t
                JOIN organizations o ON o.id = t.organization_id
                WHERE t.transaction_date >= ?
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT ' . max(1, $limit));
            $stmt->execute([$fromDate]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forOrganization(int $organizationId, string $typeFilter = 'all', string $dateSort = 'desc'): array
    {
        return $this->profile('transactions.for_organization', function () use ($organizationId, $typeFilter, $dateSort): array {
            $sql = 'SELECT * FROM financial_transactions WHERE organization_id = ?';
            $params = [$organizationId];

            if ($typeFilter !== 'all') {
                $sql .= ' AND type = ?';
                $params[] = $typeFilter;
            }

            $order = strtolower($dateSort) === 'asc' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY transaction_date {$order}, id {$order}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listWithOrganization(string $query = '', int $limit = 25, int $offset = 0): array
    {
        return $this->profile('transactions.list_with_organization', function () use ($query, $limit, $offset): array {
            $where = '';
            $params = [];
            $query = trim($query);
            if ($query !== '') {
                $where = 'WHERE t.description LIKE ? OR o.name LIKE ?';
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
                $params = [$like, $like];
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM financial_transactions t JOIN organizations o ON o.id = t.organization_id $where");
            $countStmt->execute($params);

            $stmt = $this->db->prepare("SELECT t.*, o.name AS organization_name
                FROM financial_transactions t
                JOIN organizations o ON o.id = t.organization_id
                $where
                ORDER BY t.transaction_date DESC, t.id DESC
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listForOrganization(int $organizationId, int $limit = 25, int $offset = 0): array
    {
        return $this->profile('transactions.list_for_organization', function () use ($organizationId, $limit, $offset): array {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM financial_transactions WHERE organization_id = ?');
            $countStmt->execute([$organizationId]);

            $stmt = $this->db->prepare('SELECT * FROM financial_transactions
                WHERE organization_id = ?
                ORDER BY transaction_date DESC, id DESC
                LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute([$organizationId]);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function changeRequestsForOrganizationByRequester(int $organizationId, int $requesterId, int $limit = 20): array
    {
        return $this->profile('transactions.change_requests_for_organization_by_requester', function () use ($organizationId, $requesterId, $limit): array {
            $stmt = $this->db->prepare('SELECT * FROM transaction_change_requests WHERE organization_id = ? AND requested_by = ? ORDER BY created_at DESC LIMIT ' . max(1, $limit));
            $stmt->execute([$organizationId, $requesterId]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function changeRequestList(string $status = '', int $limit = 25, int $offset = 0): array
    {
        return $this->profile('transactions.change_request_list', function () use ($status, $limit, $offset): array {
            $where = '';
            $params = [];
            if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $where = 'WHERE tcr.status = ?';
                $params[] = $status;
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM transaction_change_requests tcr $where");
            $countStmt->execute($params);

            $stmt = $this->db->prepare("SELECT tcr.*, o.name AS organization_name, u.name AS requested_by_name
                FROM transaction_change_requests tcr
                JOIN organizations o ON o.id = tcr.organization_id
                JOIN users u ON u.id = tcr.requested_by
                $where
                ORDER BY tcr.created_at DESC, tcr.id DESC
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    public function create(int $organizationId, string $type, float $amount, string $description, string $transactionDate, ?string $receiptPath = null): int
    {
        $stmt = $this->db->prepare('INSERT INTO financial_transactions (organization_id, type, amount, description, transaction_date, receipt_path) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$organizationId, $type, round($amount, 2), $description, $transactionDate, $receiptPath]);

        return (int) $this->db->lastInsertId();
    }

    public function existsForOrganization(int $transactionId, int $organizationId): bool
    {
        if ($transactionId <= 0 || $organizationId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? LIMIT 1');
        $stmt->execute([$transactionId, $organizationId]);

        return (bool) $stmt->fetch();
    }

    public function activeExistsForOrganization(int $transactionId, int $organizationId): bool
    {
        if ($transactionId <= 0 || $organizationId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id FROM financial_transactions WHERE id = ? AND organization_id = ? AND is_voided = 0 LIMIT 1');
        $stmt->execute([$transactionId, $organizationId]);

        return (bool) $stmt->fetch();
    }

    public function hasPendingChangeRequest(int $transactionId, string $actionType): bool
    {
        if ($transactionId <= 0 || !in_array($actionType, ['update', 'delete'], true)) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id FROM transaction_change_requests WHERE transaction_id = ? AND action_type = ? AND status = ? LIMIT 1');
        $stmt->execute([$transactionId, $actionType, 'pending']);

        return (bool) $stmt->fetch();
    }

    public function requestUpdate(int $transactionId, int $organizationId, int $requestedBy, string $type, float $amount, string $description, string $transactionDate): int
    {
        if (!$this->activeExistsForOrganization($transactionId, $organizationId)) {
            throw new \RuntimeException('Voided transactions cannot be updated.');
        }

        $stmt = $this->db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, proposed_type, proposed_amount, proposed_description, proposed_transaction_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $transactionId,
            $organizationId,
            $requestedBy,
            'update',
            $type,
            round($amount, 2),
            $description,
            $transactionDate,
            'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function requestDelete(int $transactionId, int $organizationId, int $requestedBy): int
    {
        if (!$this->activeExistsForOrganization($transactionId, $organizationId)) {
            throw new \RuntimeException('Voided transactions cannot be deleted again.');
        }

        $stmt = $this->db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$transactionId, $organizationId, $requestedBy, 'delete', 'pending']);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingChangeRequest(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM transaction_change_requests WHERE id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, 'pending']);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    /**
     * @param array<string, mixed> $request
     */
    public function applyApprovedChangeRequest(array $request, ?string $voidReason = null): void
    {
        if ((string) $request['action_type'] === 'update') {
            $stmt = $this->db->prepare('UPDATE financial_transactions SET type = ?, amount = ?, description = ?, transaction_date = ? WHERE id = ? AND organization_id = ? AND is_voided = 0');
            $stmt->execute([
                (string) $request['proposed_type'],
                round((float) $request['proposed_amount'], 2),
                (string) $request['proposed_description'],
                (string) $request['proposed_transaction_date'],
                (int) $request['transaction_id'],
                (int) $request['organization_id'],
            ]);

            return;
        }

        $reason = trim((string) ($voidReason ?? ''));
        if ($reason === '') {
            $reason = 'Approved delete request';
        }

        $stmt = $this->db->prepare('UPDATE financial_transactions SET is_voided = 1, voided_at = CURRENT_TIMESTAMP, void_reason = ? WHERE id = ? AND organization_id = ? AND is_voided = 0');
        $stmt->execute([$reason, (int) $request['transaction_id'], (int) $request['organization_id']]);
    }

    public function markChangeRequestDecision(int $requestId, string $status, string $adminNote): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE transaction_change_requests SET status = ?, admin_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $adminNote, $requestId]);
    }

    /**
     * @return array<string, mixed>|false
     */
    public function totalsByType(): array|false
    {
        return $this->profile('transactions.totals_by_type', fn() => $this->db->query("SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
                FROM financial_transactions
                WHERE is_voided = 0")->fetch());
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
