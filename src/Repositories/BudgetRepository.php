<?php

declare(strict_types=1);

namespace Involve\Repositories;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class BudgetRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forOrganization(int $organizationId): array
    {
        if ($organizationId <= 0) {
            return [];
        }

        return $this->profile('budgets.for_organization', function () use ($organizationId): array {
            $stmt = $this->db->prepare(
                'SELECT b.*, u.name AS created_by_name
                 FROM budgets b
                 LEFT JOIN users u ON u.id = b.created_by
                 WHERE b.organization_id = ?
                 ORDER BY b.period_start DESC, b.id DESC'
            );
            $stmt->execute([$organizationId]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $budgetId, ?int $organizationId = null): ?array
    {
        if ($budgetId <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM budgets WHERE id = ?';
        $params = [$budgetId];
        if ($organizationId !== null) {
            $sql .= ' AND organization_id = ?';
            $params[] = $organizationId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $budget = $stmt->fetch();

        return $budget ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeForOrganization(int $organizationId, ?string $date = null): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }

        $date = $this->normalizeDate($date) ?? date('Y-m-d');

        return $this->profile('budgets.active_for_organization', function () use ($organizationId, $date): ?array {
            $stmt = $this->db->prepare(
                "SELECT *
                 FROM budgets
                 WHERE organization_id = ?
                   AND status = 'active'
                   AND period_start <= ?
                   AND period_end >= ?
                 ORDER BY period_start DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute([$organizationId, $date, $date]);
            $budget = $stmt->fetch();

            return $budget ?: null;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineItems(int $budgetId, bool $withUsage = true): array
    {
        if ($budgetId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT * FROM budget_line_items WHERE budget_id = ? ORDER BY category_name ASC, id ASC');
        $stmt->execute([$budgetId]);
        $lines = $stmt->fetchAll() ?: [];

        if (!$withUsage) {
            return $lines;
        }

        foreach ($lines as &$line) {
            $line = $this->hydrateLineUsage($line);
        }
        unset($line);

        return $lines;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lineItem(int $lineItemId, ?int $budgetId = null): ?array
    {
        if ($lineItemId <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM budget_line_items WHERE id = ?';
        $params = [$lineItemId];
        if ($budgetId !== null) {
            $sql .= ' AND budget_id = ?';
            $params[] = $budgetId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $line = $stmt->fetch();

        return $line ? $this->hydrateLineUsage($line) : null;
    }

    public function pendingLineAmount(int $lineItemId, ?int $excludeRequestId = null): float
    {
        if ($lineItemId <= 0) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(amount), 0) FROM expense_requests WHERE budget_line_item_id = ? AND status = 'pending'";
        $params = [$lineItemId];
        if ($excludeRequestId !== null && $excludeRequestId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeRequestId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round((float) $stmt->fetchColumn(), 2);
    }

    /**
     * @param array<string, mixed> $lineItem
     */
    public function remainingLineAmount(array $lineItem, bool $reservePending = true, ?int $excludeRequestId = null): float
    {
        $allocatedAmount = round((float) ($lineItem['allocated_amount'] ?? 0), 2);
        $spentAmount = round((float) ($lineItem['spent_amount'] ?? 0), 2);
        $pendingAmount = $reservePending ? $this->pendingLineAmount((int) ($lineItem['id'] ?? 0), $excludeRequestId) : 0.0;

        return round($allocatedAmount - $spentAmount - $pendingAmount, 2);
    }

    public function create(int $organizationId, ?int $createdBy, string $title, string $periodStart, string $periodEnd, float $totalAmount, string $status = 'draft'): int
    {
        $title = trim($title);
        $periodStart = $this->normalizeDate($periodStart) ?? '';
        $periodEnd = $this->normalizeDate($periodEnd) ?? '';
        $totalAmount = round($totalAmount, 2);

        if ($organizationId <= 0 || $title === '' || $periodStart === '' || $periodEnd === '' || $totalAmount < 0) {
            throw new InvalidArgumentException('Invalid budget values.');
        }

        if ($periodStart > $periodEnd) {
            throw new InvalidArgumentException('Budget start date must be before the end date.');
        }

        if (!in_array($status, ['draft', 'active', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid budget status.');
        }

        $stmt = $this->db->prepare('INSERT INTO budgets (organization_id, created_by, title, period_start, period_end, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$organizationId, $createdBy, $title, $periodStart, $periodEnd, $totalAmount, $status]);

        return (int) $this->db->lastInsertId();
    }

    public function createLineItem(int $budgetId, string $categoryName, ?string $description, float $allocatedAmount): int
    {
        $categoryName = trim($categoryName);
        $description = trim((string) $description);
        $allocatedAmount = round($allocatedAmount, 2);

        if ($budgetId <= 0 || $categoryName === '' || $allocatedAmount < 0) {
            throw new InvalidArgumentException('Invalid budget line values.');
        }

        $budget = $this->find($budgetId);
        if (!$budget) {
            throw new RuntimeException('Budget not found.');
        }

        if ((string) ($budget['status'] ?? '') !== 'draft') {
            throw new RuntimeException('Budget line items can only be added while the budget is in draft.');
        }

        $stmt = $this->db->prepare('INSERT INTO budget_line_items (budget_id, category_name, description, allocated_amount) VALUES (?, ?, ?, ?)');
        $stmt->execute([$budgetId, $categoryName, $description !== '' ? $description : null, $allocatedAmount]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $budgetId, string $status): void
    {
        if ($budgetId <= 0 || !in_array($status, ['draft', 'active', 'closed'], true)) {
            throw new InvalidArgumentException('Invalid budget status update.');
        }

        $budget = $this->find($budgetId);
        if (!$budget) {
            throw new RuntimeException('Budget not found.');
        }

        if ($status === 'active') {
            $lineTotalStmt = $this->db->prepare('SELECT COALESCE(SUM(allocated_amount), 0) FROM budget_line_items WHERE budget_id = ?');
            $lineTotalStmt->execute([$budgetId]);
            $lineTotal = round((float) $lineTotalStmt->fetchColumn(), 2);
            if ($lineTotal <= 0) {
                throw new RuntimeException('A budget must have at least one line item before activation.');
            }

            if ($lineTotal > round((float) ($budget['total_amount'] ?? 0), 2)) {
                throw new RuntimeException('Budget line allocations cannot exceed the budget total.');
            }
        }

        $stmt = $this->db->prepare('UPDATE budgets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $budgetId]);
    }

    /**
     * @param array<string, mixed> $lineItem
     * @return array<string, mixed>
     */
    public function hydrateLineUsage(array $lineItem): array
    {
        $pendingAmount = $this->pendingLineAmount((int) ($lineItem['id'] ?? 0));
        $allocatedAmount = round((float) ($lineItem['allocated_amount'] ?? 0), 2);
        $spentAmount = round((float) ($lineItem['spent_amount'] ?? 0), 2);

        $lineItem['pending_amount'] = $pendingAmount;
        $lineItem['remaining_amount'] = round($allocatedAmount - $spentAmount - $pendingAmount, 2);

        return $lineItem;
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $date;
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
