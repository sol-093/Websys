<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - AUTH AND PUBLIC PAGES
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Logout
 * 2. Home and About
 * 3. Login and Register
 * 4. Verify Email
 * 5. Forgot and Reset Password
 *
 * EDIT GUIDE:
 * - Edit this file for public/auth page markup.
 * - Edit includes/features/auth/actions.php for POST behavior.
 * ================================================
 */

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
                    <a href="?page=about" class="inline-flex items-center border border-emerald-200/50 text-emerald-800 font-medium px-4 py-2 rounded-lg text-sm hover:bg-white/30 transition"><span class="icon-label"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>About</span></span></a>
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
                    <span class="snapshot-label icon-label"><?= uiIcon('students-owners', 'ui-icon ui-icon-sm') ?><span>Students & Owners</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $memberCount ?></span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/10 border border-emerald-100/25 snapshot-item">
                    <span class="snapshot-label icon-label"><?= uiIcon('announce', 'ui-icon ui-icon-sm') ?><span>Announcements</span></span>
                    <span class="font-semibold highlight-glow snapshot-value"><?= $announcementCount ?></span>
                </div>
            </div>
        </div>

        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('admin', 'ui-icon') ?><span>For Admin</span></h3>
            <p class="text-sm text-slate-600">Create organizations, assign one owner, and filter all student records.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('owner', 'ui-icon') ?><span>For Owners</span></h3>
            <p class="text-sm text-slate-600">Update organization profile, post announcements, and maintain income/expense logs.</p>
        </div>
        <div class="glass lg:col-span-4 p-5">
            <h3 class="font-semibold mb-2 icon-label"><?= uiIcon('students-owners', 'ui-icon') ?><span>For Students</span></h3>
            <p class="text-sm text-slate-600">Join organizations and monitor complete budget reports with transparency.</p>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

