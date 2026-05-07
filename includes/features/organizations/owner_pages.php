<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - OWNER ORGANIZATION PAGES
 * ================================================
 *
 * SECTION MAP:
 * 1. Owner Workspace Data Loader
 * 2. Shared Owner Navigation
 * 3. Shared Membership Panels
 * 4. Organization Management Page
 * 5. Membership Management Page
 * 6. Financial Management Page
 *
 * WORK GUIDE:
 * - Edit this file for owner-facing page markup.
 * - Keep POST workflows in transactions/actions.php and workflows.php.
 * ================================================
 */

function buildOwnerWorkspaceData(PDO $db, array $user): array
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

    $txTypeFilter = (string) ($_GET['tx_type'] ?? 'all');
    if (!in_array($txTypeFilter, ['all', 'income', 'expense'], true)) {
        $txTypeFilter = 'all';
    }

    $txDateSort = strtolower((string) ($_GET['tx_sort'] ?? 'desc'));
    if (!in_array($txDateSort, ['asc', 'desc'], true)) {
        $txDateSort = 'desc';
    }

    $activeAnnouncementCutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $announcementStmt = $db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND (expires_at IS NULL OR expires_at >= ?) ORDER BY id DESC');
    $announcementStmt->execute([(int) $org['id'], $activeAnnouncementCutoff]);
    $allAnnouncements = $announcementStmt->fetchAll();
    $announcementPreview = array_slice($allAnnouncements, 0, 3);

    $joinRequestStmt = $db->prepare("SELECT r.id, r.created_at, u.name, u.email, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom
        FROM organization_join_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.organization_id = ? AND r.status = 'pending'
        ORDER BY r.created_at DESC");
    $joinRequestStmt->execute([(int) $org['id']]);
    $pendingJoinRequestsAll = $joinRequestStmt->fetchAll();
    $pendingJoinPagination = paginateArray($pendingJoinRequestsAll, 'pg_myorg_join', 5);

    $memberStmt = $db->prepare('SELECT u.id, u.name, u.email, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom, om.joined_at,
        CASE WHEN o.owner_id = u.id THEN 1 ELSE 0 END AS is_owner
        FROM organization_members om
        JOIN users u ON u.id = om.user_id
        JOIN organizations o ON o.id = om.organization_id
        WHERE om.organization_id = ?
        ORDER BY CASE WHEN o.owner_id = u.id THEN 0 ELSE 1 END, u.name ASC');
    $memberStmt->execute([(int) $org['id']]);
    $orgMembersAll = $memberStmt->fetchAll();
    $orgMemberPagination = paginateArray($orgMembersAll, 'pg_myorg_members', 8);

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
    $transactionsAll = $txStmt->fetchAll();
    $transactionsPagination = paginateArray($transactionsAll, 'pg_myorg_tx', 10);

    $txRequestStmt = $db->prepare("SELECT * FROM transaction_change_requests WHERE organization_id = ? AND requested_by = ? ORDER BY created_at DESC LIMIT 20");
    $txRequestStmt->execute([(int) $org['id'], (int) $user['id']]);
    $myTxRequestsAll = $txRequestStmt->fetchAll();
    $myTxRequestsPagination = paginateArray($myTxRequestsAll, 'pg_myorg_req', 8);

    return [
        'ownedOrganizations' => $ownedOrganizations,
        'org' => $org,
        'txTypeFilter' => $txTypeFilter,
        'txDateSort' => $txDateSort,
        'allAnnouncements' => $allAnnouncements,
        'announcementPreview' => $announcementPreview,
        'pendingJoinRequests' => $pendingJoinPagination['items'],
        'pendingJoinPagination' => $pendingJoinPagination,
        'pendingJoinCount' => count($pendingJoinRequestsAll),
        'orgMembers' => $orgMemberPagination['items'],
        'orgMemberPagination' => $orgMemberPagination,
        'orgMemberCount' => count($orgMembersAll),
        'transactions' => $transactionsPagination['items'],
        'transactionsPagination' => $transactionsPagination,
        'myTxRequests' => $myTxRequestsPagination['items'],
        'myTxRequestsPagination' => $myTxRequestsPagination,
    ];
}

function renderOwnerWorkspaceHeader(array $org, string $currentPage): void
{
    $orgId = (int) ($org['id'] ?? 0);
    ?>
    <div id="owner-membership-requests" class="glass rounded-lg p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-xl font-semibold icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span><?= e((string) ($org['name'] ?? 'My Organization')) ?></span></h1>
                <p class="section-helper-copy">Focus on one workspace at a time.</p>
            </div>
            <div class="grid w-full grid-cols-1 gap-2 text-xs sm:w-auto sm:grid-cols-3 lg:flex lg:flex-wrap">
                <a href="?page=my_org_manage&org_id=<?= $orgId ?>" class="inline-flex w-full items-center justify-center rounded-md border px-3 py-2 text-center transition-colors sm:w-auto lg:justify-start <?= $currentPage === 'my_org_manage' ? 'border-emerald-300/30 bg-emerald-500/10 text-emerald-800' : 'border-slate-300/30 bg-white/10 text-slate-700 hover:bg-white/15' ?>">Organization Management</a>
                <a href="?page=my_org_members&org_id=<?= $orgId ?>" class="inline-flex w-full items-center justify-center rounded-md border px-3 py-2 text-center transition-colors sm:w-auto lg:justify-start <?= $currentPage === 'my_org_members' ? 'border-emerald-300/30 bg-emerald-500/10 text-emerald-800' : 'border-slate-300/30 bg-white/10 text-slate-700 hover:bg-white/15' ?>">Membership Management</a>
                <a href="?page=my_org_finance&org_id=<?= $orgId ?>" class="inline-flex w-full items-center justify-center rounded-md border px-3 py-2 text-center transition-colors sm:w-auto lg:justify-start <?= $currentPage === 'my_org_finance' ? 'border-emerald-300/30 bg-emerald-500/10 text-emerald-800' : 'border-slate-300/30 bg-white/10 text-slate-700 hover:bg-white/15' ?>">Financial Management</a>
                <a href="?page=my_org&org_id=<?= $orgId ?>" class="hidden md:inline-flex w-auto self-start whitespace-nowrap rounded-md border border-slate-300/30 bg-white/10 px-2 py-2 text-xs text-slate-700 transition-colors hover:bg-white/15">Back</a>
            </div>
        </div>
    </div>
    <?php
}

function renderOwnerMembershipPanels(array $org, array $pendingJoinRequests, array $pendingJoinPagination, int $pendingJoinCount, array $orgMembers, array $orgMemberPagination, int $orgMemberCount): void
{
    ?>
    <div id="owner-membership-requests" class="glass rounded-lg p-4">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>Pending Membership Requests</span></h2>
                <p class="section-helper-copy">Review join requests and decide who gets added to the roster.</p>
            </div>
        </div>
        <?php if (count($pendingJoinRequests) === 0): ?>
            <div class="empty-state-panel">No pending join requests right now.</div>
        <?php else: ?>
            <div class="owner-request-toolbar mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0 flex-1">
                    <input type="search" id="ownerJoinRequestSearch" inputmode="search" placeholder="Search request by name or email..." class="w-full border rounded px-3 py-2">
                </div>
                <div class="owner-request-toolbar-meta flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1">Visible: <span id="ownerJoinVisibleCount"><?= count($pendingJoinRequests) ?></span></span>
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1">Page size: <?= count($pendingJoinRequests) ?></span>
                </div>
            </div>
            <div class="space-y-3">
                <?php foreach ($pendingJoinRequests as $request): ?>
                    <?php $requestSearch = strtolower((string) (($request['name'] ?? '') . ' ' . ($request['email'] ?? ''))); ?>
                    <article class="owner-join-request-card rounded-lg border border-emerald-300/25 bg-white/10 p-3" data-request-search="<?= e($requestSearch) ?>">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="inline-flex min-w-0 items-center gap-3">
                                    <?= renderProfileMedia((string) ($request['name'] ?? ''), (string) ($request['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($request['profile_picture_crop_x'] ?? 50), (float) ($request['profile_picture_crop_y'] ?? 50), (float) ($request['profile_picture_zoom'] ?? 1)) ?>
                                    <div class="min-w-0">
                                        <div class="font-medium break-words"><?= e((string) ($request['name'] ?? 'Student')) ?></div>
                                        <div class="mt-0.5 text-[11px] text-slate-500">Pending member request</div>
                                    </div>
                                </div>
                                <div class="mt-1 break-all text-sm text-slate-600"><?= e((string) ($request['email'] ?? '')) ?></div>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                    <span class="owner-request-chip rounded-md border border-slate-300/30 bg-white/10 px-2 py-1 text-slate-700">Requested <?= e(date('F d, Y', strtotime((string) ($request['created_at'] ?? 'now')))) ?></span>
                                    <span class="owner-request-chip rounded-md border border-amber-300/30 bg-amber-500/10 px-2 py-1 text-amber-800">Awaiting owner response</span>
                                    <span class="owner-request-chip rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-1 text-emerald-800">Membership review</span>
                                </div>
                            </div>
                            <div class="owner-request-actions flex flex-col gap-2 sm:flex-row xl:w-auto xl:min-w-[13rem] xl:flex-col xl:items-stretch">
                                <form method="post" class="owner-request-action-form" data-confirm-message="Approve this join request?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="owner-request-btn owner-request-btn-approve inline-flex w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-xs text-white"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Approve</span></span></button>
                                </form>
                                <form method="post" class="owner-request-action-form" data-confirm-message="Decline this join request?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="respond_join_request">
                                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <button class="owner-request-btn owner-request-btn-decline inline-flex w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-xs text-white"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Decline</span></span></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div id="ownerJoinRequestEmptySearch" class="empty-state-search mt-3 hidden">No pending requests matched that search.</div>
            <?php renderPagination($pendingJoinPagination + ['anchor' => 'owner-membership-requests']); ?>
        <?php endif; ?>
    </div>

    <div class="glass rounded-lg p-4">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Current Members</span></h2>
                <p class="section-helper-copy">Search the roster, review member details, and remove access when needed.</p>
            </div>
        </div>
        <?php if ($orgMemberCount === 0): ?>
            <div class="empty-state-panel">No members have joined this organization yet.</div>
        <?php else: ?>
            <div class="owner-request-toolbar mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0 flex-1">
                    <input type="search" id="ownerMemberSearch" inputmode="search" placeholder="Search member by name or email..." class="w-full border rounded px-3 py-2">
                </div>
                <div class="owner-request-toolbar-meta flex flex-wrap items-center gap-2 text-xs text-slate-600">
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1">Visible: <span id="ownerMemberVisibleCount"><?= count($orgMembers) ?></span></span>
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1">Page size: <?= count($orgMembers) ?></span>
                </div>
            </div>
            <div id="ownerMemberList" class="space-y-3">
                <?php foreach ($orgMembers as $member): ?>
                    <?php
                        $memberId = (int) ($member['id'] ?? 0);
                        $memberName = (string) ($member['name'] ?? 'Member');
                        $memberEmail = (string) ($member['email'] ?? '');
                        $joinedAt = (string) ($member['joined_at'] ?? '');
                        $isMemberOwner = (int) ($member['is_owner'] ?? 0) === 1;
                        $memberSearch = strtolower($memberName . ' ' . $memberEmail);
                    ?>
                    <article class="owner-member-card rounded-lg border border-emerald-300/25 bg-white/10 p-3" data-member-search="<?= e($memberSearch) ?>">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="inline-flex min-w-0 items-center gap-3">
                                    <?= renderProfileMedia($memberName, (string) ($member['profile_picture_path'] ?? ''), 'user', 'xs', (float) ($member['profile_picture_crop_x'] ?? 50), (float) ($member['profile_picture_crop_y'] ?? 50), (float) ($member['profile_picture_zoom'] ?? 1)) ?>
                                    <div class="min-w-0">
                                        <div class="font-medium break-words"><?= e($memberName) ?></div>
                                        <?php if ($memberEmail !== ''): ?>
                                            <div class="mt-0.5 break-all text-sm text-slate-600"><?= e($memberEmail) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                    <?php if ($joinedAt !== ''): ?>
                                        <span class="owner-request-chip rounded-md border border-slate-300/30 bg-white/10 px-2 py-1 text-slate-700">Joined <?= e(date('F d, Y', strtotime($joinedAt))) ?></span>
                                    <?php endif; ?>
                                    <?php if ($isMemberOwner): ?>
                                        <span class="owner-request-chip rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-1 text-emerald-800">Protected owner account</span>
                                    <?php else: ?>
                                        <span class="owner-request-chip rounded-md border border-slate-300/30 bg-white/10 px-2 py-1 text-slate-700">Active roster member</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="owner-request-actions flex flex-col gap-2 sm:flex-row xl:w-auto xl:min-w-[12rem] xl:flex-col xl:items-stretch">
                                <?php if ($isMemberOwner): ?>
                                    <span class="inline-flex items-center justify-center rounded-md border border-emerald-300/30 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-800">Owner protected</span>
                                <?php else: ?>
                                    <form method="post" class="owner-request-action-form" data-confirm-message="Remove this member from the organization?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="remove_org_member">
                                        <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                        <input type="hidden" name="member_user_id" value="<?= $memberId ?>">
                                        <button class="tx-action-btn tx-action-btn-delete owner-member-remove-btn inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-xs">
                                            <span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Remove Member</span></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div id="ownerMemberEmptySearch" class="empty-state-search mt-3 hidden">No members matched that search.</div>
            <?php renderPagination($orgMemberPagination + ['anchor' => 'owner-members-current']); ?>
        <?php endif; ?>
    </div>
    <?php
}

function handleMyOrgOwnerPage(PDO $db, array $user, string $announcementCutoff): void
{
    $workspace = buildOwnerWorkspaceData($db, $user);
    extract($workspace, EXTR_SKIP);

    renderHeader('Organization Management');
    ?>
    <div class="space-y-4">
        <?php renderOwnerWorkspaceHeader($org, 'my_org_manage'); ?>

        <div class="glass rounded-lg p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('edit', 'ui-icon') ?><span>Organization Management</span></h2>
                    <p class="section-helper-copy">Manage the organization profile and member-facing updates in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-800">Profile workspace</span>
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1 text-slate-700"><?= e((string) ($org['name'] ?? 'Organization')) ?></span>
                </div>
            </div>
        </div>

        <div class="glass rounded-lg p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('edit', 'ui-icon') ?><span>Organization Settings</span></h2>
                    <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-800">Public profile</span>
                </div>
            </div>
            <?php $selectedOrgName = (string) ($org['name'] ?? 'Select organization'); ?>
            <form method="get" class="mb-4 flex flex-wrap gap-2 items-stretch sm:items-start relative" id="myOrgSwitcherForm" data-dropdown-root>
                <input type="hidden" name="page" value="my_org_manage">
                <input type="hidden" name="org_id" id="myOrgOrgId" data-dropdown-value value="<?= (int) $org['id'] ?>">
                <div class="relative w-full min-w-0 sm:min-w-[16rem] sm:flex-1" data-dropdown-wrapper>
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
                <button class="owner-manage-secondary-btn w-full sm:w-auto px-4 py-2 rounded-md"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>
            <form method="post" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_my_org">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <?php
                $orgLogoCropX = (float) ($org['logo_crop_x'] ?? 50);
                $orgLogoCropY = (float) ($org['logo_crop_y'] ?? 50);
                $orgLogoZoom = (float) ($org['logo_zoom'] ?? 1);
                ?>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-slate-700">Organization Name</label>
                    <input name="name" value="<?= e($org['name']) ?>" class="w-full border rounded px-3 py-2">
                    <p class="text-xs text-slate-500">Use the name students will recognize.</p>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-slate-700">Description</label>
                    <textarea name="description" rows="4" class="w-full border rounded px-3 py-2"><?= e($org['description']) ?></textarea>
                    <p class="text-xs text-slate-500">Keep it short and clear.</p>
                </div>
                <div class="md:col-span-2 space-y-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Organization Logo</label>
                        <p class="mt-1 text-xs text-slate-500">Shown on the org page, dashboard, and join cards.</p>
                    </div>
                    <div class="mt-2 space-y-3" data-image-crop-form>
                        <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                            <div class="shrink-0" data-crop-preview>
                                <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', $orgLogoCropX, $orgLogoCropY, $orgLogoZoom) ?>
                            </div>
                            <label for="orgLogoInput" class="org-logo-upload-trigger flex w-full min-w-0 cursor-pointer items-center gap-3 rounded-lg border border-dashed px-4 py-3 text-sm transition-colors sm:flex-1">
                                <span class="org-logo-upload-trigger-icon inline-flex h-9 w-9 items-center justify-center rounded-md shadow-sm"><?= uiIcon('upload', 'ui-icon') ?></span>
                                <span class="min-w-0 flex-1 font-medium">Choose organization logo</span>
                                <span class="org-logo-upload-trigger-subtext shrink-0 text-xs">Click to browse</span>
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
                    <button class="owner-manage-primary-btn px-4 py-2 rounded-md"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Organization Info</span></span></button>
                </div>
            </form>
        </div>

        <section class="announcement-workspace glass rounded-lg p-4">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Communications &amp; Broadcasts</span></h2>
                        <span class="announcement-workspace-chip">Broadcasting</span>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Create member-facing updates for <?= e((string) $org['name']) ?>.</p>
                </div>
                <button type="button" id="myOrgAnnouncementsOpen" class="announcement-open-btn inline-flex items-center rounded-md border px-3 py-2 text-xs font-medium transition-colors">View all announcements</button>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
                <div class="announcement-compose-panel rounded-lg border p-4">
                    <div class="mb-4 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Post New Announcement</span></h3>
                            <span class="announcement-compose-chip">Compose</span>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Create a member update.</p>
                    </div>

                    <form method="post" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_announcement">
                        <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">

                        <div class="space-y-2">
                            <label class="announcement-field-label" for="ownerAnnouncementTitle">Announcement Title</label>
                            <input id="ownerAnnouncementTitle" name="title" placeholder="Upcoming social night RSVP?" class="w-full border rounded px-3 py-2" required>
                            <p class="announcement-field-help">Use a short, clear headline.</p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                            <div class="space-y-2">
                                <label class="announcement-field-label" for="ownerAnnouncementLabel">Category / Label</label>
                                <div class="announcement-tag-shell flex flex-wrap items-center gap-2 rounded-md border px-3 py-2">
                                    <span class="announcement-tag-prefix inline-flex items-center rounded-md px-2 py-1 text-[11px] font-medium">Single tag</span>
                                    <input id="ownerAnnouncementLabel" name="label" maxlength="40" placeholder="Social, urgent, internal" class="announcement-tag-input min-w-0 flex-1 border-0 bg-transparent p-0 shadow-none focus:ring-0">
                                </div>
                                <p class="announcement-field-help">Optional tag shown on the announcement card.</p>
                            </div>

                            <fieldset class="space-y-2">
                                <legend class="announcement-field-label">Visibility</legend>
                                <div class="announcement-visibility-grid grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <label class="announcement-visibility-option">
                                        <input type="radio" name="duration_days" value="7" class="sr-only">
                                        <span class="announcement-visibility-card">
                                            <strong>7 days</strong>
                                            <small>Short reminder</small>
                                        </span>
                                    </label>
                                    <label class="announcement-visibility-option">
                                        <input type="radio" name="duration_days" value="14" class="sr-only">
                                        <span class="announcement-visibility-card">
                                            <strong>14 days</strong>
                                            <small>Two weeks</small>
                                        </span>
                                    </label>
                                    <label class="announcement-visibility-option">
                                        <input type="radio" name="duration_days" value="30" class="sr-only" checked>
                                        <span class="announcement-visibility-card">
                                            <strong>30 days</strong>
                                            <small>Recommended</small>
                                        </span>
                                    </label>
                                    <label class="announcement-visibility-option">
                                        <input type="radio" name="duration_days" value="60" class="sr-only">
                                        <span class="announcement-visibility-card">
                                            <strong>60 days</strong>
                                            <small>Longer cycle</small>
                                        </span>
                                    </label>
                                    <label class="announcement-visibility-option">
                                        <input type="radio" name="duration_days" value="90" class="sr-only">
                                        <span class="announcement-visibility-card">
                                            <strong>90 days</strong>
                                            <small>Extended notice</small>
                                        </span>
                                    </label>
                                </div>
                                <p class="announcement-field-help">Choose how long this update stays visible.</p>
                            </fieldset>
                        </div>

                        <div class="space-y-2">
                            <label class="announcement-field-label" for="ownerAnnouncementContent">Details</label>
                            <textarea id="ownerAnnouncementContent" name="content" rows="5" placeholder="Share the complete update, call to action, or event reminder for your members here." class="w-full border rounded px-3 py-2" required></textarea>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="announcement-field-help">Write the full message members will see.</p>
                                <span id="announcementVisibilityNote" class="announcement-field-note">Visible until the selected schedule ends</span>
                            </div>
                        </div>

                        <button class="owner-manage-primary-btn inline-flex items-center rounded-md px-4 py-2"><span class="icon-label"><?= uiIcon('create', 'ui-icon ui-icon-sm') ?><span>Post</span></span></button>
                    </form>
                </div>

                <div class="announcement-preview-panel rounded-lg border p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Latest Broadcasts</span></h3>
                                <span class="announcement-preview-chip">Recent</span>
                            </div>
                            <p class="text-sm text-slate-600 dark:text-slate-300">Review the newest live announcements here.</p>
                        </div>
                        <span class="announcement-preview-count rounded-md border px-2.5 py-1 text-xs"><?= count($allAnnouncements) ?> active</span>
                    </div>

                    <div class="space-y-3 max-h-80 overflow-auto">
                        <?php foreach ($announcementPreview as $announcement): ?>
                            <?php $announcementExcerpt = mb_strimwidth((string) $announcement['content'], 0, 170, '...'); ?>
                            <article class="announcement-preview-card rounded-lg border p-3 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="announcement-preview-title font-medium text-slate-900 dark:text-slate-100"><?= e((string) $announcement['title']) ?></div>
                                        <div class="mt-1 text-xs announcement-meta">Visible until <?= e(!empty($announcement['expires_at']) ? date('F d, Y', strtotime((string) $announcement['expires_at'])) : 'manually cleared') ?></div>
                                    </div>
                                    <?php if (trim((string) ($announcement['label'] ?? '')) !== ''): ?>
                                        <span class="announcement-label-chip"><?= e((string) $announcement['label']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="announcement-preview-body mt-2 text-sm announcement-body"><?= e($announcementExcerpt) ?></p>
                            </article>
                        <?php endforeach; ?>
                        <?php if (count($announcementPreview) === 0): ?>
                            <div class="announcement-empty-state rounded-lg border border-dashed px-4 py-5 text-sm">No active broadcasts right now. Your next member update will appear here.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div id="myOrgAnnouncementsModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-[2px] px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-12 w-full max-w-3xl">
            <div class="announcement-modal-panel rounded-lg border p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>All Organization Announcements</span></h3>
                        <p class="section-helper-copy">Review live announcements and remove outdated ones.</p>
                    </div>
                    <button type="button" id="myOrgAnnouncementsClose" class="text-2xl leading-none text-slate-500 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white" aria-label="Close modal">&times;</button>
                </div>

                <div class="announcement-modal-toolbar mb-4 space-y-3">
                    <div class="announcement-modal-summary flex flex-wrap items-center gap-2">
                        <span class="announcement-preview-count rounded-md border px-2.5 py-1 text-xs"><span id="announcementModalVisibleCount"><?= count($allAnnouncements) ?></span> active</span>
                        <span class="announcement-workspace-chip">Newest first</span>
                        <span class="announcement-compose-chip">Live broadcasts</span>
                    </div>
                    <?php if (count($allAnnouncements) > 0): ?>
                        <div>
                            <input id="announcementModalSearch" type="search" inputmode="search" autocomplete="off" placeholder="Search announcement title, label, or details..." class="announcement-modal-search w-full border px-3 py-2">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="space-y-3">
                    <?php foreach ($allAnnouncements as $announcement): ?>
                        <?php
                            $announcementExcerpt = mb_strimwidth((string) $announcement['content'], 0, 260, '...');
                            $announcementSearch = strtolower(trim((string) (($announcement['title'] ?? '') . ' ' . ($announcement['label'] ?? '') . ' ' . ($announcement['content'] ?? ''))));
                        ?>
                        <article class="announcement-modal-card rounded-lg border p-4" data-announcement-search="<?= e($announcementSearch) ?>">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium announcement-modal-title"><?= e((string) $announcement['title']) ?></div>
                                    <div class="mt-1 text-xs announcement-meta">Visible until <?= e(!empty($announcement['expires_at']) ? date('F d, Y', strtotime((string) $announcement['expires_at'])) : 'manually cleared') ?></div>
                                </div>
                                <div class="announcement-modal-status flex flex-wrap items-center gap-2">
                                    <span class="announcement-preview-chip">Active</span>
                                    <?php if (trim((string) ($announcement['label'] ?? '')) !== ''): ?>
                                        <span class="announcement-label-chip"><?= e((string) $announcement['label']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="announcement-modal-body mt-3 text-sm announcement-body"><?= e($announcementExcerpt) ?></p>
                            <form method="post" class="announcement-modal-actions mt-3 flex justify-end">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_announcement">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                <button class="tx-action-btn tx-action-btn-delete inline-flex items-center justify-center rounded-md px-3 py-2 text-xs"><span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Delete Announcement</span></span></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                    <?php if (count($allAnnouncements) === 0): ?>
                        <div class="announcement-empty-state rounded-lg border border-dashed px-4 py-5 text-sm">No active broadcasts right now. Publish a new announcement to start the feed.</div>
                    <?php endif; ?>
                </div>
                <div id="announcementModalEmptySearch" class="announcement-empty-state mt-3 hidden rounded-lg border border-dashed px-4 py-5 text-sm">No broadcasts matched that search.</div>
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

    <script>
        (function () {
            const searchInput = document.getElementById('announcementModalSearch');
            const cards = Array.from(document.querySelectorAll('.announcement-modal-card[data-announcement-search]'));
            const emptyState = document.getElementById('announcementModalEmptySearch');
            const count = document.getElementById('announcementModalVisibleCount');

            if (!searchInput || cards.length === 0) {
                return;
            }

            const filterAnnouncements = function () {
                const query = searchInput.value.trim().toLowerCase();
                let visible = 0;

                cards.forEach(function (card) {
                    const haystack = card.getAttribute('data-announcement-search') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        visible += 1;
                    }
                });

                if (count) {
                    count.textContent = String(visible);
                }

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visible !== 0 || query === '');
                }
            };

            searchInput.addEventListener('input', filterAnnouncements);
        })();
    </script>

    <script>
        (function () {
            const durationInputs = Array.from(document.querySelectorAll('input[name="duration_days"]'));
            const visibilityNote = document.getElementById('announcementVisibilityNote');

            if (!visibilityNote || durationInputs.length === 0) {
                return;
            }

            const formatFutureDate = function (days) {
                const date = new Date();
                date.setDate(date.getDate() + days);
                return date.toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            };

            const getSelectedDuration = function () {
                const selected = durationInputs.find(function (input) {
                    return input.checked;
                });

                return selected ? Number(selected.value || 30) : 30;
            };

            const syncPreview = function () {
                const duration = getSelectedDuration();
                const visibleUntil = formatFutureDate(duration);

                if (visibilityNote) {
                    visibilityNote.textContent = 'Visible until ' + visibleUntil;
                }
            };

            durationInputs.forEach(function (input) {
                input.addEventListener('change', syncPreview);
            });

            syncPreview();
        })();
    </script>

    <script>
        (function () {
            const searchInput = document.getElementById('ownerJoinRequestSearch');
            const requestCards = Array.from(document.querySelectorAll('.owner-join-request-card'));
            const emptyState = document.getElementById('ownerJoinRequestEmptySearch');
            const visibleCount = document.getElementById('ownerJoinVisibleCount');
            const pendingCount = document.getElementById('ownerJoinPendingCount');

            if (!searchInput || requestCards.length === 0) {
                return;
            }

            if (pendingCount) {
                pendingCount.textContent = String(requestCards.length);
            }

            const filterRequests = function () {
                const query = searchInput.value.trim().toLowerCase();
                let matchedCount = 0;

                requestCards.forEach(function (card) {
                    const haystack = card.getAttribute('data-request-search') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        matchedCount += 1;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = String(matchedCount);
                }

                if (emptyState) {
                    emptyState.classList.toggle('hidden', matchedCount !== 0 || query === '');
                }
            };

            searchInput.addEventListener('input', filterRequests);
        })();
    </script>

    <script>
        (function () {
            const searchInput = document.getElementById('ownerMemberSearch');
            const memberCards = Array.from(document.querySelectorAll('.owner-member-card'));
            const emptyState = document.getElementById('ownerMemberEmptySearch');
            const visibleCount = document.getElementById('ownerMemberVisibleCount');

            if (!searchInput || memberCards.length === 0) {
                return;
            }

            const filterMembers = function () {
                const query = searchInput.value.trim().toLowerCase();
                let matchedCount = 0;

                memberCards.forEach(function (card) {
                    const haystack = card.getAttribute('data-member-search') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        matchedCount += 1;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = String(matchedCount);
                }

                if (emptyState) {
                    emptyState.classList.toggle('hidden', matchedCount !== 0 || query === '');
                }
            };

            searchInput.addEventListener('input', filterMembers);
        })();
    </script>

    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleMyOrgFinancePage(PDO $db, array $user, string $announcementCutoff): void
{
    $workspace = buildOwnerWorkspaceData($db, $user);
    extract($workspace, EXTR_SKIP);

    renderHeader('Financial Management');
    ?>
    <div class="space-y-4">
        <?php renderOwnerWorkspaceHeader($org, 'my_org_finance'); ?>

        <div class="glass rounded-lg p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Financial Management</span></h2>
                    <p class="section-helper-copy">Record transactions and review finance requests in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <a href="#tx-requests" class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-800 transition-colors hover:bg-emerald-500/15">Jump to pending requests</a>
                    <a href="#tx-history" class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1 text-slate-700 transition-colors hover:bg-white/15">Jump to transaction history</a>
                </div>
            </div>
        </div>

        <?php $selectedOrgName = (string) ($org['name'] ?? 'Select organization'); ?>
        <div class="glass rounded-lg p-4">
            <form method="get" class="flex flex-wrap gap-2 items-stretch sm:items-start relative" id="myOrgFinanceSwitcherForm" data-dropdown-root>
                <input type="hidden" name="page" value="my_org_finance">
                <input type="hidden" name="tx_type" value="<?= e($txTypeFilter) ?>">
                <input type="hidden" name="tx_sort" value="<?= e($txDateSort) ?>">
                <input type="hidden" name="org_id" id="myOrgFinanceOrgId" data-dropdown-value value="<?= (int) $org['id'] ?>">
                <div class="relative w-full min-w-0 sm:min-w-[16rem] sm:flex-1" data-dropdown-wrapper>
                    <button type="button" id="myOrgFinanceSwitcherButton" data-dropdown-toggle="myOrgFinanceSwitcherMenu" aria-expanded="false" class="w-full flex items-center border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                        <span id="myOrgFinanceSwitcherLabel" data-dropdown-label class="truncate text-left"><?= e($selectedOrgName) ?></span>
                    </button>
                    <div id="myOrgFinanceSwitcherMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md" aria-labelledby="myOrgFinanceSwitcherButton">
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
                <button class="owner-manage-secondary-btn w-full sm:w-auto px-4 py-2 rounded-md"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Open</span></span></button>
            </form>
        </div>

        <div class="glass rounded-lg p-4">
                <div class="mb-4 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold icon-label"><?= uiIcon('create', 'ui-icon') ?><span>Add Income / Expense</span></h2>
                        <span class="inline-flex items-center rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-300/25 dark:bg-emerald-400/10 dark:text-emerald-200">Finance log</span>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Record cash flow, dates, and receipt details in one place.</p>
                </div>
                <form method="post" enctype="multipart/form-data" class="space-y-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 owner-transaction-entry-row" data-dropdown-root>
                        <input type="hidden" name="type" data-dropdown-value value="income">
                        <div class="relative w-full" data-dropdown-wrapper>
                            <button type="button" data-dropdown-toggle="myOrgAddTypeMenu" aria-expanded="false" class="owner-transaction-trigger w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                <span data-dropdown-label class="truncate text-left">Income</span>
                                <span class="hidden text-xs">▼</span>
                            </button>
                            <div id="myOrgAddTypeMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                <ul class="p-2 text-sm font-medium space-y-1">
                                    <li><button type="button" data-dropdown-option data-active="true" data-option-value="income" data-option-label="Income" class="block w-full rounded px-3 py-2 text-left transition-colors">Income</button></li>
                                    <li><button type="button" data-dropdown-option data-active="false" data-option-value="expense" data-option-label="Expense" class="block w-full rounded px-3 py-2 text-left transition-colors">Expense</button></li>
                                </ul>
                            </div>
                        </div>
                        <input type="number" step="0.01" name="amount" placeholder="Amount" class="owner-transaction-input border rounded px-3 py-2" data-currency required>
                    </div>
                    <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" class="owner-transaction-input owner-transaction-date w-full border rounded px-3 py-2" required>
                    <input name="description" placeholder="Description" class="w-full border rounded px-3 py-2" required>
                    <div class="rounded-lg border border-emerald-300/25 bg-white/35 px-3 py-3 dark:border-emerald-300/15 dark:bg-emerald-950/15">
                        <div class="mb-2">
                            <div class="text-sm font-medium text-slate-700 dark:text-slate-200">Receipt</div>
                            <div class="text-xs text-slate-500 dark:text-slate-300">Optional image or PDF.</div>
                        </div>
                        <div class="flex w-full flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:gap-3">
                        <label for="myOrgReceiptInput" class="owner-manage-primary-btn inline-flex w-full cursor-pointer items-center justify-center rounded-md px-3 py-1.5 text-xs sm:w-auto">
                            Upload Receipt
                        </label>
                        <span id="myOrgReceiptFilename" class="min-w-0 truncate text-sm text-slate-500 dark:text-slate-300 sm:flex-1">No file chosen</span>
                        <input id="myOrgReceiptInput" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" onchange="var f=this.files&&this.files[0]?this.files[0].name:'No file chosen'; var n=document.getElementById('myOrgReceiptFilename'); if(n){ n.textContent=f; }">
                        </div>
                    </div>
                    <button class="owner-manage-primary-btn w-full rounded-md px-4 py-2 sm:w-auto"><span class="icon-label"><?= uiIcon('save', 'ui-icon ui-icon-sm') ?><span>Save Transaction</span></span></button>
                </form>
        </div>

        <div id="tx-history" class="glass rounded-lg p-4 overflow-auto">
            <div class="mb-2 flex flex-col items-stretch justify-between gap-2 sm:flex-row sm:items-center">
                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Transaction History</span></h2>
                <a href="?page=my_org_finance&org_id=<?= (int) $org['id'] ?>&action=export_transactions&format=pdf&tx_type=<?= urlencode($txTypeFilter) ?>&tx_sort=<?= urlencode($txDateSort) ?>" class="owner-manage-secondary-btn report-export-btn inline-flex w-full items-center justify-center gap-2 rounded-md px-3 py-2 text-sm sm:w-auto">
                    Export PDF
                </a>
            </div>
            <form method="get" action="?page=my_org_finance#tx-history" class="mb-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end" data-dropdown-root onsubmit="const b=this.querySelector('[data-filter-submit]'); if(b){ b.disabled=true; b.textContent='Filtering...'; }">
                <input type="hidden" name="page" value="my_org_finance">
                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                <div class="w-full sm:w-auto">
                    <label class="block text-xs text-slate-600 mb-1">Type</label>
                    <div class="relative" data-dropdown-wrapper>
                        <input type="hidden" name="tx_type" data-dropdown-value value="<?= e($txTypeFilter) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxTypeMenu" aria-expanded="false" class="finance-filter-control w-full flex items-center justify-between gap-3 border rounded px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-xs transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txTypeFilter === 'income' ? 'Income' : ($txTypeFilter === 'expense' ? 'Expense' : 'All')) ?></span>
                            <span class="hidden text-xs">▼</span>
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
                <div class="w-full sm:w-auto">
                    <label class="block text-xs text-slate-600 mb-1">Date</label>
                    <div class="relative" data-dropdown-wrapper>
                        <input type="hidden" name="tx_sort" data-dropdown-value value="<?= e($txDateSort) ?>">
                        <button type="button" data-dropdown-toggle="myOrgTxSortMenu" aria-expanded="false" class="finance-filter-control w-full flex items-center justify-between gap-3 border rounded px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 text-xs transition-colors">
                            <span data-dropdown-label class="truncate text-left"><?= e($txDateSort === 'asc' ? 'Oldest first' : 'Newest first') ?></span>
                            <span class="hidden text-xs">▼</span>
                        </button>
                        <div id="myOrgTxSortMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-max min-w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                            <ul class="p-2 text-sm font-medium space-y-1">
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'desc' ? 'true' : 'false' ?>" data-option-value="desc" data-option-label="Newest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Newest first</button></li>
                                <li><button type="button" data-dropdown-option data-active="<?= $txDateSort === 'asc' ? 'true' : 'false' ?>" data-option-value="asc" data-option-label="Oldest first" class="block w-full rounded px-3 py-2 text-left transition-colors">Oldest first</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <button data-filter-submit class="owner-manage-secondary-btn finance-filter-btn w-full rounded-md px-2.5 py-1.5 text-xs sm:w-auto"><span class="icon-label"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?><span>Apply</span></span></button>
            </form>
            <div class="table-wrapper hidden md:block">
                <table class="w-full text-sm table-fixed">
                    <colgroup>
                        <col class="w-[13%]">
                        <col class="w-[11%]">
                        <col class="w-[12%]">
                        <col class="w-[38%]">
                        <col class="w-[10%]">
                        <col class="w-[16%]">
                    </colgroup>
                    <thead>
                    <tr class="text-left border-b border-emerald-400">
                        <th class="py-2 pr-4">Date</th>
                        <th class="pr-4">Type</th>
                        <th class="pr-4">Amount</th>
                        <th class="pr-6">Description</th>
                        <th class="pr-4 text-left">Receipt</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $row): ?>
                        <tr class="border-b border-emerald-300 align-top">
                            <td class="py-3 pr-4 whitespace-nowrap"><?= e(date('F d, Y', strtotime((string) $row['transaction_date']))) ?></td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                <?php $txType = strtolower((string) $row['type']); ?>
                                <span class="tx-type-badge <?= $txType === 'income' ? 'tx-type-badge-income' : 'tx-type-badge-expense' ?>">
                                    <?= e($txType) ?>
                                </span>
                            </td>
                            <td class="py-3 pr-4 whitespace-nowrap font-medium">PHP<?= number_format((float) $row['amount'], 2) ?></td>
                            <td class="py-3 pr-6 break-words"><?= e((string) $row['description']) ?></td>
                            <td class="py-3 pr-4 text-left">
                                <?php if (!empty($row['receipt_path'])): ?>
                                    <a href="<?= e((string) $row['receipt_path']) ?>" target="_blank" class="tx-action-btn tx-action-btn-view inline-flex items-center justify-center rounded-md px-3 py-2 text-sm"><span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View</span></span></a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-right">
                                <div class="inline-flex w-full items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="tx-action-btn tx-action-btn-view inline-flex min-h-[2.4rem] min-w-[2.4rem] items-center justify-center rounded-md px-2"
                                        data-tx-edit-open
                                        data-tx-id="<?= (int) $row['id'] ?>"
                                        data-tx-type="<?= e((string) $row['type']) ?>"
                                        data-tx-amount="<?= e(number_format((float) $row['amount'], 2, '.', '')) ?>"
                                        data-tx-date="<?= e((string) $row['transaction_date']) ?>"
                                        data-tx-description="<?= e((string) $row['description']) ?>"
                                        data-tx-receipt="<?= e((string) ($row['receipt_path'] ?? '')) ?>"
                                        aria-label="Edit transaction details"
                                    ><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?></button>
                                    <form method="post" data-confirm-message="Delete transaction?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_transaction">
                                        <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                        <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                        <button class="tx-action-btn tx-action-btn-delete inline-flex min-h-[2.4rem] min-w-[2.4rem] items-center justify-center rounded-md px-2" aria-label="Request delete">
                                            <?= uiIcon('delete', 'ui-icon ui-icon-sm') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-cards md:hidden space-y-3">
                <?php foreach ($transactions as $row): ?>
                    <?php $type = (string) $row['type']; ?>
                    <article class="tx-mobile-card rounded-lg border border-emerald-200 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e((string) $org['name']) ?></div>
                            <span class="tx-mobile-type-badge inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold <?= $type === 'income' ? 'tx-mobile-type-income bg-emerald-100 text-emerald-700' : 'tx-mobile-type-expense bg-red-100 text-red-700' ?>"><?= e(ucfirst($type)) ?></span>
                        </div>
                        <div class="mt-2 text-2xl font-bold leading-tight text-slate-900">PHP<?= number_format((float) $row['amount'], 2) ?></div>
                        <div class="mt-2 text-xs text-slate-600"><?= e(date('F d, Y', strtotime((string) $row['transaction_date']))) ?></div>
                        <p class="mt-2 text-sm text-slate-700" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e((string) $row['description']) ?>
                        </p>
                        <div class="tx-mobile-actions mt-3 space-y-2">
                            <?php if (!empty($row['receipt_path'])): ?>
                                <a href="<?= e((string) $row['receipt_path']) ?>" target="_blank" class="tx-action-btn tx-action-btn-view row-action-hit-target inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm">
                                    <span class="icon-label"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?><span>View Receipt</span></span>
                                </a>
                            <?php endif; ?>
                            <button
                                type="button"
                                class="tx-action-btn tx-action-btn-update inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm"
                                data-tx-edit-open
                                data-tx-id="<?= (int) $row['id'] ?>"
                                data-tx-type="<?= e((string) $row['type']) ?>"
                                data-tx-amount="<?= e(number_format((float) $row['amount'], 2, '.', '')) ?>"
                                data-tx-date="<?= e((string) $row['transaction_date']) ?>"
                                data-tx-description="<?= e((string) $row['description']) ?>"
                                data-tx-receipt="<?= e((string) ($row['receipt_path'] ?? '')) ?>"
                            >
                                <span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Edit Transaction</span></span>
                            </button>
                            <form method="post" data-confirm-message="Delete transaction?">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                                <input type="hidden" name="tx_id" value="<?= (int) $row['id'] ?>">
                                <button class="row-action-hit-target tx-action-btn tx-action-btn-delete inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm">
                                    <span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Request Delete</span></span>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php renderPagination($transactionsPagination + ['anchor' => 'tx-history']); ?>
        </div>

        <div id="tx-requests" class="glass rounded-lg p-4 overflow-auto">
            <div class="mb-3 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('requests', 'ui-icon') ?><span>My Pending/Recent Transaction Requests</span></h2>
                    <span class="inline-flex items-center rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-300/25 dark:bg-emerald-400/10 dark:text-emerald-200">Request trail</span>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300">Track your latest finance update and delete requests here.</p>
            </div>
            <div class="table-wrapper hidden md:block">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="text-left border-b border-emerald-400/40">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Action</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2">Admin Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myTxRequests as $req): ?>
                        <?php
                            $status = strtolower((string) ($req['status'] ?? ''));
                            $statusClass = 'updates-status updates-status-' . preg_replace('/[^a-z]/', '', $status);
                        ?>
                        <tr class="border-b border-emerald-300/25 align-top">
                            <td class="py-3 pr-3 whitespace-nowrap"><?= e(date('F d, Y', strtotime((string) $req['created_at']))) ?></td>
                            <td class="py-3 pr-3">
                                <span class="inline-flex items-center rounded-md border border-slate-300/25 bg-white/10 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-emerald-300/15 dark:bg-emerald-950/20 dark:text-slate-200">
                                    <?= e(ucwords(str_replace('_', ' ', (string) $req['action_type']))) ?>
                                </span>
                            </td>
                            <td class="py-3 pr-3">
                                <span class="<?= e($statusClass) ?> icon-badge"><?= uiIcon(match ($status) {
                                    'approved', 'accepted' => 'approved',
                                    'rejected', 'declined', 'removed' => 'rejected',
                                    'pending' => 'pending',
                                    default => 'default',
                                }, 'ui-icon ui-icon-sm') ?><?= e(ucfirst($status)) ?></span>
                            </td>
                            <td class="py-3 text-slate-600 dark:text-slate-300">
                                <?= e(trim((string) ($req['admin_note'] ?? '')) !== '' ? (string) $req['admin_note'] : 'No admin note yet.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($myTxRequests) === 0): ?>
                        <tr>
                            <td colspan="4" class="py-6">
                                <div class="empty-state-panel">No recent transaction requests yet.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="md:hidden space-y-3">
                <?php foreach ($myTxRequests as $req): ?>
                    <?php
                        $status = strtolower((string) ($req['status'] ?? ''));
                        $statusClass = 'updates-status updates-status-' . preg_replace('/[^a-z]/', '', $status);
                    ?>
                    <article class="tx-request-card rounded-lg border border-emerald-200 bg-white/60 p-3 shadow-sm dark:border-emerald-300/15 dark:bg-emerald-950/20">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= e(date('F d, Y', strtotime((string) $req['created_at']))) ?></div>
                                <div class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e(ucwords(str_replace('_', ' ', (string) $req['action_type']))) ?></div>
                            </div>
                            <span class="<?= e($statusClass) ?> icon-badge"><?= uiIcon(match ($status) {
                                'approved', 'accepted' => 'approved',
                                'rejected', 'declined', 'removed' => 'rejected',
                                'pending' => 'pending',
                                default => 'default',
                            }, 'ui-icon ui-icon-sm') ?><?= e(ucfirst($status)) ?></span>
                        </div>
                        <div class="mt-3 rounded-lg border border-emerald-300/20 bg-white/35 px-3 py-3 text-sm text-slate-600 dark:border-emerald-300/15 dark:bg-emerald-950/15 dark:text-slate-300">
                            <?= e(trim((string) ($req['admin_note'] ?? '')) !== '' ? (string) $req['admin_note'] : 'No admin note yet.') ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (count($myTxRequests) === 0): ?>
                    <div class="empty-state-panel">No recent transaction requests yet.</div>
                <?php endif; ?>
            </div>
            <?php renderPagination($myTxRequestsPagination + ['anchor' => 'tx-requests']); ?>
        </div>
    </div>

    <div id="txEditModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-[2px] px-4 py-6 overflow-y-auto" data-modal-close>
        <div class="mx-auto mt-8 w-full max-w-xl">
            <div class="tx-edit-modal-panel rounded-lg p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="tx-edit-modal-title text-lg font-semibold icon-label"><?= uiIcon('edit', 'ui-icon') ?><span>Edit Transaction Details</span></h3>
                        <p class="tx-edit-modal-copy mt-1 text-sm">Review the transaction details, then request an update or delete from one place.</p>
                    </div>
                    <button type="button" id="txEditModalClose" class="text-slate-500 hover:text-slate-900 text-2xl leading-none" aria-label="Close modal">&times;</button>
                </div>

                <form method="post" id="txEditModalUpdateForm" class="space-y-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input type="hidden" name="tx_id" id="txEditModalTxId" value="">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="tx-edit-modal-label mb-1 block text-xs">Type</label>
                            <select name="type" id="txEditModalType" class="tx-edit-modal-field w-full border rounded px-3 py-2">
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div>
                            <label class="tx-edit-modal-label mb-1 block text-xs">Amount</label>
                            <input name="amount" id="txEditModalAmount" type="number" step="0.01" class="tx-edit-modal-field w-full border rounded px-3 py-2" data-currency>
                        </div>
                    </div>
                    <div>
                        <label class="tx-edit-modal-label mb-1 block text-xs">Date</label>
                        <input name="transaction_date" id="txEditModalDate" type="date" class="tx-edit-modal-field owner-transaction-inline-date w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="tx-edit-modal-label mb-1 block text-xs">Description</label>
                        <textarea name="description" id="txEditModalDescription" rows="3" class="tx-edit-modal-field w-full border rounded px-3 py-2"></textarea>
                    </div>
                    <div>
                        <label class="tx-edit-modal-label mb-1 block text-xs">Receipt</label>
                        <div class="tx-edit-modal-receipt flex items-center gap-2 rounded-lg px-3 py-3">
                            <span class="tx-edit-modal-receipt-icon inline-flex h-9 w-9 items-center justify-center rounded-md"><?= uiIcon('view', 'ui-icon ui-icon-sm') ?></span>
                            <a href="#" id="txEditModalReceiptLink" target="_blank" class="tx-action-btn tx-action-btn-view inline-flex items-center justify-center rounded-md px-3 py-2 text-sm">View Receipt</a>
                            <span id="txEditModalReceiptEmpty" class="tx-edit-modal-receipt-empty hidden text-sm">No receipt attached</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button class="tx-action-btn tx-action-btn-update inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm">
                            <span class="icon-label"><?= uiIcon('edit', 'ui-icon ui-icon-sm') ?><span>Request Update</span></span>
                        </button>
                        <button type="button" id="txEditModalDeleteButton" class="tx-action-btn tx-action-btn-delete inline-flex w-full items-center justify-center rounded-md px-3 py-2 text-sm">
                            <span class="icon-label"><?= uiIcon('delete', 'ui-icon ui-icon-sm') ?><span>Request Delete</span></span>
                        </button>
                    </div>
                </form>

                <form method="post" id="txEditModalDeleteForm" class="hidden" data-confirm-message="Delete transaction?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_transaction">
                    <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                    <input type="hidden" name="tx_id" id="txEditModalDeleteTxId" value="">
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('txEditModal');
            const closeBtn = document.getElementById('txEditModalClose');
            const openButtons = Array.from(document.querySelectorAll('[data-tx-edit-open]'));
            const txIdField = document.getElementById('txEditModalTxId');
            const deleteTxIdField = document.getElementById('txEditModalDeleteTxId');
            const typeField = document.getElementById('txEditModalType');
            const amountField = document.getElementById('txEditModalAmount');
            const dateField = document.getElementById('txEditModalDate');
            const descriptionField = document.getElementById('txEditModalDescription');
            const receiptLink = document.getElementById('txEditModalReceiptLink');
            const receiptEmpty = document.getElementById('txEditModalReceiptEmpty');
            const deleteButton = document.getElementById('txEditModalDeleteButton');
            const deleteForm = document.getElementById('txEditModalDeleteForm');

            if (!modal || !closeBtn || openButtons.length === 0) {
                return;
            }

            const closeModal = function () {
                modal.classList.add('hidden');
            };

            const openModal = function (button) {
                txIdField.value = button.getAttribute('data-tx-id') || '';
                deleteTxIdField.value = txIdField.value;
                typeField.value = button.getAttribute('data-tx-type') || 'expense';
                amountField.value = button.getAttribute('data-tx-amount') || '';
                dateField.value = button.getAttribute('data-tx-date') || '';
                descriptionField.value = button.getAttribute('data-tx-description') || '';

                const receiptPath = button.getAttribute('data-tx-receipt') || '';
                if (receiptPath !== '') {
                    receiptLink.href = receiptPath;
                    receiptLink.classList.remove('hidden');
                    receiptEmpty.classList.add('hidden');
                } else {
                    receiptLink.href = '#';
                    receiptLink.classList.add('hidden');
                    receiptEmpty.classList.remove('hidden');
                }

                modal.classList.remove('hidden');
            };

            openButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button);
                });
            });

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

            if (deleteButton && deleteForm) {
                deleteButton.addEventListener('click', function () {
                    deleteForm.requestSubmit();
                });
            }
        })();
    </script>

    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleMyOrgMembersPage(PDO $db, array $user, string $announcementCutoff): void
{
    $workspace = buildOwnerWorkspaceData($db, $user);
    extract($workspace, EXTR_SKIP);

    renderHeader('Membership Management');
    ?>
    <div class="space-y-4">
        <?php renderOwnerWorkspaceHeader($org, 'my_org_members'); ?>

        <div class="glass rounded-lg p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold icon-label"><?= uiIcon('students', 'ui-icon') ?><span>Membership Management</span></h2>
                    <p class="section-helper-copy">Handle join requests and roster access in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-800">Members: <?= (int) $orgMemberCount ?></span>
                    <span class="rounded-md border border-slate-300/30 bg-white/10 px-2.5 py-1 text-slate-700">Pending requests: <?= (int) $pendingJoinCount ?></span>
                </div>
            </div>
        </div>

        <?php renderOwnerMembershipPanels($org, $pendingJoinRequests, $pendingJoinPagination, $pendingJoinCount, $orgMembers, $orgMemberPagination, $orgMemberCount); ?>
    </div>

    <script>
        (function () {
            const searchInput = document.getElementById('ownerJoinRequestSearch');
            const requestCards = Array.from(document.querySelectorAll('.owner-join-request-card'));
            const emptyState = document.getElementById('ownerJoinRequestEmptySearch');
            const visibleCount = document.getElementById('ownerJoinVisibleCount');
            const pendingCount = document.getElementById('ownerJoinPendingCount');

            if (!searchInput || requestCards.length === 0) {
                return;
            }

            if (pendingCount) {
                pendingCount.textContent = String(requestCards.length);
            }

            const filterRequests = function () {
                const query = searchInput.value.trim().toLowerCase();
                let matchedCount = 0;

                requestCards.forEach(function (card) {
                    const haystack = card.getAttribute('data-request-search') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        matchedCount += 1;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = String(matchedCount);
                }

                if (emptyState) {
                    emptyState.classList.toggle('hidden', matchedCount !== 0 || query === '');
                }
            };

            searchInput.addEventListener('input', filterRequests);
        })();
    </script>

    <script>
        (function () {
            const searchInput = document.getElementById('ownerMemberSearch');
            const memberCards = Array.from(document.querySelectorAll('.owner-member-card'));
            const emptyState = document.getElementById('ownerMemberEmptySearch');
            const visibleCount = document.getElementById('ownerMemberVisibleCount');

            if (!searchInput || memberCards.length === 0) {
                return;
            }

            const filterMembers = function () {
                const query = searchInput.value.trim().toLowerCase();
                let matchedCount = 0;

                memberCards.forEach(function (card) {
                    const haystack = card.getAttribute('data-member-search') || '';
                    const isVisible = query === '' || haystack.includes(query);
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        matchedCount += 1;
                    }
                });

                if (visibleCount) {
                    visibleCount.textContent = String(matchedCount);
                }

                if (emptyState) {
                    emptyState.classList.toggle('hidden', matchedCount !== 0 || query === '');
                }
            };

            searchInput.addEventListener('input', filterMembers);
        })();
    </script>

    <script src="assets/js/owner-org-switcher.js"></script>
    <?php
    renderFooter();
    exit;
}
