<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - DASHBOARD PAGE COMPOSITION
 * ================================================
 *
 * SECTION MAP:
 * 1. Local Formatter Closures
 * 2. Dashboard Markup Include
 * 3. Client Data JSON and Chart Scripts
 *
 * WORK GUIDE:
 * - Edit this file for dashboard composition.
 * - Edit data.php for queries and markup.php for sections.
 * ================================================
 */

function handleDashboardPage(array $dashboardData, array $user): void
{
    extract($dashboardData, EXTR_OVERWRITE);

    $renderDeltaBadge = static function (float $deltaPct, bool $hasPreviousData): void {
        if (!$hasPreviousData || abs($deltaPct) < 0.0001) {
            echo '<div class="text-xs text-gray-500 mt-1">&mdash;</div>';
            return;
        }

        if ($deltaPct > 0) {
            echo '<div class="text-xs text-emerald-600 mt-1">&uarr; ' . number_format($deltaPct, 1) . '%</div>';
            return;
        }

        echo '<div class="text-xs text-red-500 mt-1">&darr; ' . number_format(abs($deltaPct), 1) . '%</div>';
    };

    $formatAnnouncementExpiry = static function (?string $expiresAt): string {
        $expiresAt = trim((string) ($expiresAt ?? ''));
        if ($expiresAt === '') {
            return 'No expiry';
        }

        try {
            $now = new DateTimeImmutable('now');
            $expiry = new DateTimeImmutable($expiresAt);
            $daysLeft = (int) $now->diff($expiry)->format('%r%a');
        } catch (Throwable) {
            return 'Expires soon';
        }

        if ($daysLeft < 0) {
            return 'Expired';
        }

        if ($daysLeft === 0) {
            return 'Expires today';
        }

        if ($daysLeft === 1) {
            return 'Expires in 1 day';
        }

        return 'Expires in ' . $daysLeft . ' days';
    };

    renderHeader('Dashboard');
    require __DIR__ . '/markup.php';

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
    <script src="assets/js/dashboard-page.js"></script>
    <?php

    renderFooter();
    exit;
}
