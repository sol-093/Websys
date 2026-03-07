<?php

declare(strict_types=1);

function handleAdminStudentsPage(PDO $db): void
{
    requireRole(['admin']);
    $q = trim((string) ($_GET['q'] ?? ''));

    if ($q !== '') {
        $stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE role IN ('student','owner') AND (name LIKE ? OR email LIKE ?) ORDER BY name");
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        $students = $stmt->fetchAll();
    } else {
        $students = $db->query("SELECT id, name, email, role, created_at FROM users WHERE role IN ('student','owner') ORDER BY name")->fetchAll();
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
                        <td class="py-2"><?= e($student['name']) ?></td>
                        <td><?= e($student['email']) ?></td>
                        <td><?= e($student['role']) ?></td>
                        <td><?= e($student['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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

    $requests = $db->query("SELECT r.*, o.name AS organization_name, u.name AS requester_name
        FROM transaction_change_requests r
        JOIN organizations o ON o.id = r.organization_id
        JOIN users u ON u.id = r.requested_by
        ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.created_at DESC")->fetchAll();
    $requestsPagination = paginateArray($requests, 'pg_admin_requests', 10);
    $requests = $requestsPagination['items'];

    renderHeader('Transaction Requests');
    ?>
    <div class="bg-white shadow rounded p-4 overflow-auto">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Owner Requests for Transaction Edit/Delete</span></h1>
        <table class="w-full text-sm">
            <thead>
            <tr class="border-b text-left">
                <th class="py-2">Org</th>
                <th>Requester</th>
                <th>Action</th>
                <th>Proposal</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th>Decision</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $req): ?>
                <tr class="border-b align-top">
                    <td class="py-2"><?= e($req['organization_name']) ?></td>
                    <td><?= e($req['requester_name']) ?></td>
                    <td><?= e($req['action_type']) ?></td>
                    <td>
                        <?php if ($req['action_type'] === 'update'): ?>
                            <div class="text-xs">Type: <?= e((string) $req['proposed_type']) ?></div>
                            <div class="text-xs">Amount: ₱<?= number_format((float) $req['proposed_amount'], 2) ?></div>
                            <div class="text-xs">Date: <?= e((string) $req['proposed_transaction_date']) ?></div>
                            <div class="text-xs">Desc: <?= e((string) $req['proposed_description']) ?></div>
                        <?php else: ?>
                            <div class="text-xs">Delete transaction #<?= (int) $req['transaction_id'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="icon-label"><?php
                        $requestStatus = strtolower((string) $req['status']);
                        $requestStatusIcon = match ($requestStatus) {
                            'approved', 'accepted' => 'approved',
                            'rejected', 'declined' => 'rejected',
                            'pending' => 'pending',
                            default => 'default',
                        };
                        ?><?= uiIcon($requestStatusIcon, 'ui-icon ui-icon-sm') ?><?= e((string) $req['status']) ?></span></td>
                    <td><?= e((string) ($req['admin_note'] ?? '')) ?></td>
                    <td class="min-w-56">
                        <?php if ((string) $req['status'] === 'pending'): ?>
                            <form method="post" class="space-y-1">
                                <input type="hidden" name="action" value="process_tx_change_request">
                                <input type="hidden" name="request_id" value="<?= (int) $req['id'] ?>">
                                <input name="admin_note" placeholder="Optional note" class="w-full border rounded px-2 py-1 text-xs">
                                <div class="flex gap-2">
                                    <button name="decision" value="approve" class="bg-emerald-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                    <button name="decision" value="reject" class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Reject</span></span></button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="text-xs text-gray-500">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php renderPagination($requestsPagination); ?>
    </div>
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
            <div class="overflow-x-auto">
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
    $orgs = $db->query('SELECT id, name FROM organizations ORDER BY name')->fetchAll();
    $org = null;
    if ($orgId > 0) {
        $stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
        $stmt->execute([$orgId]);
        $org = $stmt->fetch();
    }

    renderHeader('Organization Overview');
    ?>
    <div class="bg-white shadow rounded p-4">
        <h1 class="text-xl font-semibold mb-3 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>Organization Overview (Admin)</span></h1>
        <form method="get" class="mb-4 flex gap-2">
            <input type="hidden" name="page" value="my_org">
            <select name="org_id" class="border rounded px-3 py-2">
                <option value="">Select organization</option>
                <?php foreach ($orgs as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $orgId === (int) $option['id'] ? 'selected' : '' ?>>
                        <?= e($option['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
        </form>

        <?php if ($org): ?>
            <?php
            $txStmt = $db->prepare('SELECT * FROM financial_transactions WHERE organization_id = ? ORDER BY transaction_date DESC, id DESC');
            $txStmt->execute([(int) $org['id']]);
            $tx = $txStmt->fetchAll();
            $adminTxPagination = paginateArray($tx, 'pg_myorg_admin_tx', 12);
            $tx = $adminTxPagination['items'];
            ?>
            <h2 class="text-lg font-semibold"><?= e($org['name']) ?></h2>
            <p class="text-gray-600 mb-3"><?= e($org['description']) ?></p>
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
            <?php renderPagination($adminTxPagination); ?>
        <?php endif; ?>
    </div>
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

    $orgs = $db->query("SELECT o.*, u.name AS owner_name, oa.status AS assignment_status, su.name AS assigned_student_name
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
            <form method="post" class="space-y-2">
                <input type="hidden" name="action" value="create_org">
                <input name="name" placeholder="Organization name" required class="w-full border rounded px-3 py-2">
                <textarea name="description" placeholder="Description" class="w-full border rounded px-3 py-2"></textarea>
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

        <div class="md:col-span-2 bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-3 icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h2>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Name</th>
                    <th>Description</th>
                    <th>Visibility</th>
                    <th>Owner</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orgs as $org): ?>
                    <tr class="border-b align-top">
                        <td class="py-2 font-medium"><?= e($org['name']) ?></td>
                        <td><?= e($org['description']) ?></td>
                        <td>
                            <span class="text-xs"><?= e(getOrganizationVisibilityLabel($org)) ?></span>
                        </td>
                        <td>
                            <form method="post" class="flex gap-2 items-center">
                                <input type="hidden" name="action" value="assign_owner">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <select name="owner_id" class="border rounded px-2 py-1 text-xs">
                                    <option value="">-- none --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= (int) $student['id'] ?>" <?= (int) $org['owner_id'] === (int) $student['id'] ? 'selected' : '' ?>>
                                            <?= e($student['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="bg-slate-700 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save</span></span></button>
                            </form>
                            <span class="text-xs text-gray-500"><?= e($org['owner_name'] ?? 'Unassigned') ?></span>
                            <?php if (!empty($org['assignment_status'])): ?>
                                <div class="text-[11px] text-amber-200 mt-1">Pending: <?= e($org['assigned_student_name'] ?? 'Student') ?> (awaiting response)</div>
                            <?php endif; ?>
                        </td>
                        <td class="space-y-2 min-w-52">
                            <form method="post" class="space-y-1">
                                <input type="hidden" name="action" value="update_org_admin">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-2 py-1 text-xs">
                                <textarea name="description" class="w-full border rounded px-2 py-1 text-xs"><?= e($org['description']) ?></textarea>
                                <select name="org_category" class="w-full border rounded px-2 py-1 text-xs">
                                    <?php foreach ($orgCategoryOptions as $categoryKey => $categoryLabel): ?>
                                        <option value="<?= e($categoryKey) ?>" <?= (string) ($org['org_category'] ?? 'collegewide') === $categoryKey ? 'selected' : '' ?>><?= e($categoryLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="target_institute" class="w-full border rounded px-2 py-1 text-xs">
                                    <option value="">Institute target (for institutewide)</option>
                                    <?php foreach ($instituteOptions as $institute): ?>
                                        <option value="<?= e($institute) ?>" <?= (string) ($org['target_institute'] ?? '') === $institute ? 'selected' : '' ?>><?= e($institute) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="target_program" class="w-full border rounded px-2 py-1 text-xs">
                                    <option value="">Program target (for program-based)</option>
                                    <?php foreach ($programOptions as $programOption): ?>
                                        <option value="<?= e($programOption) ?>" <?= (string) ($org['target_program'] ?? '') === $programOption ? 'selected' : '' ?>><?= e($programOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="bg-blue-600 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Update</span></span></button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this organization?')">
                                <input type="hidden" name="action" value="delete_org">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <button class="bg-red-600 text-white text-xs px-2 py-1 rounded"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete</span></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php renderPagination($orgsPagination); ?>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}
