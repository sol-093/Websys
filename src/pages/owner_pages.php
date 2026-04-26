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

    $activeAnnouncementCutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND (expires_at IS NULL OR expires_at >= ?) ORDER BY id DESC');
    $stmt->execute([(int) $org['id'], $activeAnnouncementCutoff]);
    $announcements = $stmt->fetchAll();
    $allAnnouncements = $announcements;
    $announcementPreview = array_slice($allAnnouncements, 0, 3);

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

    $joinRequestStmt = $db->prepare("SELECT r.id, r.created_at, u.name, u.email, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom
        FROM organization_join_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.organization_id = ? AND r.status = 'pending'
        ORDER BY r.created_at DESC");
    $joinRequestStmt->execute([(int) $org['id']]);
    $pendingJoinRequests = $joinRequestStmt->fetchAll();

    $pendingJoinPagination = paginateArray($pendingJoinRequests, 'pg_myorg_join', 5);
    $pendingJoinRequests = $pendingJoinPagination['items'];
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
                                <div class="font-medium inline-flex items-center gap-2"><?= renderProfileMedia((string) ($request['name'] ?? ''), (string) ($request['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($request['profile_picture_crop_x'] ?? 50), (float) ($request['profile_picture_crop_y'] ?? 50), (float) ($request['profile_picture_zoom'] ?? 1)) ?><span><?= e($request['name']) ?></span></div>
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
                    <button type="button" id="myOrgSwitcherButton" data-dropdown-toggle="myOrgSwitcherMenu" aria-expanded="false" class="w-full flex items-center border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                        <span id="myOrgSwitcherLabel" data-dropdown-label class="truncate text-left"><?= e($selectedOrgName) ?></span>
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
            <form method="post" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <?php
                $orgLogoCropX = (float) ($org['logo_crop_x'] ?? 50);
                $orgLogoCropY = (float) ($org['logo_crop_y'] ?? 50);
                $orgLogoZoom = (float) ($org['logo_zoom'] ?? 1);
                ?>
                <div>
                    <label class="text-sm text-gray-600">Organization Name</label>
                    <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="text-sm text-gray-600">Description</label>
                    <textarea name="description" class="w-full border rounded px-3 py-2"><?= e($org['description']) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Organization Logo</label>
                    <div class="mt-2 space-y-3" data-image-crop-form>
                        <div class="flex items-center gap-3">
                            <div class="shrink-0" data-crop-preview>
                                <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', $orgLogoCropX, $orgLogoCropY, $orgLogoZoom) ?>
                            </div>
                            <label for="orgLogoInput" class="inline-flex min-h-[3rem] flex-1 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/70 px-4 py-3 text-sm text-emerald-900 transition-colors hover:bg-emerald-100/70">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-white text-emerald-700 shadow-sm">
                                    <svg viewBox="0 0 24 24" class="h-4.5 w-4.5" fill="currentColor" aria-hidden="true"><path d="M12 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7L12 20l4.7-5.3c.8-1 1.3-2.3 1.3-3.7 0-3.3-2.7-6-6-6zm0 8.3A2.3 2.3 0 1 1 12 8.7a2.3 2.3 0 0 1 0 4.6z"/></svg>
                                </span>
                                <span class="font-medium">Choose organization logo</span>
                                <span class="text-xs text-emerald-700/80">Click to browse</span>
                            </label>
                            <input id="orgLogoInput" type="file" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" data-image-input>
                        </div>
                        <div class="hidden grid grid-cols-1 md:grid-cols-3 gap-3" aria-hidden="true">
                            <label class="space-y-1 text-xs text-slate-600">
                                <span>Crop X</span>
                                <input type="range" min="0" max="100" step="1" name="logo_crop_x" value="<?= (float) $orgLogoCropX ?>" class="w-full" data-crop-x>
                            </label>
                            <label class="space-y-1 text-xs text-slate-600">
                                <span>Crop Y</span>
                                <input type="range" min="0" max="100" step="1" name="logo_crop_y" value="<?= (float) $orgLogoCropY ?>" class="w-full" data-crop-y>
                            </label>
                            <label class="space-y-1 text-xs text-slate-600">
                                <span>Zoom</span>
                                <input type="range" min="0.75" max="2.25" step="0.05" name="logo_zoom" value="<?= (float) $orgLogoZoom ?>" class="w-full" data-crop-zoom>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Organization Info</span></span></button>
                </div>
            </form>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white shadow rounded p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Post Announcement</span></h2>
                    <button type="button" id="myOrgAnnouncementsOpen" class="text-xs text-indigo-700 underline">View all announcements</button>
                </div>
                <form method="post" class="space-y-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_announcement">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input name="title" placeholder="Title" class="w-full border rounded px-3 py-2" required>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <input name="label" maxlength="40" placeholder="Label (optional, e.g. Urgent)" class="w-full border rounded px-3 py-2">
                        <select name="duration_days" class="w-full border rounded px-3 py-2" required>
                            <option value="7">Visible for 7 days</option>
                            <option value="14">Visible for 14 days</option>
                            <option value="30" selected>Visible for 30 days</option>
                            <option value="60">Visible for 60 days</option>
                            <option value="90">Visible for 90 days</option>
                        </select>
                    </div>
                    <textarea name="content" placeholder="Announcement details" class="w-full border rounded px-3 py-2" required></textarea>
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Post</span></span></button>
                </form>

                <div class="mt-4 space-y-2 max-h-72 overflow-auto">
                    <?php foreach ($announcementPreview as $announcement): ?>
                        <div class="border rounded p-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-medium"><?= e($announcement['title']) ?></div>
                                <?php if (trim((string) ($announcement['label'] ?? '')) !== ''): ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full border border-emerald-300/40 bg-emerald-500/20"><?= e((string) $announcement['label']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Expires: <?= e((string) ($announcement['expires_at'] ?? '')) ?></div>
                            <div class="text-sm text-slate-700"><?= e($announcement['content']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($announcementPreview) === 0): ?>
                        <p class="text-sm text-gray-500">No active announcements right now.</p>
                    <?php endif; ?>
                </div>
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
                    <div class="w-full border rounded px-3 py-2 flex items-center gap-3">
                        <label for="myOrgReceiptInput" class="inline-flex items-center justify-center bg-indigo-700 text-white px-3 py-1.5 rounded text-xs cursor-pointer hover:bg-indigo-800 transition-colors">
                            Upload Receipt
                        </label>
                        <span id="myOrgReceiptFilename" class="text-sm text-slate-500 truncate">No file chosen</span>
                        <input id="myOrgReceiptInput" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" onchange="var f=this.files&&this.files[0]?this.files[0].name:'No file chosen'; var n=document.getElementById('myOrgReceiptFilename'); if(n){ n.textContent=f; }">
                    </div>
                    <button class="bg-indigo-700 text-white px-4 py-2 rounded"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Transaction</span></span></button>
                </form>
            </div>
        </div>

        <div id="tx-history" class="bg-white shadow rounded p-4 overflow-auto">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Transaction History</span></h2>
                <a href="?page=my_org_manage&org_id=<?= (int) $org['id'] ?>&action=export_transactions&format=pdf&tx_type=<?= urlencode($txTypeFilter) ?>&tx_sort=<?= urlencode($txDateSort) ?>" class="report-export-btn inline-flex items-center gap-2 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100 transition-colors">
                    Export PDF
                </a>
            </div>
            <form method="get" action="?page=my_org_manage#tx-history" class="mb-3 flex flex-wrap items-end gap-2" data-dropdown-root onsubmit="const b=this.querySelector('[data-filter-submit]'); if(b){ b.disabled=true; b.textContent='Filtering...'; }">
                <input type="hidden" name="page" value="my_org_manage">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Type</label>
                    <div class="relative" data-dropdown-wrapper>
                        <input type="hidden" name="tx_type" data-dropdown-value value="<?= e($txTypeFilter) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxTypeMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-xs transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txTypeFilter === 'income' ? 'Income' : ($txTypeFilter === 'expense' ? 'Expense' : 'All')) ?></span>
                            <span class="hidden text-xs">▾</span>
                        </button>
                        <div id="myOrgTxTypeMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-max min-w-full overflow-hidden rounded border z-20 backdrop-blur-md">
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
                    <div class="relative" data-dropdown-wrapper>
                        <input type="hidden" name="tx_sort" data-dropdown-value value="<?= e($txDateSort) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxSortMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-xs transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txDateSort === 'asc' ? 'Oldest first' : 'Newest first') ?></span>
                            <span class="hidden text-xs">▾</span>
                        </button>
                        <div id="myOrgTxSortMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-max min-w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                            <ul class="p-2 text-sm font-medium space-y-1">
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'desc' ? 'true' : 'false' ?>" data-option-value="desc" data-option-label="Newest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Newest first</button></li>
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'asc' ? 'true' : 'false' ?>" data-option-value="asc" data-option-label="Oldest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Oldest first</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button data-filter-submit class="bg-indigo-700 text-white px-2.5 py-1.5 rounded text-xs"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Filter</span></span></button>
            </form>
            <div class="table-wrapper">
                <table class="hidden sm:table w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-[12%]">
                        <col class="w-[10%]">
                        <col class="w-[14%]">
                        <col class="w-[26%]">
                        <col class="w-[10%]">
                        <col class="w-[28%]">
                    </colgroup>
                    <thead>
                    <tr class="text-left border-b border-emerald-400">
                        <th class="py-2 px-2">Date</th>
                        <th class="px-2">Type</th>
                        <th class="px-2">Amount</th>
                        <th class="px-2">Description</th>
                        <th class="pl-1 pr-6 text-left">Receipt</th>
                        <th class="pl-6 pr-2">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $row): ?>
                        <tr class="border-b border-emerald-300 align-top">
                            <td class="py-3 px-2 whitespace-nowrap"><?= e($row['transaction_date']) ?></td>
                            <td class="py-3 px-2 whitespace-nowrap"><?= e($row['type']) ?></td>
                            <td class="py-3 px-2 whitespace-nowrap">₱<?= number_format((float) $row['amount'], 2) ?></td>
                            <td class="py-3 px-2 break-words"><?= e($row['description']) ?></td>
                            <td class="py-3 pl-1 pr-6 text-left">
                                <?php if (!empty($row['receipt_path'])): ?>
                                    <a href="<?= e($row['receipt_path']) ?>" target="_blank" class="row-action-hit-target inline-flex min-w-[44px] min-h-[44px] items-center justify-start p-2 text-indigo-700 underline"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View</span></span></a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 pl-6 pr-2 space-y-2">
                                <form method="post" class="grid grid-cols-2 gap-2">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="update_transaction">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                    <select name="type" class="border rounded px-2 py-1.5 text-xs w-full">
                                        <option value="income" <?= $row['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                                        <option value="expense" <?= $row['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                                    </select>
                                    <input name="amount" type="number" step="0.01" value="<?= e((string) $row['amount']) ?>" class="border rounded px-2 py-1.5 text-xs w-full" data-currency>
                                    <input name="transaction_date" type="date" value="<?= e($row['transaction_date']) ?>" class="border rounded px-2 py-1.5 text-xs w-full">
                                    <input name="description" value="<?= e($row['description']) ?>" class="col-span-2 border rounded px-2 py-1.5 text-xs w-full">
                                    <button class="row-action-hit-target col-span-2 inline-flex items-center justify-center px-2 py-1.5 bg-blue-600 text-white rounded text-xs"><span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Request Update</span></span></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete transaction?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                    <button class="row-action-hit-target w-full inline-flex items-center justify-center px-2 py-1.5 bg-red-600 text-white rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Request Delete</span></span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-cards sm:hidden space-y-3">
                <?php foreach ($transactions as $row): ?>
                    <?php $type = (string) $row['type']; ?>
                    <article class="rounded-lg border border-emerald-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e((string) $org['name']) ?></div>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $type === 'income' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>"><?= e(ucfirst($type)) ?></span>
                        </div>
                        <div class="mt-2 text-2xl font-bold leading-tight text-slate-900">₱<?= number_format((float) $row['amount'], 2) ?></div>
                        <div class="mt-2 text-xs text-slate-600"><?= e((string) $row['transaction_date']) ?></div>
                        <p class="mt-2 text-sm text-slate-700" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e((string) $row['description']) ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php renderPagination($transactionsPagination); ?>
        </div>

        <div class="bg-white shadow rounded p-4 overflow-auto">
            <h2 class="text-lg font-semibold mb-2 icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>My Pending/Recent Transaction Requests</span></h2>
            <div class="table-wrapper">
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
            </div>
            <?php renderPagination($myTxRequestsPagination); ?>
        </div>
    </div>

    <div id="myOrgAnnouncementsModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-[2px] px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-12 w-full max-w-3xl">
            <div class="rounded-2xl border border-slate-200/70 bg-white/95 p-5 shadow-[0_24px_60px_rgba(15,23,42,0.38)] max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>All Organization Announcements</span></h3>
                        <p class="text-sm text-slate-600 mt-1">Manage every active announcement for <?= e((string) $org['name']) ?>.</p>
                    </div>
                    <button type="button" id="myOrgAnnouncementsClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>

                <div class="space-y-2">
                    <?php foreach ($allAnnouncements as $announcement): ?>
                        <div class="border rounded p-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-medium"><?= e((string) $announcement['title']) ?></div>
                                <?php if (trim((string) ($announcement['label'] ?? '')) !== ''): ?>
                                    <span class="text-[11px] px-2 py-0.5 rounded-full border border-emerald-300/40 bg-emerald-500/20"><?= e((string) $announcement['label']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Expires: <?= e((string) ($announcement['expires_at'] ?? '')) ?></div>
                            <div class="text-sm text-slate-700 mt-1"><?= e((string) $announcement['content']) ?></div>
                            <form method="post" class="mt-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                <button class="bg-red-600 text-white px-2 py-1 rounded text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete</span></span></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($allAnnouncements) === 0): ?>
                        <p class="text-sm text-gray-500">No active announcements right now.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('myOrgAnnouncementsModal');
            const openBtn = document.getElementById('myOrgAnnouncementsOpen');
            const closeBtn = document.getElementById('myOrgAnnouncementsClose');
            if (!modal || !openBtn || !closeBtn) {
                return;
            }

            const openModal = function () {
                modal.classList.remove('hidden');
            };

            const closeModal = function () {
                modal.classList.add('hidden');
            };

            openBtn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
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
