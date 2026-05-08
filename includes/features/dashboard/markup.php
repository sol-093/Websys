<?php /*
===============================================
INVOLVE - DASHBOARD MARKUP
===============================================

SECTION MAP:
1. Hero and KPI Cards
2. Charts and Budget Transparency
3. Organization Panels
4. Announcements and Transaction Sections

WORK GUIDE:
- Edit this file for dashboard HTML only.
- Keep data preparation in data.php.
===============================================
*/ ?>
<div class="dashboard-shell space-y-3">
    <section class="grid xl:grid-cols-12 gap-3">
        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="dashboard-kicker">Overview</div>
                    <h1 class="dashboard-headline modern-title">Operations are on track, budgets are transparent, and every organization is in sync.</h1>
                    <p id="dashboardWelcomeMessage" class="dashboard-copy mt-3">Welcome, <?= e($user['name']) ?>. Track collections, spending, announcements, and ownership activity from one focused workspace.</p>
                </div>
                <div id="dashboardLiveTimestamp" class="dashboard-stamp" data-live-dashboard-time="1"><?= e($dashboardTimestamp) ?></div>
            </div>
            <div class="dashboard-metric-grid mt-4">
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value">&#8369;<?= number_format($kpiIncome, 2) ?></div>
                    <?php $renderDeltaBadge((float) $income_delta_pct, ((int) $incomePreviousMonthCount) > 0); ?>
                    <div class="dashboard-metric-label">Total income recorded</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value <?= $kpiBalance >= 0 ? 'text-green-300' : 'text-red-300' ?>">&#8369;<?= number_format($kpiBalance, 2) ?></div>
                    <?php $renderDeltaBadge((float) $balance_delta_pct, ((int) $previousMonthTransactionCount) > 0); ?>
                    <div class="dashboard-metric-label">Current net balance</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= count($orgs) ?></div>
                    <div class="dashboard-metric-label">Organizations in view</div>
                </div>
            </div>
        </div>

        <div id="dashboardBudgetTransparencySection" class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <h2 class="dashboard-section-title">Finance status</h2>
            <p class="dashboard-section-copy mt-1">A compact reading of spend, balance, and workload based on live records.</p>
            <div class="mt-4 space-y-3">
                <div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Expense share of income</span>
                        <span class="dashboard-stat-value"><?= $expenseRatio ?>%</span>
                    </div>
                    <div class="dashboard-progress mt-3"><span style="width: 0%" data-width="<?= (int) $expenseRatio ?>"></span></div>
                </div>
                <div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Balance retained</span>
                        <span class="dashboard-stat-value"><?= $balanceRatio ?>%</span>
                    </div>
                    <div class="dashboard-progress mt-3"><span style="width: 0%" data-width="<?= (int) $balanceRatio ?>"></span></div>
                </div>
                <div class="dashboard-stat-list pt-1">
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Latest announcements</span>
                        <span class="dashboard-stat-value"><?= $latestAnnouncementCount ?></span>
                    </div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Active budgets</span>
                        <span class="dashboard-stat-value"><?= (int) $budgetFlowActiveBudgetCount ?></span>
                    </div>
                    <div class="dashboard-stat-row">
                        <span class="dashboard-stat-label">Budget lines needing attention</span>
                        <span class="dashboard-stat-value <?= (int) $budgetFlowCriticalLineCount > 0 ? 'text-red-300' : ((int) $budgetFlowWatchLineCount > 0 ? 'text-amber-300' : 'text-green-300') ?>"><?= (int) $budgetFlowAttentionCount ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (count($pendingAssignments) > 0): ?>
        <section id="pending-assignments" class="glass dashboard-panel p-4 md:p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="dashboard-section-title">Pending assignments</h2>
                    <p class="dashboard-section-copy mt-1">Assignments waiting for a student response.</p>
                </div>
                <div class="dashboard-stamp"><?= count($pendingAssignments) ?> awaiting action</div>
            </div>
            <div class="grid md:grid-cols-2 gap-2 mt-3">
                <?php foreach ($pendingAssignments as $assignment): ?>
                    <div class="dashboard-feed-item flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="w-full sm:w-auto">
                            <div class="font-medium"><?= e($assignment['organization_name']) ?></div>
                            <div class="dashboard-feed-meta mt-1">Assigned on <?= e($assignment['created_at']) ?></div>
                        </div>
                        <div class="flex gap-2">
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="accept">
                                <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Accept</span></span></button>
                            </form>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="respond_owner_assignment">
                                <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
                                <input type="hidden" name="decision" value="decline">
                                <button class="bg-red-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Decline</span></span></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php renderPagination($pendingAssignmentsPagination); ?>
        </section>
    <?php endif; ?>

    <section class="grid xl:grid-cols-12 gap-3">
        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Monthly trend</h2>
                    <p class="dashboard-section-copy mt-1">Income and expense totals by month.</p>
                </div>
                <div class="dashboard-stamp"><?= $recentReportCount ?> recent reports tracked</div>
            </div>
            <div class="dashboard-metric-grid mb-3">
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value text-red-300">&#8369;<?= number_format($kpiExpense, 2) ?></div>
                    <?php $renderDeltaBadge((float) $expenses_delta_pct, ((int) $expensePreviousMonthCount) > 0); ?>
                    <div class="dashboard-metric-label">Expense total</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= count($activityPreview) ?></div>
                    <div class="dashboard-metric-label">Activity items loaded</div>
                </div>
                <div class="dashboard-metric-card">
                    <div class="dashboard-metric-value"><?= $latestAnnouncementCount ?></div>
                    <div class="dashboard-metric-label">Announcement highlights</div>
                </div>
            </div>
            <canvas id="trendChart" height="112"></canvas>
            <p id="trendChartFallback" class="hidden mt-3 rounded border border-amber-300/40 bg-amber-500/15 px-3 py-2 text-sm text-amber-100" role="status">
                The trend graph is temporarily unavailable. Dashboard data remains up to date below.
            </p>
            <div class="trend-insight-grid mt-5 grid md:grid-cols-3 gap-2">
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot"></span>
                    <div>
                        <div class="dashboard-feed-title">Current month net</div>
                        <div class="dashboard-feed-meta mt-1 <?= $latestTrendNet >= 0 ? 'text-green-300' : 'text-red-300' ?>">
                            &#8369;<?= number_format($latestTrendNet, 2) ?>
                        </div>
                        <div class="dashboard-feed-body mt-1"><?= e($latestTrendDirectionLabel) ?></div>
                    </div>
                </div>
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot warn"></span>
                    <div>
                        <div class="dashboard-feed-title">Peak expense month</div>
                        <div class="dashboard-feed-meta mt-1"><?= e($peakExpenseMonth) ?></div>
                        <div class="dashboard-feed-body mt-1">&#8369;<?= number_format($peakExpenseValue, 2) ?> spent</div>
                    </div>
                </div>
                <div class="dashboard-feed-item trend-insight-card">
                    <span class="dashboard-feed-dot"></span>
                    <div>
                        <div class="dashboard-feed-title">Healthy months</div>
                        <div class="dashboard-feed-meta mt-1"><?= $healthyMonthCount ?> of <?= $trendPointCount ?></div>
                        <div class="dashboard-feed-body mt-1">Months where income met or exceeded expense.</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="dashboardAnnouncementsSection" class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <h2 class="dashboard-section-title">Live activity</h2>
                    <p class="dashboard-section-copy mt-1">Recent announcements and audit items.</p>
                </div>
                <button type="button" id="openAnnouncementsModalQuick" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View all</span></span></button>
            </div>
            <div class="space-y-2">
                <?php foreach ($latestAnnouncementsPreview as $item): ?>
                    <?php $feedDotClass = (int) ($item['is_pinned'] ?? 0) === 1 ? 'dashboard-feed-dot warn' : 'dashboard-feed-dot'; ?>
                    <div class="dashboard-feed-item">
                        <span class="<?= e($feedDotClass) ?>"></span>
                        <div>
                            <div class="dashboard-feed-title flex items-center gap-2"><?= e($item['title']) ?>
                                <?php if (trim((string) ($item['label'] ?? '')) !== ''): ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-300/40"><?= e((string) $item['label']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dashboard-feed-meta mt-1"><?= e($item['organization_name']) ?> &middot; <?= e($formatAnnouncementExpiry((string) ($item['expires_at'] ?? null))) ?></div>
                            <div class="dashboard-feed-body mt-1"><?= e($item['content']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($activityPreview as $item): ?>
                    <div class="dashboard-feed-item">
                        <span class="dashboard-feed-dot"></span>
                        <div>
                            <div class="dashboard-feed-title"><?= e($item['label']) ?></div>
                            <div class="dashboard-feed-meta mt-1"><?= e($item['type']) ?> &middot; <?= e($item['created_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($latestAnnouncementsPreview) === 0 && count($activityPreview) === 0): ?>
                    <p class="dashboard-section-copy">No recent announcements or activity items are available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Organizations</h2>
                    <p class="dashboard-section-copy mt-1">Current organizations and join eligibility.</p>
                </div>
                <button type="button" id="openOrganizationsModal" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View all</span></span></button>
            </div>
            <div class="space-y-2">
                <?php foreach ($dashboardOrganizationsPreview as $org): ?>
                    <div class="dashboard-feed-item flex-col lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3 text-left">
                            <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                            <div>
                                <div class="dashboard-feed-title"><?= e($org['name']) ?></div>
                                <div class="dashboard-feed-body mt-1"><?= e($org['description']) ?></div>
                                <div class="dashboard-feed-meta mt-2">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                                <div class="dashboard-feed-meta mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                            </div>
                        </div>
                        <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="join_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <?php
                                    $orgId = (int) $org['id'];
                                    $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                    $isJoined = in_array($orgId, $joinedIds, true);
                                    $canJoin = canUserJoinOrganization($org, $user);
                                    $disabled = $isJoined || $requestStatus === 'pending' || !$canJoin;
                                    if (!$canJoin) {
                                        $btnClass = 'bg-slate-200/40 border-slate-300/60 text-slate-600';
                                        $label = getJoinRestrictionLabel($org);
                                    } elseif ($isJoined) {
                                        $btnClass = 'bg-white/10 border-emerald-200/30 text-slate-700';
                                        $label = 'Joined';
                                    } elseif ($requestStatus === 'pending') {
                                        $btnClass = 'bg-amber-500/25 border-amber-300/50 text-white-900';
                                        $label = 'Requested';
                                    } else {
                                        $btnClass = 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35';
                                        $label = 'Request Join';
                                    }
                                ?>
                                <button class="inline-flex items-center justify-center whitespace-nowrap min-w-[5rem] px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                    <span><?= $label ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4 overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Recent reports</h2>
                    <p class="dashboard-section-copy mt-1">Latest income and expense entries with receipt visibility.</p>
                </div>
                <div class="dashboard-stamp">
                    <span class="dashboard-desktop-only">Showing <?= $recentReportCount ?> latest items</span>
                    <span class="dashboard-mobile-only">Showing <?= min(5, $recentReportCount) ?> latest items</span>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="dashboard-table dashboard-recent-reports-table w-full text-sm table-fixed">
                    <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 w-[20%]">Date</th>
                        <th class="w-[30%]">Organization</th>
                        <th class="w-[16%]">Type</th>
                        <th class="w-[20%]">Amount</th>
                        <th class="w-[14%]">Receipt</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $index => $tx): ?>
                        <tr class="border-b <?= $index >= 5 ? 'dashboard-mobile-trim' : '' ?>">
                            <td class="py-2"><?= e(date('F d, Y', strtotime((string)$tx['transaction_date']))) ?></td>
                            <td>
                                <span class="inline-flex items-center gap-2">
                                    <?= renderProfileMedia((string) ($tx['organization_name'] ?? ''), (string) ($tx['organization_logo_path'] ?? ''), 'organization', 'xs', (float) ($tx['organization_logo_crop_x'] ?? 50), (float) ($tx['organization_logo_crop_y'] ?? 50), (float) ($tx['organization_logo_zoom'] ?? 1)) ?>
                                    <span><?= e($tx['organization_name']) ?></span>
                                </span>
                            </td>
                            <td class="<?= $tx['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>"><?= e($tx['type']) ?></td>
                            <td>&#8369;<?= number_format((float) $tx['amount'], 2) ?></td>
                            <td>
                                <?php if (!empty($tx['receipt_path'])): ?>
                                    <a class="text-indigo-100 underline" target="_blank" href="<?= e($tx['receipt_path']) ?>"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></a>
                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass dashboard-panel xl:col-span-12 p-4 md:p-4 overflow-hidden">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-3">
                <div>
                    <h2 class="dashboard-section-title">Financial summary by organization</h2>
                    <p class="dashboard-section-copy mt-1">Income, expense, and balance grouped by organization.</p>
                </div>
                <button type="button" id="openFinancialSummaryModal" class="text-xs underline text-indigo-100"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View charts</span></span></button>
            </div>
            <div class="table-wrapper">
                <table class="dashboard-table dashboard-summary-table w-full text-sm table-fixed">
                    <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 pr-4 w-[46%]">Organization</th>
                        <th class="py-2 pr-3 w-[18%]">Income</th>
                        <th class="py-2 pr-3 w-[18%]">Expense</th>
                        <th class="py-2 w-[18%]">Balance</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summary as $row): ?>
                        <?php $balance = (float) $row['total_income'] - (float) $row['total_expense']; ?>
                        <tr class="border-b">
                            <td class="py-2 pr-4">
                                <span class="inline-flex items-center gap-2">
                                    <?= renderProfileMedia((string) ($row['name'] ?? ''), (string) ($row['logo_path'] ?? ''), 'organization', 'xs', (float) ($row['logo_crop_x'] ?? 50), (float) ($row['logo_crop_y'] ?? 50), (float) ($row['logo_zoom'] ?? 1)) ?>
                                    <span><?= e($row['name']) ?></span>
                                </span>
                            </td>
                            <td class="py-2 pr-3 text-green-700 whitespace-nowrap">&#8369;<?= number_format((float) $row['total_income'], 2) ?></td>
                            <td class="py-2 pr-3 text-red-700 whitespace-nowrap">&#8369;<?= number_format((float) $row['total_expense'], 2) ?></td>
                            <td class="py-2 whitespace-nowrap <?= $balance >= 0 ? 'text-green-800' : 'text-red-800' ?>">&#8369;<?= number_format($balance, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pt-3">
                <?php renderPagination($summaryPagination); ?>
            </div>
        </div>
    </section>

    <div id="organizationsModal" class="updates-modal-overlay hidden" data-modal-close role="dialog" aria-modal="true" aria-labelledby="organizationsModalTitle">
        <div class="glass w-full max-w-5xl max-h-[90dvh] overflow-y-auto" data-modal-panel>
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="organizationsModalTitle" class="text-lg font-semibold">All Organizations</h3>
                <button type="button" id="closeOrganizationsModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-4 space-y-3 max-h-[74vh] overflow-y-auto themed-scroll pr-1">
                <?php foreach ($orgs as $org): ?>
                    <div class="border rounded p-3 flex flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                        <div class="flex items-start gap-3 text-left">
                            <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'md', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                            <div>
                                <div class="font-medium"><?= e($org['name']) ?></div>
                                <p class="text-sm text-slate-600"><?= e($org['description']) ?></p>
                                <div class="text-xs text-slate-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                                <div class="text-xs text-emerald-800 mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                            </div>
                        </div>
                        <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="join_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <?php
                                    $orgId = (int) $org['id'];
                                    $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                    $isJoined = in_array($orgId, $joinedIds, true);
                                    $canJoin = canUserJoinOrganization($org, $user);
                                    $disabled = $isJoined || $requestStatus === 'pending' || !$canJoin;
                                    if (!$canJoin) {
                                        $btnClass = 'bg-slate-200/40 border-slate-300/60 text-slate-600';
                                        $label = getJoinRestrictionLabel($org);
                                    } elseif ($isJoined) {
                                        $btnClass = 'bg-white/10 border-emerald-200/30 text-slate-700';
                                        $label = 'Joined';
                                    } elseif ($requestStatus === 'pending') {
                                        $btnClass = 'bg-amber-500/25 border-amber-300/50 text-amber-900';
                                        $label = 'Requested';
                                    } else {
                                        $btnClass = 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35';
                                        $label = 'Request Join';
                                    }
                                ?>
                                <button class="px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                    <?= $label ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="announcementsModal" class="updates-modal-overlay hidden" data-modal-close role="dialog" aria-modal="true" aria-labelledby="announcementsModalTitle">
        <div class="glass w-full max-w-5xl max-h-[90dvh] overflow-y-auto" data-modal-panel>
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="announcementsModalTitle" class="text-lg font-semibold">All Latest Announcements</h3>
                <button type="button" id="closeAnnouncementsModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-4 space-y-3 max-h-[74vh] overflow-y-auto themed-scroll pr-1">
                <?php foreach ($announcements as $item): ?>
                    <div class="border rounded p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="font-medium"><?= e($item['title']) ?></div>
                            <div class="flex items-center gap-1">
                                <?php if (trim((string) ($item['label'] ?? '')) !== ''): ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-300/40"><?= e((string) $item['label']) ?></span>
                                <?php endif; ?>
                                <?php if ((int) ($item['is_pinned'] ?? 0) === 1): ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-300/40">Important</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-xs text-slate-500"><?= e($item['organization_name']) ?> &middot; <?= e($formatAnnouncementExpiry((string) ($item['expires_at'] ?? null))) ?></div>
                        <div class="text-sm mt-1"><?= e($item['content']) ?></div>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <form method="post" class="mt-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="<?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'unpin_announcement_admin' : 'pin_announcement_admin' ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="return_page" value="dashboard">
                                <button class="px-2 py-1 rounded text-xs border">
                                    <?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin as Important' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($announcements) === 0): ?>
                    <p class="section-helper-copy">No active announcements right now.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="financialSummaryModal" class="updates-modal-overlay hidden financial-summary-overlay" data-modal-close role="dialog" aria-modal="true" aria-labelledby="financialSummaryModalTitle">
        <div class="glass financial-summary-panel w-full max-w-6xl max-h-[90dvh] overflow-y-auto" data-modal-panel>
            <div class="flex items-center justify-between border-b border-emerald-200/30 px-4 py-3">
                <h3 id="financialSummaryModalTitle" class="text-lg font-semibold">Financial Health Snapshot</h3>
                <button type="button" id="closeFinancialSummaryModal" class="px-2 py-1 rounded border text-sm">Close</button>
            </div>
            <div class="p-3 space-y-2 max-h-[calc(100dvh-8.5rem)] overflow-y-auto themed-scroll">
                <div class="grid md:grid-cols-3 gap-1">
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value text-green-300">&#8369;<?= number_format($summaryIncomeTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Total income (all organizations)</div>
                    </div>
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value text-red-300">&#8369;<?= number_format($summaryExpenseTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Total expense (all organizations)</div>
                    </div>
                    <div class="dashboard-metric-card">
                        <div class="dashboard-metric-value <?= $summaryNetTotal >= 0 ? 'text-green-300' : 'text-red-300' ?>">&#8369;<?= number_format($summaryNetTotal, 2) ?></div>
                        <div class="dashboard-metric-label">Net balance (all organizations)</div>
                    </div>
                </div>
                <div class="grid xl:grid-cols-2 gap-2">
                    <div class="glass p-2 w-full h-full flex flex-col">
                        <h4 class="dashboard-section-title">Top Organizations by Net Balance</h4>
                        <p class="dashboard-section-copy mt-1">Higher bars indicate stronger surplus after expenses.</p>
                        <div class="dashboard-ranking-chart-wrap">
                            <?php
                            $chartRankingRows = array_slice($summaryRankingTop, 0, 6);
                            $chartExpenseRows = array_slice($summaryExpenseTop, 0, 6);
                            $rankingMaxBalance = max(1.0, ...array_map(static fn(array $row): float => abs((float) ($row['balance'] ?? 0)), $chartRankingRows));
                            $rankingMaxExpense = max(1.0, ...array_map(static fn(array $row): float => (float) ($row['expense'] ?? 0), $chartExpenseRows));
                            $formatChartAmount = static function (float $value): string {
                                $absolute = abs($value);
                                if ($absolute >= 1000000) {
                                    return number_format($value / 1000000, $absolute >= 10000000 ? 0 : 1) . 'm';
                                }

                                if ($absolute >= 1000) {
                                    return number_format($value / 1000, 0) . 'k';
                                }

                                return number_format($value, 0);
                            };
                            $buildChartAxis = static function (float $maxValue) use ($formatChartAmount): array {
                                $step = max(10000.0, ceil(max($maxValue, 1.0) / 6 / 10000) * 10000);
                                $axisMax = $step * 6;
                                $labels = [];
                                for ($i = 0; $i <= 6; $i++) {
                                    $labels[] = $formatChartAmount($step * $i);
                                }

                                return [$axisMax, $labels];
                            };
                            [$rankingAxisMaxBalance, $rankingBalanceAxisLabels] = $buildChartAxis($rankingMaxBalance);
                            [$rankingAxisMaxExpense, $rankingExpenseAxisLabels] = $buildChartAxis($rankingMaxExpense);
                            ?>
                            <button type="button" id="dashboardRankingToggle" class="dashboard-chart-toggle" aria-pressed="false" data-chart-toggle>Top Net Balance</button>
                            <table id="dashboardRankingChart" class="charts-css bar hide-data data-start show-labels show-primary-axis show-data-axes show-6-secondary-axes dashboard-net-balance-chart">
                                <caption class="sr-only">Top organizations financial ranking</caption>
                                <tbody data-chart-mode="balance">
                                    <?php foreach ($chartRankingRows as $index => $row): ?>
                                        <?php
                                        $balanceValue = (float) ($row['balance'] ?? 0);
                                        $balanceSize = $rankingAxisMaxBalance > 0 ? min(1, max(0, abs($balanceValue) / $rankingAxisMaxBalance)) : 0;
                                        ?>
                                        <tr class="chart-tone-<?= $index % 2 === 0 ? 'primary' : 'secondary' ?>">
                                            <th scope="row"><?= e((string) ($row['name'] ?? 'Organization')) ?></th>
                                            <td style="--size: <?= number_format($balanceSize, 4, '.', '') ?>;" data-tooltip="Net balance: PHP <?= e(number_format($balanceValue, 2)) ?>" aria-label="Net balance: PHP <?= e(number_format($balanceValue, 2)) ?>">
                                                <span class="data">&#8369;<?= number_format($balanceValue, 2) ?></span>
                                                <span class="chart-bar-value">&#8369;<?= e($formatChartAmount($balanceValue)) ?></span>
                                                <span class="chart-tooltip-arrow" aria-hidden="true"></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tbody data-chart-mode="expense" hidden>
                                    <?php foreach ($chartExpenseRows as $index => $row): ?>
                                        <?php
                                        $expenseValue = (float) ($row['expense'] ?? 0);
                                        $expenseSize = $rankingAxisMaxExpense > 0 ? min(1, max(0, $expenseValue / $rankingAxisMaxExpense)) : 0;
                                        ?>
                                        <tr class="chart-tone-<?= $index % 2 === 0 ? 'primary' : 'secondary' ?>">
                                            <th scope="row"><?= e((string) ($row['name'] ?? 'Organization')) ?></th>
                                            <td style="--size: <?= number_format($expenseSize, 4, '.', '') ?>;" data-tooltip="Expense: PHP <?= e(number_format($expenseValue, 2)) ?>" aria-label="Expense: PHP <?= e(number_format($expenseValue, 2)) ?>">
                                                <span class="data">&#8369;<?= number_format($expenseValue, 2) ?></span>
                                                <span class="chart-bar-value">&#8369;<?= e($formatChartAmount($expenseValue)) ?></span>
                                                <span class="chart-tooltip-arrow" aria-hidden="true"></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="dashboard-chart-axis" data-chart-axis="balance" aria-hidden="true">
                                <?php foreach ($rankingBalanceAxisLabels as $label): ?>
                                    <span><?= e($label) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="dashboard-chart-axis" data-chart-axis="expense" aria-hidden="true" hidden>
                                <?php foreach ($rankingExpenseAxisLabels as $label): ?>
                                    <span><?= e($label) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-auto grid md:grid-cols-3 xl:grid-cols-3 gap-1 pt-3">
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Top performer</div>
                                    <div class="dashboard-feed-meta mt-1"><?= e((string) ($topPerformer['name'] ?? 'N/A')) ?></div>
                                    <div class="dashboard-feed-body mt-1 text-green-300">&#8369;<?= number_format((float) ($topPerformer['balance'] ?? 0), 2) ?></div>
                                </div>
                            </div>
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Highest spend pressure</div>
                                    <div class="dashboard-feed-meta mt-1"><?= e((string) ($highestPressure['name'] ?? 'N/A')) ?></div>
                                    <div class="dashboard-feed-body mt-1"><?= (int) round(((float) ($highestPressure['expense_ratio'] ?? 0)) * 100) ?>% expense ratio</div>
                                </div>
                            </div>
                            <div class="dashboard-feed-item trend-insight-card">
                                <div>
                                    <div class="dashboard-feed-title">Average net</div>
                                    <div class="dashboard-feed-meta mt-1">Across <?= count($summaryRankingRows) ?> organizations</div>
                                    <div class="dashboard-feed-body mt-1 <?= $averageNet >= 0 ? 'text-green-300' : 'text-red-300' ?>">&#8369;<?= number_format($averageNet, 2) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="glass p-2 w-full h-full space-y-2">
                        <div>
                            <h4 class="dashboard-section-title">Organization Priority List</h4>
                            <p class="dashboard-section-copy mt-1">Status reflects spending pressure and current balance.</p>
                        </div>
                        <div class="space-y-1.5">
                            <?php foreach (array_slice($summaryRankingRows, 0, 4) as $row): ?>
                                <div class="dashboard-feed-item trend-insight-card items-center justify-between">
                                    <div>
                                        <div class="dashboard-feed-title"><?= e($row['name']) ?></div>
                                        <div class="dashboard-feed-meta mt-1">Net: &#8369;<?= number_format((float) $row['balance'], 2) ?></div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] font-medium <?= e($row['status_class']) ?>"><?= e($row['status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <div class="dashboard-section-title">Needs Attention</div>
                            <div class="mt-1 space-y-1">
                                <?php if (count($summaryAttentionRows) === 0): ?>
                                    <p class="dashboard-section-copy">No organizations are currently flagged for risk or heavy spend pressure.</p>
                                <?php else: ?>
                                    <?php foreach ($summaryAttentionRows as $row): ?>
                                        <div class="dashboard-feed-meta"><?= e($row['name']) ?> &middot; Expense ratio <?= (int) round($row['expense_ratio'] * 100) ?>%</div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
