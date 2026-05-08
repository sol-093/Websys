<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - DASHBOARD DATA
 * ================================================
 *
 * SECTION MAP:
 * 1. KPI Totals and Trends
 * 2. Ranking and Chart Data
 * 3. Announcements and Reports
 * 4. Organization Panels
 *
 * WORK GUIDE:
 * - Edit this file when changing dashboard queries, metrics, date windows, or chart data.
 * ================================================
 */

function buildDashboardViewData(PDO $db, array $user, array $config, string $announcementCutoff, string $recentReportCutoffDate): array
{
    $orgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();
    $membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
    $membershipStmt->execute([(int) $user['id']]);
    $joinedIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));
    $orgs = sortOrganizationsForDashboardPanel($orgs, $user, $joinedIds);

    $requestStmt = $db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
    $requestStmt->execute([(int) $user['id']]);
    $joinRequestStatus = [];
    foreach ($requestStmt->fetchAll() as $req) {
        $joinRequestStatus[(int) $req['organization_id']] = (string) $req['status'];
    }

    $activeAnnouncementCutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $announcementsStmt = $db->prepare('SELECT a.*, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE (a.expires_at IS NULL OR a.expires_at >= ?)
        ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC
        LIMIT 30');
    $announcementsStmt->execute([$activeAnnouncementCutoff]);
    $announcements = $announcementsStmt->fetchAll();

    $transactionsStmt = $db->prepare('SELECT t.*, o.name AS organization_name, o.logo_path AS organization_logo_path, o.logo_crop_x AS organization_logo_crop_x, o.logo_crop_y AS organization_logo_crop_y, o.logo_zoom AS organization_logo_zoom
        FROM financial_transactions t
        JOIN organizations o ON o.id = t.organization_id
        WHERE t.transaction_date >= ?
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 8');
    $transactionsStmt->execute([$recentReportCutoffDate]);
    $transactions = $transactionsStmt->fetchAll();

    $visibleOrganizationIds = array_values(array_unique(array_map(static fn(array $org): int => (int) $org['id'], $orgs)));
    $summary = [];
    $budgetFlowActiveBudgetCount = 0;
    $budgetFlowPendingExpenseRequestCount = 0;
    $budgetFlowAttentionCount = 0;
    $budgetFlowCriticalLineCount = 0;
    $budgetFlowWatchLineCount = 0;
    if (count($visibleOrganizationIds) > 0) {
        $summaryPlaceholders = implode(',', array_fill(0, count($visibleOrganizationIds), '?'));
        $summaryStmt = $db->prepare("SELECT o.id, o.name, o.logo_path, o.logo_crop_x, o.logo_crop_y, o.logo_zoom,
            COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_expense
            FROM organizations o
            LEFT JOIN financial_transactions t ON t.organization_id = o.id
            WHERE o.id IN ($summaryPlaceholders)
            GROUP BY o.id, o.name, o.logo_path
            ORDER BY o.name");
        $summaryStmt->execute($visibleOrganizationIds);
        $summary = $summaryStmt->fetchAll();

        $pendingExpenseStmt = $db->prepare("SELECT COUNT(*) FROM expense_requests WHERE organization_id IN ($summaryPlaceholders) AND status = 'pending'");
        $pendingExpenseStmt->execute($visibleOrganizationIds);
        $budgetFlowPendingExpenseRequestCount = (int) $pendingExpenseStmt->fetchColumn();

        $activeBudgetStmt = $db->prepare("SELECT COUNT(*) FROM budgets WHERE organization_id IN ($summaryPlaceholders) AND status = 'active'");
        $activeBudgetStmt->execute($visibleOrganizationIds);
        $budgetFlowActiveBudgetCount = (int) $activeBudgetStmt->fetchColumn();

        $budgetLineUsageStmt = $db->prepare("SELECT bli.allocated_amount, bli.spent_amount,
                COALESCE(SUM(CASE WHEN er.status = 'pending' THEN er.amount ELSE 0 END), 0) AS pending_amount
            FROM budget_line_items bli
            JOIN budgets b ON b.id = bli.budget_id
            LEFT JOIN expense_requests er ON er.budget_line_item_id = bli.id
            WHERE b.organization_id IN ($summaryPlaceholders)
              AND b.status = 'active'
            GROUP BY bli.id, bli.allocated_amount, bli.spent_amount");
        $budgetLineUsageStmt->execute($visibleOrganizationIds);
        foreach ($budgetLineUsageStmt->fetchAll() ?: [] as $lineUsage) {
            $allocated = (float) ($lineUsage['allocated_amount'] ?? 0);
            if ($allocated <= 0) {
                continue;
            }
            $usedRatio = ((float) ($lineUsage['spent_amount'] ?? 0) + (float) ($lineUsage['pending_amount'] ?? 0)) / $allocated;
            if ($usedRatio >= 0.9) {
                $budgetFlowCriticalLineCount++;
                $budgetFlowAttentionCount++;
            } elseif ($usedRatio >= 0.75) {
                $budgetFlowWatchLineCount++;
                $budgetFlowAttentionCount++;
            }
        }
    }

    $kpi = $db->query("SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
        FROM financial_transactions")->fetch();

    $kpiIncome = (float) ($kpi['income'] ?? 0);
    $kpiExpense = (float) ($kpi['expense'] ?? 0);
    $kpiBalance = $kpiIncome - $kpiExpense;

    $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
    $previousMonthStart = $monthStart->modify('-1 month');
    $nextMonthStart = $monthStart->modify('+1 month');

    $kpiMonthlyStmt = $db->prepare("SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS income_current,
        COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS income_previous,
        COALESCE(SUM(CASE WHEN type = 'income' AND transaction_date >= ? AND transaction_date < ? THEN 1 ELSE 0 END), 0) AS income_previous_count,
        COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS expense_current,
        COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN amount ELSE 0 END), 0) AS expense_previous
        , COALESCE(SUM(CASE WHEN type = 'expense' AND transaction_date >= ? AND transaction_date < ? THEN 1 ELSE 0 END), 0) AS expense_previous_count
        FROM financial_transactions");
    $kpiMonthlyStmt->execute([
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
    $kpiMonthly = $kpiMonthlyStmt->fetch();

    $incomeCurrentMonthTotal = (float) ($kpiMonthly['income_current'] ?? 0);
    $incomePreviousMonthTotal = (float) ($kpiMonthly['income_previous'] ?? 0);
    $incomePreviousMonthCount = (int) ($kpiMonthly['income_previous_count'] ?? 0);
    $expenseCurrentMonthTotal = (float) ($kpiMonthly['expense_current'] ?? 0);
    $expensePreviousMonthTotal = (float) ($kpiMonthly['expense_previous'] ?? 0);
    $expensePreviousMonthCount = (int) ($kpiMonthly['expense_previous_count'] ?? 0);
    $previousMonthTransactionCount = $incomePreviousMonthCount + $expensePreviousMonthCount;
    $balanceCurrentMonthTotal = $incomeCurrentMonthTotal - $expenseCurrentMonthTotal;
    $balancePreviousMonthTotal = $incomePreviousMonthTotal - $expensePreviousMonthTotal;

    $income_delta_pct = (($incomeCurrentMonthTotal - $incomePreviousMonthTotal) / max($incomePreviousMonthTotal, 1)) * 100;
    $expenses_delta_pct = (($expenseCurrentMonthTotal - $expensePreviousMonthTotal) / max($expensePreviousMonthTotal, 1)) * 100;
    $balance_delta_pct = (($balanceCurrentMonthTotal - $balancePreviousMonthTotal) / max($balancePreviousMonthTotal, 1)) * 100;

    $activityStmt = $db->prepare("SELECT 'announcement' AS type, title AS label, created_at, organization_id FROM announcements WHERE created_at >= ?
        UNION ALL
        SELECT 'transaction' AS type, description AS label, created_at, organization_id FROM financial_transactions WHERE transaction_date >= ?
        ORDER BY created_at DESC
        LIMIT 16");
    $activityStmt->execute([$announcementCutoff, $recentReportCutoffDate]);
    $activity = $activityStmt->fetchAll();

    $dbDriver = (string) (($config['db']['driver'] ?? 'sqlite'));
    if ($dbDriver === 'mysql') {
        $trendRows = $db->query("SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
            FROM financial_transactions
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6")->fetchAll();
    } else {
        $trendRows = $db->query("SELECT strftime('%Y-%m', transaction_date) AS month,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense
            FROM financial_transactions
            GROUP BY strftime('%Y-%m', transaction_date)
            ORDER BY month DESC
            LIMIT 6")->fetchAll();
    }

    $trendRows = array_reverse($trendRows);
    $trendLabels = array_map(static fn(array $r): string => (string) $r['month'], $trendRows);
    $trendIncome = array_map(static fn(array $r): float => (float) $r['income'], $trendRows);
    $trendExpense = array_map(static fn(array $r): float => (float) $r['expense'], $trendRows);

    $trendPointCount = count($trendRows);
    $latestTrendNet = 0.0;
    $latestTrendDelta = null;
    $peakExpenseMonth = '-';
    $peakExpenseValue = 0.0;
    $healthyMonthCount = 0;

    if ($trendPointCount > 0) {
        $latest = $trendRows[$trendPointCount - 1];
        $latestTrendNet = (float) $latest['income'] - (float) $latest['expense'];

        if ($trendPointCount > 1) {
            $previous = $trendRows[$trendPointCount - 2];
            $previousTrendNet = (float) $previous['income'] - (float) $previous['expense'];
            $latestTrendDelta = $latestTrendNet - $previousTrendNet;
        }

        foreach ($trendRows as $row) {
            $income = (float) $row['income'];
            $expense = (float) $row['expense'];
            if ($income >= $expense) {
                $healthyMonthCount++;
            }
            if ($expense >= $peakExpenseValue) {
                $peakExpenseValue = $expense;
                $peakExpenseMonth = (string) $row['month'];
            }
        }
    }

    $latestTrendDirectionLabel = $latestTrendDelta === null
        ? 'No prior month baseline'
        : ($latestTrendDelta >= 0 ? 'Net improved vs previous month' : 'Net declined vs previous month');

    $pendingAssignments = [];
    if (in_array($user['role'], ['student', 'owner'], true)) {
        $stmt = $db->prepare("SELECT oa.id, oa.created_at, o.id AS organization_id, o.name AS organization_name
            FROM owner_assignments oa
            JOIN organizations o ON o.id = oa.organization_id
            WHERE oa.student_id = ? AND oa.status = 'pending'
            ORDER BY oa.created_at DESC");
        $stmt->execute([(int) $user['id']]);
        $pendingAssignments = $stmt->fetchAll();
    }

    $pendingAssignmentsPagination = paginateArray($pendingAssignments, 'pg_dash_assign', 2);
    $pendingAssignments = $pendingAssignmentsPagination['items'];
    $pendingTransactionRequestCount = (int) $db->query("SELECT COUNT(*) FROM transaction_change_requests WHERE status = 'pending'")->fetchColumn();
    $dashboardOrganizationsPreview = array_slice($orgs, 0, 3);
    $summaryAll = $summary;
    $summaryPagination = paginateArray($summaryAll, 'pg_dash_summary', 4);
    $summary = $summaryPagination['items'];
    $activityPagination = paginateArray($activity, 'pg_dash_activity', 2);
    $activity = $activityPagination['items'];
    $latestAnnouncementsPreview = array_slice($announcements, 0, 3);
    $activityPreview = array_slice($activity, 0, 2);
    $recentReportsDisplayLimit = 8;
    $transactions = array_slice($transactions, 0, $recentReportsDisplayLimit);
    $dashboardTimestamp = (new DateTimeImmutable('now'))->format('l, F j, Y | g:i A');
    $expenseRatio = $kpiIncome > 0 ? (int) min(100, round(($kpiExpense / $kpiIncome) * 100)) : 0;
    $balanceRatio = $kpiIncome > 0 ? (int) max(0, min(100, round((max($kpiBalance, 0) / $kpiIncome) * 100))) : 0;
    $recentReportCount = count($transactions);
    $latestAnnouncementCount = count($latestAnnouncementsPreview);
    $pendingAssignmentCount = count($pendingAssignments);

    $summaryChartRows = array_slice($summaryAll, 0, 8);
    $summaryIncomeTotal = (float) array_reduce(
        $summaryAll,
        static fn(float $carry, array $row): float => $carry + (float) $row['total_income'],
        0.0
    );
    $summaryExpenseTotal = (float) array_reduce(
        $summaryAll,
        static fn(float $carry, array $row): float => $carry + (float) $row['total_expense'],
        0.0
    );
    $summaryNetTotal = $summaryIncomeTotal - $summaryExpenseTotal;

    $summaryRankingRows = array_map(
        static function (array $row): array {
            $income = (float) $row['total_income'];
            $expense = (float) $row['total_expense'];
            $balance = $income - $expense;
            $expenseRatio = $income > 0 ? ($expense / $income) : 1.0;

            $status = 'Healthy';
            $statusClass = 'text-emerald-300 border-emerald-300/40 bg-emerald-500/10';
            if ($balance < 0) {
                $status = 'Risk';
                $statusClass = 'text-red-300 border-red-300/40 bg-red-500/10';
            } elseif ($expenseRatio >= 0.9) {
                $status = 'Watch';
                $statusClass = 'text-amber-300 border-amber-300/40 bg-amber-500/10';
            }

            return [
                'name' => (string) $row['name'],
                'income' => $income,
                'expense' => $expense,
                'balance' => $balance,
                'status' => $status,
                'status_class' => $statusClass,
                'expense_ratio' => $expenseRatio,
            ];
        },
        $summaryAll
    );

    usort(
        $summaryRankingRows,
        static fn(array $a, array $b): int => $b['balance'] <=> $a['balance']
    );

    $summaryRankingTop = array_slice($summaryRankingRows, 0, 8);
    $summaryRankingLabels = array_map(static fn(array $row): string => (string) $row['name'], $summaryRankingTop);
    $summaryRankingBalances = array_map(static fn(array $row): float => (float) $row['balance'], $summaryRankingTop);
    $summaryExpenseRows = $summaryRankingRows;
    usort(
        $summaryExpenseRows,
        static fn(array $a, array $b): int => $b['expense'] <=> $a['expense']
    );
    $summaryExpenseTop = array_slice($summaryExpenseRows, 0, 8);
    $summaryExpenseLabels = array_map(static fn(array $row): string => (string) $row['name'], $summaryExpenseTop);
    $summaryExpenseValues = array_map(static fn(array $row): float => (float) $row['expense'], $summaryExpenseTop);

    $summaryAttentionRows = array_values(array_filter(
        $summaryRankingRows,
        static fn(array $row): bool => $row['balance'] < 0 || $row['expense_ratio'] >= 0.9
    ));
    $summaryAttentionRows = array_slice($summaryAttentionRows, 0, 4);

    $topPerformer = $summaryRankingRows[0] ?? null;
    $highestPressure = null;
    foreach ($summaryRankingRows as $row) {
        if ($highestPressure === null || $row['expense_ratio'] > $highestPressure['expense_ratio']) {
            $highestPressure = $row;
        }
    }
    $averageNet = count($summaryRankingRows) > 0
        ? (float) (array_sum(array_map(static fn(array $row): float => (float) $row['balance'], $summaryRankingRows)) / count($summaryRankingRows))
        : 0.0;

    return compact(
        'activity',
        'activityPagination',
        'activityPreview',
        'announcements',
        'averageNet',
        'balanceRatio',
        'budgetFlowActiveBudgetCount',
        'budgetFlowAttentionCount',
        'budgetFlowCriticalLineCount',
        'budgetFlowPendingExpenseRequestCount',
        'budgetFlowWatchLineCount',
        'dashboardOrganizationsPreview',
        'dashboardTimestamp',
        'expenseRatio',
        'healthyMonthCount',
        'highestPressure',
        'incomePreviousMonthCount',
        'incomePreviousMonthTotal',
        'income_delta_pct',
        'joinedIds',
        'joinRequestStatus',
        'previousMonthTransactionCount',
        'balancePreviousMonthTotal',
        'balance_delta_pct',
        'kpiBalance',
        'kpiExpense',
        'kpiIncome',
        'latestAnnouncementCount',
        'latestAnnouncementsPreview',
        'latestTrendDelta',
        'latestTrendDirectionLabel',
        'latestTrendNet',
        'orgs',
        'peakExpenseMonth',
        'peakExpenseValue',
        'pendingAssignmentCount',
        'pendingAssignments',
        'pendingAssignmentsPagination',
        'expensePreviousMonthCount',
        'expensePreviousMonthTotal',
        'expenses_delta_pct',
        'recentReportCount',
        'summary',
        'summaryAll',
        'summaryAttentionRows',
        'summaryChartRows',
        'summaryExpenseTotal',
        'summaryExpenseLabels',
        'summaryExpenseTop',
        'summaryExpenseValues',
        'summaryIncomeTotal',
        'summaryNetTotal',
        'summaryPagination',
        'summaryRankingBalances',
        'summaryRankingLabels',
        'summaryRankingRows',
        'summaryRankingTop',
        'topPerformer',
        'transactions',
        'trendExpense',
        'trendIncome',
        'trendLabels',
        'trendPointCount',
        'trendRows'
    );
}