function handleAboutPage(?array $user): void
{
    renderHeader('About');
    $teamMembers = [
        [
            'name' => 'Mark Russel Cas',
            'role' => 'Project Lead/Full Stack Developer',
            'image' => 'uploads/assets/cas.png',
            'linkedin' => 'https://www.linkedin.com/in/markcas093/',
            'facebook' => 'https://www.facebook.com/mark.cas.334',
            'github' => 'https://github.com/sol-093',
        ],
        [
            'name' => 'Cj Cantor',
            'role' => 'System Analyst',
            'image' => 'uploads/assets/cj.jpg',
            'linkedin' => 'https://www.linkedin.com/in/cj-cantor-a57432405/',
            'facebook' => 'https://www.facebook.com/shiijeeeee.07?rdid=alujSvI93igammWn&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1E2e8RbXxF%2F#',
            'github' => '#',
        ],
        [
            'name' => 'John Joshua Catan',
            'role' => 'Backend Developer',
            'image' => 'uploads/assets/josh.png',
            'linkedin' => '#',
            'facebook' => 'https://www.facebook.com/joshuacatan26',
            'github' => '#',
        ],
        [
            'name' => 'Eunice Comandante',
            'role' => 'Frontend Developer',
            'image' => 'uploads/assets/eunice.png',
            'linkedin' => '#',
            'facebook' => 'https://www.facebook.com/euniiii.7?rdid=X1UKVVDZuVKIUvgv&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F1CgVMwE3CK%2F#',
            'github' => '#',
        ],
        [
            'name' => 'Jhon Mike Cariño',
            'role' => 'QA / Documentation',
            'image' => 'uploads/assets/jm.png',
            'linkedin' => 'https://www.linkedin.com/in/jhon-mike-cari%C3%B1o-9b1160406/',
            'facebook' => 'https://www.facebook.com/jaycarl.delacruz.5?rdid=SuDmC9eg0adz20W1&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F18eLUY53dF#',
            'github' => 'https://github.com/jhonmikecarino0-source',
        ],
    ];
    ?>
    <section class="max-w-5xl mx-auto space-y-5">
        <div class="glass p-6 md:p-8 about-hero">
            <div class="hero-kicker inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-300/20 text-xs font-semibold mb-4 border border-emerald-200/30">
                <?= uiIcon('dashboard', 'ui-icon ui-icon-sm') ?>
                About the Platform
            </div>
            <h1 class="about-logo" aria-label="INVOLVE Student Organization Management and Budget Transparency System">
                <img src="uploads/assets/involvelogo dark.png" alt="" class="about-logo-img nav-logo-light">
                <img src="uploads/assets/involvelogo light.png" alt="" class="about-logo-img nav-logo-dark">
            </h1>
            <p class="mt-5 text-slate-600 max-w-3xl text-base md:text-lg about-copy">
                A campus platform focused on responsible governance, transparent budgeting, and better collaboration between students and organization leaders.
            </p>
        </div>

        <div class="glass p-5 md:p-6">
            <h2 class="text-lg font-semibold mb-3 icon-label\"><?= uiIcon('open', 'ui-icon') ?><span>What INVOLVE Means</span></h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">I - Innovation</p>
                    <p class="about-meaning-copy mt-1 text-xs">Using technology to improve student organization management.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">N - Navigation</p>
                    <p class="about-meaning-copy mt-1 text-xs">Helping users easily access organizations, announcements, records, and reports.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">V - Visibility</p>
                    <p class="about-meaning-copy mt-1 text-xs">Making organization information and financial records easier to view and monitor.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">O - Organization</p>
                    <p class="about-meaning-copy mt-1 text-xs">Keeping members, requests, announcements, and transactions in one system.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">L - Leadership</p>
                    <p class="about-meaning-copy mt-1 text-xs">Supporting organization owners and admins in managing responsibilities.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3">
                    <p class="about-meaning-title text-sm font-semibold">V - Verification</p>
                    <p class="about-meaning-copy mt-1 text-xs">Protecting accounts and actions through verification, permissions, and secure processes.</p>
                </div>
                <div class="about-meaning-card rounded-lg p-3 sm:col-span-2 lg:col-span-3">
                    <p class="about-meaning-title text-sm font-semibold">E - Engagement</p>
                    <p class="about-meaning-copy mt-1 text-xs">Encouraging students to join organizations and stay updated with activities.</p>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <article class="glass p-5">
                <h2 class="font-semibold mb-2 icon-label\"><?= uiIcon('open', 'ui-icon') ?><span>Mission</span></h2>
                <p class="text-sm text-slate-700">
                    To provide a centralized and secure platform that helps student organizations manage members, announcements,
                    requests, and financial records with transparency, accountability, and ease of access.
                </p>
            </article>
            <article class="glass p-5">
                <h2 class="font-semibold mb-2 icon-label\"><?= uiIcon('dashboard', 'ui-icon') ?><span>Vision</span></h2>
                <p class="text-sm text-slate-700">
                    To become a trusted digital system for campus organization management where students, owners, and administrators
                    can collaborate, monitor activities, and promote responsible financial transparency.
                </p>
            </article>
        </div>

        <div class="glass p-5 md:p-6">
            <h2 class="text-lg font-semibold mb-3 icon-label\"><?= uiIcon('students-owners', 'ui-icon') ?><span>Core Values</span></h2>
            <ul class="space-y-2 text-sm text-slate-700 list-disc pl-5">
                <li><strong>Transparency:</strong> clear and accessible organization updates and financial reports.</li>
                <li><strong>Integrity:</strong> honest reporting, responsible approvals, and policy-aligned decisions.</li>
                <li><strong>Service:</strong> tools designed to support students, officers, and campus administrators.</li>
                <li><strong>Collaboration:</strong> shared visibility that encourages participation and trust.</li>
            </ul>
        </div>

        <div class="glass p-5 md:p-6">
            <h2 class="text-lg font-semibold mb-1 icon-label\"><?= uiIcon('owner', 'ui-icon') ?><span>The Team Behind This Project</span></h2>
            <p class="text-sm text-slate-600 mb-4">The Five minds with One Control. </p>
            <div class="flex flex-wrap justify-center gap-5">
                <?php foreach ($teamMembers as $index => $member): ?>
                    <?php
                    $cardThemes = [
                        'from-slate-200 to-slate-300 border-slate-300/50',
                        'from-emerald-100 to-emerald-200 border-emerald-200/70',
                        'from-sky-100 to-blue-200 border-sky-200/70',
                        'from-amber-100 to-orange-200 border-amber-200/70',
                        'from-rose-100 to-pink-200 border-rose-200/70',
                    ];
                    $themeClass = $cardThemes[$index % count($cardThemes)];
                    $imagePath = trim((string) ($member['image'] ?? ''));
                    $imagePath = str_replace('\\', '/', $imagePath);
                    $displayName = trim((string) ($member['name'] ?? ''));
                    $linkedinUrl = trim((string) ($member['linkedin'] ?? '#'));
                    $facebookUrl = trim((string) ($member['facebook'] ?? '#'));
                    $githubUrl = trim((string) ($member['github'] ?? '#'));
                    if ($displayName === '') {
                        $displayName = 'Name Placeholder';
                    }
                    ?>
                    <article class="relative w-full max-w-[16rem] h-[23rem] rounded-2xl overflow-hidden border <?= e($themeClass) ?> bg-gradient-to-b shadow-[0_18px_36px_rgba(15,23,42,0.15)]">
                        <?php if ($imagePath !== ''): ?>
                            <img src="<?= e($imagePath) ?>" alt="<?= e($displayName) ?>" class="absolute inset-0 w-full h-full object-cover object-top" loading="lazy">
                        <?php else: ?>
                            <div class="absolute inset-0 flex items-center justify-center px-4">
                                <div class="text-center">
                                    <div class="mx-auto mb-2 w-fit">
                                        <?= renderProfilePlaceholder($displayName, 'user', 'lg') ?>
                                    </div>
                                    <p class="text-xs text-slate-600 tracking-wide uppercase">Photo Placeholder</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-slate-900/40 to-transparent pointer-events-none"></div>

                        <div class="absolute left-3 right-3 bottom-3 rounded-xl px-3 py-2 shadow-[0_10px_20px_rgba(15,23,42,0.15)]" style="background-color:#ffffff !important;border:1px solid #cbd5e1 !important;opacity:1 !important;">
                            <h3 class="font-semibold text-sm leading-tight" style="color:#0f172a !important;opacity:1 !important;"><?= e($displayName) ?></h3>
                            <p class="text-[11px] font-medium mt-0.5" style="color:#0f766e !important;opacity:1 !important;"><?= e((string) $member['role']) ?></p>
                            <div class="mt-2 h-px bg-slate-200"></div>
                            <div class="mt-2 flex items-center gap-2">
                                <a href="<?= e($linkedinUrl !== '' ? $linkedinUrl : '#') ?>" class="inline-flex items-center justify-center w-6 h-6 rounded-md transition-colors" style="background-color:#ffffff !important;border:1px solid #cbd5e1 !important;color:#334155 !important;opacity:1 !important;" aria-label="LinkedIn" title="LinkedIn" target="_blank" rel="noopener noreferrer">
                                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
                                        <path d="M4.98 3.5C4.98 4.88 3.86 6 2.48 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8h4V23h-4V8zM8 8h3.83v2.05h.05c.53-1.01 1.84-2.08 3.79-2.08 4.05 0 4.8 2.66 4.8 6.12V23h-4v-7.89c0-1.88-.03-4.29-2.62-4.29-2.62 0-3.02 2.04-3.02 4.15V23H8V8z"/>
                                    </svg>
                                </a>
                                <a href="<?= e($facebookUrl !== '' ? $facebookUrl : '#') ?>" class="inline-flex items-center justify-center w-6 h-6 rounded-md transition-colors" style="background-color:#ffffff !important;border:1px solid #cbd5e1 !important;color:#334155 !important;opacity:1 !important;" aria-label="Facebook" title="Facebook" target="_blank" rel="noopener noreferrer">
                                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
                                        <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.88 3.78-3.88 1.1 0 2.25.2 2.25.2v2.46H15.2c-1.25 0-1.64.78-1.64 1.57V12h2.8l-.45 2.89h-2.35v6.99C18.34 21.12 22 16.99 22 12z"/>
                                    </svg>
                                </a>
                                <a href="<?= e($githubUrl !== '' ? $githubUrl : '#') ?>" class="inline-flex items-center justify-center w-6 h-6 rounded-md transition-colors" style="background-color:#ffffff !important;border:1px solid #cbd5e1 !important;color:#334155 !important;opacity:1 !important;" aria-label="GitHub" title="GitHub" target="_blank" rel="noopener noreferrer">
                                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">
                                        <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.1.79-.25.79-.56v-2.01c-3.2.7-3.88-1.36-3.88-1.36-.53-1.33-1.28-1.69-1.28-1.69-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.19 1.77 1.19 1.03 1.76 2.71 1.25 3.37.95.1-.75.4-1.25.73-1.53-2.55-.29-5.24-1.27-5.24-5.66 0-1.25.44-2.27 1.17-3.07-.12-.29-.51-1.46.11-3.05 0 0 .96-.31 3.14 1.17a10.8 10.8 0 0 1 5.72 0c2.18-1.48 3.14-1.17 3.14-1.17.62 1.59.23 2.76.11 3.05.73.8 1.17 1.82 1.17 3.07 0 4.4-2.69 5.36-5.25 5.65.41.35.78 1.04.78 2.1v3.11c0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5z"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
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
    $showResend = isset($_GET['show_resend']);
    $pendingEmail = (string) ($_SESSION['pending_verification_email'] ?? '');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('login', 'ui-icon') ?><span>Welcome back</span></h1>
        <p class="text-sm text-slate-600 mb-4">Sign in to continue to your organization dashboard.</p>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
            <input name="email" type="email" placeholder="Email" required class="w-full border rounded px-3 py-2">
            <input name="password" type="password" placeholder="Password" required data-password-toggle class="w-full border rounded px-3 py-2">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></button>
        </form>
        <?php if ($showResend): ?>
            <div class="auth-notice auth-notice-warning mt-4 p-3 rounded">
                <p class="text-sm mb-2">Didn't receive the verification email?</p>
                <form method="post" class="space-y-2">
                    <input type="hidden" name="action" value="resend_verification">
                    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                    <input name="email" type="email" placeholder="Your email" value="<?= e($pendingEmail) ?>" required class="w-full border rounded px-3 py-2 text-sm">
                    <button class="bg-emerald-600 text-white px-4 py-2 rounded w-full text-sm"><span class="icon-label justify-center"><?= uiIcon('mail', 'ui-icon ui-icon-sm') ?><span>Resend Verification Email</span></span></button>
                </form>
            </div>
        <?php endif; ?>
        <div class="mt-4 text-center">
            <a href="?page=forgot_password" class="text-sm text-indigo-700 hover:underline">Forgot your password?</a>
        </div>
        <?php if ($googleLoginReady): ?>
            <div class="my-3 text-center text-gray-500 text-sm">or</div>
            <a href="?page=google_login" class="block w-full border rounded px-4 py-2 text-center hover:bg-gray-50 font-medium">
                <span class="icon-label justify-center"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Continue with Google</span></span>
            </a>
        <?php else: ?>
            <p class="text-xs text-amber-700 mt-3"></p>
        <?php endif; ?>
    </div>
    <?php
    renderFooter();
    exit;
}

