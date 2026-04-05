<?php

declare(strict_types=1);

function handleMyOrgOwnerPage(PDO $db, array $user, string $announcementCutoff): void
{
    requireRole(['owner']);
    $ownedOrganizations = getOwnedOrganizations((int) $user['id']);
    if (count($ownedOrganizations) === 0) {
        setFlash('error', 'No organization is assigned to your account yet.');
        redirect('?page=dashboard');
    }

    $selectedOrgId = (int) ($_GET['org_id'] ?? 0);
    if ($selectedOrgId <= 0) {
        $selectedOrgId = (int) $ownedOrganizations[0]['id'];
    }

    $org = getOwnedOrganizationById((int) $user['id'], $selectedOrgId);
    if (!$org) {
        setFlash('error', 'Selected organization is not assigned to your account.');
        redirect('?page=my_org');
    }

    $stmt = $db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND created_at >= ? ORDER BY id DESC');
    $stmt->execute([(int) $org['id'], $announcementCutoff]);
    $announcements = $stmt->fetchAll();

    $txTypeFilter = (string) ($_GET['tx_type'] ?? 'all');
    if (!in_array($txTypeFilter, ['all', 'income', 'expense'], true)) {
        $txTypeFilter = 'all';
    }

    $txDateSort = strtolower((string) ($_GET['tx_sort'] ?? 'desc'));
    if (!in_array($txDateSort, ['asc', 'desc'], true)) {
        $txDateSort = 'desc';
    }

    $txSql = 'SELECT * FROM financial_transactions WHERE organization_id = ?';
    $txParams = [(int) $org['id']];
    if ($txTypeFilter !== 'all') {
        $txSql .= ' AND type = ?';
        $txParams[] = $txTypeFilter;
    }
    $txOrder = $txDateSort === 'asc' ? 'ASC' : 'DESC';
    $txSql .= " ORDER BY transaction_date {$txOrder}, id {$txOrder}";

    $stmt = $db->prepare($txSql);
    $stmt->execute($txParams);
    $transactions = $stmt->fetchAll();

    $txRequestStmt = $db->prepare("SELECT * FROM transaction_change_requests WHERE organization_id = ? AND requested_by = ? ORDER BY created_at DESC LIMIT 20");
    $txRequestStmt->execute([(int) $org['id'], (int) $user['id']]);
    $myTxRequests = $txRequestStmt->fetchAll();

    $joinRequestStmt = $db->prepare("SELECT r.id, r.created_at, u.name, u.email
        FROM organization_join_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.organization_id = ? AND r.status = 'pending'
        ORDER BY r.created_at DESC");
    $joinRequestStmt->execute([(int) $org['id']]);
    $pendingJoinRequests = $joinRequestStmt->fetchAll();

    $pendingJoinPagination = paginateArray($pendingJoinRequests, 'pg_myorg_join', 5);
    $pendingJoinRequests = $pendingJoinPagination['items'];
    $announcementsPagination = paginateArray($announcements, 'pg_myorg_ann', 5);
    $announcements = $announcementsPagination['items'];
    $transactionsPagination = paginateArray($transactions, 'pg_myorg_tx', 10);
    $transactions = $transactionsPagination['items'];
    $myTxRequestsPagination = paginateArray($myTxRequests, 'pg_myorg_req', 8);
    $myTxRequests = $myTxRequestsPagination['items'];

    renderHeader('My Organization');
    ?>
    <div class="space-y-4">
        <div class="bg-white shadow rounded p-4">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Pending Membership Requests</span></h2>
            <?php if (count($pendingJoinRequests) === 0): ?>
                <p class="text-sm text-gray-500">No pending join requests for this organization.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($pendingJoinRequests as $request): ?>
                        <div class="border rounded p-3 flex flex-wrap justify-between items-center gap-3">
                            <div>
                                <div class="font-medium"><?= e($request['name']) ?></div>
                                #userOrgMembersModal .user-org-members-panel {
                                    background: rgba(83, 255, 183, 0.43);
                                    border-color: rgba(148, 163, 184, 0.35);
                                    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
                                }

                                #userOrgMembersModal h3,
                                #userOrgMembersModal p,
                                #userOrgMembersModal .user-org-member-item,
                                #userOrgMembersModal .text-slate-800 {
                                    color: #0f172a !important;
                                }

                                #userOrgMembersModal .text-slate-600,
                                #userOrgMembersModal .text-slate-500 {
                                    color: #475569 !important;
                                }

                                #userOrgMembersSearch {
                                    background: rgba(124, 255, 142, 0.41);
                                    border-color: rgba(148, 163, 184, 0.55);
                                    color: #0f172a;
                                }

                                #userOrgMembersList {
                                    background: rgba(116, 255, 202, 0.57);
                                }

                                <div class="text-xs text-gray-500"><?= e($request['email']) ?> · <?= e($request['created_at']) ?></div>
                                    background: rgba(142, 255, 202, 0.33);
                            <div class="flex gap-2">
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                </form>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <button class="bg-red-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Decline</span></span></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php renderPagination($pendingJoinPagination); ?>
            <?php endif; ?>
        </div>

        <div class="bg-white shadow rounded p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <h1 class="text-xl font-semibold icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>My Organization</span></h1>
                <a href="?page=my_org&org_id=<?= (int) $org['id'] ?>" class="text-sm text-indigo-700 underline whitespace-nowrap mt-1"><span class="icon-label"><?= uiIcon('prev', 'ui-icon ui-icon-sm') ?><span>Back to My Organization</span></span></a>
            </div>
            <?php $selectedOrgName = (string) ($org['name'] ?? 'Select organization'); ?>
            <form method="get" class="mb-4 flex gap-2 items-start relative" id="myOrgSwitcherForm" data-dropdown-root>
                <input type="hidden" name="page" value="my_org_manage">
                <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
                <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
                <input type="hidden" name="org_id" id="myOrgOrgId" data-dropdown-value value="<?= (int) $org['id'] ?>">
                <div class="relative min-w-[16rem]" data-dropdown-wrapper>
                    <button type="button" id="myOrgSwitcherButton" data-dropdown-toggle="myOrgSwitcherMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                        <span id="myOrgSwitcherLabel" data-dropdown-label class="truncate text-left"><?= e($selectedOrgName) ?></span>
                        <span class="hidden text-xs">▾</span>
                    </button>
                    <div id="myOrgSwitcherMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md" aria-labelledby="myOrgSwitcherButton">
                        <ul class="p-2 text-sm font-medium space-y-1">
                            <?php foreach ($ownedOrganizations as $ownedOption): ?>
                                <?php $isCurrentOrg = (int) $org['id'] === (int) $ownedOption['id']; ?>
                                <li>
                                    <button type="button" data-dropdown-option data-active="<?= $isCurrentOrg ? 'true' : 'false' ?>" data-org-id="<?= (int) $ownedOption['id'] ?>" data-org-name="<?= e($ownedOption['name']) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors">
                                        <?= e($ownedOption['name']) ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>
            <form method="post" class="grid md:grid-cols-2 gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div>
                    <label class="text-sm text-gray-600">Organization Name</label>
                    <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Description</label>
                    <textarea name="description" class="w-full border rounded px-3 py-2"><?= e($org['description']) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Organization Info</span></span></button>
                </div>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Post Announcement</span></h2>
                <form method="post" class="space-y-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_announcement">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input name="title" placeholder="Title" class="w-full border rounded px-3 py-2" required>
                    <textarea name="content" placeholder="Announcement details" class="w-full border rounded px-3 py-2" required></textarea>
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Post</span></span></button>
                </form>

                <div class="mt-4 space-y-2 max-h-72 overflow-auto">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="border rounded p-2">
                            <div class="font-medium"><?= e($announcement['title']) ?></div>
                            <div class="text-sm text-slate-700"><?= e($announcement['content']) ?></div>
                            <form method="post" class="mt-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete</span></span></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php renderPagination($announcementsPagination); ?>
            </div>

            <div class="bg-white shadow rounded p-4">
                <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Add Income / Expense</span></h2>
                <form method="post" enctype="multipart/form-data" class="space-y-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <div class="grid grid-cols-2 gap-2" data-dropdown-root>
                        <input type="hidden" name="type" data-dropdown-value value="income">
                        <div class="relative w-full" data-dropdown-wrapper>
                            <button type="button" data-dropdown-toggle="myOrgAddTypeMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                <span data-dropdown-label class="truncate text-left">Income</span>
                                <span class="hidden text-xs">▾</span>
                            </button>
                            <div id="myOrgAddTypeMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                <ul class="p-2 text-sm font-medium space-y-1">
                                    <li><button type="button" data-dropdown-option data-active="true" data-option-value="income" data-option-label="Income" class="block w-full rounded px-3 py-2 text-left transition-colors">Income</button></li>
                                    <li><button type="button" data-dropdown-option data-active="false" data-option-value="expense" data-option-label="Expense" class="block w-full rounded px-3 py-2 text-left transition-colors">Expense</button></li>
                                </ul>
                            </div>
                        </div>
                        <input type="number" step="0.01" name="amount" placeholder="Amount" class="border rounded px-3 py-2" data-currency required>
                    </div>
                    <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" class="w-full border rounded px-3 py-2" required>
                    <input name="description" placeholder="Description" class="w-full border rounded px-3 py-2" required>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full border rounded px-3 py-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Transaction</span></span></button>
                </form>
            </div>
        </div>

        <div id="tx-history" class="bg-white shadow rounded p-4 overflow-auto">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Transaction History</span></h2>
                <a href="?page=my_org&org_id=<?= (int) $org['id'] ?>&action=export_transactions&format=csv" class="report-export-btn inline-flex items-center gap-2 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                    Export CSV
                </a>
            </div>
            <form method="get" action="?page=my_org_manage#tx-history" class="mb-3 flex flex-wrap items-end gap-2" data-dropdown-root onsubmit="const b=this.querySelector('[data-filter-submit]'); if(b){ b.disabled=true; b.textContent='Filtering...'; }">
                <input type="hidden" name="page" value="my_org_manage">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                    <div class="relative min-w-[10rem]" data-dropdown-wrapper>
                        <input type="hidden" name="tx_type" data-dropdown-value value="<?= e($txTypeFilter) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxTypeMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-sm transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txTypeFilter === 'income' ? 'Income' : ($txTypeFilter === 'expense' ? 'Expense' : 'All')) ?></span>
                            <span class="hidden text-xs">▾</span>
                        </button>
                        <div id="myOrgTxTypeMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                            <ul class="p-2 text-sm font-medium space-y-1">
                                <li><button type="button" data-dropdown-option data-active="<?= $txTypeFilter === 'all' ? 'true' : 'false' ?>" data-option-value="all" data-option-label="All" class="block w-full rounded px-3 py-2 text-left transition-colors">All</button></li>
                                <li><button type="button" data-dropdown-option data-active="<?= $txTypeFilter === 'income' ? 'true' : 'false' ?>" data-option-value="income" data-option-label="Income" class="block w-full rounded px-3 py-2 text-left transition-colors">Income</button></li>
                                <li><button type="button" data-dropdown-option data-active="<?= $txTypeFilter === 'expense' ? 'true' : 'false' ?>" data-option-value="expense" data-option-label="Expense" class="block w-full rounded px-3 py-2 text-left transition-colors">Expense</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Date</label>
                    <div class="relative min-w-[10rem]" data-dropdown-wrapper>
                        <input type="hidden" name="tx_sort" data-dropdown-value value="<?= e($txDateSort) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxSortMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-sm transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txDateSort === 'asc' ? 'Oldest first' : 'Newest first') ?></span>
                            <span class="hidden text-xs">▾</span>
                        </button>
                        <div id="myOrgTxSortMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                            <ul class="p-2 text-sm font-medium space-y-1">
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'desc' ? 'true' : 'false' ?>" data-option-value="desc" data-option-label="Newest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Newest first</button></li>
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'asc' ? 'true' : 'false' ?>" data-option-value="asc" data-option-label="Oldest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Oldest first</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button data-filter-submit class="bg-indigo-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
            </form>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b border-emerald-400">
                    <th class="py-2">Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Description</th>
                    <th>Receipt</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $row): ?>
                    <tr class="border-b border-emerald-300 align-top">
                        <td class="py-3"><?= e($row['transaction_date']) ?></td>
                        <td class="py-3"><?= e($row['type']) ?></td>
                        <td class="py-3">₱<?= number_format((float) $row['amount'], 2) ?></td>
                        <td class="py-3"><?= e($row['description']) ?></td>
                        <td class="py-3">
                            <?php if (!empty($row['receipt_path'])): ?>
                                <a href="<?= e($row['receipt_path']) ?>" target="_blank" class="text-indigo-700 underline"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View</span></span></a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 space-y-1 min-w-56">
                            <form method="post" class="grid grid-cols-2 gap-1">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <select name="type" class="border rounded px-2 py-1 text-xs">
                                    <option value="income" <?= $row['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $row['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                                <input name="amount" type="number" step="0.01" value="<?= e((string) $row['amount']) ?>" class="border rounded px-2 py-1 text-xs" data-currency>
                                <input name="transaction_date" type="date" value="<?= e($row['transaction_date']) ?>" class="border rounded px-2 py-1 text-xs">
                                <input name="description" value="<?= e($row['description']) ?>" class="col-span-2 border rounded px-2 py-1 text-xs">
                                <button class="bg-blue-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Request Update</span></span></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete transaction?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Request Delete</span></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($transactionsPagination); ?>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>My Pending/Recent Transaction Requests</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Date</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Admin Note</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($myTxRequests as $req): ?>
                    <tr class="border-b">
                        <td class="py-2"><?= e((string) $req['created_at']) ?></td>
                        <td><?= e((string) $req['action_type']) ?></td>
                        <td><?= e((string) $req['status']) ?></td>
                        <td><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($myTxRequestsPagination); ?>
        </div>
    </div>
    <script src="static/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}
