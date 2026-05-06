<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - NOTIFICATIONS PAGE
 * ================================================
 *
 * SECTION MAP:
 * 1. Notifications Page Controller
 *
 * WORK GUIDE:
 * - Edit this file for the user-facing notification center and personal activity feed.
 * ================================================
 */

function handleNotificationsPage(PDO $db, array $user): void
{
    $days = max(1, min(90, (int) ($_GET['days'] ?? 14)));
    $updates = collectUserRequestUpdates((int) ($user['id'] ?? 0), $days, 30);
    $activity = collectUserAuditTrail((int) ($user['id'] ?? 0), $days, 30);

    $approvedCount = 0;
    $attentionCount = 0;
    foreach ($updates as $item) {
        $status = strtolower((string) ($item['status'] ?? ''));
        if (in_array($status, ['approved', 'accepted'], true)) {
            $approvedCount++;
        }
        if (in_array($status, ['rejected', 'declined', 'removed'], true)) {
            $attentionCount++;
        }
    }

    $updatesPagination = paginateArray($updates, 'pg_notifications_updates', 8);
    $updates = $updatesPagination['items'];
    $activityPagination = paginateArray($activity, 'pg_notifications_activity', 10);
    $activity = $activityPagination['items'];

    renderHeader('Notifications');
    ?>
    <section class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Notifications</h1>
                <p class="text-sm text-slate-600">Review approval outcomes, membership updates, and your recent account activity in one place.</p>
            </div>
            <form method="get" class="transparency-toolbar flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="notifications">
                <label class="text-sm text-slate-600" for="days">Window</label>
                <select name="days" id="days" class="themed-field themed-select px-2 py-1 text-sm" onchange="this.form.submit()">
                    <?php foreach ([7, 14, 30, 60, 90] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $days === $opt ? 'selected' : '' ?>>Last <?= $opt ?> days</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <article class="glass transparency-stat-card rounded-xl p-4">
                <div class="text-sm text-slate-600">Recent updates</div>
                <div class="mt-2 text-2xl font-semibold"><?= count($updatesPagination['all_items'] ?? $updates) ?></div>
                <div class="mt-1 text-xs text-slate-500">Approval and workflow responses</div>
            </article>
            <article class="glass transparency-stat-card rounded-xl p-4">
                <div class="text-sm text-slate-600">Positive outcomes</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700"><?= $approvedCount ?></div>
                <div class="mt-1 text-xs text-slate-500">Approved or accepted in this window</div>
            </article>
            <article class="glass transparency-stat-card rounded-xl p-4">
                <div class="text-sm text-slate-600">Needs attention</div>
                <div class="mt-2 text-2xl font-semibold text-rose-700"><?= $attentionCount ?></div>
                <div class="mt-1 text-xs text-slate-500">Declined, rejected, or removed items</div>
            </article>
            <article class="glass transparency-stat-card rounded-xl p-4">
                <div class="text-sm text-slate-600">Your activity</div>
                <div class="mt-2 text-2xl font-semibold"><?= count($activityPagination['all_items'] ?? $activity) ?></div>
                <div class="mt-1 text-xs text-slate-500">Audit entries recorded for your account</div>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.1fr,0.9fr]">
            <section class="glass transparency-panel rounded-xl p-4">
                <div class="mb-3">
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('update', 'ui-icon') ?><span>Workflow Updates</span></h2>
                    <p class="text-sm text-slate-600 mt-1">Latest decisions and membership notifications related to your requests.</p>
                </div>

                <?php if (!$updates): ?>
                    <p class="text-sm text-slate-600">No request or security updates were recorded in this time window.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($updates as $item): ?>
                            <?php
                                $status = strtolower((string) ($item['status'] ?? ''));
                                $statusClass = 'updates-status updates-status-' . preg_replace('/[^a-z]/', '', $status);
                                $statusIcon = match ($status) {
                                    'approved', 'accepted' => 'approved',
                                    'rejected', 'declined', 'removed' => 'rejected',
                                    'pending' => 'pending',
                                    default => 'default',
                                };
                            ?>
                            <article class="transparency-entry transparency-entry-success rounded-xl p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium"><?= e((string) ($item['kind'] ?? 'Update')) ?></div>
                                        <div class="text-sm text-slate-600 mt-1"><?= e((string) ($item['message'] ?? '')) ?></div>
                                    </div>
                                    <span class="<?= e($statusClass) ?> icon-badge shrink-0"><?= uiIcon($statusIcon, 'ui-icon ui-icon-sm') ?><?= e(ucfirst($status)) ?></span>
                                </div>
                                <div class="text-xs text-slate-500 mt-2"><?= e((string) ($item['event_at'] ?? '')) ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php renderPagination($updatesPagination); ?>
                <?php endif; ?>
            </section>

            <section class="glass transparency-panel rounded-xl p-4">
                <div class="mb-3">
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('audit', 'ui-icon') ?><span>Your Recorded Activity</span></h2>
                    <p class="text-sm text-slate-600 mt-1">Actions this account performed that were captured in the audit trail.</p>
                </div>

                <?php if (!$activity): ?>
                    <p class="text-sm text-slate-600">No recent audit entries were recorded for your account.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($activity as $item): ?>
                            <?php $family = getAuditActionFamily((string) ($item['action'] ?? '')); ?>
                            <article class="transparency-entry transparency-entry-neutral rounded-xl p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium"><?= e(formatAuditActionLabel((string) ($item['action'] ?? ''))) ?></div>
                                        <div class="text-xs uppercase tracking-wide text-slate-500 mt-1"><?= e($family) ?><?php if (($item['entity_type'] ?? '') !== ''): ?> · <?= e((string) $item['entity_type']) ?><?php endif; ?><?php if (($item['entity_id'] ?? null) !== null): ?> #<?= (int) $item['entity_id'] ?><?php endif; ?></div>
                                    </div>
                                    <div class="text-xs text-slate-500 shrink-0"><?= e((string) ($item['created_at'] ?? '')) ?></div>
                                </div>
                                <?php if ((string) ($item['details'] ?? '') !== ''): ?>
                                    <div class="text-sm text-slate-600 mt-2"><?= e((string) $item['details']) ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php renderPagination($activityPagination); ?>
                <?php endif; ?>
            </section>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}