function handleRegisterPage(): void
{
    renderHeader('Register');
    $programOptions = getProgramOptions();
    $programInstituteMap = getProgramInstituteMap();
    ?>
    <div class="max-w-3xl mx-auto glass p-6 md:p-8 mt-8">
        <h1 class="text-2xl md:text-3xl font-semibold mb-1 icon-label\"><?= uiIcon('register', 'ui-icon') ?><span>Student Registration</span></h1>
        <p class="text-sm text-slate-600 mb-6">Fill out the form carefully for registration.</p>

        <form method="post" id="registerForm" class="space-y-5">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="name" id="registerFullName" value="">

            <div>
                <label class="block text-sm font-semibold mb-2">Student Name</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div>
                        <input id="registerFirstName" type="text" placeholder="First Name" required class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <input id="registerMiddleName" type="text" placeholder="Middle Name" class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <input id="registerLastName" type="text" placeholder="Last Name" required class="w-full border rounded px-3 py-2">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="registerProgram" class="block text-sm font-semibold mb-2">Program</label>
                    <div class="relative w-full" data-dropdown-root data-themed-picker>
                        <input id="registerProgram" name="program" type="hidden" data-dropdown-value value="">
                        <div class="relative w-full" data-dropdown-wrapper>
                            <button type="button" data-dropdown-toggle="registerProgramMenu" aria-expanded="false" class="w-full flex items-center justify-between gap-3 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400/25 transition-colors">
                                <span data-dropdown-label data-dropdown-placeholder="Please Select" class="truncate text-left">Please Select</span>
                            </button>
                            <div id="registerProgramMenu" data-dropdown-menu class="absolute left-0 top-full mt-2 hidden w-full overflow-hidden rounded border z-20 backdrop-blur-md">
                                <ul class="p-2 text-sm font-medium space-y-1 max-h-72 overflow-auto scrollbar-hidden">
                                    <li><button type="button" data-dropdown-option data-active="true" data-option-value="" data-option-label="Please Select" class="block w-full rounded px-3 py-2 text-left transition-colors">Please Select</button></li>
                                    <?php foreach ($programOptions as $programName): ?>
                                        <li><button type="button" data-dropdown-option data-active="false" data-option-value="<?= e($programName) ?>" data-option-label="<?= e($programName) ?>" class="block w-full rounded px-3 py-2 text-left transition-colors"><?= e($programName) ?></button></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p id="registerInstitutePreview" class="mt-1 text-xs text-slate-600">Institute will be assigned from the selected program.</p>
                </div>

                <div>
                    <label for="registerEmail" class="block text-sm font-semibold mb-2">Student E-mail</label>
                    <input id="registerEmail" name="email" type="email" placeholder="ex: myname@example.com" required class="w-full border rounded px-3 py-2">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="registerSection" class="block text-sm font-semibold mb-2">Program-Year and Section</label>
                    <input id="registerSection" name="section" type="text" placeholder="e.g. BSCS - 102" required class="w-full border rounded px-3 py-2">
                    <p class="mt-1 text-xs text-slate-650">Use the program abbreviation, then the year and section.</p>
                </div>
            </div>

            <div>
                <label for="registerPassword" class="block text-sm font-semibold mb-2">Password</label>
                <input id="registerPassword" name="password" type="password" placeholder="Create a secure password" required data-password-toggle class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label for="registerConfirmPassword" class="block text-sm font-semibold mb-2">Confirm Password</label>
                <input id="registerConfirmPassword" name="confirm_password" type="password" placeholder="Re-enter your password" required minlength="8" data-password-toggle class="w-full border rounded px-3 py-2">
            </div>

            <div class="register-consent-panel rounded border p-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <input id="privacyConsent" name="privacy_consent" type="checkbox" value="1" required class="register-consent-checkbox h-4 w-4 rounded cursor-pointer shrink-0">
                    <label for="privacyConsent" class="register-consent-copy text-sm md:text-base cursor-pointer">I agree with the</label>
                    <button type="button" id="openPrivacyModal" class="register-consent-link text-sm md:text-base font-semibold underline underline-offset-2">terms and conditions</button>
                    <span class="register-consent-copy text-sm md:text-base">.</span>
                </div>
            </div>

            <div class="pt-1 text-center">
                <button class="bg-indigo-700 text-white px-6 py-2.5 rounded min-w-[170px]"><span class="icon-label justify-center\"><?= uiIcon('register', 'ui-icon ui-icon-sm') ?><span>Create Account</span></span></button>
            </div>
        </form>
    </div>

    <div id="privacyModal" class="updates-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle">
        <div class="glass w-full max-w-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 id="privacyModalTitle" class="text-lg font-semibold">Terms and Conditions</h2>
                <button type="button" id="closePrivacyModal" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
            </div>
            <div class="text-sm text-slate-700 space-y-2 max-h-[60vh] overflow-auto pr-1">
                <p>By selecting "I Agree" and creating an account, you confirm that you have read, understood, and accepted these Terms and Conditions for the Student Organization Management and Budget Transparency System.</p>
                <p>This platform is provided to support student organization administration, governance workflows, and financial transparency. You agree to use the platform only for lawful, authorized, and school-related purposes and in compliance with institutional policies and applicable laws.</p>
                <p>You are responsible for providing accurate and updated registration information and for maintaining the confidentiality of your account credentials. You must not share your account with others or attempt to access data, features, or records beyond your assigned role permissions.</p>
                <p>The system records account activities and workflow actions for security, audit, and compliance purposes. Unauthorized access, data tampering, misuse of workflows, publication of false or misleading information, or any attempt to disrupt platform operations may result in account suspension, revocation of access, and referral for disciplinary or legal action.</p>
                <p>The platform processes personal data in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173), its implementing rules, and relevant National Privacy Commission issuances. Data is collected and used only for legitimate operational purposes, protected with reasonable safeguards, and retained according to legal and institutional requirements.</p>
                <p>The institution may update these Terms and Conditions to reflect legal, policy, or operational changes. Continued use of the platform after updates are published constitutes acceptance of the revised terms. If you do not agree, you must discontinue use of the platform and contact the system administrator for account assistance.</p>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="declinePrivacy" class="px-3 py-2 rounded border border-slate-300 text-slate-700">Close</button>
                <button type="button" id="acceptPrivacy" class="px-3 py-2 rounded bg-emerald-600 text-white">I Agree to the Terms</button>
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
            const registerForm = document.getElementById('registerForm');
            const fullNameInput = document.getElementById('registerFullName');
            const firstNameInput = document.getElementById('registerFirstName');
            const middleNameInput = document.getElementById('registerMiddleName');
            const lastNameInput = document.getElementById('registerLastName');
            const programInput = document.getElementById('registerProgram');
            const institutePreview = document.getElementById('registerInstitutePreview');
            const programInstituteMap = <?= json_encode($programInstituteMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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

            const params = new URLSearchParams(window.location.search);
            if (params.get('privacy') === '1') {
                openModal();
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            if (registerForm && fullNameInput && firstNameInput && lastNameInput) {
                registerForm.addEventListener('submit', function (event) {
                    const first = firstNameInput.value.trim();
                    const middle = middleNameInput ? middleNameInput.value.trim() : '';
                    const last = lastNameInput.value.trim();

                    if (first === '' || last === '') {
                        event.preventDefault();
                        return;
                    }

                    fullNameInput.value = [first, middle, last].filter(Boolean).join(' ');
                });
            }

            if (programInput && institutePreview) {
                const updateInstitutePreview = function () {
                    const selectedProgram = programInput.value;
                    const instituteName = programInstituteMap[selectedProgram];
                    institutePreview.textContent = instituteName
                        ? 'Institute: ' + instituteName
                        : 'Institute will be assigned from the selected program.';
                };

                programInput.addEventListener('change', updateInstitutePreview);
                updateInstitutePreview();
            }
        })();
    </script>
    <script src="assets/js/owner-org-switcher.js"></script>
    <script src="assets/js/register-form.js"></script>
    <?php
    renderFooter();
    exit;
}

