<?php

declare(strict_types=1);

function handleAnnouncementsPage(PDO $db, $user, string $announcementCutoff): void
{
    $stmt = $db->prepare('SELECT a.*, o.name AS organization_name
        FROM announcements a
        JOIN organizations o ON o.id = a.organization_id
        WHERE a.created_at >= ?
        ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC');
    $stmt->execute([$announcementCutoff]);
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
            <p class="text-sm text-gray-600">No announcements in the last 30 days.</p>
        <?php else: ?>
            <div class="relative">
                <?php foreach ($slides as $index => $slideItems): ?>
                    <div class="announcement-slide <?= $index === 0 ? '' : 'hidden' ?>" data-slide-index="<?= $index ?>">
                        <div class="grid md:grid-cols-3 gap-3">
                            <?php foreach ($slideItems as $item): ?>
                                <article class="border rounded p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <h2 class="font-semibold"><?= e($item['title']) ?></h2>
                                        <?php if ((int) ($item['is_pinned'] ?? 0) === 1): ?>
                                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-300/40 icon-label"><?= uiIcon('pin', 'ui-icon ui-icon-sm') ?><span>Important</span></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1"><?= e($item['organization_name']) ?> · <?= e($item['created_at']) ?></div>
                                    <p class="text-sm mt-2"><?= e($item['content']) ?></p>
                                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                                        <form method="post" class="mt-2">
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
    $allOrgs = applyOrganizationVisibilityForUser($allOrgs, $user);

    $membershipStmt = $db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
    $membershipStmt->execute([(int) $user['id']]);
    $joinedIds = array_map('intval', array_column($membershipStmt->fetchAll(), 'organization_id'));

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
                <div class="border rounded p-3 flex flex-col md:flex-row justify-between items-center md:items-start gap-2 text-center md:text-left">
                    <div>
                        <div class="font-medium"><?= e($org['name']) ?></div>
                        <p class="text-sm text-gray-600"><?= e($org['description']) ?></p>
                        <div class="text-xs text-gray-500 mt-1">Owner: <?= e($org['owner_name'] ?? 'Unassigned') ?></div>
                        <div class="text-xs text-emerald-800 mt-1"><?= e(getOrganizationVisibilityLabel($org)) ?></div>
                    </div>
                    <?php if (in_array($user['role'], ['student', 'owner'], true)): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="join_org">
                            <input type="hidden" name="org_id" value="<?= (int) $org['id'] ?>">
                            <?php
                                $orgId = (int) $org['id'];
                                $requestStatus = (string) ($joinRequestStatus[$orgId] ?? '');
                                $isJoined = in_array($orgId, $joinedIds, true);
                                $disabled = $isJoined || $requestStatus === 'pending';
                                $btnClass = $isJoined
                                    ? 'bg-white/10 border-emerald-200/30 text-slate-700'
                                    : ($requestStatus === 'pending'
                                        ? 'bg-amber-500/25 border-amber-300/50 text-amber-900'
                                        : 'bg-emerald-500/25 border-emerald-300/50 text-emerald-900 hover:bg-emerald-500/35');
                                $label = $isJoined ? 'Joined' : ($requestStatus === 'pending' ? 'Requested' : 'Request Join');
                                $joinIcon = $isJoined ? 'approved' : ($requestStatus === 'pending' ? 'pending' : 'requests');
                            ?>
                            <button class="px-3 py-1 rounded text-xs border backdrop-blur-md <?= $btnClass ?>" <?= $disabled ? 'disabled' : '' ?>>
                                <span class="icon-label"><?= uiIcon($joinIcon, 'ui-icon ui-icon-sm') ?><span><?= $label ?></span></span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}
