<?php

declare(strict_types=1);

namespace Involve\Repositories;

use DateTimeImmutable;
use PDO;

final class DashboardRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly OrganizationRepository $organizations,
        private readonly TransactionRepository $transactions
    ) {
    }

    public static function fromConnection(PDO $db): self
    {
        return new self($db, new OrganizationRepository($db), new TransactionRepository($db));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function organizationsForDashboard(): array
    {
        return $this->remember('organizations:directory:all_with_owner_names', 60, fn() => $this->organizations->allWithOwnerNames());
    }

    /**
     * @return list<int>
     */
    public function membershipIdsForUser(int $userId): array
    {
        return $this->organizations->membershipIdsForUser($userId);
    }

    /**
     * @return array<int, string>
     */
    public function joinRequestStatusForUser(int $userId): array
    {
        return $this->organizations->joinRequestStatusForUser($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeAnnouncements(string $activeCutoff, int $limit = 30): array
    {
        $cacheBucket = substr($activeCutoff, 0, 16);

        return $this->remember('dashboard:active_announcements:' . $cacheBucket . ':' . $limit, 60, fn() => (new AnnouncementRepository($this->db))->activeWithOrganization($activeCutoff, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentTransactions(string $fromDate, int $limit = 8): array
    {
        return $this->remember('dashboard:recent_transactions:' . $fromDate . ':' . $limit, 60, fn() => $this->transactions->recentWithOrganization($fromDate, $limit));
    }

    /**
     * @return array<string, mixed>|false
     */
    public function financialTotals(): array|false
    {
        return $this->remember('dashboard:financial_totals', 60, fn() => $this->transactions->totalsByType());
    }

    /**
     * @param list<int> $organizationIds
     * @return array{summary: list<array<string, mixed>>, pending_expense_count: int, active_budget_count: int, line_usage: list<array<string, mixed>>}
     */
    public function aggregateSections(array $organizationIds): array
    {
        $organizationIds = array_values(array_filter(array_map('intval', $organizationIds), static fn(int $id): bool => $id > 0));
        if ($organizationIds === []) {
            return [
                'summary' => [],
                'pending_expense_count' => 0,
                'active_budget_count' => 0,
                'line_usage' => [],
            ];
        }

        return $this->remember('dashboard:aggregates:' . md5(implode(',', $organizationIds)), 60, function () use ($organizationIds): array {
            return $this->profile('dashboard.aggregate_sections', function () use ($organizationIds): array {
                $placeholders = implode(',', array_fill(0, count($organizationIds), '?'));
                $summaryStmt = $this->db->prepare("SELECT o.id, o.name, o.logo_path, o.logo_crop_x, o.logo_crop_y, o.logo_zoom,
                    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
                    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
                    FROM organizations o
                    LEFT JOIN financial_transactions t ON t.organization_id = o.id
                    WHERE o.id IN ($placeholders)
                    GROUP BY o.id, o.name, o.logo_path
                    ORDER BY o.name");
                $summaryStmt->execute($organizationIds);

                $pendingExpenseStmt = $this->db->prepare("SELECT COUNT(*) FROM expense_requests WHERE organization_id IN ($placeholders) AND status = 'pending'");
                $pendingExpenseStmt->execute($organizationIds);

                $activeBudgetStmt = $this->db->prepare("SELECT COUNT(*) FROM budgets WHERE organization_id IN ($placeholders) AND status = 'active'");
                $activeBudgetStmt->execute($organizationIds);

                $budgetLineUsageStmt = $this->db->prepare("SELECT bli.allocated_amount, bli.spent_amount,
                        COALESCE(SUM(CASE WHEN er.status = 'pending' THEN er.amount ELSE 0 END), 0) AS pending_amount
                    FROM budget_line_items bli
                    JOIN budgets b ON b.id = bli.budget_id
                    LEFT JOIN expense_requests er ON er.budget_line_item_id = bli.id
                    WHERE b.organization_id IN ($placeholders)
                      AND b.status = 'active'
                    GROUP BY bli.id, bli.allocated_amount, bli.spent_amount");
                $budgetLineUsageStmt->execute($organizationIds);

                return [
                    'summary' => $summaryStmt->fetchAll() ?: [],
                    'pending_expense_count' => (int) $pendingExpenseStmt->fetchColumn(),
                    'active_budget_count' => (int) $activeBudgetStmt->fetchColumn(),
                    'line_usage' => $budgetLineUsageStmt->fetchAll() ?: [],
                ];
            });
        });
    }

    /**
     * @return array<string, mixed>|false
     */
    public function monthlyKpi(DateTimeImmutable $monthStart): array|false
    {
        $previousMonthStart = $monthStart->modify('-1 month');
        $nextMonthStart = $monthStart->modify('+1 month');

        return $this->remember('dashboard:kpi_monthly:' . $monthStart->format('Y-m-d'), 60, function () use ($monthStart, $nextMonthStart, $previousMonthStart): array|false {
            return $this->profile('dashboard.kpi_monthly', function () use ($monthStart, $nextMonthStart, $previousMonthStart): array|false {
                $stmt = $this->db->prepare("SELECT
                    COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS income_current,
                    COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS income_previous,
                    COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN 1 ELSE 0 END), 0) AS income_previous_count,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS expense_current,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS expense_previous,
                    COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN 1 ELSE 0 END), 0) AS expense_previous_count
                    FROM financial_transactions");
                $stmt->execute([
                    $monthStart->format('Y-m-d'),
                    $nextMonthStart->format('Y-m-d'),
                    $previousMonthStart->format('Y-m-d'),
                    $monthStart->format('Y-m-d'),
                    $previousMonthStart->format('Y-m-d'),
                    $monthStart->format('Y-m-d'),
                    $monthStart->format('Y-m-d'),
                    $nextMonthStart->format('Y-m-d'),
                    $previousMonthStart->format('Y-m-d'),
                    $monthStart->format('Y-m-d'),
                    $previousMonthStart->format('Y-m-d'),
                    $monthStart->format('Y-m-d'),
                ]);

                return $stmt->fetch();
            });
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(string $announcementCutoff, string $recentReportCutoffDate): array
    {
        return $this->profile('dashboard.recent_activity', function () use ($announcementCutoff, $recentReportCutoffDate): array {
            $stmt = $this->db->prepare("SELECT 'announcement' AS type, title AS label, created_at, organization_id FROM announcements WHERE created_at >= ?
                UNION ALL
                SELECT 'transaction' AS type, description AS label, created_at, organization_id FROM financial_transactions WHERE transaction_date >= ?
                ORDER BY created_at DESC
                LIMIT 16");
            $stmt->execute([$announcementCutoff, $recentReportCutoffDate]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function activityList(string $cutoff, int $limit, int $offset): array
    {
        return $this->profile('dashboard.api_activity_list', function () use ($cutoff, $limit, $offset): array {
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM (
                SELECT id FROM announcements WHERE created_at >= ?
                UNION ALL
                SELECT id FROM financial_transactions WHERE transaction_date >= ?
            ) activity_count");
            $countStmt->execute([$cutoff, $cutoff]);

            $stmt = $this->db->prepare("SELECT * FROM (
                SELECT 'announcement' AS type, a.id, a.title AS label, a.created_at, a.organization_id, o.name AS organization_name
                FROM announcements a
                JOIN organizations o ON o.id = a.organization_id
                WHERE a.created_at >= ?
                UNION ALL
                SELECT 'transaction' AS type, t.id, t.description AS label, t.created_at, t.organization_id, o.name AS organization_name
                FROM financial_transactions t
                JOIN organizations o ON o.id = t.organization_id
                WHERE t.transaction_date >= ?
            ) activity
            ORDER BY created_at DESC
            LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute([$cutoff, $cutoff]);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlyTrendRows(string $dbDriver): array
    {
        return $this->profile('dashboard.monthly_trends', function () use ($dbDriver): array {
            if ($dbDriver === 'mysql') {
                return $this->db->query("SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
                    FROM financial_transactions
                    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                    ORDER BY month DESC
                    LIMIT 6")->fetchAll() ?: [];
            }

            return $this->db->query("SELECT strftime('%Y-%m', transaction_date) AS month,
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
                FROM financial_transactions
                GROUP BY strftime('%Y-%m', transaction_date)
                ORDER BY month DESC
                LIMIT 6")->fetchAll() ?: [];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingAssignmentsForStudent(int $studentId): array
    {
        if ($studentId <= 0) {
            return [];
        }

        return $this->profile('dashboard.pending_assignments_for_student', function () use ($studentId): array {
            $stmt = $this->db->prepare("SELECT oa.id, oa.created_at, o.id AS organization_id, o.name AS organization_name
                FROM owner_assignments oa
                JOIN organizations o ON o.id = oa.organization_id
                WHERE oa.student_id = ? AND oa.status = 'pending'
                ORDER BY oa.created_at DESC");
            $stmt->execute([$studentId]);

            return $stmt->fetchAll() ?: [];
        });
    }

    public function pendingTransactionRequestCount(): int
    {
        return (int) $this->profile('dashboard.pending_transaction_request_count', fn() => $this->db->query("SELECT COUNT(*) FROM transaction_change_requests WHERE status = 'pending'")->fetchColumn());
    }

    private function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return function_exists('cacheRemember') ? \cacheRemember($key, $ttlSeconds, $callback) : $callback();
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
