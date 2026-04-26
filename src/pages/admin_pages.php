<?php

declare(strict_types=1);

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

    renderHeader('Filter Students');
    ?>
    <div class="bg-white shadow rounded p-4">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Filter All Student Information</span></h1>
        <form method="get" class="flex gap-2 mb-4">
            <input type="hidden" name="page" value="admin_students">
            <input name="q" value="<?= e($q) ?>" placeholder="Search by name or email" class="border rounded px-3 py-2 w-full">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
        </form>

        <div class="overflow-auto">
            <div class="table-wrapper">
                <table class="w-full text-sm">
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
                        <td><?= e($student['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
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
    <div class="bg-white shadow rounded p-6 overflow-auto">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Owner Requests for Transaction Edit/Delete</span></h1>
        <div class="table-wrapper">
            <table class="w-full text-sm table-fixed">
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
                            <div class="text-xs">Date: <?= e((string) $req['proposed_transaction_date']) ?></div>
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
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="approve" class="inline-flex items-center justify-center bg-emerald-600 text-white px-3 py-2 rounded-md min-w-[6.25rem] hover:bg-emerald-700 transition-colors">
                                    <span class="icon-label w-[4.75rem] justify-start leading-none"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span class="inline-block w-[3.8rem] text-left leading-none">Approve</span></span>
                                </button>
                                <button type="button" data-tx-request-open data-request-id="<?= (int) $req['id'] ?>" data-request-action="reject" class="inline-flex items-center justify-center bg-red-600 text-white px-3 py-2 rounded-md min-w-[6.25rem] hover:bg-red-700 transition-colors">
                                    <span class="icon-label w-[4.75rem] justify-start leading-none"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span class="inline-block w-[2.5rem] text-center leading-none">Reject</span></span>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-500 whitespace-nowrap">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        </div>
        <?php renderPagination($requestsPagination); ?>
    </div>
    <div id="txRequestNoteModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-16 w-full max-w-lg">
            <div class="glass p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Review Request</span></h2>
                        <p class="text-sm text-slate-600 mt-1">Leave an admin note before approving or rejecting.</p>
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
                        <button type="button" id="txRequestModalCancel" class="border border-slate-300 text-slate-700 px-3 py-2 rounded text-sm">Cancel</button>
                        <button type="submit" id="txRequestModalSubmit" class="bg-emerald-600 text-white px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2">
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

            function openModal(requestId, decision) {
                requestIdInput.value = requestId;
                decisionInput.value = decision;
                submitLabel.textContent = decision === 'approve' ? 'Approve Request' : 'Reject Request';
                submitButton.className = decision === 'approve'
                    ? 'bg-emerald-600 text-white px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2'
                    : 'bg-red-600 text-white px-4 py-2.5 rounded-md text-sm inline-flex items-center gap-2';
                modal.classList.remove('hidden');
                noteInput.value = '';
                noteInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
                requestIdInput.value = '';
                decisionInput.value = '';
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
    <script src="static/js/owner-org-switcher.js"></script>
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
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT al.*, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         WHERE al.created_at >= ?
         ORDER BY al.id DESC
         LIMIT 300"
    );
    $stmt->execute([$cutoff]);
    $logs = $stmt->fetchAll();
    $logsPagination = paginateArray($logs, 'pg_admin_audit', 20);
    $logs = $logsPagination['items'];

    renderHeader('Audit Logs', $user);
    ?>
    <section class="bg-white rounded shadow p-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Audit Logs</h2>
            <form method="get" class="flex items-center gap-2">
                <input type="hidden" name="page" value="admin_audit" />
                <label class="text-sm text-gray-600" for="days">Last</label>
                <select name="days" id="days" class="border rounded px-2 py-1 text-sm" onchange="this.form.submit()">
                    <?php foreach ([1, 3, 7, 14, 30, 90] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $days === $opt ? 'selected' : '' ?>><?= $opt ?> days</option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!$logs): ?>
            <p class="text-sm text-gray-600">No audit entries in the selected range.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 pr-3">Time</th>
                            <th class="text-left py-2 pr-3">Actor</th>
                            <th class="text-left py-2 pr-3">Action</th>
                            <th class="text-left py-2 pr-3">Entity</th>
                            <th class="text-left py-2 pr-3">Entity ID</th>
                            <th class="text-left py-2 pr-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr class="border-b align-top">
                                <td class="py-2 pr-3 whitespace-nowrap"><?= e($log['created_at']) ?></td>
                                <td class="py-2 pr-3"><?= e($log['actor_name'] ?: ('User#' . (int) $log['user_id'])) ?></td>
                                <td class="py-2 pr-3"><?= e($log['action']) ?></td>
                                <td class="py-2 pr-3"><?= e($log['entity_type'] ?? '-') ?></td>
                                <td class="py-2 pr-3"><?= $log['entity_id'] !== null ? (int) $log['entity_id'] : '-' ?></td>
                                <td class="py-2 pr-3 break-words max-w-xl"><?= e($log['details'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php renderPagination($logsPagination); ?>
            </div>
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
    <div class="bg-white shadow rounded p-4">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>Organization Overview</span></h1>
        <form method="get" class="mb-4 flex items-start gap-2 relative">
            <input type="hidden" name="page" value="my_org">
            <input type="hidden" name="org_id" id="adminOrgIdInput" value="<?= $orgId > 0 ? (int) $orgId : '' ?>">
            <input type="hidden" name="org_search_name" id="adminOrgSearchName" value="<?= e($selectedOrgName) ?>">
            <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
            <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
            <button type="button" id="adminOrgSearchButton" class="inline-flex items-center gap-2 border rounded px-4 py-2 bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                <?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Search Organizations</span>
            </button>
            <button type="submit" class="bg-indigo-700 text-white px-4 py-2 rounded inline-flex items-center gap-2 hover:bg-indigo-800 transition-colors">
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
            <p class="text-gray-600 mb-3"><?= e($org['description']) ?></p>

            <div class="mb-4 rounded-xl border border-emerald-200/55 bg-emerald-50/30 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-emerald-900">Total Members: <?= (int) $orgMemberCount ?></p>
                    <?php if ($canSeeMemberNames): ?>
                        <button type="button" id="adminOrgMembersOpen" class="inline-flex items-center gap-2 bg-emerald-700 text-white px-3 py-2 rounded text-xs hover:bg-emerald-800 transition-colors">
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
                                    <p class="text-sm text-slate-600 mt-1">Search members for <?= e((string) $org['name']) ?>.</p>
                                </div>
                                <button type="button" id="adminOrgMembersClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                            </div>
                            <div class="mb-3">
                                <input type="text" id="adminOrgMembersSearch" inputmode="search" placeholder="Search member name..." class="w-full border rounded px-3 py-2">
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
                                        <div class="px-3 py-3 text-sm text-slate-600">No members have joined this organization yet.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-800">Transaction History</h3>
                <a href="?page=my_org&org_id=<?= (int) $org['id'] ?>&action=export_transactions&format=pdf&tx_type=<?= urlencode($txTypeFilter) ?>&tx_sort=<?= urlencode($txDateSort) ?>" class="report-export-btn inline-flex items-center gap-2 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                    Export PDF
                </a>
            </div>

            <form method="get" action="?page=my_org" class="mb-3 flex flex-wrap items-end gap-2">
                <input type="hidden" name="page" value="my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
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

            <div class="table-wrapper">
                <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Date</th><th>Type</th><th>Amount</th><th>Description</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tx as $row): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e($row['transaction_date']) ?></td>
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
                        <p class="text-sm text-slate-600 mt-1">Search or choose from the available organizations.</p>
                    </div>
                    <button type="button" id="adminOrgSearchModalClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>

                <div class="mb-4">
                    <input type="text" id="adminOrgSearchInput" inputmode="search" placeholder="Search organizations..." class="w-full border rounded px-3 py-3">
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
            const orgIdInput = document.getElementById('adminOrgIdInput');
            const orgSearchNameInput = document.getElementById('adminOrgSearchName');
            const form = searchButton.closest('form');

            const membersOpenButton = document.getElementById('adminOrgMembersOpen');
            const membersModal = document.getElementById('adminOrgMembersModal');
            const membersCloseButton = document.getElementById('adminOrgMembersClose');
            const membersSearchInput = document.getElementById('adminOrgMembersSearch');
            const memberItems = Array.from(document.querySelectorAll('.admin-org-member-item'));

            function openModal() {
                modal.classList.remove('hidden');
                searchInput.value = '';
                filterList();
                searchInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            function openMembersModal() {
                if (!membersModal) return;
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
            }

            function filterList() {
                const query = searchInput.value.trim().toLowerCase();
                searchList.querySelectorAll('.admin-org-search-item').forEach(function (item) {
                    const name = (item.getAttribute('data-org-name') || '').toLowerCase();
                    const description = (item.getAttribute('data-org-description') || '').toLowerCase();
                    const matches = query === '' || name.includes(query) || description.includes(query);
                    item.classList.toggle('hidden', !matches);
                });
            }

            function filterMembers() {
                if (!membersSearchInput) return;
                const query = membersSearchInput.value.trim().toLowerCase();
                memberItems.forEach(function (item) {
                    const name = item.getAttribute('data-member-name') || '';
                    item.classList.toggle('hidden', !(query === '' || name.includes(query)));
                });
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

    $memberStmt = $db->prepare('SELECT u.name, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom FROM organization_members om JOIN users u ON u.id = om.user_id WHERE om.organization_id = ? ORDER BY u.name ASC');
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
                <a href="?page=my_org_manage&org_id=<?= (int) $selectedOrgId ?>" class="bg-emerald-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Manage Organization</span></span></a>
            <?php endif; ?>
        </div>

        <form method="get" class="mb-4 flex gap-2 items-start flex-wrap relative" data-dropdown-root>
            <input type="hidden" name="page" value="my_org">
            <input type="hidden" name="org_id" data-dropdown-value value="<?= (int) $selectedOrgId ?>">
            <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
            <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
            <div class="relative min-w-[16rem]" data-dropdown-wrapper>
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
            <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
        </form>

        <h2 class="text-lg font-semibold inline-flex items-center gap-2"><?= e((string) $org['name']) ?>
            <?php if (in_array((int) $selectedOrgId, $ownedOrgIds, true)): ?>
                <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-300/40">Owned</span>
            <?php endif; ?>
        </h2>
        <p class="text-gray-600 mb-3"><?= e((string) ($org['description'] ?? '')) ?></p>

        <div class="mb-4 rounded-xl border border-emerald-200/55 bg-emerald-50/30 p-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-emerald-900">Total Members: <?= (int) $orgMemberCount ?></p>
                <?php if ($canSeeMemberNames): ?>
                    <button type="button" id="userOrgMembersOpen" class="inline-flex items-center gap-2 bg-emerald-700 text-white px-3 py-2 rounded text-xs hover:bg-emerald-800 transition-colors">
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
                                <p class="text-sm text-slate-600 mt-1">Search members for <?= e((string) $org['name']) ?>.</p>
                            </div>
                            <button type="button" id="userOrgMembersClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                        </div>
                        <div class="mb-3">
                            <input type="text" id="userOrgMembersSearch" inputmode="search" placeholder="Search member name..." class="w-full border rounded px-3 py-2">
                        </div>
                        <div class="max-h-[62vh] overflow-auto rounded border border-slate-200/60">
                            <div id="userOrgMembersList" class="divide-y divide-slate-200/50">
                                <?php if ($orgMemberCount > 0): ?>
                                    <?php foreach ($orgMembers as $member): ?>
                                        <?php $memberName = (string) ($member['name'] ?? 'Member'); ?>
                                        <div class="user-org-member-item px-3 py-2 text-sm text-slate-800 transition-colors" data-member-name="<?= e(strtolower($memberName)) ?>">
                                            <span class="inline-flex items-center gap-2">
                                                <?= renderProfileMedia($memberName, (string) ($member['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($member['profile_picture_crop_x'] ?? 50), (float) ($member['profile_picture_crop_y'] ?? 50), (float) ($member['profile_picture_zoom'] ?? 1)) ?>
                                                <span><?= e($memberName) ?></span>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="px-3 py-3 text-sm text-slate-600">No members have joined this organization yet.</div>
                                <?php endif; ?>
                            </div>
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
                        memberItems.forEach(function (item) {
                            const name = item.getAttribute('data-member-name') || '';
                            item.classList.toggle('hidden', !(query === '' || name.includes(query)));
                        });
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
            <table class="w-full text-sm">
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
                    <td class="py-2"><?= e((string) $row['transaction_date']) ?></td>
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
    <script src="static/js/owner-org-switcher.js"></script>
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
    <div class="grid md:grid-cols-3 gap-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Create Organization</span></h2>
            <form method="post" enctype="multipart/form-data" class="space-y-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_org">
                <input name="name" placeholder="Organization name" required class="w-full border rounded px-3 py-2">
                <textarea name="description" placeholder="Description" class="w-full border rounded px-3 py-2"></textarea>
                <div class="space-y-3">
                    <label class="block text-xs text-gray-600 mb-1">Organization Logo (optional)</label>
                    <div class="space-y-3" data-image-crop-form>
                        <div class="flex items-center gap-3">
                            <div class="shrink-0">
                                <?= renderProfilePlaceholder('Organization', 'organization', 'sm') ?>
                            </div>
                            <label for="adminCreateOrgLogo" class="inline-flex min-h-[3rem] flex-1 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-900 transition-colors hover:bg-emerald-100/70">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm">
                                    <svg viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor" aria-hidden="true"><path d="M12 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7L12 20l4.7-5.3c.8-1 1.3-2.3 1.3-3.7 0-3.3-2.7-6-6-6zm0 8.3A2.3 2.3 0 1 1 12 8.7a2.3 2.3 0 0 1 0 4.6z"/></svg>
                                </span>
                                <span class="font-medium">Choose organization logo</span>
                                <span class="text-xs text-emerald-700/80">Click to browse</span>
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
                <select name="org_category" class="w-full border rounded px-3 py-2" required>
                    <?php foreach ($orgCategoryOptions as $categoryKey => $categoryLabel): ?>
                        <option value="<?= e($categoryKey) ?>"><?= e($categoryLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="target_institute" class="w-full border rounded px-3 py-2">
                    <option value="">Institute target (for institutewide)</option>
                    <?php foreach ($instituteOptions as $institute): ?>
                        <option value="<?= e($institute) ?>"><?= e($institute) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="target_program" class="w-full border rounded px-3 py-2">
                    <option value="">Program target (for program-based)</option>
                    <?php foreach ($programOptions as $programOption): ?>
                        <option value="<?= e($programOption) ?>"><?= e($programOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Create</span></span></button>
            </form>
        </div>

        <div class="md:col-span-2 bg-white shadow rounded p-6 overflow-auto">
            <h2 class="text-lg font-semibold mb-4 icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h2>
            <div class="table-wrapper">
                <table class="w-full text-sm table-fixed">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-3 px-4 w-[18%]">Name</th>
                    <th class="py-3 px-4 w-[30%]">Description</th>
                    <th class="py-3 px-4 w-[15%]">Visibility</th>
                    <th class="py-3 px-4 w-[22%]">Owner</th>
                    <th class="py-3 px-4 w-[15%]">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orgs as $org): ?>
                    <tr class="border-b align-top">
                        <td class="py-4 px-4 font-medium break-words leading-relaxed">
                            <span class="inline-flex items-center gap-2">
                                <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'xs', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                <span><?= e($org['name']) ?></span>
                            </span>
                        </td>
                        <td class="py-4 px-4 break-words leading-relaxed"><?= e($org['description']) ?></td>
                        <td class="py-4 px-4">
                            <span class="text-xs"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                        </td>
                        <td class="py-4 px-4">
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
                        <td class="py-4 px-4">
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
                                class="bg-blue-600 text-white text-sm px-3.5 py-2 rounded inline-flex items-center gap-2 hover:bg-blue-700 transition-colors"
                            >
                                <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Update</span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
            <?php renderPagination($orgsPagination); ?>
        </div>
    </div>
    <div id="orgEditModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-10 w-full max-w-3xl">
            <div class="glass p-6 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>Edit Organization</span></h2>
                        <p class="text-sm text-slate-600 mt-1 max-w-xl">Update the organization details in one place without crowding the page.</p>
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
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0" data-crop-preview>
                                        <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                    </div>
                                    <label for="orgEditModalLogo" class="inline-flex min-h-[3rem] flex-1 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-900 transition-colors hover:bg-emerald-100/70">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm">
                                            <svg viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor" aria-hidden="true"><path d="M12 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7L12 20l4.7-5.3c.8-1 1.3-2.3 1.3-3.7 0-3.3-2.7-6-6-6zm0 8.3A2.3 2.3 0 1 1 12 8.7a2.3 2.3 0 0 1 0 4.6z"/></svg>
                                        </span>
                                        <span class="font-medium">Choose organization logo</span>
                                        <span class="text-xs text-emerald-700/80">Click to browse</span>
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
                            <select id="orgEditModalOwner" name="owner_id" class="w-full border rounded px-3 py-3">
                                <option value="">-- none --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) $student['id'] ?>"><?= e($student['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalInstitute" class="block text-sm font-medium text-slate-700">Target Institute</label>
                            <select id="orgEditModalInstitute" name="target_institute" class="w-full border rounded px-3 py-3">
                                <option value="">Institute target (for institutewide)</option>
                                <?php foreach ($instituteOptions as $institute): ?>
                                    <option value="<?= e($institute) ?>"><?= e($institute) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label for="orgEditModalProgram" class="block text-sm font-medium text-slate-700">Target Program</label>
                            <select id="orgEditModalProgram" name="target_program" class="w-full border rounded px-3 py-3">
                                <option value="">Program target (for program-based)</option>
                                <?php foreach ($programOptions as $programOption): ?>
                                    <option value="<?= e($programOption) ?>"><?= e($programOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <button type="button" id="orgEditModalCancel" class="border border-slate-300 text-slate-700 px-3 py-2 rounded text-sm">Cancel</button>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2.5 rounded text-sm inline-flex items-center gap-2 hover:bg-blue-700 transition-colors">
                            <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Save Changes</span>
                        </button>
                    </div>
                </form>

                <form method="post" id="orgEditModalDeleteForm" class="mt-5 flex justify-start pt-4 border-t border-slate-200/60" onsubmit="return confirm('Delete this organization?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_org">
                    <input type="hidden" name="org_id" id="orgEditModalDeleteOrgId" value="">
                    <button type="submit" class="bg-red-600 text-white px-3 py-2 rounded text-sm inline-flex items-center gap-2 hover:bg-red-700 transition-colors">
                        <?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete Organization</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
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
            const instituteInput = document.getElementById('orgEditModalInstitute');
            const programInput = document.getElementById('orgEditModalProgram');

            function openModal(button) {
                const orgId = button.getAttribute('data-org-id') || '';
                orgIdInput.value = orgId;
                deleteOrgIdInput.value = orgId;
                nameInput.value = button.getAttribute('data-org-name') || '';
                descriptionInput.value = button.getAttribute('data-org-description') || '';
                categoryInput.value = button.getAttribute('data-org-category') || 'collegewide';
                ownerInput.value = button.getAttribute('data-org-owner-id') || '';
                instituteInput.value = button.getAttribute('data-org-target-institute') || '';
                programInput.value = button.getAttribute('data-org-target-program') || '';
                modal.classList.remove('hidden');
                nameInput.focus();
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            document.querySelectorAll('[data-org-edit-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button);
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
    <?php
    renderFooter();
    exit;
}
