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
    $dashboardRepository = Involve\Repositories\DashboardRepository::fromConnection($db);
    $orgs = $dashboardRepository->organizationsForDashboard();
    $joinedIds = $dashboardRepository->membershipIdsForUser((int) $user['id']);
    $orgs = sortOrganizationsForDashboardPanel($orgs, $user, $joinedIds);

    $joinRequestStatus = $dashboardRepository->joinRequestStatusForUser((int) $user['id']);

    $activeAnnouncementCutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $announcements = $dashboardRepository->activeAnnouncements($activeAnnouncementCutoff, 30);

    $transactions = $dashboardRepository->recentTransactions($recentReportCutoffDate, 8);

    $visibleOrganizationIds = array_values(array_unique(array_map(static fn(array $org): int => (int) $org['id'], $orgs)));
    $summary = [];
    $budgetFlowActiveBudgetCount = 0;
    $budgetFlowPendingExpenseRequestCount = 0;
    $budgetFlowAttentionCount = 0;
    $budgetFlowCriticalLineCount = 0;
    $budgetFlowWatchLineCount = 0;
    if (count($visibleOrganizationIds) > 0) {
        $aggregateData = $dashboardRepository->aggregateSections($visibleOrganizationIds);

        $summary = $aggregateData['summary'];
        $budgetFlowPendingExpenseRequestCount = (int) $aggregateData['pending_expense_count'];
        $budgetFlowActiveBudgetCount = (int) $aggregateData['active_budget_count'];
        foreach ($aggregateData['line_usage'] as $lineUsage) {
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

    $kpi = $dashboardRepository->financialTotals();

    $kpiIncome = (float) ($kpi['income'] ?? 0);
    $kpiExpense = (float) ($kpi['expense'] ?? 0);
    $kpiBalance = $kpiIncome - $kpiExpense;

    $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
    $kpiMonthly = $dashboardRepository->monthlyKpi($monthStart);

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

    $activity = $dashboardRepository->recentActivity($announcementCutoff, $recentReportCutoffDate);

    $dbDriver = (string) (($config['db']['driver'] ?? 'sqlite'));
    $trendRows = $dashboardRepository->monthlyTrendRows($dbDriver);

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
        $pendingAssignments = $dashboardRepository->pendingAssignmentsForStudent((int) $user['id']);
    }

    $pendingAssignmentsPagination = paginateArray($pendingAssignments, 'pg_dash_assign', 2);
    $pendingAssignments = $pendingAssignmentsPagination['items'];
    $pendingTransactionRequestCount = $dashboardRepository->pendingTransactionRequestCount();

    $dashboardOrganizationPreviewBaseLimit = 3;
    $dashboardOrganizationPreviewMaxLimit = 10;
    $dashboardAnnouncementPreviewBaseLimit = 3;
    $dashboardAnnouncementPreviewMaxLimit = 8;
    $dashboardActivityPreviewBaseLimit = 2;
    $dashboardActivityPreviewMaxLimit = 6;
    $dashboardSummaryPreviewLimit = 4;
    $recentReportsDisplayLimit = 8;

    $dashboardOrganizationsPreview = array_slice($orgs, 0, $dashboardOrganizationPreviewMaxLimit);
    $summaryAll = $summary;
    $summaryPagination = paginateArray($summaryAll, 'pg_dash_summary', $dashboardSummaryPreviewLimit);
    $summary = $summaryPagination['items'];
    $activityPagination = paginateArray($activity, 'pg_dash_activity', $dashboardActivityPreviewMaxLimit);
    $activity = $activityPagination['items'];
    $latestAnnouncementsPreview = array_slice($announcements, 0, $dashboardAnnouncementPreviewMaxLimit);
    $activityPreview = array_slice($activity, 0, $dashboardActivityPreviewMaxLimit);
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
        'dashboardOrganizationPreviewBaseLimit',
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
        'dashboardAnnouncementPreviewBaseLimit',
        'dashboardActivityPreviewBaseLimit',
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
        'pendingTransactionRequestCount',
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
