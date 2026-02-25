<?php

declare(strict_types=1);

function renderHeader(string $title = 'Dashboard'): void
{
    $config = require __DIR__ . '/config.php';
    $user = currentUser();
    $flash = getFlash();
    $currentPage = (string) ($_GET['page'] ?? ($user ? 'dashboard' : 'home'));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> - <?= e($config['app_name']) ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            :root {
                --green-500: #34d399;
                --green-700: #10b981;
                --green-800: #0f766e;
                --line: rgba(16, 185, 129, 0.28);
            }

            body {
                background:
                    radial-gradient(1000px 500px at 0% 0%, rgba(16, 185, 129, 0.18), transparent 60%),
                    radial-gradient(1000px 560px at 100% 0%, rgba(52, 211, 153, 0.16), transparent 62%),
                    linear-gradient(180deg, #f7fffb 0%, #ecfdf5 100%);
                color: #0f172a;
            }

            body.theme-dark {
                background:
                    radial-gradient(900px 500px at 12% 8%, rgba(22, 163, 74, 0.24), transparent 60%),
                    radial-gradient(1000px 560px at 82% 0%, rgba(45, 212, 191, 0.22), transparent 62%),
                    linear-gradient(135deg, #011b16 0%, #022a21 46%, #01392c 100%);
                color: #f0fdf4;
            }

            .glass {
                background: rgba(255, 255, 255, 0.62);
                border: 1px solid var(--line);
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 1rem;
            }

            body.theme-dark .glass {
                background: rgba(255, 255, 255, 0.08);
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                color: #ecfdf5;
            }

            .bg-white.shadow.rounded,
            .bg-white.shadow.rounded.p-4,
            .bg-white.shadow.rounded.p-6 {
                background: rgba(255, 255, 255, 0.62) !important;
                border: 1px solid var(--line) !important;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08) !important;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 1rem !important;
            }

            body.theme-dark .bg-white.shadow.rounded,
            body.theme-dark .bg-white.shadow.rounded.p-4,
            body.theme-dark .bg-white.shadow.rounded.p-6 {
                background: rgba(255, 255, 255, 0.08) !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
            }

            .bg-indigo-700 { background-color: var(--green-700) !important; }
            .bg-indigo-900,
            .bg-indigo-950 { background-color: var(--green-800) !important; }
            .text-indigo-700,
            .text-indigo-100 { color: #7ef5c4 !important; }
            a.underline { text-decoration-color: rgba(4, 120, 87, 0.5); }

            .modern-title {
                color: #064e3b;
                letter-spacing: -0.02em;
                text-shadow: 0 0 14px rgba(16, 185, 129, 0.22);
            }

            body.theme-dark .modern-title {
                color: #f3fff8;
                text-shadow: 0 0 24px rgba(184, 243, 74, 0.28);
            }

            .highlight-glow {
                color: var(--green-500);
                text-shadow: 0 0 14px rgba(16, 185, 129, 0.3);
            }

            .nav-link {
                color: #065f46;
                transition: all 0.2s ease;
                position: relative;
            }

            body.theme-dark .nav-link {
                color: #d5fbe9;
            }

            .nav-link:hover {
                color: #064e3b;
            }

            body.theme-dark .nav-link:hover {
                color: #f4fff9;
            }

            .nav-link-active {
                color: #059669;
                text-shadow: 0 0 12px rgba(16, 185, 129, 0.28);
            }

            body.theme-dark .nav-link-active {
                color: var(--green-500);
                text-shadow: 0 0 16px rgba(184, 243, 74, 0.4);
            }

            .nav-link-active::after {
                content: '';
                position: absolute;
                left: 0;
                right: 0;
                bottom: -6px;
                height: 2px;
                border-radius: 999px;
                background: linear-gradient(90deg, transparent, var(--green-500), transparent);
            }

            .nav-greeting {
                color: #065f46;
                font-weight: 500;
            }

            body.theme-dark .nav-greeting {
                color: #d1fae5;
            }

            .hero-kicker {
                color: #065f46;
            }

            body.theme-dark .hero-kicker {
                color: #d1fae5;
            }

            body:not(.theme-dark) .snapshot-title {
                color: #0f172a;
            }

            body:not(.theme-dark) .snapshot-list {
                color: #1f2937;
            }

            body:not(.theme-dark) .snapshot-item {
                background: rgba(255, 255, 255, 0.55) !important;
                border-color: rgba(110, 231, 183, 0.55) !important;
            }

            body:not(.theme-dark) .snapshot-label {
                color: #1f2937;
                font-weight: 500;
            }

            body:not(.theme-dark) .snapshot-value {
                color: #059669 !important;
                text-shadow: none;
            }

            .text-slate-800,
            .text-slate-600,
            .text-gray-600,
            .text-gray-500 {
                color: #334155 !important;
            }

            body.theme-dark .text-slate-800,
            body.theme-dark .text-slate-700,
            body.theme-dark .text-slate-600,
            body.theme-dark .text-slate-500,
            body.theme-dark .text-gray-600,
            body.theme-dark .text-gray-700,
            body.theme-dark .text-gray-500 {
                color: #ecfdf5 !important;
            }

            body.theme-dark .border-slate-300 {
                border-color: rgba(167, 243, 208, 0.45) !important;
            }

            body.theme-dark .text-green-700,
            body.theme-dark .text-green-800,
            body.theme-dark .text-emerald-700,
            body.theme-dark .text-emerald-800,
            body.theme-dark .text-emerald-900 {
                color: #bbf7d0 !important;
            }

            body.theme-dark .text-red-700,
            body.theme-dark .text-red-800 {
                color: #fecaca !important;
            }

            body.theme-dark .text-amber-700 {
                color: #fef08a !important;
            }

            body.theme-dark .text-slate-900 {
                color: #f0fdf4 !important;
            }

            input, textarea, select {
                background: rgba(255, 255, 255, 0.72) !important;
                border-color: rgba(16, 185, 129, 0.32) !important;
                color: #064e3b !important;
            }

            body.theme-dark input,
            body.theme-dark textarea,
            body.theme-dark select {
                background: rgba(0, 0, 0, 0.2) !important;
                border-color: rgba(110, 231, 183, 0.35) !important;
                color: #f1fff7 !important;
            }

            select {
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 0.6rem;
                appearance: none;
                -webkit-appearance: none;
                padding-right: 2rem !important;
                background-image: linear-gradient(45deg, transparent 50%, #059669 50%), linear-gradient(135deg, #059669 50%, transparent 50%);
                background-position: calc(100% - 16px) calc(50% - 2px), calc(100% - 10px) calc(50% - 2px);
                background-size: 6px 6px, 6px 6px;
                background-repeat: no-repeat;
            }

            select option {
                background-color: #eafff4;
                color: #14532d;
            }

            select option:checked,
            select option:hover {
                background: linear-gradient(135deg, #6ee7b7, #34d399);
                color: #064e3b;
            }

            body.theme-dark select option {
                background-color: #08372d;
                color: #eafff4;
            }

            body.theme-dark select option:checked,
            body.theme-dark select option:hover {
                background: linear-gradient(135deg, #0f766e, #14b8a6);
                color: #ffffff;
            }

            body:not(.theme-dark) input::placeholder,
            body:not(.theme-dark) textarea::placeholder,
            body:not(.theme-dark) select::placeholder {
                color: rgba(6, 78, 59, 0.72) !important;
                opacity: 1 !important;
            }

            body:not(.theme-dark) input::-webkit-input-placeholder,
            body:not(.theme-dark) textarea::-webkit-input-placeholder {
                color: rgba(6, 78, 59, 0.72) !important;
                opacity: 1 !important;
            }

            body:not(.theme-dark) input::-moz-placeholder,
            body:not(.theme-dark) textarea::-moz-placeholder {
                color: rgba(6, 78, 59, 0.72) !important;
                opacity: 1 !important;
            }

            .placeholder\:gray-400::placeholder,
            .placeholder\:gray-500::placeholder,
            .placeholder\:slate-400::placeholder,
            .placeholder\:slate-500::placeholder {
                color: rgba(6, 78, 59, 0.72) !important;
                opacity: 1 !important;
            }

            body.theme-dark input::placeholder,
            body.theme-dark textarea::placeholder {
                color: rgba(209, 250, 229, 0.65) !important;
            }

            body.theme-dark #themeToggle {
                border-color: rgba(167, 243, 208, 0.45) !important;
                background: rgba(255, 255, 255, 0.08);
            }

            .theme-switch {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 32px;
                border-radius: 999px;
                border: 1px solid rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.2);
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .theme-switch::after {
                content: '';
                position: absolute;
                top: 3px;
                width: 10px;
                height: 10px;
                border-radius: 999px;
                background: #ffffff;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
                transition: all 0.2s ease;
            }

            #themeToggle {
                display: none;
            }

            #themeToggle:checked + .theme-switch {
                background: rgba(16, 185, 129, 0.75);
                border-color: rgba(16, 185, 129, 0.9);
            }

            #themeToggle:checked + .theme-switch::after {
                top: 19px;
            }

            body.theme-dark .theme-switch {
                border-color: rgba(167, 243, 208, 0.5);
                background: rgba(255, 255, 255, 0.14);
            }

            body.theme-dark .theme-switch::after {
                top: 19px;
            }

            .hamburger-btn {
                border: 1px solid rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.14);
                width: 2.3rem;
                height: 2.3rem;
                border-radius: 0.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .hamburger-line {
                display: block;
                width: 16px;
                height: 2px;
                border-radius: 999px;
                background: #065f46;
                transition: all 0.2s ease;
            }

            .hamburger-line + .hamburger-line {
                margin-top: 3px;
            }

            body.theme-dark .hamburger-line {
                background: #d1fae5;
            }

            .mobile-nav-panel {
                border-top: 1px solid rgba(16, 185, 129, 0.2);
                margin-top: 0.75rem;
                padding-top: 0.75rem;
            }
        </style>
    </head>
    <body class="min-h-screen">
        <script>
            (function () {
                const saved = localStorage.getItem('websys-theme');
                if (saved === 'dark') {
                    document.body.classList.add('theme-dark');
                }
            })();
        </script>
        <nav class="glass sticky top-3 z-20 mx-3 mt-3 text-slate-800">
            <div class="max-w-7xl mx-auto px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <a href="?page=home" class="font-bold tracking-tight text-emerald-900 text-xl modern-title\"><?= e($config['app_name']) ?></a>
                    <div class="hidden md:flex gap-4 text-sm items-center">
                        <a href="?page=home" class="nav-link <?= $currentPage === 'home' ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                                <a href="?page=my_org" class="nav-link <?= $currentPage === 'my_org' ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <span class="nav-greeting">Hi, <?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
                            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                            <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                            <a href="?page=logout" class="bg-indigo-900 text-white px-3 py-1 rounded hover:bg-indigo-950">Logout</a>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $currentPage === 'login' ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-3 py-1 rounded hover:bg-emerald-700 shadow-sm">Register</a>
                            <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                            <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                        <?php endif; ?>
                    </div>
                    <div class="md:hidden flex items-center gap-2">
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

                <div id="mobileNavMenu" class="mobile-nav-panel hidden md:hidden">
                    <div class="flex flex-col gap-3 text-sm">
                        <a href="?page=home" class="nav-link <?= $currentPage === 'home' ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                                <a href="?page=my_org" class="nav-link <?= $currentPage === 'my_org' ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <span class="nav-greeting">Hi, <?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
                            <a href="?page=logout" class="bg-indigo-900 text-white px-3 py-2 rounded text-center hover:bg-indigo-950">Logout</a>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $currentPage === 'login' ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-3 py-2 rounded text-center hover:bg-emerald-700 shadow-sm">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto p-4 md:p-6">
            <?php if ($flash): ?>
                <div class="glass mb-4 rounded px-4 py-3 text-white <?= $flash['type'] === 'error' ? 'border-red-300/60 bg-red-500/20' : 'border-emerald-300/60 bg-emerald-500/20' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>
    <?php
}

function renderFooter(): void
{
    ?>
        <script>
            (function () {
                const root = document.body;
                const key = 'websys-theme';

                const btn = document.getElementById('themeToggle');
                if (!btn) return;
                btn.checked = root.classList.contains('theme-dark');
                btn.addEventListener('change', function () {
                    root.classList.toggle('theme-dark', btn.checked);
                    localStorage.setItem(key, btn.checked ? 'dark' : 'light');
                });

                const navToggle = document.getElementById('navMenuToggle');
                const mobileNavMenu = document.getElementById('mobileNavMenu');
                if (navToggle && mobileNavMenu) {
                    navToggle.addEventListener('click', function () {
                        const isOpen = !mobileNavMenu.classList.contains('hidden');
                        mobileNavMenu.classList.toggle('hidden', isOpen);
                        navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    });

                    window.addEventListener('resize', function () {
                        if (window.innerWidth >= 768) {
                            mobileNavMenu.classList.add('hidden');
                            navToggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            })();
        </script>
        </main>
    </body>
    </html>
    <?php
}
