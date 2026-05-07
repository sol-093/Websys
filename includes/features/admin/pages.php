<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - ADMIN PAGES
 * ================================================
 *
 * SECTION MAP:
 * 1. Student Management
 * 2. Requests Queue
 * 3. Audit Logs
 * 4. Admin My Organization View
 * 5. Organization Management
 *
 * WORK GUIDE:
 * - Edit this file for admin-facing page markup and page-local scripts.
 * ================================================
 */

function handleAdminStudentsPage(PDO $db): void
{
    requireRole(['admin']);
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($q !== '') {
        $stmt = $db->prepare("SELECT id, name, email, role, created_at, profile_picture_path, profile_picture_crop_x, profile_picture_crop_y, profile_picture_zoom FROM users WHERE role IN ('student','owner') AND (name LIKE ? OR email LIKE ?) ORDER BY name");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        $students = $stmt->fetchAll();
    } else {
        $students = $db->query("SELECT id, name, email, role, created_at, profile_picture_path, profile_picture_crop_x, profile_picture_crop_y, profile_picture_zoom FROM users WHERE role IN ('student','owner') ORDER BY name")->fetchAll();
    }
    $studentsPagination = paginateArray($students, 'pg_admin_students', 12);
    $students = $studentsPagination['items'];

    renderHeader('Student Records');
    ?>
    <div class="glass transparency-panel rounded-xl p-4">
        <div class="mb-4">
            <h1 class="text-xl font-semibold mb-1 icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Student Records</span></h1>
            <p class="section-helper-copy">Search student and owner accounts by name or email.</p>
        </div>
        <form method="get" class="transparency-toolbar flex flex-wrap gap-2 mb-4 items-stretch sm:items-center">
            <input type="hidden" name="page" value="admin_students">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or email" class="themed-field w-full px-3 py-2">
            <button class="themed-button w-full sm:w-auto px-4 py-2"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
        </form>

        <div class="overflow-auto">
            <?php if (!$students): ?>
                <div class="empty-state-panel">
                    No student records matched that search.
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="hidden md:table w-full min-w-[640px] text-sm">
                    <thead>
                    <tr class="border-b text-left">
                        <th class="py-2">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Joined</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr class="border-b">
                            <td class="py-2">
                                <span class="inline-flex items-center gap-2">
                                    <?= renderProfileMedia((string) ($student['name'] ?? ''), (string) ($student['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($student['profile_picture_crop_x'] ?? 50), (float) ($student['profile_picture_crop_y'] ?? 50), (float) ($student['profile_picture_zoom'] ?? 1)) ?>
                                    <span><?= e($student['name']) ?></span>
                                </span>
                            </td>
                            <td><?= e($student['email']) ?></td>
                            <td><?= e($student['role']) ?></td>
                            <td><?= e(date('F d, Y', strtotime((string) $student['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
                <div class="md:hidden space-y-3">
                    <?php foreach ($students as $student): ?>
                        <article class="admin-mobile-card rounded-xl border border-emerald-200/40 bg-white/10 p-3">
                            <div class="admin-mobile-title inline-flex items-center gap-2 min-w-0">
                                <?= renderProfileMedia((string) ($student['name'] ?? ''), (string) ($student['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($student['profile_picture_crop_x'] ?? 50), (float) ($student['profile_picture_crop_y'] ?? 50), (float) ($student['profile_picture_zoom'] ?? 1)) ?>
                                <span class="break-words leading-5"><?= e((string) $student['name']) ?></span>
                            </div>
                            <div class="admin-mobile-copy mt-2 break-words"><?= e((string) $student['email']) ?></div>
                            <div class="admin-mobile-meta mt-2 flex flex-wrap items-center gap-2">
                                <span class="admin-mobile-chip meta-chip meta-chip-accent"><?= e((string) $student['role']) ?></span>
                                <span class="break-words">Joined: <?= e(date('F d, Y', strtotime((string) $student['created_at']))) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php renderPagination($studentsPagination); ?>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

function handleAdminRequestsPage(PDO $db): void
{
    requireRole(['admin']);

    $requests = $db->query("SELECT r.*, o.name AS organization_name, o.logo_path AS organization_logo_path, o.logo_crop_x AS organization_logo_crop_x, o.logo_crop_y AS organization_logo_crop_y, o.logo_zoom AS organization_logo_zoom, u.name AS requester_name, u.profile_picture_path AS requester_profile_picture_path, u.profile_picture_crop_x AS requester_profile_picture_crop_x, u.profile_picture_crop_y AS requester_profile_picture_crop_y, u.profile_picture_zoom AS requester_profile_picture_zoom
        FROM transaction_change_requests r
        JOIN organizations o ON o.id = r.organization_id
        JOIN users u ON u.id = r.requested_by
        ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.created_at DESC")->fetchAll();
    $requestsPagination = paginateArray($requests, 'pg_admin_requests', 10);
    $requests = $requestsPagination['items'];

    renderHeader('Transaction Requests');
    ?>
    <div class="glass transparency-panel rounded-xl p-4 overflow-auto">
        <div class="mb-4">
            <h1 class="text-xl font-semibold mb-1 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Owner Requests for Transaction Edit/Delete</span></h1>
            <p class="section-helper-copy">Review finance change requests and leave a decision trail for owners.</p>
        </div>
        <div class="table-wrapper">
            <table class="hidden md:table w-full min-w-[1040px] text-sm table-fixed">
            <thead>
            <tr class="border-b text-left">
                <th class="py-3 px-4 w-[14%]">Org</th>
                <th class="py-3 px-4 w-[14%]">Requester</th>
                <th class="py-3 px-4 w-[10%]">Action</th>
                <th class="py-3 px-4 w-[20%]">Proposal</th>
                <th class="py-3 px-4 w-[12%]">Status</th>
                <th class="py-3 px-4 w-[18%]">Admin Note</th>
                <th class="py-3 px-4 w-[12%]">Decision</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $req): ?>
                <tr class="border-b align-top">
                    <td class="py-4 px-4 align-top break-words">
                        <span class="inline-flex items-center gap-2">
                            <?= renderProfileMedia((string) ($req['organization_name'] ?? ''), (string) ($req['organization_logo_path'] ?? ''), 'organization', 'xs', (float) ($req['organization_logo_crop_x'] ?? 50), (float) ($req['organization_logo_crop_y'] ?? 50), (float) ($req['organization_logo_zoom'] ?? 1)) ?>
                            <span><?= e($req['organization_name']) ?></span>
                        </span>
                    </td>
                    <td class="py-4 px-4 align-top break-words">
                        <span class="inline-flex items-center gap-2">
                            <?= renderProfileMedia((string) ($req['requester_name'] ?? ''), (string) ($req['requester_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($req['requester_profile_picture_crop_x'] ?? 50), (float) ($req['requester_profile_picture_crop_y'] ?? 50), (float) ($req['requester_profile_picture_zoom'] ?? 1)) ?>
                            <span><?= e($req['requester_name']) ?></span>
                        </span>
                    </td>
                    <td class="py-4 px-4 align-top capitalize break-words"><?= e($req['action_type']) ?></td>
                    <td class="py-4 px-4 align-top whitespace-normal leading-relaxed break-words">
                        <?php if ($req['action_type'] === 'update'): ?>
                            <div class="text-xs">Type: <?= e((string) $req['proposed_type']) ?></div>
                            <div class="text-xs">Amount: ₱<?= number_format((float) $req['proposed_amount'], 2) ?></div>
                            <div class="text-xs">Date: <?= e(date('F d, Y', strtotime((string)$req['proposed_transaction_date']))) ?></div>
                            <div class="text-xs">Desc: <?= e((string) $req['proposed_description']) ?></div>
                        <?php else: ?>
                            <div class="text-xs">Delete transaction #<?= (int) $req['transaction_id'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-4 px-4 align-top break-words"><span class="icon-label"><?php
                        $requestStatus = strtolower((string) $req['status']);
                        $requestStatusIcon = match ($requestStatus) {
                            'approved', 'accepted' => 'approved',
                            'rejected', 'declined' => 'rejected',
                            'pending' => 'pending',
                            default => 'default',
                        };
                        ?><?= uiIcon($requestStatusIcon, 'ui-icon ui-icon-sm') ?><?= e((string) $req['status']) ?></span></td>
                    <td class="py-4 px-4 align-top whitespace-normal leading-relaxed break-words"><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    <td class="py-4 px-4 align-top">
                        <?php if ((string) $req['status'] === 'pending'): ?>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="approve" class="owner-manage-primary-btn inline-flex items-center justify-center px-3 py-2 rounded-md min-w-[6.25rem]">
                                    <span class="icon-label w-[4.75rem] justify-start leading-none"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span class="inline-block w-[3.8rem] text-left leading-none">Approve</span></span>
                                </button>
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="reject" class="tx-action-btn tx-action-btn-delete inline-flex items-center justify-center px-3 py-2 rounded-md min-w-[6.25rem]">
                                    <span class="icon-label w-[4.75rem] justify-start leading-none"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span class="inline-block w-[2.5rem] text-center leading-none">Reject</span></span>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-slate-500 whitespace-nowrap">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        </div>
        <div class="md:hidden space-y-3">
            <?php foreach ($requests as $req): ?>
                <?php $requestStatus = strtolower((string) $req['status']); ?>
                <article class="admin-mobile-card rounded-xl border border-emerald-200/40 bg-white/10 p-3">
                    <div class="space-y-2">
                        <div class="admin-mobile-title inline-flex items-center gap-2 min-w-0">
                            <?= renderProfileMedia((string) ($req['organization_name'] ?? ''), (string) ($req['organization_logo_path'] ?? ''), 'organization', 'xs', (float) ($req['organization_logo_crop_x'] ?? 50), (float) ($req['organization_logo_crop_y'] ?? 50), (float) ($req['organization_logo_zoom'] ?? 1)) ?>
                            <span class="break-words leading-5"><?= e((string) ($req['organization_name'] ?? 'Organization')) ?></span>
                        </div>
                        <div class="admin-mobile-meta inline-flex items-center gap-2 min-w-0">
                            <?= renderProfileMedia((string) ($req['requester_name'] ?? ''), (string) ($req['requester_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($req['requester_profile_picture_crop_x'] ?? 50), (float) ($req['requester_profile_picture_crop_y'] ?? 50), (float) ($req['requester_profile_picture_zoom'] ?? 1)) ?>
                            <span class="break-words">Requester: <?= e((string) ($req['requester_name'] ?? '')) ?></span>
                        </div>
                        <div class="admin-mobile-meta">
                            <span class="font-semibold">Action:</span>
                            <span class="capitalize"><?= e((string) $req['action_type']) ?></span>
                        </div>
                        <div class="admin-mobile-meta leading-relaxed break-words">
                            <?php if ($req['action_type'] === 'update'): ?>
                                <div>Type: <?= e((string) $req['proposed_type']) ?></div>
                                <div>Amount: ₱<?= number_format((float) $req['proposed_amount'], 2) ?></div>
                                <div>Date: <?= e((string) $req['proposed_transaction_date']) ?></div>
                                <div>Desc: <?= e((string) $req['proposed_description']) ?></div>
                            <?php else: ?>
                                <div>Delete transaction #<?= (int) $req['transaction_id'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="admin-mobile-meta break-words">
                            <span class="font-semibold">Status:</span>
                            <span><?= e((string) $req['status']) ?></span>
                        </div>
                        <?php if ((string) ($req['admin_note'] ?? '') !== ''): ?>
                            <div class="admin-mobile-meta break-words">
                                <span class="font-semibold">Admin note:</span>
                                <span><?= e((string) $req['admin_note']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ((string) $req['status'] === 'pending'): ?>
                            <div class="grid grid-cols-1 gap-2 pt-1">
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="approve" class="owner-manage-primary-btn w-full inline-flex items-center justify-center px-3 py-2 rounded-md text-sm">
                                    <span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span>
                                </button>
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="reject" class="tx-action-btn tx-action-btn-delete w-full inline-flex items-center justify-center px-3 py-2 rounded-md text-sm">
                                    <span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Reject</span></span>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-xs text-slate-500">Processed</div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php renderPagination($requestsPagination); ?>
    </div>
    <div id="txRequestNoteModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-16 w-full max-w-lg">
            <div class="glass p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Review Request</span></h2>
                        <p class="section-helper-copy">Leave an admin note before approving or rejecting.</p>
                    </div>
                    <button type="button" id="txRequestModalClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>

                <form method="post" class="space-y-4" id="txRequestNoteForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="process_tx_change_request">
                    <input type="hidden" name="request_id" id="txRequestModalRequestId" value="">
                    <input type="hidden" name="decision" id="txRequestModalDecision" value="">

                    <div>
                        <label for="txRequestModalNote" class="block text-sm font-medium text-slate-700 mb-2">Admin Note</label>
                        <textarea id="txRequestModalNote" name="admin_note" rows="4" class="w-full border rounded px-3 py-2" placeholder="Optional note for the requester"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" id="txRequestModalCancel" class="owner-manage-secondary-btn px-3 py-2 rounded-md text-sm">Cancel</button>
                        <button type="submit" id="txRequestModalSubmit" class="owner-manage-primary-btn px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2">
                            <?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span id="txRequestModalSubmitLabel">Submit</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const modal = document.getElementById('txRequestNoteModal');
            const closeButton = document.getElementById('txRequestModalClose');
            const cancelButton = document.getElementById('txRequestModalCancel');
            const requestIdInput = document.getElementById('txRequestModalRequestId');
            const decisionInput = document.getElementById('txRequestModalDecision');
            const noteInput = document.getElementById('txRequestModalNote');
            const submitButton = document.getElementById('txRequestModalSubmit');
            const submitLabel = document.getElementById('txRequestModalSubmitLabel');
            let lastTrigger = null;

            function openModal(requestId, decision) {
                lastTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                requestIdInput.value = requestId;
                decisionInput.value = decision;
                submitLabel.textContent = decision === 'approve' ? 'Approve Request' : 'Reject Request';
                submitButton.className = decision === 'approve'
                    ? 'owner-manage-primary-btn px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2'
                    : 'tx-action-btn tx-action-btn-delete px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2';
                modal.classList.remove('hidden');
                noteInput.value = '';
                noteInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
                requestIdInput.value = '';
                decisionInput.value = '';
                noteInput.value = '';
                if (lastTrigger && typeof lastTrigger.focus === 'function') {
                    lastTrigger.focus();
                }
                lastTrigger = null;
            }

            document.querySelectorAll('[data-tx-request-open]').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    openModal(link.getAttribute('data-request-id') || '', link.getAttribute('data-request-action') || 'approve');
                });
            });

            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleAdminAuditPage(PDO $db, array $user): void
{
    requireLogin();
    if (($user['role'] ?? '') !== 'admin') {
        setFlash('error', 'Admin access required.');
        redirect('?page=dashboard');
    }

    $days = max(1, (int) ($_GET['days'] ?? 7));
    $days = min(90, $days);
    $query = trim((string) ($_GET['q'] ?? ''));
    $family = trim((string) ($_GET['family'] ?? 'all'));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $familyMap = [
        'all' => null,
        'auth' => ['auth.', 'profile.'],
        'organization' => ['organization.', 'join_request.', 'assignment.'],
        'finance' => ['finance.'],
        'announcement' => ['announcement.'],
    ];

    $sql = "SELECT al.*, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE al.created_at >= ?";
    $params = [$cutoff];

    if (isset($familyMap[$family]) && is_array($familyMap[$family])) {
        $familyConditions = [];
        foreach ($familyMap[$family] as $prefix) {
            $familyConditions[] = 'al.action LIKE ?';
            $params[] = $prefix . '%';
        }
        $sql .= ' AND (' . implode(' OR ', $familyConditions) . ')';
    }

    if ($query !== '') {
        $sql .= ' AND (al.action LIKE ? OR al.entity_type LIKE ? OR al.details LIKE ? OR COALESCE(u.name, "") LIKE ?)';
        $like = '%' . $query . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql .= ' ORDER BY al.id DESC LIMIT 500';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $allLogs = $stmt->fetchAll();
    $logs = $allLogs;
    $logsPagination = paginateArray($logs, 'pg_admin_audit', 20);
    $logs = $logsPagination['items'];

    $uniqueActors = [];
    $familyCounts = [
        'auth' => 0,
        'organization' => 0,
        'finance' => 0,
        'announcement' => 0,
        'system' => 0,
    ];
    foreach ($allLogs as $log) {
        $actorKey = (string) ($log['user_id'] ?? '') . '|' . (string) ($log['actor_name'] ?? '');
        $uniqueActors[$actorKey] = true;
        $familyKey = getAuditActionFamily((string) ($log['action'] ?? ''));
        $familyCounts[$familyKey] = ($familyCounts[$familyKey] ?? 0) + 1;
    }

    renderHeader('Audit Logs', $user);
    ?>
    <section class="glass transparency-panel rounded-xl p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-lg font-semibold">Audit Logs</h2>
                <p class="text-sm text-gray-600">Review who changed what, when it happened, and the context stored with each event.</p>
            </div>
            <form method="get" class="transparency-toolbar flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="admin_audit" />
                <label class="text-sm text-gray-600" for="days">Last</label>
                <select name="days" id="days" class="themed-field themed-select px-2 py-1 text-sm" onchange="this.form.submit()">
                    <?php foreach ([1, 3, 7, 14, 30, 90] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $days === $opt ? 'selected' : '' ?>><?= $opt ?> days</option>
                    <?php endforeach; ?>
                </select>
                <select name="family" class="themed-field themed-select px-2 py-1 text-sm" onchange="this.form.submit()">
                    <option value="all" <?= $family === 'all' ? 'selected' : '' ?>>All activity</option>
                    <option value="auth" <?= $family === 'auth' ? 'selected' : '' ?>>Auth & profile</option>
                    <option value="organization" <?= $family === 'organization' ? 'selected' : '' ?>>Organization</option>
                    <option value="finance" <?= $family === 'finance' ? 'selected' : '' ?>>Finance</option>
                    <option value="announcement" <?= $family === 'announcement' ? 'selected' : '' ?>>Announcements</option>
                </select>
                <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search actor, action, or details" class="themed-field px-2 py-1 text-sm min-w-[240px]">
                <button type="submit" class="themed-button px-3 py-1 text-sm">Filter</button>
            </form>
        </div>

        <div class="grid gap-3 md:grid-cols-4 mb-4">
            <article class="transparency-stat-card transparency-stat-card-emerald rounded-xl p-3">
                <div class="text-xs uppercase tracking-wide text-slate-600">Entries</div>
                <div class="mt-1 text-2xl font-semibold"><?= count($allLogs) ?></div>
            </article>
            <article class="transparency-stat-card transparency-stat-card-slate rounded-xl p-3">
                <div class="text-xs uppercase tracking-wide text-slate-600">Actors</div>
                <div class="mt-1 text-2xl font-semibold"><?= count($uniqueActors) ?></div>
            </article>
            <article class="transparency-stat-card transparency-stat-card-sky rounded-xl p-3">
                <div class="text-xs uppercase tracking-wide text-slate-600">Auth & Profile</div>
                <div class="mt-1 text-2xl font-semibold"><?= (int) ($familyCounts['auth'] ?? 0) ?></div>
            </article>
            <article class="transparency-stat-card transparency-stat-card-amber rounded-xl p-3">
                <div class="text-xs uppercase tracking-wide text-slate-600">Org & Finance</div>
                <div class="mt-1 text-2xl font-semibold"><?= (int) (($familyCounts['organization'] ?? 0) + ($familyCounts['finance'] ?? 0)) ?></div>
            </article>
        </div>

        <?php if (!$logs): ?>
            <div class="empty-state-panel">No audit entries in the selected range.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="audit-log-table hidden md:table w-full min-w-[1180px] text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 pr-3">Time</th>
                            <th class="text-left py-2 pr-3">Actor</th>
                            <th class="text-left py-2 pr-3">Action</th>
                            <th class="text-left py-2 pr-3">Entity</th>
                            <th class="text-left py-2 pr-3">Source</th>
                            <th class="text-left py-2 pr-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="audit-log-row border-b align-top">
                                <td class="py-2 pr-3 whitespace-nowrap"><?= e((string) $log['created_at']) ?></td>
                                <td class="py-2 pr-3"><?= e($log['actor_name'] ?: ('User#' . (int) $log['user_id'])) ?></td>
                                <td class="py-2 pr-3">
                                    <div class="font-medium"><?= e(formatAuditActionLabel((string) $log['action'])) ?></div>
                                    <div class="text-xs text-slate-500 uppercase tracking-wide"><?= e(getAuditActionFamily((string) $log['action'])) ?></div>
                                </td>
                                <td class="py-2 pr-3"><?= e((string) ($log['entity_type'] ?? '-')) ?><?= $log['entity_id'] !== null ? ' #' . (int) $log['entity_id'] : '' ?></td>
                                <td class="py-2 pr-3">
                                    <div class="text-xs text-slate-600"><?= e((string) ($log['ip_address'] ?? 'unknown')) ?></div>
                                    <div class="text-xs text-slate-500 break-words max-w-xs"><?= e((string) ($log['user_agent'] ?? '')) ?></div>
                                </td>
                                <td class="py-2 pr-3 break-words max-w-xl"><?= e($log['details'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="md:hidden space-y-3">
                <?php foreach ($logs as $log): ?>
                    <article class="admin-mobile-card transparency-entry transparency-entry-neutral rounded-xl p-3">
                        <div class="admin-mobile-meta break-words"><?= e((string) $log['created_at']) ?></div>
                        <div class="admin-mobile-title mt-1 break-words"><?= e((string) ($log['actor_name'] ?: ('User#' . (int) $log['user_id']))) ?></div>
                        <div class="admin-mobile-meta mt-1"><span class="font-semibold">Action:</span> <?= e(formatAuditActionLabel((string) $log['action'])) ?></div>
                        <div class="admin-mobile-meta mt-1"><span class="font-semibold">Entity:</span> <?= e((string) ($log['entity_type'] ?? '-')) ?><?= $log['entity_id'] !== null ? ' #' . (int) $log['entity_id'] : '' ?></div>
                        <div class="admin-mobile-meta mt-1"><span class="font-semibold">Source:</span> <?= e((string) ($log['ip_address'] ?? 'unknown')) ?></div>
                        <?php if ((string) ($log['details'] ?? '') !== ''): ?>
                            <div class="admin-mobile-meta mt-2 break-words leading-relaxed">
                                <span class="font-semibold">Details:</span> <?= e((string) $log['details']) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php renderPagination($logsPagination); ?>
        <?php endif; ?>
    </section>
    <?php
    renderFooter();
    exit;
}

function handleMyOrgAdminPage(PDO $db): void
{
    $orgId = (int) ($_GET['org_id'] ?? 0);
    $txTypeFilter = (string) ($_GET['tx_type'] ?? 'all');
    if (!in_array($txTypeFilter, ['all', 'income', 'expense'], true)) {
        $txTypeFilter = 'all';
    }

    $txDateSort = strtolower((string) ($_GET['tx_sort'] ?? 'desc'));
    if (!in_array($txDateSort, ['asc', 'desc'], true)) {
        $txDateSort = 'desc';
    }

    $orgs = $db->query('SELECT id, name, description, org_category, target_institute, target_program FROM organizations ORDER BY name')->fetchAll();
    $org = null;
    if ($orgId > 0) {
        $stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
    }

    renderHeader('Organization Overview');
    $selectedOrgName = 'Select organization';
    foreach ($orgs as $option) {
        if ($orgId > 0 && (int) $option['id'] === $orgId) {
            $selectedOrgName = (string) $option['name'];
            break;
        }
    }
    ?>
    <div class="glass transparency-panel rounded-xl p-4">
        <div class="mb-4">
            <h1 class="text-xl font-semibold mb-1 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>Organization Overview</span></h1>
            <p class="section-helper-copy">Open an organization, review members, and inspect transaction history from one view.</p>
        </div>
        <form method="get" class="mb-4 flex flex-wrap items-stretch sm:items-start gap-2 relative">
            <input type="hidden" name="page" value="my_org">
            <input type="hidden" name="org_id" id="adminOrgIdInput" value="<?= $orgId > 0 ? (int) $orgId : '' ?>">
            <input type="hidden" name="org_search_name" id="adminOrgSearchName" value="<?= e($selectedOrgName) ?>">
            <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
            <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
            <button type="button" id="adminOrgSearchButton" class="owner-manage-primary-btn w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md px-4 py-2">
                <?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Search Organizations</span>
            </button>
            <button type="submit" class="owner-manage-secondary-btn w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-md px-4 py-2">
                <?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span>
            </button>
        </form>

        <?php if ($org): ?>
            <?php
            $txSql = 'SELECT * FROM financial_transactions WHERE organization_id = ?';
            $txParams = [(int) $org['id']];
            if ($txTypeFilter !== 'all') {
                $txSql .= ' AND type = ?';
                $txParams[] = $txTypeFilter;
            }
            $txOrder = $txDateSort === 'asc' ? 'ASC' : 'DESC';
            $txSql .= " ORDER BY transaction_date {$txOrder}, id {$txOrder}";

            $txStmt = $db->prepare($txSql);
            $txStmt->execute($txParams);
            $tx = $txStmt->fetchAll();
            $adminTxPagination = paginateArray($tx, 'pg_myorg_admin_tx', 12);
            $tx = $adminTxPagination['items'];

            $memberStmt = $db->prepare('SELECT u.name, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom FROM organization_members om JOIN users u ON u.id = om.user_id WHERE om.organization_id = ? ORDER BY u.name ASC');
            $memberStmt->execute([(int) $org['id']]);
            $orgMembers = $memberStmt->fetchAll();
            $orgMemberCount = count($orgMembers);

            $viewer = currentUser();
            $viewerId = (int) ($viewer['id'] ?? 0);
            $canSeeMemberNames = false;
            if ($viewerId > 0) {
                $viewerMembershipStmt = $db->prepare('SELECT 1 FROM organization_members WHERE organization_id = ? AND user_id = ? LIMIT 1');
                $viewerMembershipStmt->execute([(int) $org['id'], $viewerId]);
                $canSeeMemberNames = (bool) $viewerMembershipStmt->fetchColumn();
            }
            ?>
            <h2 class="text-lg font-semibold"><?= e($org['name']) ?></h2>
            <p class="text-slate-600 mb-3"><?= e($org['description']) ?></p>

            <div class="mb-4 rounded-xl border border-emerald-200/55 bg-emerald-50/30 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-emerald-900">Total Members: <?= (int) $orgMemberCount ?></p>
                    <?php if ($canSeeMemberNames): ?>
                        <button type="button" id="adminOrgMembersOpen" class="owner-manage-primary-btn inline-flex items-center gap-2 rounded-md px-3 py-2 text-xs">
                            <?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View Members</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($canSeeMemberNames): ?>
                <div id="adminOrgMembersModal" class="updates-modal-overlay hidden admin-org-members-modal" data-modal-close>
                    <div class="mx-auto mt-8 w-full max-w-2xl">
                        <div class="admin-org-members-panel glass p-6 max-h-[92dvh] overflow-y-auto" data-modal-panel>
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Organization Members</span></h3>
                                    <p class="section-helper-copy">Search members for <?= e((string) $org['name']) ?>.</p>
                                </div>
                                <button type="button" id="adminOrgMembersClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                            </div>
                            <div class="mb-3 flex flex-wrap gap-2 text-xs">
                                <span class="meta-chip meta-chip-accent">Members: <?= (int) $orgMemberCount ?></span>
                                <span class="meta-chip"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                            </div>
                            <div class="mb-3">
                                <input type="search" id="adminOrgMembersSearch" inputmode="search" placeholder="Search member name..." class="w-full border rounded px-3 py-2">
                            </div>
                            <div class="max-h-[62vh] overflow-auto rounded border border-slate-200/60">
                                <div id="adminOrgMembersList" class="divide-y divide-slate-200/50">
                                    <?php if ($orgMemberCount > 0): ?>
                                        <?php foreach ($orgMembers as $member): ?>
                                            <?php $memberName = (string) ($member['name'] ?? 'Member'); ?>
                                            <div class="admin-org-member-item px-3 py-2 text-sm text-slate-800 transition-colors" data-member-name="<?= e(strtolower($memberName)) ?>">
                                                <span class="inline-flex items-center gap-2">
                                                    <?= renderProfileMedia($memberName, (string) ($member['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($member['profile_picture_crop_x'] ?? 50), (float) ($member['profile_picture_crop_y'] ?? 50), (float) ($member['profile_picture_zoom'] ?? 1)) ?>
                                                    <span><?= e($memberName) ?></span>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-state-search">No members have joined this organization yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="adminOrgMembersEmptySearch" class="empty-state-search mt-3 hidden">No members matched that search.</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-800">Transaction History</h3>
                <a href="?page=my_org&org_id=<?= (int) $org['id'] ?>&action=export_transactions&format=pdf&tx_type=<?= urlencode($txTypeFilter) ?>&tx_sort=<?= urlencode($txDateSort) ?>" class="owner-manage-secondary-btn report-export-btn inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm">
                    Export PDF
                </a>
            </div>

            <form method="get" action="?page=my_org" class="mb-3 flex flex-wrap items-end gap-2">
                <input type="hidden" name="page" value="my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Type</label>
                    <select name="tx_type" class="border rounded px-2.5 py-1.5 text-xs">
                        <option value="all" <?= $txTypeFilter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="income" <?= $txTypeFilter === 'income' ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= $txTypeFilter === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Date</label>
                    <select name="tx_sort" class="border rounded px-2.5 py-1.5 text-xs">
                        <option value="desc" <?= $txDateSort === 'desc' ? 'selected' : '' ?>>Newest first</option>
                        <option value="asc" <?= $txDateSort === 'asc' ? 'selected' : '' ?>>Oldest first</option>
                    </select>
                </div>
                <button class="owner-manage-secondary-btn inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
            </form>

            <div class="table-wrapper">
                <table class="w-full min-w-[640px] text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Date</th><th>Type</th><th>Amount</th><th>Description</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tx as $row): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e(date('F d, Y', strtotime((string)$row['transaction_date']))) ?></td>
                        <td class="<?= $row['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>"><?= e($row['type']) ?></td>
                        <td>₱<?= number_format((float) $row['amount'], 2) ?></td>
                        <td><?= e($row['description']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
            <?php renderPagination($adminTxPagination); ?>
        <?php endif; ?>
    </div>
    <div id="adminOrgSearchModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-[2px] px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-12 w-full max-w-2xl">
            <div class="rounded-2xl border border-slate-200/70 bg-white/95 p-5 shadow-[0_24px_60px_rgba(15,23,42,0.38)] dark:border-emerald-300/25 dark:bg-[#021a14]/95 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('search', 'ui-icon') ?><span>Select Organization</span></h2>
                        <p class="section-helper-copy">Search or choose from the available organizations.</p>
                    </div>
                    <button type="button" id="adminOrgSearchModalClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>
                <div class="mb-3 flex flex-wrap gap-2 text-xs">
                    <span class="meta-chip meta-chip-accent">Available: <?= count($orgs) ?></span>
                    <span class="meta-chip">Search by name or description</span>
                </div>

                <div class="search-panel mb-4">
                    <label for="adminOrgSearchInput" class="search-panel-label">Search Organizations</label>
                    <input type="search" id="adminOrgSearchInput" inputmode="search" placeholder="Search organizations..." class="w-full border rounded px-3 py-3">
                </div>

                <div class="admin-org-search-scroll max-h-[60vh] overflow-auto rounded border border-slate-200/45">
                    <div class="divide-y divide-slate-200/45" id="adminOrgSearchList">
                        <?php foreach ($orgs as $option): ?>
                            <button
                                type="button"
                                class="admin-org-search-item w-full text-left px-4 py-3 transition-colors"
                                data-org-id="<?= (int) $option['id'] ?>"
                                data-org-name="<?= e((string) $option['name']) ?>"
                                data-org-description="<?= e((string) ($option['description'] ?? '')) ?>"
                            >
                                <div class="font-medium text-slate-900"><?= e((string) $option['name']) ?></div>
                                <div class="text-xs text-slate-600 mt-1"><span class="font-semibold">Visibility:</span> <?= e(getOrganizationVisibilityLabel($option)) ?></div>
                                <?php if (!empty($option['description'])): ?>
                                    <div class="text-xs text-slate-500 mt-1 line-clamp-2"><?= e((string) $option['description']) ?></div>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="adminOrgSearchEmpty" class="empty-state-search mt-3 hidden">No organizations matched that search.</div>
            </div>
        </div>
    </div>
    <style>
        #adminOrgSearchModal > div > div {
            background: rgb(249, 255, 252);
            border-color: rgba(148, 163, 184, 0.35);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }

        #adminOrgSearchInput {
            background: rgba(240, 255, 245, 0.98);
            border-color: rgba(148, 163, 184, 0.55);
            color: #0f172a;
        }

        #adminOrgSearchModal h2,
        #adminOrgSearchModal p,
        #adminOrgSearchModal .admin-org-search-item,
        #adminOrgSearchModal .admin-org-search-item .text-slate-900 {
            color: #0f172a;
        }

        #adminOrgSearchModal .admin-org-search-item .text-slate-600 {
            color: #475569;
        }

        #adminOrgSearchModal .admin-org-search-item .text-slate-500 {
            color: #64748b;
        }

        .admin-org-search-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .admin-org-search-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        #adminOrgSearchModal .admin-org-search-item {
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(226, 232, 240, 0.65);
        }

        #adminOrgSearchModal .admin-org-search-item:hover {
            background: rgba(236, 253, 245, 0.96);
        }

        body.theme-dark #adminOrgSearchModal h2,
        body.theme-dark #adminOrgSearchModal p,
        body.theme-dark #adminOrgSearchModal .admin-org-search-item,
        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-900 {
            color: #ecfdf5 !important;
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-600 {
            color: #a7f3d0 !important;
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-500 {
            color: rgba(209, 250, 229, 0.76) !important;
        }

        body.theme-dark #adminOrgSearchModal > div > div {
            background: rgba(2, 22, 18, 0.96);
            border-color: rgba(110, 231, 183, 0.28);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
        }

        body.theme-dark #adminOrgSearchModal h2,
        body.theme-dark #adminOrgSearchModal p,
        body.theme-dark #adminOrgSearchModal .admin-org-search-item,
        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-900 {
            color: #ecfdf5 !important;
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-600 {
            color: #a7f3d0 !important;
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item .text-slate-500 {
            color: rgba(209, 250, 229, 0.76) !important;
        }

        body.theme-dark #adminOrgSearchInput {
            background: rgba(2, 44, 34, 0.66);
            border-color: rgba(110, 231, 183, 0.35);
            color: #ecfdf5;
        }

        body.theme-dark #adminOrgSearchInput::placeholder {
            color: rgba(209, 250, 229, 0.52);
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item {
            background: rgba(2, 22, 18, 0.96);
        }

        body.theme-dark #adminOrgSearchModal .admin-org-search-item:hover {
            background: rgba(6, 78, 59, 0.62);
        }

        #adminOrgMembersModal .admin-org-member-item:hover {
            background: rgba(167, 243, 208, 0.24);
        }

        body.theme-dark #adminOrgMembersModal .admin-org-members-panel {
            background: rgba(2, 22, 18, 0.96);
            border-color: rgba(110, 231, 183, 0.28);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
        }

        body.theme-dark #adminOrgMembersModal h3,
        body.theme-dark #adminOrgMembersModal p,
        body.theme-dark #adminOrgMembersModal .admin-org-member-item,
        body.theme-dark #adminOrgMembersModal .text-slate-800 {
            color: #ecfdf5 !important;
        }

        body.theme-dark #adminOrgMembersModal .text-slate-600,
        body.theme-dark #adminOrgMembersModal .text-slate-500 {
            color: #a7f3d0 !important;
        }

        body.theme-dark #adminOrgMembersSearch {
            background: rgba(2, 44, 34, 0.66);
            border-color: rgba(110, 231, 183, 0.35);
            color: #ecfdf5;
        }

        body.theme-dark #adminOrgMembersModal .admin-org-member-item:hover {
            background: rgba(6, 78, 59, 0.62);
        }
    </style>
    <script>
        (function () {
            const searchButton = document.getElementById('adminOrgSearchButton');
            const modal = document.getElementById('adminOrgSearchModal');
            const closeButton = document.getElementById('adminOrgSearchModalClose');
            const searchInput = document.getElementById('adminOrgSearchInput');
            const searchList = document.getElementById('adminOrgSearchList');
            const searchEmpty = document.getElementById('adminOrgSearchEmpty');
            const orgIdInput = document.getElementById('adminOrgIdInput');
            const orgSearchNameInput = document.getElementById('adminOrgSearchName');
            const form = searchButton.closest('form');

            const membersOpenButton = document.getElementById('adminOrgMembersOpen');
            const membersModal = document.getElementById('adminOrgMembersModal');
            const membersCloseButton = document.getElementById('adminOrgMembersClose');
            const membersSearchInput = document.getElementById('adminOrgMembersSearch');
            const memberItems = Array.from(document.querySelectorAll('.admin-org-member-item'));
            const membersEmptySearch = document.getElementById('adminOrgMembersEmptySearch');
            let lastSearchTrigger = null;
            let lastMembersTrigger = null;

            function openModal() {
                lastSearchTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                modal.classList.remove('hidden');
                searchInput.value = '';
                filterList();
                searchInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
                searchInput.value = '';
                filterList();
                if (lastSearchTrigger && typeof lastSearchTrigger.focus === 'function') {
                    lastSearchTrigger.focus();
                }
                lastSearchTrigger = null;
            }

            function openMembersModal() {
                if (!membersModal) return;
                lastMembersTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                membersModal.classList.remove('hidden');
                if (membersSearchInput) {
                    membersSearchInput.value = '';
                    filterMembers();
                    membersSearchInput.focus();
                }
            }

            function closeMembersModal() {
                if (!membersModal) return;
                membersModal.classList.add('hidden');
                if (membersSearchInput) {
                    membersSearchInput.value = '';
                    filterMembers();
                }
                if (lastMembersTrigger && typeof lastMembersTrigger.focus === 'function') {
                    lastMembersTrigger.focus();
                }
                lastMembersTrigger = null;
            }

            function filterList() {
                const query = searchInput.value.trim().toLowerCase();
                let visibleCount = 0;
                searchList.querySelectorAll('.admin-org-search-item').forEach(function (item) {
                    const name = (item.getAttribute('data-org-name') || '').toLowerCase();
                    const description = (item.getAttribute('data-org-description') || '').toLowerCase();
                    const matches = query === '' || name.includes(query) || description.includes(query);
                    item.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                    }
                });
                if (searchEmpty) {
                    searchEmpty.classList.toggle('hidden', visibleCount !== 0);
                }
            }

            function filterMembers() {
                if (!membersSearchInput) return;
                const query = membersSearchInput.value.trim().toLowerCase();
                let visibleCount = 0;
                memberItems.forEach(function (item) {
                    const name = item.getAttribute('data-member-name') || '';
                    const matches = query === '' || name.includes(query);
                    item.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                    }
                });
                if (membersEmptySearch) {
                    membersEmptySearch.classList.toggle('hidden', visibleCount !== 0);
                }
            }

            searchButton.addEventListener('click', openModal);
            closeButton.addEventListener('click', closeModal);
            searchInput.addEventListener('input', filterList);

            if (membersOpenButton) {
                membersOpenButton.addEventListener('click', openMembersModal);
            }
            if (membersCloseButton) {
                membersCloseButton.addEventListener('click', closeMembersModal);
            }
            if (membersSearchInput) {
                membersSearchInput.addEventListener('input', filterMembers);
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            if (membersModal) {
                membersModal.addEventListener('click', function (event) {
                    if (event.target === membersModal) {
                        closeMembersModal();
                    }
                });
            }

            document.querySelectorAll('.admin-org-search-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    orgIdInput.value = item.getAttribute('data-org-id') || '';
                    orgSearchNameInput.value = item.getAttribute('data-org-name') || '';
                    closeModal();
                    form.submit();
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    if (!modal.classList.contains('hidden')) {
                        closeModal();
                    }
                    if (membersModal && !membersModal.classList.contains('hidden')) {
                        closeMembersModal();
                    }
                }
            });
        })();
    </script>
    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleMyOrgUserOverviewPage(PDO $db, array $user): void
{
    requireRole(['owner', 'student']);

    $orgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();
    $membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
    $membershipStmt->execute([(int) $user['id']]);
    $memberOrganizationIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));
    $orgs = applyOrganizationVisibilityForUser($orgs, $user, $memberOrganizationIds);
    $ownedOrgIds = [];
    foreach ($orgs as $candidateOrg) {
        if ((int) ($candidateOrg['owner_id'] ?? 0) === (int) $user['id']) {
            $ownedOrgIds[] = (int) $candidateOrg['id'];
        }
    }

    usort($orgs, static function (array $left, array $right) use ($ownedOrgIds): int {
        $leftOwned = in_array((int) ($left['id'] ?? 0), $ownedOrgIds, true);
        $rightOwned = in_array((int) ($right['id'] ?? 0), $ownedOrgIds, true);

        if ($leftOwned !== $rightOwned) {
            return $leftOwned ? -1 : 1;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    if (count($orgs) === 0) {
        setFlash('error', 'No organizations are available for your account yet.');
        redirect('?page=dashboard');
    }

    $selectedOrgId = (int) ($_GET['org_id'] ?? 0);
    if ($selectedOrgId <= 0) {
        $selectedOrgId = (int) $orgs[0]['id'];
    }

    $org = null;
    foreach ($orgs as $candidate) {
        if ((int) $candidate['id'] === $selectedOrgId) {
            $org = $candidate;
            break;
        }
    }

    if (!$org) {
        $org = $orgs[0];
        $selectedOrgId = (int) $org['id'];
    }

    $txTypeFilter = (string) ($_GET['tx_type'] ?? 'all');
    if (!in_array($txTypeFilter, ['all', 'income', 'expense'], true)) {
        $txTypeFilter = 'all';
    }

    $txDateSort = strtolower((string) ($_GET['tx_sort'] ?? 'desc'));
    if (!in_array($txDateSort, ['asc', 'desc'], true)) {
        $txDateSort = 'desc';
    }

    $txSql = 'SELECT * FROM financial_transactions WHERE organization_id = ?';
    $txParams = [(int) $selectedOrgId];
    if ($txTypeFilter !== 'all') {
        $txSql .= ' AND type = ?';
        $txParams[] = $txTypeFilter;
    }
    $txOrder = $txDateSort === 'asc' ? 'ASC' : 'DESC';
    $txSql .= " ORDER BY transaction_date {$txOrder}, id {$txOrder}";

    $txStmt = $db->prepare($txSql);
    $txStmt->execute($txParams);
    $transactions = $txStmt->fetchAll();
    $userTxPagination = paginateArray($transactions, 'pg_myorg_user_tx', 12);
    $transactions = $userTxPagination['items'];

    $memberStmt = $db->prepare('SELECT u.id, u.name, u.email, u.role, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom, om.joined_at,
        CASE WHEN o.owner_id = u.id THEN 1 ELSE 0 END AS is_owner
        FROM organization_members om
        JOIN users u ON u.id = om.user_id
        JOIN organizations o ON o.id = om.organization_id
        WHERE om.organization_id = ?
        ORDER BY CASE WHEN o.owner_id = u.id THEN 0 ELSE 1 END, u.name ASC');
    $memberStmt->execute([(int) $selectedOrgId]);
    $orgMembers = $memberStmt->fetchAll();
    $orgMemberCount = count($orgMembers);

    $viewerMembershipStmt = $db->prepare('SELECT 1 FROM organization_members WHERE organization_id = ? AND user_id = ? LIMIT 1');
    $viewerMembershipStmt->execute([(int) $selectedOrgId, (int) $user['id']]);
    $canSeeMemberNames = (bool) $viewerMembershipStmt->fetchColumn();

    renderHeader('My Organization');
    $selectedOrgName = (string) ($org['name'] ?? 'Select organization');
    ?>
    <div class="bg-white shadow rounded p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h1 class="text-xl font-semibold icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>Organization Overview</span></h1>
            <?php if (($user['role'] ?? '') === 'owner' && in_array((int) $selectedOrgId, $ownedOrgIds, true)): ?>
                <a href="?page=my_org_manage&org_id=<?= (int) $selectedOrgId ?>" class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-800 transition-colors hover:bg-emerald-500/15"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Manage Organization</span></span></a>
            <?php endif; ?>
        </div>

        <form method="get" class="mb-4 flex gap-2 items-stretch sm:items-start flex-wrap relative" data-dropdown-root>
            <input type="hidden" name="page" value="my_org">
            <input type="hidden" name="org_id" data-dropdown-value value="<?= (int) $selectedOrgId ?>">
            <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
            <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
            <div class="relative w-full min-w-0 sm:min-w-[16rem] sm:flex-1" data-dropdown-wrapper>
                <button type="button" data-dropdown-toggle="userOrgSwitcherMenu" aria-expanded="false" class="w-full flex items-center border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                    <span data-dropdown-label class="truncate text-left"><?= e($selectedOrgName) ?></span>
                </button>
                <div id="userOrgSwitcherMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                    <ul class="p-2 text-sm font-medium space-y-1">
                        <?php foreach ($orgs as $option): ?>
                            <li>
                                <button type="button" data-dropdown-option data-active="<?= $selectedOrgId === (int) $option['id'] ? 'true' : 'false' ?>" data-org-id="<?= (int) $option['id'] ?>" data-org-name="<?= e((string) $option['name']) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="truncate"><?= e((string) $option['name']) ?></span>
                                        <?php if (in_array((int) ($option['id'] ?? 0), $ownedOrgIds, true)): ?>
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-300/40">Owned</span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button class="w-full sm:w-auto rounded border border-slate-300/30 bg-white/10 px-4 py-2 text-slate-700 transition-colors hover:bg-white/15"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
        </form>

        <h2 class="text-lg font-semibold inline-flex items-center gap-2"><?= e((string) $org['name']) ?>
            <?php if (in_array((int) $selectedOrgId, $ownedOrgIds, true)): ?>
                <span class="text-[11px] px-2 py-0.5 rounded-md border border-emerald-300/30 bg-emerald-500/10 text-emerald-800">Owned</span>
            <?php endif; ?>
        </h2>
        <p class="text-gray-600 mb-3"><?= e((string) ($org['description'] ?? '')) ?></p>

        <div class="mb-4 rounded-lg border border-emerald-300/25 bg-white/10 p-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-semibold text-emerald-800">Total Members: <?= (int) $orgMemberCount ?></span>
                    <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-800"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                    <?php if (($user['role'] ?? '') === 'owner' && in_array((int) $selectedOrgId, $ownedOrgIds, true)): ?>
                        <span class="rounded-md border border-emerald-300/25 bg-emerald-500/8 px-2 py-0.5 text-[11px] text-emerald-700">Owner controls available</span>
                    <?php endif; ?>
                </div>
                <?php if ($canSeeMemberNames): ?>
                    <button type="button" id="userOrgMembersOpen" class="inline-flex items-center gap-2 bg-emerald-700 text-white px-3 py-2 rounded-md text-xs hover:bg-emerald-800 transition-colors">
                        <?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View Members</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" action="?page=my_org" class="mb-3 flex flex-wrap items-end gap-2">
            <input type="hidden" name="page" value="my_org">
            <input type="hidden" name="org_id" value="<?= (int) $selectedOrgId ?>">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Type</label>
                <select name="tx_type" class="border rounded px-2.5 py-1.5 text-xs">
                    <option value="all" <?= $txTypeFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="income" <?= $txTypeFilter === 'income' ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= $txTypeFilter === 'expense' ? 'selected' : '' ?>>Expense</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Date</label>
                <select name="tx_sort" class="border rounded px-2.5 py-1.5 text-xs">
                    <option value="desc" <?= $txDateSort === 'desc' ? 'selected' : '' ?>>Newest first</option>
                    <option value="asc" <?= $txDateSort === 'asc' ? 'selected' : '' ?>>Oldest first</option>
                </select>
            </div>
            <button class="inline-flex items-center justify-center bg-indigo-700 text-white px-2.5 py-1.5 rounded text-xs"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
        </form>

        <?php if ($canSeeMemberNames): ?>
            <div id="userOrgMembersModal" class="updates-modal-overlay hidden user-org-members-modal" data-modal-close>
                <div class="mx-auto mt-8 w-full max-w-2xl">
                    <div class="user-org-members-panel glass p-6 max-h-[92dvh] overflow-y-auto" data-modal-panel>
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-lg font-semibold icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Organization Members</span></h3>
                                <p class="section-helper-copy">Search members for <?= e((string) $org['name']) ?> and review who owns or joined the roster.</p>
                            </div>
                            <button type="button" id="userOrgMembersClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                        </div>
                        <div class="mb-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-800">Members: <?= (int) $orgMemberCount ?></span>
                            <span class="rounded-md border border-emerald-300/25 bg-white/10 px-2.5 py-1 text-slate-700"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                        </div>
                        <div class="mb-3">
                            <input type="search" id="userOrgMembersSearch" inputmode="search" placeholder="Search member name or email..." class="w-full border rounded px-3 py-2">
                        </div>
                        <div class="max-h-[62vh] overflow-auto rounded border border-slate-200/60">
                            <div id="userOrgMembersList" class="divide-y divide-slate-200/50">
                                <?php if ($orgMemberCount > 0): ?>
                                    <?php foreach ($orgMembers as $member): ?>
                                        <?php $memberName = (string) ($member['name'] ?? 'Member'); ?>
                                        <?php
                                            $memberEmail = (string) ($member['email'] ?? '');
                                            $joinedAt = (string) ($member['joined_at'] ?? '');
                                            $isMemberOwner = (int) ($member['is_owner'] ?? 0) === 1;
                                            $isCurrentViewer = (int) ($member['id'] ?? 0) === (int) $user['id'];
                                        ?>
                                        <div class="user-org-member-item px-3 py-3 text-sm text-slate-800 transition-colors" data-member-search="<?= e(strtolower($memberName . ' ' . $memberEmail)) ?>">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0 inline-flex items-start gap-3">
                                                    <?= renderProfileMedia($memberName, (string) ($member['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($member['profile_picture_crop_x'] ?? 50), (float) ($member['profile_picture_crop_y'] ?? 50), (float) ($member['profile_picture_zoom'] ?? 1)) ?>
                                                    <div class="min-w-0">
                                                        <div class="font-medium break-words"><?= e($memberName) ?></div>
                                                        <?php if ($memberEmail !== ''): ?>
                                                            <div class="text-xs text-slate-500 break-all mt-0.5"><?= e($memberEmail) ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($joinedAt !== ''): ?>
                                                            <div class="text-xs text-slate-500 mt-1">Joined <?= e(date('F d, Y', strtotime($joinedAt))) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="shrink-0 flex flex-wrap justify-end gap-1">
                                                    <?php if ($isMemberOwner): ?>
                                                        <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-800">Owner</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state-search">No members have joined this organization yet.</div>
                                <?php endif; ?>
                            </div>
                            <div id="userOrgMembersEmptySearch" class="empty-state-search hidden">No members matched that search.</div>
                        </div>
                    </div>
                </div>
            </div>
            <style>
                #adminOrgMembersModal .admin-org-member-item:hover,
                #userOrgMembersModal .user-org-member-item:hover {
                    background: rgba(16, 185, 129, 0.14);
                }

                body.theme-dark #adminOrgMembersModal .admin-org-members-panel,
                body.theme-dark #userOrgMembersModal .user-org-members-panel {
                    background: rgba(4, 24, 18, 0.72);
                    border-color: rgba(110, 231, 183, 0.28);
                    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
                }

                body.theme-dark #adminOrgMembersModal h3,
                body.theme-dark #adminOrgMembersModal p,
                body.theme-dark #adminOrgMembersModal .admin-org-member-item,
                body.theme-dark #adminOrgMembersModal .text-slate-800,
                body.theme-dark #userOrgMembersModal h3,
                body.theme-dark #userOrgMembersModal p,
                body.theme-dark #userOrgMembersModal .user-org-member-item,
                body.theme-dark #userOrgMembersModal .text-slate-800 {
                    color: #ecfdf5 !important;
                }

                body.theme-dark #adminOrgMembersModal .text-slate-600,
                body.theme-dark #adminOrgMembersModal .text-slate-500,
                body.theme-dark #userOrgMembersModal .text-slate-600,
                body.theme-dark #userOrgMembersModal .text-slate-500 {
                    color: #a7f3d0 !important;
                }

                body.theme-dark #adminOrgMembersSearch,
                body.theme-dark #userOrgMembersSearch {
                    background: rgba(2, 44, 34, 0.42);
                    border-color: rgba(110, 231, 183, 0.35);
                    color: #ecfdf5;
                }

                body.theme-dark #adminOrgMembersModal .admin-org-member-item:hover,
                body.theme-dark #userOrgMembersModal .user-org-member-item:hover {
                    background: rgba(6, 78, 59, 0.62);
                }
            </style>
            <script>
                (function () {
                    const openButton = document.getElementById('userOrgMembersOpen');
                    const modal = document.getElementById('userOrgMembersModal');
                    const closeButton = document.getElementById('userOrgMembersClose');
                    const searchInput = document.getElementById('userOrgMembersSearch');
                    const memberItems = Array.from(document.querySelectorAll('.user-org-member-item'));
                    const emptySearchState = document.getElementById('userOrgMembersEmptySearch');

                    if (!openButton || !modal || !closeButton || !searchInput) {
                        return;
                    }

                    function openModal() {
                        modal.classList.remove('hidden');
                        searchInput.value = '';
                        filterMembers();
                        searchInput.focus();
                    }

                    function closeModal() {
                        modal.classList.add('hidden');
                    }

                    function filterMembers() {
                        const query = searchInput.value.trim().toLowerCase();
                        let visibleCount = 0;
                        memberItems.forEach(function (item) {
                            const searchText = item.getAttribute('data-member-search') || '';
                            const isVisible = query === '' || searchText.includes(query);
                            item.classList.toggle('hidden', !isVisible);
                            if (isVisible) {
                                visibleCount += 1;
                            }
                        });
                        if (emptySearchState) {
                            emptySearchState.classList.toggle('hidden', visibleCount !== 0 || query === '');
                        }
                    }

                    openButton.addEventListener('click', openModal);
                    closeButton.addEventListener('click', closeModal);
                    searchInput.addEventListener('input', filterMembers);

                    modal.addEventListener('click', function (event) {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                })();
            </script>
        <?php endif; ?>

        <div class="table-wrapper">
            <table class="w-full min-w-[640px] text-sm">
            <thead>
            <tr class="text-left border-b">
                <th class="py-2">Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $row): ?>
                <tr class="border-b">
                    <td class="py-2"><?= e(date('F d, Y', strtotime((string) $row['transaction_date']))) ?></td>
                    <td class="<?= (string) $row['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>"><?= e((string) $row['type']) ?></td>
                    <td>₱<?= number_format((float) $row['amount'], 2) ?></td>
                    <td><?= e((string) $row['description']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        </div>
        <?php renderPagination($userTxPagination); ?>
    </div>
    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleAdminOrgsPage(PDO $db): void
{
    requireRole(['admin']);
    $instituteOptions = getInstituteOptions();
    $programInstituteMap = getProgramInstituteMap();
    $programOptions = array_keys($programInstituteMap);
    $orgCategoryOptions = getOrgCategoryOptions();

    $orgs = $db->query("SELECT o.*, u.name AS owner_name, u.profile_picture_path AS owner_profile_picture_path, u.profile_picture_crop_x AS owner_profile_picture_crop_x, u.profile_picture_crop_y AS owner_profile_picture_crop_y, u.profile_picture_zoom AS owner_profile_picture_zoom, oa.status AS assignment_status, su.name AS assigned_student_name, su.profile_picture_path AS assigned_student_profile_picture_path, su.profile_picture_crop_x AS assigned_student_profile_picture_crop_x, su.profile_picture_crop_y AS assigned_student_profile_picture_crop_y, su.profile_picture_zoom AS assigned_student_profile_picture_zoom
        FROM organizations o
        LEFT JOIN users u ON u.id = o.owner_id
        LEFT JOIN owner_assignments oa ON oa.organization_id = o.id AND oa.status = 'pending'
        LEFT JOIN users su ON su.id = oa.student_id
        ORDER BY o.id DESC")->fetchAll();
    $orgsPagination = paginateArray($orgs, 'pg_admin_orgs', 8);
    $orgs = $orgsPagination['items'];
    $students = $db->query("SELECT id, name, email FROM users WHERE role IN ('student','owner') ORDER BY name ASC")->fetchAll();

    renderHeader('Manage Organizations');
    ?>
    <div class="space-y-4">
        <div class="glass transparency-panel rounded-xl p-4 overflow-visible">
            <div class="mb-4">
                <h2 class="text-lg font-semibold mb-1 icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Create Organization</span></h2>
                <p class="section-helper-copy">Set up a new organization, assign its scope, and prepare it for ownership.</p>
            </div>
            <form method="post" enctype="multipart/form-data" class="space-y-3" data-org-scope-form>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_org">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                    <div class="space-y-3">
                        <input name="name" placeholder="Organization name" required class="w-full border rounded px-3 py-2">
                        <textarea name="description" placeholder="Description" class="w-full border rounded px-3 py-2 min-h-[7rem]"></textarea>
                        <div class="space-y-3">
                            <label class="block text-xs text-slate-600 mb-1">Organization Logo (optional)</label>
                            <div class="space-y-3" data-image-crop-form>
                                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                                    <div class="shrink-0">
                                        <?= renderProfilePlaceholder('Organization', 'organization', 'sm') ?>
                                    </div>
                                    <label for="adminCreateOrgLogo" class="org-logo-upload-trigger flex w-full min-w-0 cursor-pointer items-center gap-3 rounded-xl border border-dashed px-4 py-3 text-sm transition-colors sm:flex-1">
                                        <span class="org-logo-upload-trigger-icon inline-flex h-9 w-9 items-center justify-center rounded-full shadow-sm"><?= uiIcon('upload', 'ui-icon') ?></span>
                                        <span class="min-w-0 flex-1 font-medium">Choose organization logo</span>
                                        <span class="org-logo-upload-trigger-subtext shrink-0 text-xs">Click to browse</span>
                                    </label>
                                    <input id="adminCreateOrgLogo" type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" data-image-input>
                                </div>
                                <div class="hidden grid grid-cols-1 md:grid-cols-3 gap-3" aria-hidden="true">
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Crop X</span>
                                        <input type="range" min="0" max="100" step="1" name="logo_crop_x" value="50" class="w-full" data-crop-x>
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Crop Y</span>
                                        <input type="range" min="0" max="100" step="1" name="logo_crop_y" value="50" class="w-full" data-crop-y>
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Zoom</span>
                                        <input type="range" min="0.75" max="2.25" step="0.05" name="logo_zoom" value="1" class="w-full" data-crop-zoom>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <fieldset class="space-y-2">
                            <legend class="block text-sm font-medium text-slate-700">Organization scope</legend>
                            <p class="text-xs text-slate-600">Choose who should be allowed to discover and join this organization.</p>
                            <div class="org-scope-grid">
                                <label class="org-scope-card">
                                    <input type="radio" name="org_category" value="collegewide" checked data-org-scope-input>
                                    <span class="org-scope-card-title">Collegewide</span>
                                    <span class="org-scope-card-copy">Open to all students.</span>
                                </label>
                                <label class="org-scope-card">
                                    <input type="radio" name="org_category" value="institutewide" data-org-scope-input>
                                    <span class="org-scope-card-title">Institute-based</span>
                                    <span class="org-scope-card-copy">Limited to one institute.</span>
                                </label>
                                <label class="org-scope-card">
                                    <input type="radio" name="org_category" value="program_based" data-org-scope-input>
                                    <span class="org-scope-card-title">Program-based</span>
                                    <span class="org-scope-card-copy">Limited to one program.</span>
                                </label>
                            </div>
                        </fieldset>
                        <div class="space-y-2 hidden" data-org-scope-section="institutewide">
                            <label class="block text-sm font-medium text-slate-700" for="adminCreateOrgInstitute">Institute</label>
                            <div class="relative w-full" data-dropdown-root data-themed-picker>
                                <input type="hidden" id="adminCreateOrgInstitute" name="target_institute" data-dropdown-value value="">
                                <div class="relative w-full" data-dropdown-wrapper>
                                    <button type="button" data-dropdown-toggle="adminCreateOrgInstituteMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                        <span data-dropdown-label data-dropdown-placeholder="Choose institute" class="truncate text-left">Choose institute</span>
                                    </button>
                                    <div id="adminCreateOrgInstituteMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                        <ul class="scrollbar-hidden p-2 text-sm font-medium space-y-1 max-h-64 overflow-y-auto">
                                            <li><button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="Choose institute" class="block w-full rounded px-3 py-2 text-left transition-colors">Choose institute</button></li>
                                            <?php foreach ($instituteOptions as $institute): ?>
                                                <li><button type="button" data-dropdown-option data-active="false" data-option-value="<?= e($institute) ?>" data-option-label="<?= e($institute) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors"><?= e($institute) ?></button></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-600">Only students from this institute will be eligible to join.</p>
                        </div>
                        <div class="space-y-2 hidden" data-org-scope-section="program_based">
                            <label class="block text-sm font-medium text-slate-700" for="adminCreateOrgProgram">Program</label>
                            <div class="relative w-full" data-dropdown-root data-themed-picker>
                                <input type="hidden" id="adminCreateOrgProgram" name="target_program" data-dropdown-value value="">
                                <div class="relative w-full" data-dropdown-wrapper>
                                    <button type="button" data-dropdown-toggle="adminCreateOrgProgramMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                        <span data-dropdown-label data-dropdown-placeholder="Choose program" class="truncate text-left">Choose program</span>
                                    </button>
                                    <div id="adminCreateOrgProgramMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                        <ul class="scrollbar-hidden p-2 text-sm font-medium space-y-1 max-h-64 overflow-y-auto">
                                            <li><button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="Choose program" class="block w-full rounded px-3 py-2 text-left transition-colors">Choose program</button></li>
                                            <?php foreach ($programOptions as $programOption): ?>
                                                <li><button type="button" data-dropdown-option data-active="false" data-option-value="<?= e($programOption) ?>" data-option-label="<?= e($programOption) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors"><?= e($programOption) ?></button></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-600">Program-based organizations automatically inherit the correct institute.</p>
                        </div>
                    </div>
                </div>
                <button class="owner-manage-primary-btn px-4 py-2 rounded-md"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Create</span></span></button>
            </form>
        </div>

        <div class="md:col-span-2 glass transparency-panel rounded-xl p-4 overflow-auto">
            <div class="mb-4">
                <h2 class="text-lg font-semibold mb-1 icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h2>
                <p class="section-helper-copy">Review visibility, ownership, and pending assignments in one list.</p>
            </div>
            <div class="search-panel mb-4">
                <label for="adminOrgInlineSearch" class="search-panel-label">Search Organizations</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-700"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?></span>
                    <input type="search" id="adminOrgInlineSearch" inputmode="search" placeholder="Search organization, owner, visibility, or description..." class="themed-field w-full py-2 pl-10 pr-3">
                </div>
            </div>
            <div class="table-wrapper hidden lg:block">
                <table class="hidden lg:table w-full text-sm table-fixed">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-3 px-3 w-[22%]">Name</th>
                    <th class="py-3 px-3 w-[38%]">Description</th>
                    <th class="py-3 px-3 w-[16%]">Visibility</th>
                    <th class="py-3 px-3 w-[14%]">Owner</th>
                    <th class="py-3 px-3 w-[10%]">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orgs as $org): ?>
                    <?php
                        $orgSearchText = strtolower(trim(
                            (string) ($org['name'] ?? '') . ' ' .
                            (string) ($org['description'] ?? '') . ' ' .
                            (string) ($org['owner_name'] ?? '') . ' ' .
                            (string) getOrganizationVisibilityLabel($org)
                        ));
                    ?>
                    <tr class="border-b align-top admin-org-row" data-org-search="<?= e($orgSearchText) ?>">
                        <td class="py-4 px-3 font-medium break-words leading-relaxed">
                            <span class="inline-flex items-center gap-2">
                                <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'xs', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                <span><?= e($org['name']) ?></span>
                            </span>
                        </td>
                        <td class="py-4 px-3 break-words leading-relaxed"><?= e($org['description']) ?></td>
                        <td class="py-4 px-3">
                            <span class="text-xs leading-5"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                        </td>
                        <td class="py-4 px-3">
                            <div class="space-y-2">
                                <div class="text-sm font-medium inline-flex items-center gap-2">
                                    <?= renderProfileMedia((string) ($org['owner_name'] ?? 'Unassigned'), (string) ($org['owner_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($org['owner_profile_picture_crop_x'] ?? 50), (float) ($org['owner_profile_picture_crop_y'] ?? 50), (float) ($org['owner_profile_picture_zoom'] ?? 1)) ?>
                                    <span><?= e($org['owner_name'] ?? 'Unassigned') ?></span>
                                </div>
                                <?php if (!empty($org['assignment_status'])): ?>
                                    <div class="text-[11px] text-amber-200 inline-flex items-center gap-2">
                                        <?= renderProfileMedia((string) ($org['assigned_student_name'] ?? 'Student'), (string) ($org['assigned_student_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($org['assigned_student_profile_picture_crop_x'] ?? 50), (float) ($org['assigned_student_profile_picture_crop_y'] ?? 50), (float) ($org['assigned_student_profile_picture_zoom'] ?? 1)) ?>
                                        <span>Pending: <?= e($org['assigned_student_name'] ?? 'Student') ?> (awaiting response)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-3">
                            <button
                                type="button"
                                data-org-edit-open
                                data-org-id="<?= (int) $org['id'] ?>"
                                data-org-name="<?= e((string) $org['name']) ?>"
                                data-org-description="<?= e((string) $org['description']) ?>"
                                data-org-category="<?= e((string) ($org['org_category'] ?? 'collegewide')) ?>"
                                data-org-target-institute="<?= e((string) ($org['target_institute'] ?? '')) ?>"
                                data-org-target-program="<?= e((string) ($org['target_program'] ?? '')) ?>"
                                data-org-owner-id="<?= (int) ($org['owner_id'] ?? 0) ?>"
                                data-org-logo-path="<?= e((string) ($org['logo_path'] ?? '')) ?>"
                                class="owner-manage-secondary-btn text-sm px-3.5 py-2 rounded-md inline-flex items-center gap-2"
                            >
                                <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Update</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
            <div class="lg:hidden space-y-3">
                <?php foreach ($orgs as $org): ?>
                    <?php
                        $orgSearchText = strtolower(trim(
                            (string) ($org['name'] ?? '') . ' ' .
                            (string) ($org['description'] ?? '') . ' ' .
                            (string) ($org['owner_name'] ?? '') . ' ' .
                            (string) getOrganizationVisibilityLabel($org)
                        ));
                    ?>
                    <article class="admin-mobile-card rounded-xl border border-emerald-200/40 bg-white/10 p-3 admin-org-card" data-org-search="<?= e($orgSearchText) ?>">
                        <div class="space-y-2 min-w-0">
                            <div class="admin-mobile-title inline-flex items-center gap-2 min-w-0">
                                <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'xs', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                <span class="break-words leading-5"><?= e((string) $org['name']) ?></span>
                            </div>
                            <span class="inline-flex max-w-full text-[11px] px-2 py-1 rounded-full border border-emerald-300/40 bg-emerald-500/20 whitespace-normal break-words leading-4">
                                <?= e((string) getOrganizationVisibilityLabel($org)) ?>
                            </span>
                            <p class="admin-mobile-copy break-words leading-6">
                                <?= e((string) $org['description']) ?>
                            </p>
                        </div>

                        <div class="mt-3 rounded-lg border border-emerald-200/35 bg-emerald-500/10 p-2 admin-mobile-meta">
                            <div class="font-semibold mb-1">Owner</div>
                            <div class="inline-flex items-center gap-2 min-w-0">
                                <?= renderProfileMedia((string) ($org['owner_name'] ?? 'Unassigned'), (string) ($org['owner_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($org['owner_profile_picture_crop_x'] ?? 50), (float) ($org['owner_profile_picture_crop_y'] ?? 50), (float) ($org['owner_profile_picture_zoom'] ?? 1)) ?>
                                <span class="break-words leading-5"><?= e((string) ($org['owner_name'] ?? 'Unassigned')) ?></span>
                            </div>
                            <?php if (!empty($org['assignment_status'])): ?>
                                <div class="mt-2 flex flex-wrap items-start gap-2 text-[11px] text-amber-200 min-w-0">
                                    <?= renderProfileMedia((string) ($org['assigned_student_name'] ?? 'Student'), (string) ($org['assigned_student_profile_picture_path'] ?? ''), 'user', 'xs', (float) ($org['assigned_student_profile_picture_crop_x'] ?? 50), (float) ($org['assigned_student_profile_picture_crop_y'] ?? 50), (float) ($org['assigned_student_profile_picture_zoom'] ?? 1)) ?>
                                    <span class="break-words leading-4">Pending: <?= e((string) ($org['assigned_student_name'] ?? 'Student')) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <button
                                type="button"
                                data-org-edit-open
                                data-org-id="<?= (int) $org['id'] ?>"
                                data-org-name="<?= e((string) $org['name']) ?>"
                                data-org-description="<?= e((string) $org['description']) ?>"
                                data-org-category="<?= e((string) ($org['org_category'] ?? 'collegewide')) ?>"
                                data-org-target-institute="<?= e((string) ($org['target_institute'] ?? '')) ?>"
                                data-org-target-program="<?= e((string) ($org['target_program'] ?? '')) ?>"
                                data-org-owner-id="<?= (int) ($org['owner_id'] ?? 0) ?>"
                                data-org-logo-path="<?= e((string) ($org['logo_path'] ?? '')) ?>"
                                class="owner-manage-secondary-btn w-full text-sm px-3.5 py-2 rounded-md inline-flex items-center justify-center gap-2"
                            >
                                <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Update</span>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div id="adminOrgInlineSearchEmpty" class="empty-state-search mt-3 hidden">No organizations matched that search.</div>
            <div id="admin-all-organizations-pagination">
                <?php renderPagination($orgsPagination + ['anchor' => 'admin-all-organizations-pagination']); ?>
            </div>
        </div>
    </div>
    <div id="orgEditModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-10 w-full max-w-3xl">
            <div class="glass p-6 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>Edit Organization</span></h2>
                        <p class="section-helper-copy max-w-xl">Update the organization details in one place without crowding the page.</p>
                    </div>
                    <button type="button" id="orgEditModalClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>

                <form method="post" id="orgEditModalForm" enctype="multipart/form-data" class="space-y-6">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_org_admin">
                    <input type="hidden" name="org_id" id="orgEditModalOrgId" value="">

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <label for="orgEditModalName" class="block text-sm font-medium text-slate-700">Organization Name</label>
                            <input id="orgEditModalName" name="name" inputmode="text" class="w-full border rounded px-3 py-3" required>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label for="orgEditModalDescription" class="block text-sm font-medium text-slate-700">Description</label>
                            <textarea id="orgEditModalDescription" name="description" rows="4" class="w-full border rounded px-3 py-3"></textarea>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label for="orgEditModalLogo" class="block text-sm font-medium text-slate-700">Organization Logo</label>
                            <div class="space-y-3" data-image-crop-form>
                                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                                    <div class="shrink-0" data-crop-preview>
                                        <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                    </div>
                                    <label for="orgEditModalLogo" class="org-logo-upload-trigger flex w-full min-w-0 cursor-pointer items-center gap-3 rounded-xl border border-dashed px-4 py-3 text-sm transition-colors sm:flex-1">
                                        <span class="org-logo-upload-trigger-icon inline-flex h-9 w-9 items-center justify-center rounded-full shadow-sm"><?= uiIcon('upload', 'ui-icon') ?></span>
                                        <span class="min-w-0 flex-1 font-medium">Choose organization logo</span>
                                        <span class="org-logo-upload-trigger-subtext shrink-0 text-xs">Click to browse</span>
                                    </label>
                                    <input id="orgEditModalLogo" type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" data-image-input>
                                </div>
                                <div class="hidden grid grid-cols-1 md:grid-cols-3 gap-3" aria-hidden="true">
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Crop X</span>
                                        <input type="range" min="0" max="100" step="1" name="logo_crop_x" value="<?= (float) ($org['logo_crop_x'] ?? 50) ?>" class="w-full" data-crop-x>
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Crop Y</span>
                                        <input type="range" min="0" max="100" step="1" name="logo_crop_y" value="<?= (float) ($org['logo_crop_y'] ?? 50) ?>" class="w-full" data-crop-y>
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Zoom</span>
                                        <input type="range" min="0.75" max="2.25" step="0.05" name="logo_zoom" value="<?= (float) ($org['logo_zoom'] ?? 1) ?>" class="w-full" data-crop-zoom>
                                    </label>
                                </div>
                            </div>
                            <p id="orgEditModalLogoHint" class="text-xs text-slate-600">Leave empty to keep current logo.</p>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalCategory" class="block text-sm font-medium text-slate-700">Organization Category</label>
                            <select id="orgEditModalCategory" name="org_category" class="w-full border rounded px-3 py-3" required>
                                <?php foreach ($orgCategoryOptions as $categoryKey => $categoryLabel): ?>
                                    <option value="<?= e($categoryKey) ?>"><?= e($categoryLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalOwner" class="block text-sm font-medium text-slate-700">Owner</label>
                            <div class="relative w-full" data-dropdown-root data-themed-picker>
                                <input type="hidden" id="orgEditModalOwner" name="owner_id" data-dropdown-value value="">
                                <div class="relative w-full" data-dropdown-wrapper>
                                    <button type="button" data-dropdown-toggle="orgEditModalOwnerMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                        <span data-dropdown-label data-dropdown-placeholder="Search and choose owner" class="truncate text-left">Search and choose owner</span>
                                    </button>
                                    <div id="orgEditModalOwnerMenu" data-dropdown-menu class="absolute left-0 bottom-full mb-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                        <div class="border-b border-emerald-300/20 p-2">
                                            <input type="search" id="orgEditModalOwnerSearch" data-dropdown-search inputmode="search" placeholder="Search owner by name..." class="w-full border rounded px-3 py-2 text-sm">
                                        </div>
                                        <ul id="orgEditModalOwnerOptions" class="scrollbar-hidden p-2 text-sm font-medium space-y-1 max-h-64 overflow-y-auto">
                                            <li>
                                                <button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="-- none --" data-owner-name="none" class="block w-full rounded px-3 py-2 text-left transition-colors">
                                                    -- none --
                                                </button>
                                            </li>
                                            <?php foreach ($students as $student): ?>
                                                <li data-owner-option>
                                                    <button
                                                        type="button"
                                                        data-dropdown-option
                                                        data-active="false"
                                                        data-option-value="<?= (int) $student['id'] ?>"
                                                        data-option-label="<?= e($student['name']) ?>"
                                                        data-owner-name="<?= e(strtolower((string) $student['name'])) ?>"
                                                        class="block w-full rounded px-3 py-2 text-left transition-colors"
                                                    >
                                                        <?= e($student['name']) ?>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div id="orgEditModalOwnerEmpty" class="hidden border-t border-emerald-300/20 px-3 py-2 text-xs text-slate-600">
                                            No owners matched that search.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalInstitute" class="block text-sm font-medium text-slate-700">Target Institute</label>
                            <div class="relative w-full" data-dropdown-root data-themed-picker>
                                <input type="hidden" id="orgEditModalInstitute" name="target_institute" data-dropdown-value value="">
                                <div class="relative w-full" data-dropdown-wrapper>
                                    <button type="button" data-dropdown-toggle="orgEditModalInstituteMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                        <span data-dropdown-label data-dropdown-placeholder="Choose institute" class="truncate text-left">Choose institute</span>
                                    </button>
                                    <div id="orgEditModalInstituteMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                        <ul class="scrollbar-hidden p-2 text-sm font-medium space-y-1 max-h-64 overflow-y-auto">
                                            <li><button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="Choose institute" class="block w-full rounded px-3 py-2 text-left transition-colors">Choose institute</button></li>
                                            <?php foreach ($instituteOptions as $institute): ?>
                                                <li><button type="button" data-dropdown-option data-active="false" data-option-value="<?= e($institute) ?>" data-option-label="<?= e($institute) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors"><?= e($institute) ?></button></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalProgram" class="block text-sm font-medium text-slate-700">Target Program</label>
                            <div class="relative w-full" data-dropdown-root data-themed-picker>
                                <input type="hidden" id="orgEditModalProgram" name="target_program" data-dropdown-value value="">
                                <div class="relative w-full" data-dropdown-wrapper>
                                    <button type="button" data-dropdown-toggle="orgEditModalProgramMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                        <span data-dropdown-label data-dropdown-placeholder="Choose program" class="truncate text-left">Choose program</span>
                                    </button>
                                    <div id="orgEditModalProgramMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                        <ul class="scrollbar-hidden p-2 text-sm font-medium space-y-1 max-h-64 overflow-y-auto">
                                            <li><button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="Choose program" class="block w-full rounded px-3 py-2 text-left transition-colors">Choose program</button></li>
                                            <?php foreach ($programOptions as $programOption): ?>
                                                <li><button type="button" data-dropdown-option data-active="false" data-option-value="<?= e($programOption) ?>" data-option-label="<?= e($programOption) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors"><?= e($programOption) ?></button></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <button type="button" id="orgEditModalCancel" class="owner-manage-secondary-btn px-3 py-2 rounded-md text-sm">Cancel</button>
                        <button type="submit" class="owner-manage-primary-btn px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2">
                            <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Save Changes</span>
                        </button>
                    </div>
                </form>

                    <form method="post" id="orgEditModalDeleteForm" class="mt-5 flex justify-start pt-4 border-t border-slate-200/60" data-confirm-message="Delete this organization?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_org">
                    <input type="hidden" name="org_id" id="orgEditModalDeleteOrgId" value="">
                    <button type="submit" class="tx-action-btn tx-action-btn-delete px-3 py-2 rounded-md text-sm inline-flex items-center gap-2">
                        <?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete Organization</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const inlineSearchInput = document.getElementById('adminOrgInlineSearch');
            const inlineRows = Array.from(document.querySelectorAll('.admin-org-row'));
            const inlineCards = Array.from(document.querySelectorAll('.admin-org-card'));
            const inlineEmpty = document.getElementById('adminOrgInlineSearchEmpty');

            function filterInlineOrganizations() {
                if (!inlineSearchInput) {
                    return;
                }

                const query = inlineSearchInput.value.trim().toLowerCase();
                let visibleCount = 0;

                inlineRows.forEach(function (row) {
                    const haystack = row.getAttribute('data-org-search') || '';
                    const matches = query === '' || haystack.includes(query);
                    row.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                    }
                });

                inlineCards.forEach(function (card) {
                    const haystack = card.getAttribute('data-org-search') || '';
                    const matches = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !matches);
                });

                if (inlineEmpty) {
                    inlineEmpty.classList.toggle('hidden', visibleCount !== 0);
                }
            }

            if (inlineSearchInput) {
                inlineSearchInput.addEventListener('input', filterInlineOrganizations);
            }

            const modal = document.getElementById('orgEditModal');
            const closeButton = document.getElementById('orgEditModalClose');
            const cancelButton = document.getElementById('orgEditModalCancel');
            const form = document.getElementById('orgEditModalForm');
            const deleteOrgIdInput = document.getElementById('orgEditModalDeleteOrgId');
            const orgIdInput = document.getElementById('orgEditModalOrgId');
            const nameInput = document.getElementById('orgEditModalName');
            const descriptionInput = document.getElementById('orgEditModalDescription');
            const categoryInput = document.getElementById('orgEditModalCategory');
            const ownerInput = document.getElementById('orgEditModalOwner');
            const ownerSearchInput = document.getElementById('orgEditModalOwnerSearch');
            const ownerSearchEmpty = document.getElementById('orgEditModalOwnerEmpty');
            const instituteInput = document.getElementById('orgEditModalInstitute');
            const programInput = document.getElementById('orgEditModalProgram');
            const createScopeForms = document.querySelectorAll('[data-org-scope-form]');
            let lastEditTrigger = null;

            function resetThemedPicker(field) {
                if (!field) {
                    return;
                }

                field.value = '';
                const root = field.closest('[data-dropdown-root]');
                if (!root) {
                    return;
                }

                const label = root.querySelector('[data-dropdown-label]');
                if (label) {
                    label.textContent = label.getAttribute('data-dropdown-placeholder') || '';
                }

                root.querySelectorAll('[data-dropdown-option]').forEach(function (option) {
                    option.dataset.active = option.getAttribute('data-option-value') === '' ? 'true' : 'false';
                });
            }

            function syncOwnerPicker(value) {
                if (!ownerInput) {
                    return;
                }

                ownerInput.value = value || '';
                const root = ownerInput.closest('[data-dropdown-root]');
                if (!root) {
                    return;
                }

                const label = root.querySelector('[data-dropdown-label]');
                const options = Array.from(root.querySelectorAll('[data-dropdown-option]'));
                let matchedOption = null;

                options.forEach(function (option) {
                    const isMatch = (option.getAttribute('data-option-value') || '') === ownerInput.value;
                    option.dataset.active = isMatch ? 'true' : 'false';
                    if (isMatch) {
                        matchedOption = option;
                    }
                });

                if (label) {
                    label.textContent = matchedOption
                        ? (matchedOption.getAttribute('data-option-label') || matchedOption.textContent || '').trim()
                        : (label.getAttribute('data-dropdown-placeholder') || 'Search and choose owner');
                }
            }

            function filterOwnerOptions() {
                if (!ownerSearchInput) {
                    return;
                }

                const query = ownerSearchInput.value.trim().toLowerCase();
                const optionItems = Array.from(document.querySelectorAll('#orgEditModalOwnerOptions [data-owner-option]'));
                let visibleCount = 0;

                optionItems.forEach(function (item) {
                    const optionButton = item.querySelector('[data-dropdown-option]');
                    const haystack = (optionButton ? optionButton.getAttribute('data-owner-name') : '') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    item.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                if (ownerSearchEmpty) {
                    ownerSearchEmpty.classList.toggle('hidden', visibleCount !== 0);
                }
            }

            function syncCreateScope(form) {
                const checked = form.querySelector('[data-org-scope-input]:checked');
                const selectedScope = checked ? checked.value : 'collegewide';
                form.querySelectorAll('[data-org-scope-section]').forEach(function (section) {
                    const scope = section.getAttribute('data-org-scope-section');
                    const isVisible = scope === selectedScope;
                    section.classList.toggle('hidden', !isVisible);
                    section.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
                    section.querySelectorAll('[data-dropdown-value], select').forEach(function (field) {
                        if (!isVisible) {
                            if (field.matches('[data-dropdown-value]')) {
                                resetThemedPicker(field);
                            } else {
                                field.value = '';
                            }
                        }
                    });
                });
            }

            function syncEditScope() {
                const selectedScope = categoryInput.value || 'collegewide';
                const instituteWrap = instituteInput.closest('.space-y-2');
                const programWrap = programInput.closest('.space-y-2');
                const showInstitute = selectedScope === 'institutewide';
                const showProgram = selectedScope === 'program_based';

                if (instituteWrap) {
                    instituteWrap.classList.toggle('hidden', !showInstitute);
                    instituteWrap.setAttribute('aria-hidden', showInstitute ? 'false' : 'true');
                }

                if (programWrap) {
                    programWrap.classList.toggle('hidden', !showProgram);
                    programWrap.setAttribute('aria-hidden', showProgram ? 'false' : 'true');
                }

                if (!showInstitute) {
                    resetThemedPicker(instituteInput);
                }

                if (!showProgram) {
                    resetThemedPicker(programInput);
                }
            }

            createScopeForms.forEach(function (form) {
                form.querySelectorAll('[data-org-scope-input]').forEach(function (input) {
                    input.addEventListener('change', function () {
                        syncCreateScope(form);
                    });
                });
                syncCreateScope(form);
            });

            function openModal(button) {
                lastEditTrigger = button;
                const orgId = button.getAttribute('data-org-id') || '';
                orgIdInput.value = orgId;
                deleteOrgIdInput.value = orgId;
                nameInput.value = button.getAttribute('data-org-name') || '';
                descriptionInput.value = button.getAttribute('data-org-description') || '';
                categoryInput.value = button.getAttribute('data-org-category') || 'collegewide';
                syncOwnerPicker(button.getAttribute('data-org-owner-id') || '');
                instituteInput.value = button.getAttribute('data-org-target-institute') || '';
                programInput.value = button.getAttribute('data-org-target-program') || '';
                if (ownerSearchInput) {
                    ownerSearchInput.value = '';
                    filterOwnerOptions();
                }
                syncEditScope();
                modal.classList.remove('hidden');
                nameInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
                if (ownerSearchInput) {
                    ownerSearchInput.value = '';
                    filterOwnerOptions();
                }
                if (lastEditTrigger && typeof lastEditTrigger.focus === 'function') {
                    lastEditTrigger.focus();
                }
                lastEditTrigger = null;
            }

            document.querySelectorAll('[data-org-edit-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button);
                });
            });

            closeButton.addEventListener('click', closeModal);
            cancelButton.addEventListener('click', closeModal);
            categoryInput.addEventListener('change', syncEditScope);
            if (ownerSearchInput) {
                ownerSearchInput.addEventListener('input', filterOwnerOptions);
            }
            syncEditScope();

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}
