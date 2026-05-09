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
        $stmt = $this->db->prepare('INSERT INTO transaction_change_requests (transaction_id, organization_id, requested_by, action_type, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$transactionId, $organizationId, $requestedBy, 'delete', 'pending']);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function totalsByType(): array|false
    {
        return $this->profile('transactions.totals_by_type', fn() => $this->db->query("SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
                FROM financial_transactions")->fetch());
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
