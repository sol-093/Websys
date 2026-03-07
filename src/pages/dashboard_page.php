<?php

declare(strict_types=1);

function handleDashboardPage(array $dashboardData, array $user): void
{
    extract($dashboardData, EXTR_OVERWRITE);

    renderHeader('Dashboard');
    require __DIR__ . '/dashboard_page_markup.php';

    $dashboardClientData = [
        'trendLabels' => $trendLabels,
        'trendIncome' => $trendIncome,
        'trendExpense' => $trendExpense,
        'summaryRankingLabels' => $summaryRankingLabels,
        'summaryRankingBalances' => $summaryRankingBalances,
    ];
    ?>
    <script id="dashboard-client-data" type="application/json"><?= json_encode($dashboardClientData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="static/js/dashboard-page.js"></script>
    <?php

    renderFooter();
    exit;
}