function handleVerifyEmailPage(): void
{
    global $db;
    
    $token = $_GET['token'] ?? '';
    $success = false;
    $error = '';
    
    if ($token) {
        $result = handleVerifyEmailAction($db, $token);
        $success = $result['success'];
        $error = $result['error'] ?? '';
    } else {
        $error = 'No verification token provided.';
    }

    renderHeader('Email Verification');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('verify', 'ui-icon') ?><span>Email Verification</span></h1>
        <p class="text-sm text-slate-600 mb-4">Verifying your email address...</p>
        
        <?php if ($success): ?>
            <div class="auth-notice auth-notice-success mb-3 p-4 rounded">
                <h3 class="text-sm font-semibold mb-2 icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Email Verified Successfully!</span></h3>
                <p class="text-sm mb-3">Your email has been verified. You can now log in to your account.</p>
                <a href="?page=login" class="text-sm font-medium hover:underline">
                    Go to Login →
                </a>
            </div>
        <?php else: ?>
            <div class="auth-notice auth-notice-error mb-3 p-4 rounded">
                <h3 class="text-sm font-semibold mb-2 icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Verification Failed</span></h3>
                <p class="text-sm mb-3"><?php echo htmlspecialchars($error); ?></p>
                <a href="?page=login&show_resend=1" class="text-sm font-medium hover:underline">
                    Request New Verification Link →
                </a>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 text-center">
            <a href="?page=login" class="text-sm text-indigo-700 hover:underline">Back to Login</a>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

function handleForgotPasswordPage(): void
{
    renderHeader('Forgot Password');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('mail', 'ui-icon') ?><span>Reset your password</span></h1>
        <p class="text-sm text-slate-600 mb-4">Enter your email address and we'll send you a link to reset your password.</p>
        
        <?php $errorFlash = getFlash(); if ($errorFlash && $errorFlash['type'] === 'error'): ?>
            <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                <?php echo htmlspecialchars((string) $errorFlash['message']); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="forgot_password">
            <input id="email" name="email" type="email" placeholder="Email address" required class="w-full border rounded px-3 py-2">
            <button type="submit" class="bg-indigo-700 text-white px-4 py-2 rounded w-full">
                <span class="icon-label justify-center"><?= uiIcon('mail', 'ui-icon ui-icon-sm') ?><span>Send Reset Link</span></span>
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="?page=login" class="text-sm text-indigo-700 hover:underline">Back to Login</a>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}

function handleResetPasswordPage(PDO $db): void
{
    $token = $_GET['token'] ?? '';
    $tokenValid = false;
    
    if ($token) {
        $tokenHash = hash('sha256', $token);
        // Verify token exists and is not expired
        $stmt = $db->prepare('SELECT id, email, reset_expires, reset_token FROM users WHERE reset_token = ? LIMIT 1');
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();
        
        $storedToken = (string) ($user['reset_token'] ?? '');
        if ($user && $storedToken !== '' && hash_equals($tokenHash, $storedToken) && strtotime($user['reset_expires'] ?? '') >= time()) {
            $tokenValid = true;
        }
    }
    
    renderHeader('Reset Password');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1 icon-label"><?= uiIcon('security', 'ui-icon') ?><span>Set new password</span></h1>
        <p class="text-sm text-slate-600 mb-4">Create a strong password for your account.</p>
        
        <?php if (!$tokenValid): ?>
            <div class="mb-3 p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="text-sm font-semibold text-red-800 mb-2 icon-label"><?= uiIcon('rejected', 'ui-icon ui-icon-sm') ?><span>Invalid or Expired Link</span></h3>
                <p class="text-sm text-red-700 mb-3">This password reset link is invalid or has expired. Reset links are valid for 1 hour.</p>
                <a href="?page=forgot_password" class="text-sm font-medium text-red-800 hover:underline">
                    Request New Reset Link →
                </a>
            </div>
        <?php else: ?>
            <?php $errorFlash = getFlash(); if ($errorFlash && $errorFlash['type'] === 'error'): ?>
                <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                    <?php echo htmlspecialchars((string) $errorFlash['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                    <input id="password" name="password" type="password" placeholder="At least 8 characters" required minlength="8" data-password-toggle class="w-full border rounded px-3 py-2">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter password" required minlength="8" data-password-toggle class="w-full border rounded px-3 py-2">
                </div>
                
                <button type="submit" class="bg-indigo-700 text-white px-4 py-2 rounded w-full">
                    <span class="icon-label justify-center"><?= uiIcon('security', 'ui-icon ui-icon-sm') ?><span>Reset Password</span></span>
                </button>
            </form>
        <?php endif; ?>
        
        <div class="mt-4 text-center">
            <a href="?page=login" class="text-sm text-indigo-700 hover:underline">Back to Login</a>
        </div>
    </div>
    <?php
    renderFooter();
    exit;
}
