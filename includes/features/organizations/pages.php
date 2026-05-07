<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - COMMUNITY AND ORGANIZATION PAGES
 * ================================================
 *
 * SECTION MAP:
 * 1. Announcements Page
 * 2. Organizations Directory
 * 3. Profile Page
 *
 * WORK GUIDE:
 * - Edit this file for student/community-facing page markup.
 * - Edit workflows.php for organization POST actions.
 * ================================================
 */

function handleAnnouncementsPage(PDO $db, $user, string $announcementCutoff): void
{
    $activeAnnouncementCutoff = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('SELECT a.*, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE (a.expires_at IS NULL OR a.expires_at >= ?)
        ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC');
    $stmt->execute([$activeAnnouncementCutoff]);
    $allAnnouncements = $stmt->fetchAll();
    $slides = array_chunk($allAnnouncements, 3);
    $slideCount = count($slides);

    renderHeader('Announcements');
    ?>
    <section class="glass p-4">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold icon-label"><?= uiIcon('announce', 'ui-icon') ?><span>Latest Announcements</span></h1>
            <a href="?page=dashboard" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Back to Dashboard</span></span></a>
        </div>

        <?php if ($slideCount === 0): ?>
            <p class="text-sm text-gray-600">No active announcements right now.</p>
        <?php else: ?>
            <div class="relative">
                <?php foreach ($slides as $index => $slideItems): ?>
                    <div class="announcement-slide <?= $index === 0 ? '' : 'hidden' ?>" data-slide-index="<?= $index ?>">
                        <div class="grid md:grid-cols-3 gap-3">
                            <?php foreach ($slideItems as $item): ?>
                                <article class="border rounded p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <h2 class="font-semibold"><?= e($item['title']) ?></h2>
                                        <div class="flex items-center gap-1">
                                            <?php if (trim((string) ($item['label'] ?? '')) !== ''): ?>
                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-300/40"><?= e((string) $item['label']) ?></span>
                                            <?php endif; ?>
                                            <?php if ((int) ($item['is_pinned'] ?? 0) === 1): ?>
                                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-300/40 icon-label"><?= uiIcon('pin', 'ui-icon ui-icon-sm') ?><span>Important</span></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?= e($item['organization_name']) ?> · 
                                        <?= e(date('F d, Y', strtotime((string)$item['created_at']))) ?> 
                                        <?php if (!empty($item['expires_at'])): ?>
                                            · Expires <?= e(date('F d, Y', strtotime((string)$item['expires_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm mt-2"><?= e($item['content']) ?></p>
                                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                                        <form method="post" class="mt-2">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="<?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'unpin_announcement_admin' : 'pin_announcement_admin' ?>">
                                            <input type="hidden" name="announcement_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="return_page" value="announcements">
                                            <button class="px-2 py-1 rounded text-xs border">
                                                <span class="icon-label"><?= uiIcon('pin', 'ui-icon ui-icon-sm') ?><span><?= (int) ($item['is_pinned'] ?? 0) === 1 ? 'Unpin' : 'Pin as Important' ?></span></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="flex items-center justify-between mt-4">
                    <button type="button" id="announcementPrev" class="px-3 py-1 rounded border text-sm"><span class="icon-label"><?= uiIcon('prev', 'ui-icon ui-icon-sm') ?><span>Prev</span></span></button>
                    <div id="announcementDots" class="flex gap-1"></div>
                    <button type="button" id="announcementNext" class="px-3 py-1 rounded border text-sm"><span class="icon-label"><span>Next</span><?= uiIcon('next', 'ui-icon ui-icon-sm') ?></span></button>
                </div>
            </div>

            <script>
                (function () {
                    const slides = Array.from(document.querySelectorAll('.announcement-slide'));
                    const prevBtn = document.getElementById('announcementPrev');
                    const nextBtn = document.getElementById('announcementNext');
                    const dotsWrap = document.getElementById('announcementDots');
                    if (!slides.length || !prevBtn || !nextBtn || !dotsWrap) return;

                    let index = 0;
                    const dots = slides.map(function (_, i) {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'w-2 h-2 rounded-full border border-emerald-400';
                        dot.addEventListener('click', function () {
                            show(i);
                        });
                        dotsWrap.appendChild(dot);
                        return dot;
                    });

                    function show(target) {
                        index = (target + slides.length) % slides.length;
                        slides.forEach(function (slide, i) {
                            slide.classList.toggle('hidden', i !== index);
                        });
                        dots.forEach(function (dot, i) {
                            dot.classList.toggle('bg-emerald-500', i === index);
                            dot.classList.toggle('bg-transparent', i !== index);
                        });
                    }

                    prevBtn.addEventListener('click', function () {
                        show(index - 1);
                    });

                    nextBtn.addEventListener('click', function () {
                        show(index + 1);
                    });

                    show(0);
                })();
            </script>
        <?php endif; ?>
    </section>
    <?php
    renderFooter();
    exit;
}

function handleOrganizationsPage(PDO $db, array $user): void
{
    $allOrgs = $db->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')->fetchAll();

    $membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
    $membershipStmt->execute([(int) $user['id']]);
    $memberOrganizationIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));
    $allOrgs = applyOrganizationVisibilityForUser($allOrgs, $user, $memberOrganizationIds);
    $joinedIds = $memberOrganizationIds;

    $requestStmt = $db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
    $requestStmt->execute([(int) $user['id']]);
    $joinRequestStatus = [];
    foreach ($requestStmt->fetchAll() as $req) {
        $joinRequestStatus[(int) $req['organization_id']] = (string) $req['status'];
    }

    renderHeader('Organizations');
    ?>
    <section class="glass p-4">
        <div class="flex items-center justify-between mb-3">
            <h1 class="text-xl font-semibold icon-label"><?= uiIcon('orgs', 'ui-icon') ?><span>All Organizations</span></h1>
            <a href="?page=dashboard" class="bg-indigo-700 text-white px-3 py-2 rounded text-sm"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Back to Dashboard</span></span></a>
        </div>

        <div class="space-y-3 max-h-[36rem] overflow-y-auto themed-scroll pr-1">
            <?php foreach ($allOrgs as $org): ?>
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
                    $joinIcon = !$canJoin ? 'rejected' : ($isJoined ? 'approved' : ($requestStatus === 'pending' ? 'pending' : 'requests'));
                ?>
                <div class="hidden md:flex border rounded p-3 flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                    <div class="flex items-start gap-3 text-left">
                        <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'md', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                        <div>
                            <div class="font-medium"><?= e($org['name']) ?></div>
                            <p class="text-sm text-gray-600"><?= e($org['description']) ?></p>
                            <div class="text-xs text-gray-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                            <div class="text-xs text-emerald-800 mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                        </div>
                    </div>
                    <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="join_org">
                            <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                            <button data-tour="join-button" class="inline-flex items-center justify-center whitespace-nowrap min-w-[5rem] px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <span class="icon-label"><?= uiIcon($joinIcon, 'ui-icon ui-icon-sm') ?><span><?= $label ?></span></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <article class="md:hidden rounded-lg border border-emerald-200/35 bg-white/10 p-3">
                    <div class="flex items-start gap-3">
                        <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'md', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium break-words leading-5"><?= e($org['name']) ?></div>
                            <div class="mt-1 inline-flex max-w-full rounded-md border border-emerald-300/30 bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-800 break-words">
                                <?= e(getOrganizationVisibilityLabel($org)) ?>
                            </div>
                            <p class="mt-2 text-sm text-slate-600 break-words leading-6"><?= e($org['description']) ?></p>
                            <div class="mt-2 text-xs text-slate-500 break-words">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                        </div>
                    </div>
                    <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                        <form method="post" class="mt-3">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="join_org">
                            <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                            <button data-tour="join-button" class="inline-flex w-full items-center justify-center px-3 py-2 rounded-md text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <span class="icon-label"><?= uiIcon($joinIcon, 'ui-icon ui-icon-sm') ?><span><?= $label ?></span></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

function handleProfilePage(array $user): void
{
    global $db;
    $currentProgram = trim((string) ($user['program'] ?? ''));
    $currentInstitute = trim((string) ($user['institute'] ?? ''));
    $currentSection = trim((string) ($user['section'] ?? ''));

    // Get owned organization
    $ownedOrg = null;
    if ($user['role'] === 'owner' || $user['role'] === 'admin') {
        $stmt = $db->prepare('SELECT name, org_category, created_at, logo_path, logo_crop_x, logo_crop_y, logo_zoom FROM organizations WHERE owner_id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $ownedOrg = $stmt->fetch();
    }

    // Get joined organizations
    $stmt = $db->prepare('
        SELECT o.name, o.org_category, o.logo_path, o.logo_crop_x, o.logo_crop_y, o.logo_zoom, om.joined_at
        FROM organization_members om
        JOIN organizations o ON o.id = om.organization_id
        WHERE om.user_id = ?
        ORDER BY om.joined_at DESC
    ');
    $stmt->execute([(int) $user['id']]);
    $joinedOrgs = $stmt->fetchAll();
    
    renderHeader('My Profile');
    ?>
    <section class="glass p-6 max-w-4xl mx-auto profile-page">
        <?php
            $profilePictureCropX = (float) ($user['profile_picture_crop_x'] ?? 50);
            $profilePictureCropY = (float) ($user['profile_picture_crop_y'] ?? 50);
            $profilePictureZoom = (float) ($user['profile_picture_zoom'] ?? 1);
            $profilePicturePath = (string) ($user['profile_picture_path'] ?? '');
        ?>
        <div class="flex items-center gap-3 mb-1">
            <div class="relative shrink-0" data-profile-picture-actions>
                <button type="button"
                        class="inline-flex rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-400/40"
                        data-profile-picture-toggle
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="Open profile picture actions">
                    <span data-crop-preview><?= renderProfileMedia((string) ($user['name'] ?? ''), $profilePicturePath, 'user', 'lg', $profilePictureCropX, $profilePictureCropY, $profilePictureZoom) ?></span>
                </button>
                <div id="profilePictureActionsMenu" class="absolute left-0 top-full z-20 mt-2 hidden min-w-56 overflow-hidden rounded-2xl border border-emerald-200/60 bg-white/95 p-2 shadow-[0_22px_45px_rgba(15,23,42,0.18)] backdrop-blur-md" data-profile-picture-menu role="menu" aria-label="Profile picture actions">
                    <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-slate-900 transition-colors hover:bg-emerald-50 profile-picture-menu-action" data-profile-picture-view>
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 profile-picture-menu-icon">
                            <?= uiIcon('view', 'ui-icon ui-icon-sm') ?>
                        </span>
                        <span>
                            <span class="block font-medium">View profile</span>
                            <span class="block text-xs text-slate-600">Open a larger preview</span>
                        </span>
                    </button>
                    <button type="button" class="org-logo-upload-trigger profile-picture-menu-action flex w-full items-center gap-3 rounded-xl border border-dashed px-3 py-2 text-left text-sm transition-colors" data-profile-picture-edit>
                        <span class="org-logo-upload-trigger-icon profile-picture-menu-icon inline-flex h-8 w-8 items-center justify-center rounded-full">
                            <?= uiIcon('edit', 'ui-icon ui-icon-sm') ?>
                        </span>
                        <span>
                            <span class="block font-medium">Edit profile</span>
                            <span class="org-logo-upload-trigger-subtext block text-xs">Choose a new photo</span>
                        </span>
                    </button>
                </div>
            </div>
            <h1 class="text-2xl font-semibold icon-label"><?= uiIcon('user', 'ui-icon') ?><span>My Profile</span></h1>
        </div>
        <p class="text-sm text-slate-600 mb-4">Manage your account settings and preferences</p>
        
        <?php $flash = getFlash(); if ($flash && $flash['type'] === 'success'): ?>
            <div class="mb-3 p-4 bg-green-50 border border-green-200 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?= uiIcon('approved', 'ui-icon ui-icon-sm text-green-500') ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars((string) $flash['message']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($flash && $flash['type'] === 'error'): ?>
            <div class="mb-3 p-4 bg-red-50 border border-red-200 rounded">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?= uiIcon('rejected', 'ui-icon ui-icon-sm text-red-500') ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars((string) $flash['message']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <form action="?page=profile" method="POST" enctype="multipart/form-data" class="space-y-5" data-image-crop-form data-crop-auto-submit>
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="action" value="update_profile">
                <input type="file" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" data-image-input>
            <div class="hidden" aria-hidden="true">
                <input type="hidden" name="profile_picture_crop_x" value="<?= (float) $profilePictureCropX ?>" data-crop-x>
                <input type="hidden" name="profile_picture_crop_y" value="<?= (float) $profilePictureCropY ?>" data-crop-y>
                <input type="hidden" name="profile_picture_zoom" value="<?= (float) $profilePictureZoom ?>" data-crop-zoom>
            </div>
                
                <div class="space-y-4">
                    <div>

                    <style>
                        .profile-picture-preview-modal {
                            width: min(100%, 56rem);
                        }

                        .profile-picture-preview-frame {
                            width: min(86vw, 30rem);
                            height: min(86vw, 30rem);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            overflow: hidden;
                            border-radius: 1.5rem;
                            background: rgba(15, 23, 42, 0.04);
                            border: 1px solid rgba(148, 163, 184, 0.18);
                            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
                        }

                        .profile-picture-preview-image {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                        }

                        body.theme-dark #profilePictureActionsMenu {
                            background: rgba(2, 24, 18, 0.92);
                            border-color: rgba(110, 231, 183, 0.28);
                            box-shadow: 0 22px 45px rgba(0, 0, 0, 0.35);
                        }

                        body.theme-dark #profilePictureActionsMenu .profile-picture-menu-icon {
                            background: rgba(6, 78, 59, 0.92) !important;
                            color: #d1fae5 !important;
                        }

                        body.theme-dark .profile-picture-preview-frame {
                            background: rgba(2, 24, 18, 0.84);
                            border-color: rgba(110, 231, 183, 0.2);
                            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
                        }

                        body.theme-dark #profilePictureActionsMenu .profile-picture-menu-action {
                            color: #ecfdf5 !important;
                        }

                        body.theme-dark #profilePictureActionsMenu .profile-picture-menu-action:hover {
                            background: rgba(6, 78, 59, 0.58);
                        }

                        body.theme-dark #profilePictureActionsMenu .profile-picture-menu-action .text-slate-600,
                        body.theme-dark #profilePictureActionsMenu .profile-picture-menu-action .text-slate-500 {
                            color: #a7f3d0 !important;
                        }
                    </style>
                        <label for="name" class="block text-sm font-medium text-slate-700">Full Name</label>
                        <input type="text"
                               id="name"
                               value="<?php echo htmlspecialchars($user['name']); ?>"
                               readonly
                               class="w-full border rounded px-3 py-2 bg-slate-100 text-slate-700 cursor-not-allowed">
                        <p class="mt-1 text-xs text-slate-600">Full name cannot be changed.</p>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               required
                               class="w-full border rounded px-3 py-2">
                        <p class="mt-1 text-xs text-slate-600">Changing your email will require re-verification.</p>
                    </div>

                    <div id="profilePicturePreviewModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="profilePicturePreviewTitle" aria-hidden="true">
                        <div class="glass modal-panel profile-picture-preview-modal w-full max-w-4xl p-6 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                            <div class="modal-drag-handle" aria-hidden="true"></div>
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <h2 id="profilePicturePreviewTitle" class="text-lg font-semibold icon-label"><?= uiIcon('user', 'ui-icon') ?><span>Profile Picture</span></h2>
                                    <p class="text-sm text-slate-600">Preview your current profile picture.</p>
                                </div>
                                <button type="button" id="profilePicturePreviewClose" class="text-slate-600 hover:text-slate-900 text-xl leading-none" aria-label="Close preview">&times;</button>
                            </div>
                            <div class="flex justify-center">
                                <div class="profile-picture-preview-frame" data-profile-picture-preview-large>
                                    <?php if ($profilePicturePath !== ''): ?>
                                        <img src="<?= e($profilePicturePath) ?>" alt="<?= e((string) ($user['name'] ?? 'Profile picture')) ?>" class="profile-picture-preview-image">
                                    <?php else: ?>
                                        <?= renderProfilePlaceholder((string) ($user['name'] ?? ''), 'user', 'lg') ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm text-slate-800 border-t border-emerald-200/40 pt-4 profile-meta">
                        <div>
                            <span class="font-semibold">Year and Section:</span> <?= e($currentSection !== '' ? $currentSection : 'Not set') ?>
                        </div>
                        <div>
                            <span class="font-semibold">Program:</span> <?= e($currentProgram !== '' ? $currentProgram : 'Not set') ?>
                        </div>
                        <div>
                            <span class="font-semibold">Institute:</span> <?= e($currentInstitute !== '' ? $currentInstitute : 'Not set') ?>
                        </div>
                        <div><span class="font-semibold">Role:</span> <?= htmlspecialchars($user['role']) ?></div>
                        <div><span class="font-semibold">Account Status:</span> <?= htmlspecialchars(ucfirst((string) ($user['account_status'] ?? 'active'))) ?></div>
                        <div><span class="font-semibold">Email Verified:</span> <?= ((int) ($user['email_verified'] ?? 0) === 1) ? 'Verified' : 'Not Verified' ?></div>
                        <div><span class="font-semibold">Member Since:</span> <?php
                            $createdAt = $user['created_at'] ?? 'Unknown';
                            if ($createdAt !== 'Unknown') {
                                $date = new DateTime($createdAt);
                                echo e($date->format('F d, Y'));
                            } else {
                                echo 'Unknown';
                            }
                        ?></div>
                    </div>
                    
                    <!-- Organization Information -->
                    <div class="mt-6 pt-6 border-t border-emerald-200/40 profile-org-section">
                        <h3 class="text-sm font-semibold text-slate-900 mb-4 icon-label"><?= uiIcon('orgs', 'ui-icon ui-icon-sm') ?><span>Organization Information</span></h3>
                        
                        <?php if ($ownedOrg): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-slate-700 mb-2 profile-org-label">Owned Organization</label>
                                <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-200/50 profile-org-card profile-org-card-owned flex items-start gap-3">
                                    <?= renderProfileMedia((string) ($ownedOrg['name'] ?? ''), (string) ($ownedOrg['logo_path'] ?? ''), 'organization', 'sm', (float) ($ownedOrg['logo_crop_x'] ?? 50), (float) ($ownedOrg['logo_crop_y'] ?? 50), (float) ($ownedOrg['logo_zoom'] ?? 1)) ?>
                                    <div>
                                        <p class="text-sm font-semibold text-emerald-900">
                                            <?php echo htmlspecialchars($ownedOrg['name']); ?>
                                        </p>
                                        <p class="text-xs text-emerald-700 mt-1">
                                            <?php echo htmlspecialchars($ownedOrg['org_category']); ?>
                                            • Created <?php 
                                                $orgDate = new DateTime($ownedOrg['created_at']);
                                                echo e($orgDate->format('F d, Y'));
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 profile-org-label">Joined Organizations</label>
                            <?php if (count($joinedOrgs) > 0): ?>
                                <div class="space-y-2">
                                    <?php foreach ($joinedOrgs as $org): ?>
                                        <div class="p-3 rounded-xl bg-white/10 border border-emerald-100/25 profile-org-card profile-org-card-joined flex items-start gap-3">
                                            <?= renderProfileMedia((string) ($org['name'] ?? ''), (string) ($org['logo_path'] ?? ''), 'organization', 'sm', (float) ($org['logo_crop_x'] ?? 50), (float) ($org['logo_crop_y'] ?? 50), (float) ($org['logo_zoom'] ?? 1)) ?>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">
                                                    <?php echo htmlspecialchars($org['name']); ?>
                                                </p>
                                                <p class="text-xs text-slate-600 mt-1">
                                                    <?php echo htmlspecialchars($org['org_category']); ?>
                                                    • Joined <?php 
                                                        $joinDate = new DateTime($org['joined_at']);
                                                        echo e($joinDate->format('F d, Y'));
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-slate-600 italic">You haven't joined any organizations yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                            class="bg-emerald-600 text-white px-4 py-2 rounded font-semibold">
                        <span class="icon-label justify-center"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Update Profile</span></span>
                    </button>
                    <button type="button"
                            id="changePasswordBtn"
                            class="border border-emerald-200/50 text-emerald-800 px-4 py-2 rounded hover:bg-white/30">
                        <span class="icon-label justify-center"><?= uiIcon('security', 'ui-icon ui-icon-sm') ?><span>Change Password</span></span>
                    </button>
                </div>
            </form>
    </section>
        
    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="hidden fixed inset-0 bg-slate-900/50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto max-w-md">
            <div class="glass p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-slate-900 icon-label"><?= uiIcon('security', 'ui-icon') ?><span>Change Password</span></h3>
                    <button type="button" id="closeModalBtn" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
                </div>
                
                <form action="?page=profile" method="POST">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="space-y-3">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-slate-700">Current Password</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                data-password-toggle
                                   required
                                   class="w-full border rounded px-3 py-2">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-slate-700">New Password</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                data-password-toggle
                                   required
                                   minlength="8"
                                   class="w-full border rounded px-3 py-2">
                            <p class="mt-1 text-xs text-slate-600">At least 8 characters</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-slate-700">Confirm New Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                data-password-toggle
                                   required
                                   minlength="8"
                                   class="w-full border rounded px-3 py-2">
                        </div>
                    </div>
                    
                    <div class="mt-4 flex gap-2">
                        <button type="submit"
                                class="flex-1 bg-emerald-600 text-white px-3 py-2 rounded font-semibold">
                            <span class="icon-label justify-center"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Change Password</span></span>
                        </button>
                        <button type="button"
                                id="cancelModalBtn"
                                class="flex-1 border border-slate-300 text-slate-700 px-3 py-2 rounded">
                            <span class="icon-label justify-center"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Cancel</span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        
    <script>
        // Change Password Modal Handlers
        const modal = document.getElementById('changePasswordModal');
        const openBtn = document.getElementById('changePasswordBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const cancelBtn = document.getElementById('cancelModalBtn');
        
        openBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
        });
        
        closeBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        
        cancelBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });

        (function () {
            const profileActionsWrap = document.querySelector('[data-profile-picture-actions]');
            const profileToggle = document.querySelector('[data-profile-picture-toggle]');
            const profileMenu = document.querySelector('[data-profile-picture-menu]');
            const profileViewButton = document.querySelector('[data-profile-picture-view]');
            const profileEditButton = document.querySelector('[data-profile-picture-edit]');
            const profileFileInput = document.getElementById('profile_picture');
            const profilePreviewModal = document.getElementById('profilePicturePreviewModal');
            const profilePreviewClose = document.getElementById('profilePicturePreviewClose');

            if (!profileActionsWrap || !profileToggle || !profileMenu || !profileViewButton || !profileEditButton || !profileFileInput || !profilePreviewModal || !profilePreviewClose) {
                return;
            }

            function openMenu() {
                profileMenu.classList.remove('hidden');
                profileToggle.setAttribute('aria-expanded', 'true');
            }

            function closeMenu() {
                profileMenu.classList.add('hidden');
                profileToggle.setAttribute('aria-expanded', 'false');
            }

            function openPreview() {
                closeMenu();
                profilePreviewModal.classList.remove('hidden');
                profilePreviewModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closePreview() {
                profilePreviewModal.classList.add('hidden');
                profilePreviewModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            profileToggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (profileMenu.classList.contains('hidden')) {
                    openMenu();
                } else {
                    closeMenu();
                }
            });

            profileViewButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openPreview();
            });

            profileEditButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeMenu();
                profileFileInput.click();
            });

            profilePreviewClose.addEventListener('click', function () {
                closePreview();
            });

            profilePreviewModal.addEventListener('click', function (event) {
                if (event.target === profilePreviewModal) {
                    closePreview();
                }
            });

            document.addEventListener('click', function (event) {
                if (!profileActionsWrap.contains(event.target)) {
                    closeMenu();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeMenu();
                    if (!profilePreviewModal.classList.contains('hidden')) {
                        closePreview();
                    }
                }
            });
        })();

    </script>
    <?php
    renderFooter();
    exit;
}
