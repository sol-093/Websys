<?php

declare(strict_types=1);

function handleLogoutPage(): void
{
    $logoutUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($logoutUserId > 0) {
        auditLog($logoutUserId, 'auth.logout', 'user', $logoutUserId, 'User logged out');
    }
    session_destroy();
    session_start();
    setFlash('success', 'You are logged out.');
    redirect('?page=login');
}

function handleHomePage(PDO $db, ?array $user): void
{
    $orgCount = (int) $db->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
    $memberCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student','owner')")->fetchColumn();
    $announcementCount = (int) $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();

    renderHeader('Home');
    ?>
    <section class="grid lg:grid-cols-12 gap-4 lg:gap-5">
        <div class="glass lg:col-span-8 p-6 md:p-8">
            <div class="hero-kicker inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-300/20 text-xs font-semibold mb-4 border border-emerald-200/30">
                <?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?>
                Budget Transparency • Student Organizations
            </div>
            <h1 class="modern-title text-3xl md:text-6xl font-bold tracking-tight leading-tight">
                Modern Student Organization Management for Transparent Campus Finance
            </h1>
            <p class="mt-4 text-slate-600 max-w-2xl text-base md:text-lg">
                Manage organizations, publish announcements, and track income and expenses in one shared platform. Students can join groups and view verified reports for accountability.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <?php if ($user): ?>
                    <a href="?page=dashboard" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]"><span class="icon-label"><?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?><span>Open Dashboard</span></span></a>
                <?php else: ?>
                    <a href="?page=register" class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]"><span class="icon-label"><?= uiIcon('register', 'ui-icon ui-icon-sm') ?><span>Get Started</span></span></a>
                    <a href="?page=login" class="border border-emerald-200/50 text-emerald-800 px-5 py-2.5 rounded-lg hover:bg-white/30"><span class="icon-label"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-6 snapshot-panel">
            <h2 class="text-lg font-semibold mb-4 snapshot-title icon-label"><?= uiIcon('dashboard', 'ui-icon') ?><span>Platform Snapshot</span></h2>
            <div class="space-y-3 text-sm snapshot-list">
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('orgs', 'ui-icon ui-icon-sm') ?><span>Organizations</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $orgCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('students', 'ui-icon ui-icon-sm') ?><span>Students & Owners</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $memberCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('announce', 'ui-icon ui-icon-sm') ?><span>Announcements</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $announcementCount ?></span>
                </div>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('audit', 'ui-icon') ?><span>For Admin</span></h3>
            <p class="text-sm text-slate-600">Create organizations, assign one owner, and filter all student records.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('my-org', 'ui-icon') ?><span>For Owners</span></h3>
            <p class="text-sm text-slate-600">Update organization profile, post announcements, and maintain income/expense logs.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('students', 'ui-icon') ?><span>For Students</span></h3>
            <p class="text-sm text-slate-600">Join organizations and monitor complete budget reports with transparency.</p>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

function handleLoginPage(array $config): void
{
    renderHeader('Login');
    $googleLoginReady = googleOauthEnabled($config);
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('login', 'ui-icon') ?><span>Welcome back</span></h1>
        <p class="text-sm text-slate-600 mb-4">Sign in to continue to your organization dashboard.</p>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="login">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></button>
        </form>
        <?php if ($googleLoginReady): ?>
            <div class="my-3 text-center text-gray-500 text-sm">or</div>
            <a href="?page=google_login" class="block w-full border rounded px-4 py-2 text-center hover:bg-gray-50 font-medium">
                <span class="icon-label justify-center"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Continue with Google</span></span>
            </a>
        <?php else: ?>
            <p class="text-xs text-amber-700 mt-3">Google login is disabled. Add Google keys in src/core/config.php.</p>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

function handleRegisterPage(): void
{
    renderHeader('Register');
    $programInstituteMap = getProgramInstituteMap();
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-4 icon-label"><?= uiIcon('register', 'ui-icon') ?><span>Student Registration</span></h1>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="register">
            <input name="name" placeholder="Full Name" required class="w-full border rounded px-3 py-2">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <select name="program" required class="w-full border rounded px-3 py-2">
                <option value="">Select Program</option>
                <?php foreach ($programInstituteMap as $programName => $instituteName): ?>
                    <option value="<?= e($programName) ?>"><?= e($programName) ?> (<?= e($instituteName) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <div class="rounded border border-emerald-200/40 p-3 bg-white/20">
                <div class="flex items-start gap-2">
                    <input id="privacyConsent" name="privacy_consent" type="checkbox" value="1" required class="mt-1">
                    <label for="privacyConsent" class="text-sm text-slate-700">
                        I agree to the
                        <button type="button" id="openPrivacyModal" class="font-medium text-emerald-700 underline"><span class="icon-label"><?= uiIcon('audit', 'ui-icon ui-icon-sm') ?><span>Data Privacy Consent</span></span></button>.
                    </label>
                </div>
            </div>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('register', 'ui-icon ui-icon-sm') ?><span>Create Account</span></span></button>
        </form>
    </div>

    <div id="privacyModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle">
        <div class="glass w-full max-w-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 id="privacyModalTitle" class="text-lg font-semibold icon-label"><?= uiIcon('audit', 'ui-icon') ?><span>Data Privacy Consent</span></h2>
                <button type="button" id="closePrivacyModal" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
            </div>
            <div class="text-sm text-slate-700 space-y-2 max-h-[60vh] overflow-auto pr-1">
                <p>By creating an account, you agree that this system may collect and process your personal data, such as your name, email address, role, organization memberships, and activity records, for account management and transparency reporting.</p>
                <p>Your data is used only for legitimate school organization operations, including authentication, organization management, announcement publishing, and finance report visibility.</p>
                <p>Your information is stored securely and access is limited based on system roles (admin, owner, student). We do not intentionally share your personal data with unauthorized third parties.</p>
                <p>You may request correction of inaccurate profile data through the system administrator. By proceeding, you confirm that the information you submit is accurate and that you consent to this processing.</p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="declinePrivacy" class="px-3 py-2 rounded border border-slate-300 text-slate-700"><span class="icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Close</span></span></button>
                <button type="button" id="acceptPrivacy" class="px-3 py-2 rounded bg-emerald-600 text-white"><span class="icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>I Agree</span></span></button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('privacyModal');
            const openBtn = document.getElementById('openPrivacyModal');
            const closeBtn = document.getElementById('closePrivacyModal');
            const declineBtn = document.getElementById('declinePrivacy');
            const acceptBtn = document.getElementById('acceptPrivacy');
            const checkbox = document.getElementById('privacyConsent');

            if (!modal || !openBtn || !checkbox) return;

            function openModal() {
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            openBtn.addEventListener('click', openModal);
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (declineBtn) declineBtn.addEventListener('click', closeModal);
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function () {
                    checkbox.checked = true;
                    closeModal();
                });
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        })();
    </script>
    <?php
    renderFooter();
    exit;
}
