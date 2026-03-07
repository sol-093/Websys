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

    $stmt = $db->prepare('SELECT * FROM financial_transactions WHERE organization_id = ? ORDER BY transaction_date DESC, id DESC');
    $stmt->execute([(int) $org['id']]);
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
                                <div class="text-xs text-gray-500"><?= e($request['email']) ?> · <?= e($request['created_at']) ?></div>
                            </div>
                            <div class="flex gap-2">
                                <form method="post">
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="bg-emerald-600 text-white px-3 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                </form>
                                <form method="post">
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
            <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>My Organization</span></h1>
            <form method="get" class="mb-4 flex gap-2">
                <input type="hidden" name="page" value="my_org">
                <select name="org_id" class="border rounded px-3 py-2">
                    <?php foreach ($ownedOrganizations as $ownedOption): ?>
                        <option value="<?= (int) $ownedOption['id'] ?>" <?= (int) $org['id'] === (int) $ownedOption['id'] ? 'selected' : '' ?>>
                            <?= e($ownedOption['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>
            <form method="post" class="grid md:grid-cols-2 gap-3">
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
                            <div class="text-sm text-gray-700"><?= e($announcement['content']) ?></div>
                            <form method="post" class="mt-2">
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
                    <input type="hidden" name="action" value="add_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <div class="grid grid-cols-2 gap-2">
                        <select name="type" class="border rounded px-3 py-2">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        <input type="number" step="0.01" name="amount" placeholder="Amount" class="border rounded px-3 py-2" required>
                    </div>
                    <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" class="w-full border rounded px-3 py-2" required>
                    <input name="description" placeholder="Description" class="w-full border rounded px-3 py-2" required>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full border rounded px-3 py-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Transaction</span></span></button>
                </form>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Transaction History</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
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
                    <tr class="border-b align-top">
                        <td class="py-2"><?= e($row['transaction_date']) ?></td>
                        <td><?= e($row['type']) ?></td>
                        <td>₱<?= number_format((float) $row['amount'], 2) ?></td>
                        <td><?= e($row['description']) ?></td>
                        <td>
                            <?php if (!empty($row['receipt_path'])): ?>
                                <a href="<?= e($row['receipt_path']) ?>" target="_blank" class="text-indigo-700 underline"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View</span></span></a>
                            <?php else: ?>
                                <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="space-y-1 min-w-56">
                            <form method="post" class="grid grid-cols-2 gap-1">
                                <input type="hidden" name="action" value="update_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <select name="type" class="border rounded px-2 py-1 text-xs">
                                    <option value="income" <?= $row['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $row['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                                <input name="amount" type="number" step="0.01" value="<?= e((string) $row['amount']) ?>" class="border rounded px-2 py-1 text-xs">
                                <input name="transaction_date" type="date" value="<?= e($row['transaction_date']) ?>" class="border rounded px-2 py-1 text-xs">
                                <input name="description" value="<?= e($row['description']) ?>" class="col-span-2 border rounded px-2 py-1 text-xs">
                                <button class="bg-blue-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Request Update</span></span></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete transaction?')">
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
    <?php
    renderFooter();
    exit;
}
