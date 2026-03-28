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
            <input name="password" type="password" placeholder="Password" required class="w-full border rounded px-3 py-2">
            <button class="bg-indigo-700 text-white px-4 py-2 rounded w-full"><span class="icon-label justify-center"><?= uiIcon('login', 'ui-icon ui-icon-sm') ?><span>Login</span></span></button>
        </form>
        <?php if ($showResend): ?>
            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded">
                <p class="text-sm text-amber-800 mb-2">Didn't receive the verification email?</p>
                <form method="post" class="space-y-2">
                    <input type="hidden" name="action" value="resend_verification">
                    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
                    <input name="email" type="email" placeholder="Your email" value="<?= e($pendingEmail) ?>" required class="w-full border rounded px-3 py-2 text-sm">
                    <button class="bg-emerald-600 text-white px-4 py-2 rounded w-full text-sm"><span class="icon-label justify-center"><?= uiIcon('refresh', 'ui-icon ui-icon-sm') ?><span>Resend Verification Email</span></span></button>
                </form>
            </div>
        <?php endif; ?>
        <div class="mt-4 text-center">
            <a href="?page=forgot_password" class="text-sm text-indigo-700 hover:underline">Forgot your password?</a>
        </div>
        <?php if ($googleLoginReady): ?>
            <div class="my-3 text-center text-gray-500 text-sm">or</div>
            <a href="?page=google_login" class="block w-full border rounded px-4 py-2 text-center hover:bg-gray-50 font-medium">
                <span class="icon-label justify-center"><?= uiIcon('open', 'ui-icon ui-icon-sm') ?><span>Continue with Google</span></span>
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
                        <button type="button" id="openPrivacyModal" class="font-medium text-emerald-700 underline">Data Privacy Consent</button>.
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
                <p>By selecting "I Agree" and creating an account, you expressly provide your informed and voluntary consent to the collection, use, storage, and other processing of your personal data in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173), its Implementing Rules and Regulations, and related issuances of the National Privacy Commission.</p>
                <p>The platform may collect and process personal information, including your full name, email address, academic program or institute, account role, organization memberships, submitted records, and system-generated activity logs. Such processing is undertaken for legitimate and specified purposes, including account registration and authentication, access control, platform security, organization administration, publication of authorized announcements, financial transparency reporting, and compliance with applicable institutional policies.</p>
                <p>Personal data is processed only by authorized personnel and administrators on a strict need-to-know basis. Appropriate organizational, physical, and technical safeguards are implemented to protect information against unauthorized access, disclosure, alteration, misuse, accidental loss, or destruction.</p>
                <p>Personal data is retained only for as long as necessary to fulfill the declared and lawful purposes, satisfy legal and regulatory obligations, resolve disputes, and enforce applicable terms and policies. Upon lapse of the applicable retention period, data shall be securely disposed of, anonymized, or archived, as permitted by law and institutional policy.</p>
                <p>As a data subject, you may exercise your rights to be informed, to access, to object, to rectify, to erasure or blocking, to data portability, and to lodge a complaint with the National Privacy Commission, subject to lawful limitations and due process requirements. You may also withdraw your consent at any time; however, such withdrawal may affect your continued access to certain platform features or services.</p>
                <p>For privacy-related inquiries, requests, or concerns, you may contact the system administrator or the institution's designated Data Protection Officer.</p>
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

            const params = new URLSearchParams(window.location.search);
            if (params.get('privacy') === '1') {
                openModal();
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
            <div class="mb-3 p-4 bg-green-50 border border-green-200 rounded">
                <h3 class="text-sm font-semibold text-green-800 mb-2 icon-label"><?= uiIcon('approved', 'ui-icon ui-icon-sm') ?><span>Email Verified Successfully!</span></h3>
                <p class="text-sm text-green-700 mb-3">Your email has been verified. You can now log in to your account.</p>
                <a href="?page=login" class="text-sm font-medium text-green-800 hover:underline">
                    Go to Login →
                </a>
            </div>
        <?php else: ?>
            <div class="mb-3 p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="text-sm font-semibold text-red-800 mb-2">Verification Failed</h3>
                <p class="text-sm text-red-700 mb-3"><?php echo htmlspecialchars($error); ?></p>
                <a href="?page=login&show_resend=1" class="text-sm font-medium text-red-800 hover:underline">
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
        <h1 class="text-2xl font-semibold mb-1">Reset your password</h1>
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
                <span class="icon-label justify-center"><?= uiIcon('requests', 'ui-icon ui-icon-sm') ?><span>Send Reset Link</span></span>
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

function handleResetPasswordPage(): void
{
    global $db;
    
    $token = $_GET['token'] ?? '';
    $tokenValid = false;
    
    if ($token) {
        // Verify token exists and is not expired
        $stmt = $db->prepare('SELECT id, email, reset_expires FROM users WHERE reset_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user && strtotime($user['reset_expires'] ?? '') >= time()) {
            $tokenValid = true;
        }
    }
    
    renderHeader('Reset Password');
    ?>
    <div class="max-w-md mx-auto glass p-6 mt-8">
        <h1 class="text-2xl font-semibold mb-1">Set new password</h1>
        <p class="text-sm text-slate-600 mb-4">Create a strong password for your account.</p>
        
        <?php if (!$tokenValid): ?>
            <div class="mb-3 p-4 bg-red-50 border border-red-200 rounded">
                <h3 class="text-sm font-semibold text-red-800 mb-2">Invalid or Expired Link</h3>
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
                    <input id="password" name="password" type="password" placeholder="At least 8 characters" required minlength="8" class="w-full border rounded px-3 py-2">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter password" required minlength="8" class="w-full border rounded px-3 py-2">
                </div>
                
                <button type="submit" class="bg-indigo-700 text-white px-4 py-2 rounded w-full">
                    Reset Password
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
