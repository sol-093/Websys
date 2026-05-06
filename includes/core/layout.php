<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - SHARED LAYOUT SHELL
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. renderHeader()
 * 2. Navigation and Global Search Markup
 * 3. Main Content Opening
 * 4. renderFooter()
 * 5. Footer, Toast Container, and Script Includes
 *
 * EDIT GUIDE:
 * - Edit nav/footer markup here.
 * - Edit global styles in assets/css/app.css.
 * - Edit global behavior in assets/js/theme-init.js and assets/js/app.js.
 * - Edit shared UI fragments in includes/shared/ui.php.
 * ================================================
 */

function renderHeader(string $title = 'Dashboard'): void
{
    $config = require __DIR__ . '/config.php';
    $user = currentUser();
    $flash = getFlash();
    $flashMessage = (string) ($flash['message'] ?? '');
    $flashType = (string) ($flash['type'] ?? 'info');
    $loginUpdates = $_SESSION['login_updates_popup'] ?? [];
    unset($_SESSION['login_updates_popup']);
    $currentPage = (string) ($_GET['page'] ?? ($user ? 'dashboard' : 'home'));
    $isHomeActive = $currentPage === 'home';
    $isDashboardActive = in_array($currentPage, ['dashboard', 'announcements'], true);
    $isMyOrgActive = in_array($currentPage, ['my_org', 'my_org_manage'], true);
    $isProfileActive = $currentPage === 'profile';
    $isNotificationsActive = $currentPage === 'notifications';
    $isLoginActive = in_array($currentPage, ['login', 'forgot_password', 'reset_password', 'verify_email', 'google_login', 'google_callback'], true);
    $isRegisterActive = $currentPage === 'register';
    $navAppName = (string) $config['app_name'];
    $faviconPath = 'uploads/assets/involvemoblight.png';
    $logoLight = 'uploads/assets/involvelogo dark.png';
    $logoDark = 'uploads/assets/involvelogo light.png';
    $showOnboarding = false;
    if ($user && ($user['role'] ?? '') === 'student' && (int) ($user['onboarding_done'] ?? 0) === 0) {
        $_SESSION['show_onboarding'] = true;
        $showOnboarding = true;
    } elseif (!empty($_SESSION['show_onboarding']) && ($user['role'] ?? '') === 'student') {
        $showOnboarding = true;
    }
    $displayName = '';
    if ($user) {
        $parts = preg_split('/\s+/', trim((string) ($user['name'] ?? '')));
        $firstName = (string) ($parts[0] ?? '');
        $lastName = (string) ($parts[count($parts) - 1] ?? '');
        $lastInitial = $lastName !== '' ? strtoupper(substr($lastName, 0, 1)) . '.' : '';
        $displayName = trim($firstName . ' ' . $lastInitial);
        if ($displayName === '') {
            $displayName = (string) ($user['name'] ?? '');
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> - <?= e($config['app_name']) ?></title>
        <link rel="icon" type="image/png" href="<?= e($faviconPath) ?>">
        <link rel="shortcut icon" type="image/png" href="<?= e($faviconPath) ?>">
        <link rel="apple-touch-icon" href="<?= e($faviconPath) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="assets/css/app.css?v=<?= e((string) @filemtime(__DIR__ . '/../../assets/css/app.css')) ?>">
    </head>
    <body class="min-h-screen <?= $user ? 'is-authenticated' : '' ?>" data-flash="<?= e($flashMessage) ?>" data-flash-type="<?= e($flashType) ?>" data-csrf-token="<?= e(csrfToken()) ?>" data-show-onboarding="<?= !empty($_SESSION['show_onboarding']) ? '1' : '0' ?>" data-onboarding-user-suffix="<?= e((string) (($user['id'] ?? '') !== '' ? (int) $user['id'] : 'guest')) ?>">
        <script src="assets/js/theme-init.js?v=<?= e((string) @filemtime(__DIR__ . '/../../assets/js/theme-init.js')) ?>"></script>
        <nav id="appNav" class="glass fixed top-0 inset-x-0 z-50 mx-1.5 sm:mx-2.5 mt-1.5 text-slate-800">
            <div class="max-w-7xl mx-auto px-3 py-2">
                <div class="flex items-center justify-between gap-2 min-w-0">
                    <a href="?page=home" class="nav-brand font-bold tracking-tight text-emerald-900 text-xl modern-title" aria-label="<?= e($navAppName) ?> home">
                        <span class="nav-logo" aria-hidden="true">
                            <img src="<?= e($logoLight) ?>" alt="" class="nav-logo-img nav-logo-light">
                            <img src="<?= e($logoDark) ?>" alt="" class="nav-logo-img nav-logo-dark">
                        </span>
                    </a>
                    <div class="nav-desktop hidden lg:flex gap-3 text-sm items-center">
                        <a href="?page=home" class="nav-link <?= $isHomeActive ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $isDashboardActive ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <a href="?page=organizations" class="nav-link nav-organizations-link <?= $currentPage === 'organizations' ? 'nav-link-active' : '' ?>">Organizations</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                                <a href="?page=admin_audit" class="nav-link <?= $currentPage === 'admin_audit' ? 'nav-link-active' : '' ?>">Audit Logs</a>
                            <?php endif; ?>
                            <?php if (in_array($user['role'], ['student', 'owner', 'admin'], true)): ?>
                                <a href="?page=my_org" class="nav-link <?= $isMyOrgActive ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <div class="nav-utility-controls">
                                <button type="button" id="globalSearchOpen" class="global-search-trigger" aria-label="Open global search" title="Search (Ctrl+K)">
                                    <?= icon('search') ?>
                                </button>
                                <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                                <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                                <div class="nav-profile-menu" data-nav-profile-menu>
                                    <button type="button" class="nav-profile-trigger <?= ($isProfileActive || $isNotificationsActive) ? 'is-active' : '' ?>" id="navProfileMenuToggle" aria-expanded="false" aria-haspopup="menu" aria-controls="navProfileMenu">
                                        <?= uiIcon('user', 'ui-icon ui-icon-sm') ?>
                                        <span class="nav-profile-label"><?= e($user['role'] !== 'admin' ? $displayName : 'Account') ?></span>
                                    </button>
                                    <div id="navProfileMenu" class="nav-profile-dropdown hidden" role="menu" aria-labelledby="navProfileMenuToggle">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <a href="?page=profile" class="nav-profile-item <?= $isProfileActive ? 'is-active' : '' ?>" role="menuitem">Profile</a>
                                        <?php endif; ?>
                                        <a href="?page=notifications" class="nav-profile-item <?= $isNotificationsActive ? 'is-active' : '' ?>" role="menuitem">Notifications</a>
                                        <a href="?page=logout" class="nav-profile-item nav-profile-item-danger" role="menuitem">Logout</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $isLoginActive ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-2.5 py-1 rounded hover:bg-emerald-700 shadow-sm <?= $isRegisterActive ? 'ring-2 ring-emerald-300/70' : '' ?>">Register</a>
                            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                            <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                        <?php endif; ?>
                    </div>
                    <div class="nav-mobile lg:hidden flex items-center gap-2 nav-mobile-controls">
                        <?php if ($user): ?>
                            <button type="button" id="globalSearchOpenMobile" class="global-search-trigger" aria-label="Open global search" title="Search (Ctrl+K)">
                                <?= icon('search') ?>
                            </button>
                        <?php endif; ?>
                        <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                        <button type="button" id="navMenuToggle" class="hamburger-btn" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileNavMenu">
                            <span>
                                <span class="hamburger-line"></span>
                                <span class="hamburger-line"></span>
                                <span class="hamburger-line"></span>
                            </span>
                        </button>
                    </div>
                </div>

                <div id="mobileNavMenu" class="mobile-nav-panel lg:hidden">
                    <div class="flex flex-col gap-3 text-sm">
                        <a href="?page=home" class="nav-link <?= $isHomeActive ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $isDashboardActive ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <a href="?page=organizations" class="nav-link nav-organizations-link <?= $currentPage === 'organizations' ? 'nav-link-active' : '' ?>">Organizations</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                                <a href="?page=admin_audit" class="nav-link <?= $currentPage === 'admin_audit' ? 'nav-link-active' : '' ?>">Audit Logs</a>
                            <?php endif; ?>
                            <?php if (in_array($user['role'], ['student', 'owner', 'admin'], true)): ?>
                                <a href="?page=my_org" class="nav-link <?= $isMyOrgActive ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <div class="pt-2 border-t border-emerald-200/20">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500 mb-2">Account</div>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <a href="?page=profile" class="nav-link <?= $isProfileActive ? 'nav-link-active' : '' ?>">Profile</a>
                                <?php endif; ?>
                                <a href="?page=notifications" class="nav-link <?= $isNotificationsActive ? 'nav-link-active' : '' ?>">Notifications</a>
                                <a href="?page=logout" class="bg-indigo-900 text-white px-3 py-2 rounded text-center hover:bg-indigo-950 mt-2 block">Logout</a>
                            </div>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $isLoginActive ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-3 py-2 rounded text-center hover:bg-emerald-700 shadow-sm <?= $isRegisterActive ? 'ring-2 ring-emerald-300/70' : '' ?>">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <?php if ($user): ?>
            <div id="globalSearchModal" class="updates-modal-overlay global-search-overlay hidden" data-modal-close role="dialog" aria-modal="true" aria-labelledby="globalSearchTitle" aria-hidden="true">
                <div class="glass modal-panel w-full max-w-2xl p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                    <div class="modal-drag-handle" aria-hidden="true"></div>
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 id="globalSearchTitle" class="text-lg font-semibold icon-label"><?= uiIcon('search', 'ui-icon') ?><span>Search the system</span></h2>
                            <p class="text-sm text-slate-600">Find users, organizations, and announcements from anywhere.</p>
                        </div>
                        <button type="button" id="globalSearchClose" data-modal-close-button class="text-slate-600 hover:text-slate-900 text-xl leading-none" aria-label="Close search">&times;</button>
                    </div>

                    <label class="sr-only" for="globalSearchInput">Search</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600"><?= uiIcon('search', 'ui-icon ui-icon-sm') ?></span>
                        <input id="globalSearchInput" type="search" inputmode="search" autocomplete="off" placeholder="Search people, orgs, announcements" class="w-full rounded-xl border border-emerald-200/60 bg-white/80 pl-10 pr-20 py-3 text-slate-800 shadow-sm focus:border-emerald-400 focus:ring-0">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 global-search-shortcut">Ctrl+K</span>
                    </div>

                    <div class="mt-4 max-h-[55vh] overflow-auto themed-scroll pr-1">
                        <div id="globalSearchStatus" class="text-sm text-slate-600">Type at least 2 characters to search.</div>
                        <div id="globalSearchResults" class="global-search-results mt-3" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
        <?php endif; ?>

        <main class="max-w-7xl mx-auto p-3 pt-32 sm:p-4 sm:pt-28 lg:p-6 lg:pt-28">

            <?php if ($user && is_array($loginUpdates) && count($loginUpdates) > 0): ?>
                <div id="loginUpdatesModal" class="updates-modal-overlay hidden" data-modal-close>
                    <div class="glass modal-panel w-full max-w-2xl p-5 max-h-[90dvh] overflow-y-auto" data-modal-panel>
                        <div class="modal-drag-handle" aria-hidden="true"></div>
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('update', 'ui-icon') ?><span>Request Updates</span></h2>
                                <p class="text-sm text-slate-600">Latest approval/rejection results related to your requests.</p>
                            </div>
                            <button type="button" id="closeLoginUpdatesModal" data-modal-close-button class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
                        </div>
                        <div class="space-y-2 max-h-[55vh] overflow-auto pr-1">
                            <?php foreach ($loginUpdates as $item): ?>
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
                                <div class="border border-emerald-200/30 rounded-lg p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-medium"><?= e((string) ($item['kind'] ?? 'Update')) ?></div>
                                        <span class="<?= e($statusClass) ?> icon-badge"><?= uiIcon($statusIcon, 'ui-icon ui-icon-sm') ?><?= e(ucfirst($status)) ?></span>
                                    </div>
                                    <div class="text-sm text-slate-600 mt-1"><?= e((string) ($item['message'] ?? '')) ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?= e((string) ($item['event_at'] ?? '')) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                            <a href="?page=notifications" class="border border-emerald-300/40 px-3 py-2 rounded text-sm hover:bg-emerald-50">Open Notification Center</a>
                            <button type="button" id="closeLoginUpdatesModalBtn" data-modal-close-button class="bg-indigo-900 text-white px-3 py-2 rounded hover:bg-indigo-950">Close</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
    <?php
}

function renderFooter(): void
{
    $footerUser = currentUser();
    ?>
        </main>
        <footer class="app-footer">
            <div class="footer-shell mx-auto w-full">
                <div class="footer-bottom-bar px-4 py-2 flex items-center justify-between flex-wrap">
                    <p class="text-xs app-footer-muted"><?php echo date('Y') > 2026 ? '&copy; 2026–' . date('Y') : '&copy; 2026'; ?> INVOLVE. All rights reserved.</p>
                    <div class="footer-bottom-actions">
                        <nav class="footer-social-links" aria-label="Footer social links">
                            <a href="https://x.com" target="_blank" rel="noopener noreferrer" class="app-footer-icon-link" aria-label="Visit X">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2h3.308l-7.227 8.26L22.82 22h-6.648l-5.208-6.802L4.99 22H1.68l7.73-8.835L1.26 2h6.816l4.708 6.231L18.244 2z"/></svg>
                            </a>
                            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="app-footer-icon-link" aria-label="Visit Facebook">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 8H16V4.8h-2.5c-2.76 0-4.5 1.8-4.5 4.6V12H6v3.2h3v6h3.4v-6h3.1l.5-3.2h-3.6V9.7c0-.99.28-1.7 1.1-1.7z"/></svg>
                            </a>
                            <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="app-footer-icon-link" aria-label="Visit Instagram">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4A5.8 5.8 0 0 1 16.2 22H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2zm0 1.9A3.9 3.9 0 0 0 3.9 7.8v8.4a3.9 3.9 0 0 0 3.9 3.9h8.4a3.9 3.9 0 0 0 3.9-3.9V7.8a3.9 3.9 0 0 0-3.9-3.9H7.8zm8.95 1.45a1.35 1.35 0 1 1 0 2.7 1.35 1.35 0 0 1 0-2.7zM12 7.1A4.9 4.9 0 1 1 7.1 12 4.9 4.9 0 0 1 12 7.1zm0 1.9A3 3 0 1 0 15 12a3 3 0 0 0-3-3z"/></svg>
                            </a>
                            <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="app-footer-icon-link" aria-label="Visit LinkedIn">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.4 8.6A1.9 1.9 0 1 1 5.4 4.8a1.9 1.9 0 0 1 0 3.8zM3.7 9.9h3.4V20H3.7V9.9zm5.4 0h3.3v1.4h.1c.5-.9 1.7-1.9 3.6-1.9 3.8 0 4.5 2.5 4.5 5.7V20h-3.4v-4.3c0-1-.02-2.4-1.5-2.4-1.5 0-1.8 1.1-1.8 2.3V20H9.1V9.9z"/></svg>
                            </a>
                        </nav>
                        <button id="backToTop" class="app-footer-link text-xs" onclick="window.scrollTo({top:0,behavior:'smooth'})">Back to top</button>
                    </div>
                </div>
            </div>
        </footer>

        <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

        <script src="assets/js/image-cropper.js?v=<?= e((string) @filemtime(__DIR__ . '/../../assets/js/image-cropper.js')) ?>"></script>
        <script src="assets/js/app.js?v=<?= e((string) @filemtime(__DIR__ . '/../../assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

require_once dirname(__DIR__) . '/shared/ui.php';
