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
