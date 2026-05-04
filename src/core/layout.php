<?php

declare(strict_types=1);

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
    $isLoginActive = in_array($currentPage, ['login', 'forgot_password', 'reset_password', 'verify_email', 'google_login', 'google_callback'], true);
    $isRegisterActive = $currentPage === 'register';
    $navAppName = (string) $config['app_name'];
    $logoLight = 'public/uploads/logodark.png';
    $logoDark = 'public/uploads/logolight.png';
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
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @font-face {
                font-family: 'The Solstice';
                src: url('static/fonts/THE SOLSTICE.otf') format('opentype'),
                     url('static/fonts/THE SOLSTICE.ttf') format('truetype');
                font-weight: 400;
                font-style: normal;
                font-display: swap;
            }

            :root {
                --green-500: #34d399;
                --green-700: #10b981;
                --green-800: #0f766e;
                --line: rgba(16, 185, 129, 0.28);
                --page-texture-image: url('public/uploads/kldbuilding.jpg');
            }

            html,
            body {
                overflow-x: hidden;
            }

            *,
            *::before,
            *::after {
                box-sizing: border-box;
            }

            body {
                background:
                    linear-gradient(180deg, rgba(252, 254, 253, 0.82) 0%, rgba(247, 251, 249, 0.88) 42%, rgba(244, 248, 247, 0.95) 74%, rgba(244, 248, 247, 0.99) 100%),
                    radial-gradient(900px 420px at 0% 0%, rgba(16, 185, 129, 0.08), transparent 62%),
                    radial-gradient(900px 460px at 100% 0%, rgba(45, 212, 191, 0.07), transparent 64%),
                    var(--page-texture-image),
                    linear-gradient(180deg, #fcfefd 0%, #f4f8f7 100%);
                background-size: auto, auto, auto, cover, auto;
                background-position: center top, 0% 0%, 100% 0%, right center, center;
                background-repeat: no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
                background-attachment: scroll, scroll, scroll, fixed, scroll;
                color: #0f172a;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            body.is-authenticated {
                background:
                    linear-gradient(180deg, rgba(224, 235, 230, 0.84) 0%, rgba(213, 227, 221, 0.88) 42%, rgba(203, 217, 212, 0.94) 74%, rgba(195, 211, 206, 0.97) 100%),
                    radial-gradient(900px 420px at 0% 0%, rgba(15, 118, 110, 0.13), transparent 62%),
                    radial-gradient(900px 460px at 100% 0%, rgba(5, 150, 105, 0.12), transparent 64%),
                    var(--page-texture-image),
                    linear-gradient(180deg, #e4efe9 0%, #c9d8d2 100%);
                background-size: auto, auto, auto, cover, auto;
                background-position: center top, 0% 0%, 100% 0%, right center, center;
                background-repeat: no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
                background-attachment: scroll, scroll, scroll, fixed, scroll;
            }

            main {
                width: 100%;
                flex: 1 0 auto;
            }

            body.theme-dark {
                background:
                    linear-gradient(145deg, rgba(1, 25, 18, 0.84) 0%, rgba(2, 38, 31, 0.9) 46%, rgba(4, 55, 45, 0.96) 100%),
                    radial-gradient(980px 560px at 8% 6%, rgba(52, 211, 153, 0.18), transparent 62%),
                    radial-gradient(1050px 620px at 88% 2%, rgba(20, 184, 166, 0.16), transparent 64%),
                    var(--page-texture-image),
                    linear-gradient(135deg, #011912 0%, #02261f 44%, #04372d 100%);
                background-size: auto, auto, auto, cover, auto;
                background-position: center top, 8% 6%, 88% 2%, right center, center;
                background-repeat: no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
                background-attachment: scroll, scroll, scroll, fixed, scroll;
                color: #f0fdf4;
            }

            body.theme-dark.is-authenticated {
                background:
                    linear-gradient(145deg, rgba(0, 16, 12, 0.9) 0%, rgba(1, 28, 22, 0.94) 46%, rgba(2, 38, 31, 0.98) 100%),
                    radial-gradient(980px 560px at 8% 6%, rgba(52, 211, 153, 0.14), transparent 62%),
                    radial-gradient(1050px 620px at 88% 2%, rgba(20, 184, 166, 0.13), transparent 64%),
                    var(--page-texture-image),
                    linear-gradient(135deg, #00140f 0%, #012019 44%, #022d25 100%);
                background-size: auto, auto, auto, cover, auto;
                background-position: center top, 8% 6%, 88% 2%, right center, center;
                background-repeat: no-repeat, no-repeat, no-repeat, no-repeat, no-repeat;
                background-attachment: scroll, scroll, scroll, fixed, scroll;
            }

            .glass {
                background: linear-gradient(145deg, rgba(244, 255, 238, 0.29), rgba(212, 245, 229, 0.18));
                border: 1px solid var(--line);
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 1rem;
                max-width: 100%;
                overflow-x: auto;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: inherit;
            }

            .modal-panel,
            [data-modal-panel] {
                -webkit-overflow-scrolling: touch;
            }

            .glass.scrolled {
                border-color: rgba(15, 23, 42, 0.24);
                box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
            }

            #toast-container {
                position: fixed;
                top: 5.25rem;
                right: 1.5rem;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-end;
                pointer-events: none;
                max-height: calc(100vh - 6.5rem);
                overflow: hidden;
            }

            @media (max-width: 640px) {
                #toast-container {
                    top: 4.75rem;
                    right: 0.75rem;
                    max-height: calc(100vh - 5.5rem);
                }
            }

            .toast {
                pointer-events: auto;
                width: min(24rem, calc(100vw - 2rem));
                border-radius: 0.9rem;
                border: 1px solid rgba(15, 23, 42, 0.12);
                background: rgba(255, 255, 255, 0.94);
                color: #0f172a;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                overflow: hidden;
                transform: translateX(110%);
                opacity: 0;
                animation: toast-in 0.26s ease forwards;
            }

            .toast.is-leaving {
                animation: toast-out 0.22s ease forwards;
            }

            .toast-body {
                display: flex;
                gap: 0.75rem;
                align-items: flex-start;
                padding: 0.85rem 0.95rem;
            }

            .toast-accent {
                width: 0.38rem;
                align-self: stretch;
                flex: 0 0 auto;
                border-radius: 999px;
            }

            .toast-content {
                flex: 1 1 auto;
                min-width: 0;
            }

            .toast-title {
                margin: 0;
                font-size: 0.92rem;
                font-weight: 700;
                line-height: 1.35;
            }

            .toast-message {
                margin-top: 0.15rem;
                font-size: 0.85rem;
                line-height: 1.45;
                color: rgba(15, 23, 42, 0.82);
                word-break: break-word;
            }

            .toast-close {
                flex: 0 0 auto;
                border: 0;
                background: transparent;
                color: inherit;
                cursor: pointer;
                font-size: 1.1rem;
                line-height: 1;
                padding: 0.1rem 0.2rem;
                opacity: 0.72;
            }

            .toast-close:hover {
                opacity: 1;
            }

            .toast-success {
                border-color: rgba(16, 185, 129, 0.24);
            }

            .toast-success .toast-accent {
                background: linear-gradient(180deg, #34d399, #059669);
            }

            .toast-success .toast-title {
                color: #065f46;
            }

            .toast-error {
                border-color: rgba(248, 113, 113, 0.26);
            }

            .toast-error .toast-accent {
                background: linear-gradient(180deg, #f87171, #dc2626);
            }

            .toast-error .toast-title {
                color: #991b1b;
            }

            .toast-warning {
                border-color: rgba(245, 158, 11, 0.26);
            }

            .toast-warning .toast-accent {
                background: linear-gradient(180deg, #fbbf24, #d97706);
            }

            .toast-warning .toast-title {
                color: #92400e;
            }

            .toast-info {
                border-color: rgba(59, 130, 246, 0.22);
            }

            .toast-info .toast-accent {
                background: linear-gradient(180deg, #60a5fa, #2563eb);
            }

            .toast-info .toast-title {
                color: #1d4ed8;
            }

            body.theme-dark .toast {
                background: rgba(2, 22, 18, 0.92);
                border-color: rgba(110, 231, 183, 0.2);
                color: #ecfdf5;
                box-shadow: 0 20px 42px rgba(0, 0, 0, 0.36);
            }

            body.theme-dark .toast-message {
                color: rgba(209, 250, 229, 0.8);
            }

            body.theme-dark .toast-success .toast-title {
                color: #6ee7b7;
            }

            body.theme-dark .toast-error .toast-title {
                color: #fca5a5;
            }

            body.theme-dark .toast-warning .toast-title {
                color: #fcd34d;
            }

            body.theme-dark .toast-info .toast-title {
                color: #93c5fd;
            }

            @keyframes toast-in {
                from {
                    transform: translateX(110%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            @keyframes toast-out {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(110%);
                    opacity: 0;
                }
            }

            .grid > .glass {
                min-width: 0;
            }

            img,
            video,
            canvas,
            table {
                max-width: 100%;
            }

            body.theme-dark .glass {
                background: rgba(4, 24, 18, 0.52);
                border-color: rgba(110, 231, 183, 0.2);
                box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                color: #ecfdf5;
            }

            body.theme-dark .glass.scrolled {
                border-color: rgba(110, 231, 183, 0.34);
                box-shadow: 0 16px 34px rgba(0, 0, 0, 0.4);
            }

            body.theme-dark .profile-page .profile-meta,
            body.theme-dark .profile-page .profile-org-section {
                color: #d1fae5;
            }

            body.theme-dark .profile-page .profile-org-label,
            body.theme-dark .profile-page .profile-org-card p,
            body.theme-dark .profile-page .profile-org-card .text-xs,
            body.theme-dark .profile-page label,
            body.theme-dark .profile-page h1,
            body.theme-dark .profile-page h3 {
                color: #f0fdf4;
            }

            body.theme-dark .profile-page .profile-org-card-owned {
                background: rgba(6, 78, 59, 0.58) !important;
                border-color: rgba(110, 231, 183, 0.28) !important;
            }

            body.theme-dark .profile-page .profile-org-card-joined {
                background: rgba(2, 44, 34, 0.72) !important;
                border-color: rgba(110, 231, 183, 0.18) !important;
            }

            body.theme-dark .profile-page .profile-org-card .text-emerald-900,
            body.theme-dark .profile-page .profile-org-card .text-slate-900 {
                color: #ecfdf5 !important;
            }

            body.theme-dark .profile-page .profile-org-card .text-emerald-700,
            body.theme-dark .profile-page .profile-org-card .text-slate-600 {
                color: #a7f3d0 !important;
            }

            body.theme-dark .profile-page input[readonly],
            body.theme-dark .profile-page input[type="email"],
            body.theme-dark .profile-page input[type="password"] {
                background: rgba(2, 44, 34, 0.55) !important;
                border-color: rgba(110, 231, 183, 0.24) !important;
                color: #f0fdf4 !important;
            }

            body.theme-dark .profile-page .border-emerald-200\/50,
            body.theme-dark .profile-page .border-emerald-200\/40 {
                border-color: rgba(110, 231, 183, 0.25) !important;
            }

            body.theme-dark .profile-page .text-emerald-800,
            body.theme-dark .profile-page .text-slate-700 {
                color: #d1fae5 !important;
            }

            body.theme-dark .profile-page .bg-emerald-600,
            body.theme-dark .profile-page .bg-emerald-700,
            body.theme-dark .profile-page .bg-emerald-50\/60 {
                box-shadow: none;
            }

            .bg-white.shadow.rounded,
            .bg-white.shadow.rounded.p-4,
            .bg-white.shadow.rounded.p-6 {
                background: linear-gradient(145deg, rgba(244, 255, 238, 0.29), rgba(212, 245, 229, 0.18)) !important;
                border: 1px solid rgba(15, 23, 42, 0.1) !important;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05) !important;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 1rem !important;
            }

            body.theme-dark .bg-white.shadow.rounded,
            body.theme-dark .bg-white.shadow.rounded.p-4,
            body.theme-dark .bg-white.shadow.rounded.p-6 {
                background: rgba(4, 24, 18, 0.52) !important;
                border-color: rgba(110, 231, 183, 0.2) !important;
                box-shadow: 0 10px 28px rgba(0, 0, 0, 0.28) !important;
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }

            .bg-indigo-700 { background-color: var(--green-700) !important; }
            .bg-indigo-900,
            .bg-indigo-950 { background-color: var(--green-800) !important; }
            .text-indigo-700 { color: #0f766e !important; }
            .text-indigo-100 { color: #065f46 !important; }

            body.theme-dark .text-indigo-700,
            body.theme-dark .text-indigo-100 {
                color: #7ef5c4 !important;
            }
            a.underline { text-decoration-color: rgba(4, 120, 87, 0.5); }

            .modern-title {
                color: #064e3b;
                letter-spacing: -0.02em;
                text-shadow: 0 0 14px rgba(16, 185, 129, 0.22);
            }

            .empty-state {
                max-width: 38rem;
                margin: 0 auto;
                text-align: center;
                padding: 1.5rem 1.25rem;
            }

            .empty-state-icon {
                width: 3rem;
                height: 3rem;
                margin: 0 auto 0.85rem;
                color: #047857;
                opacity: 0.9;
            }

            .empty-state-title {
                margin: 0;
                color: #064e3b;
                font-size: 1.08rem;
                font-weight: 700;
                letter-spacing: -0.01em;
            }

            .empty-state-message {
                margin: 0.55rem auto 0;
                max-width: 32rem;
                color: #475569;
                font-size: 0.92rem;
                line-height: 1.5;
            }

            .empty-state-action {
                margin-top: 1rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.4rem;
                border-radius: 0.6rem;
                border: 1px solid rgba(16, 185, 129, 0.52);
                background: linear-gradient(135deg, #10b981, #0f766e);
                color: #ecfdf5;
                padding: 0.55rem 0.9rem;
                font-size: 0.84rem;
                font-weight: 600;
                text-decoration: none;
                transition: filter 0.2s ease, transform 0.2s ease;
            }

            .empty-state-action:hover {
                filter: brightness(1.05);
                transform: translateY(-1px);
            }

            body.theme-dark .empty-state-icon {
                color: #6ee7b7;
            }

            body.theme-dark .empty-state-title {
                color: #ecfdf5;
            }

            body.theme-dark .empty-state-message {
                color: #a7f3d0;
            }

            body.theme-dark .empty-state-action {
                border-color: rgba(110, 231, 183, 0.45);
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.45), rgba(6, 95, 70, 0.72));
                color: #ecfdf5;
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
                transition: color 0.2s ease, text-shadow 0.2s ease, transform 0.2s ease;
                position: relative;
                display: inline-flex;
                align-items: center;
                white-space: nowrap;
                line-height: 1.15;
            }

            .ui-icon {
                width: 1rem;
                height: 1rem;
                flex: 0 0 auto;
            }

            .ui-icon-sm {
                width: 0.9rem;
                height: 0.9rem;
            }

            button,
            .theme-switch,
            .hamburger-btn,
            .global-search-trigger,
            .pagination-control,
            .row-action-hit-target {
                touch-action: manipulation;
            }

            .icon-label {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                min-width: 0;
            }

            .icon-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }

            @keyframes btn-spin {
                to {
                    transform: rotate(360deg);
                }
            }

            @keyframes shimmer {
                0% {
                    background-position: -200% 0;
                }
                100% {
                    background-position: 200% 0;
                }
            }

            .btn-loading {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-loading-spinner {
                width: 1rem;
                height: 1rem;
                color: currentColor;
                animation: btn-spin 0.8s linear infinite;
                flex: 0 0 auto;
            }

            .skeleton {
                position: relative;
                overflow: hidden;
                border-radius: 0.6rem;
                background: linear-gradient(90deg, rgba(203, 213, 225, 0.42) 0%, rgba(226, 232, 240, 0.78) 50%, rgba(203, 213, 225, 0.42) 100%);
                background-size: 200% 100%;
                animation: shimmer 1.5s infinite ease-in-out;
            }

            .skeleton-text {
                height: 0.85rem;
                width: 100%;
                border-radius: 999px;
            }

            .skeleton-card {
                height: 9.25rem;
                border-radius: 1rem;
            }

            .skeleton-stat {
                height: 4.2rem;
                border-radius: 0.9rem;
            }

            body.theme-dark .skeleton {
                background: linear-gradient(90deg, rgba(15, 23, 42, 0.68) 0%, rgba(51, 65, 85, 0.84) 50%, rgba(15, 23, 42, 0.68) 100%);
                background-size: 200% 100%;
            }

            .currency-input-wrap {
                position: relative;
            }

            .currency-prefix {
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
                pointer-events: none;
                color: #065f46;
                font-weight: 600;
                line-height: 1;
            }

            .currency-input-wrap input[data-currency] {
                padding-left: 1.8rem !important;
            }

            body.theme-dark .currency-prefix {
                color: #a7f3d0;
            }

            body.theme-dark .nav-link {
                color: #d5fbe9;
            }

            .nav-link:hover {
                color: #064e3b;
                transform: translateY(-2px);
                text-shadow: 0 0 14px rgba(16, 185, 129, 0.32);
            }

            body.theme-dark .nav-link:hover {
                color: #f4fff9;
                text-shadow: 0 0 18px rgba(110, 231, 183, 0.34);
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

            .nav-brand {
                display: inline-flex;
                align-items: center;
                gap: 0.65rem;
                min-width: 0;
                flex: 1 1 auto;
                max-width: calc(100% - 5.5rem);
                line-height: 1.15;
                overflow-wrap: anywhere;
                word-break: break-word;
                text-decoration: none;
                transition: transform 0.22s ease, filter 0.22s ease;
            }

            .nav-logo {
                width: 3.0rem;
                height: 3.0rem;
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                transition: transform 0.24s ease, filter 0.24s ease;
            }

            .nav-logo-img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                padding: 0.1rem;
                transition: transform 0.24s ease;
            }

            .nav-logo-dark {
                display: none;
            }

            body.theme-dark .nav-logo-light {
                display: none;
            }

            body.theme-dark .nav-logo-dark {
                display: block;
            }

            .nav-brand-text {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .nav-wordmark {
                color: #0c2b22;
                display: inline-flex;
                flex-direction: column;
                font-family: 'Playfair Display', Impact, Haettenschweiler, 'Arial Black', sans-serif;
                letter-spacing: 0;
                line-height: 0.82;
                min-width: 0;
                overflow-wrap: normal;
                text-shadow: none;
                text-transform: uppercase;
                white-space: nowrap;
                word-break: normal;
            }

            .nav-wordmark-main {
                display: block;
                font-size: clamp(1.05rem, 8vw, 1.00rem);
                line-height: 0.72;
                white-space: nowrap;
            }

            body.theme-dark .nav-wordmark {
                color: #d9ffef;
                text-shadow: 0 0 18px rgba(110, 231, 183, 0.01);
            }

            .about-hero {
                text-align: left;
            }

            .about-hero .hero-kicker,
            .about-hero .about-wordmark,
            .about-hero .about-copy {
                margin-left: 0;
                margin-right: auto;
            }

            .about-copy {
                text-align: left;
            }

            .about-wordmark {
                align-items: flex-start;
                display: flex;
                margin: 0.85rem 0 0;
                max-width: 100%;
                overflow: visible;
                text-align: left;
                width: 100%;
            }

            .about-wordmark .nav-wordmark-main {
                font-size: clamp(2.15rem, 13vw, 4.4rem);
                line-height: 0.82;
            }

            @media (min-width: 768px) {
                .about-wordmark .nav-wordmark-main {
                    font-size: clamp(4rem, 8vw, 6rem);
                }
            }

            @media (max-width: 420px) {
                .about-wordmark .nav-wordmark-main {
                    font-size: clamp(1.85rem, 12.5vw, 3.15rem);
                }
            }

            .nav-brand:hover {
                transform: translateY(-1px);
                filter: brightness(1.04);
            }

            .nav-brand:hover .nav-logo {
                transform: rotate(-3deg) scale(1.06);
                filter: drop-shadow(0 8px 14px rgba(16, 185, 129, 0.2));
            }

            .nav-brand:hover .nav-logo-img {
                transform: scale(1.05);
            }

            @media (prefers-reduced-motion: reduce) {
                .nav-link,
                .nav-brand,
                .nav-logo,
                .nav-logo-img {
                    transition: none;
                }

                .nav-link:hover,
                .nav-brand:hover,
                .nav-brand:hover .nav-logo,
                .nav-brand:hover .nav-logo-img {
                    transform: none;
                }
            }

            .nav-mobile-controls {
                flex: 0 0 auto;
            }

            .nav-desktop {
                display: none;
                min-width: 0;
                flex: 1 1 auto;
                justify-content: flex-end;
            }

            .nav-mobile {
                display: flex;
            }

            .nav-utility-controls {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
            }

            @media (max-width: 480px) {
                .nav-brand {
                    gap: 0.45rem;
                    max-width: calc(100% - 8.25rem);
                    font-size: 0.9rem;
                    line-height: 1.05;
                    letter-spacing: -0.01em;
                    overflow-wrap: normal;
                    word-break: normal;
                }

                .nav-logo {
                    width: 2.55rem;
                    height: 2.55rem;
                    border-radius: 0.6rem;
                }

                .nav-wordmark-main {
                    font-size: clamp(1rem, 6.3vw, 1.38rem);
                }

                .nav-mobile-controls {
                    gap: 0.3rem;
                }

                .nav-mobile {
                    gap: 0.3rem;
                }
            }

            @media (max-width: 380px) {
                #appNav .max-w-7xl {
                    padding-left: 0.55rem;
                    padding-right: 0.55rem;
                }

                .nav-brand {
                    gap: 0.35rem;
                    max-width: calc(100% - 7.25rem);
                }

                .nav-logo {
                    width: 2.2rem;
                    height: 2.2rem;
                }

                .nav-wordmark-main {
                    font-size: clamp(0.92rem, 5.8vw, 1.18rem);
                }

                .nav-mobile-controls,
                .nav-mobile {
                    gap: 0.2rem;
                }

                .theme-switch {
                    width: 34px;
                    height: 40px;
                }

                .hamburger-btn {
                    min-width: 40px;
                    min-height: 40px;
                    border-radius: 0.7rem;
                }

                .global-search-trigger {
                    width: 1.65rem;
                    height: 1.65rem;
                }
            }

            @media (min-width: 1024px) {
                .nav-brand {
                    flex: 1 1 24rem;
                    max-width: 25rem;
                }

                .nav-brand-text {
                    overflow-wrap: normal;
                    word-break: normal;
                }

                .nav-wordmark-main {
                    font-size: clamp(1.85rem, 3.3vw, 3.00rem);
                }

                .nav-desktop {
                    display: flex !important;
                    gap: 0.7rem;
                }

                .nav-mobile {
                    display: none !important;
                }
            }

            @media (min-width: 1024px) and (max-width: 1180px) {
                .nav-brand {
                    flex-basis: 19rem;
                    max-width: 19rem;
                }

                .nav-desktop {
                    gap: 0.7rem;
                }

                .nav-link {
                    font-size: 0.92rem;
                }
            }

            body.theme-dark .nav-greeting {
                color: #d1fae5;
            }

            .app-footer {
                margin-top: 1.25rem;
                border-top: 1px solid rgba(16, 185, 129, 0.24);
                background: rgba(255, 255, 255, 0.55);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }

            .app-footer-link {
                color: #065f46;
                text-decoration: none;
                transition: color 0.2s ease;
            }

            .app-footer-link:hover {
                color: #047857;
                text-decoration: underline;
            }

            .app-footer-muted {
                color: #334155;
            }

            body.theme-dark .app-footer {
                border-top-color: rgba(110, 231, 183, 0.34);
                background: rgba(0, 0, 0, 0.2);
            }

            body.theme-dark .app-footer-link,
            body.theme-dark .app-footer-muted {
                color: #d1fae5;
            }

            body.theme-dark .app-footer-link:hover {
                color: #bbf7d0;
            }

            .footer-section {
                min-width: 0;
            }

            .footer-main-grid {
                align-items: start;
            }

            .footer-bottom-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .footer-bottom-actions {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                margin-left: auto;
            }

            .footer-bottom-bar p {
                margin: 0;
            }

            .footer-social-links {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
            }

            .app-footer-icon-link {
                width: 1.9rem;
                height: 1.9rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #065f46;
                border: 1px solid rgba(16, 185, 129, 0.3);
                border-radius: 999px;
                transition: color 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
                text-decoration: none;
            }

            .app-footer-icon-link:hover {
                color: #047857;
                border-color: rgba(16, 185, 129, 0.55);
                background-color: rgba(16, 185, 129, 0.09);
                text-decoration: none;
            }

            .app-footer-icon-link svg {
                width: 0.95rem;
                height: 0.95rem;
            }

            .footer-accordion-toggle {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                background: transparent;
                border: 0;
                padding: 0;
                text-align: left;
                color: inherit;
                touch-action: manipulation;
            }

            .footer-accordion-title-wrap {
                display: flex;
                flex-direction: column;
                gap: 0.1rem;
                min-width: 0;
            }

            .footer-section-title {
                margin: 0;
                line-height: 1.15;
            }

            .footer-accordion-toggle:focus-visible {
                outline: 2px solid rgba(16, 185, 129, 0.7);
                outline-offset: 3px;
                border-radius: 0.35rem;
            }

            .footer-accordion-icon {
                width: 0.7rem;
                height: 0.7rem;
                border-right: 2px solid #0f766e;
                border-bottom: 2px solid #0f766e;
                transform: rotate(45deg);
                transition: transform 0.2s ease;
                flex: 0 0 auto;
                margin-right: 0.2rem;
            }

            .footer-accordion-toggle[aria-expanded="true"] .footer-accordion-icon {
                transform: rotate(-135deg);
            }

            body.theme-dark .footer-accordion-icon {
                border-color: #d1fae5;
            }

            .footer-accordion-panel {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.28s ease, padding-top 0.28s ease;
                padding-top: 0;
            }

            .footer-accordion-panel.is-open {
                max-height: 250px;
                padding-top: 0.5rem;
            }

            @media (max-width: 767px) {
                .app-footer .grid {
                    grid-template-columns: 1fr;
                    gap: 0;
                }

                .footer-main-grid {
                    padding-top: 0.12rem;
                    padding-bottom: 0.12rem;
                }

                .footer-accordion-toggle {
                    min-height: 34px;
                    padding: 0.16rem 0;
                    justify-content: flex-start;
                    gap: 0.3rem;
                }

                .footer-accordion-icon {
                    display: none;
                }

                .footer-accordion-toggle[aria-expanded="true"] {
                    color: #065f46;
                }

                .footer-section {
                    padding: 0.24rem 0;
                    border-bottom: 1px solid rgba(16, 185, 129, 0.24);
                }

                .footer-section-title {
                    font-size: 0.76rem;
                    letter-spacing: 0.04em;
                    line-height: 1;
                }

                .footer-accordion-panel.is-open {
                    padding-top: 0.12rem;
                }

                .footer-accordion-panel ul {
                    font-size: 0.7rem;
                }

                .footer-accordion-panel li {
                    line-height: 1.04;
                }

                .footer-accordion-panel li + li {
                    margin-top: 0.02rem;
                }

                .footer-accordion-panel address {
                    line-height: 1.04;
                }

                .footer-section:last-child {
                    border-bottom: 0;
                }

                body.theme-dark .footer-accordion-toggle[aria-expanded="true"] {
                    color: #6ee7b7;
                }

                .footer-bottom-bar {
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    padding-top: 0.2rem;
                    padding-bottom: 0.2rem;
                }

                .footer-bottom-bar p {
                    font-size: 0.62rem;
                    line-height: 1.06;
                }

                .footer-bottom-actions {
                    width: 100%;
                    justify-content: center;
                    flex-direction: column-reverse;
                    gap: 0.14rem;
                }

                .footer-social-links {
                    justify-content: center;
                    gap: 0.08rem;
                }

                .app-footer-icon-link {
                    width: 1.2rem;
                    height: 1.2rem;
                }

                .app-footer-icon-link svg {
                    width: 0.58rem;
                    height: 0.58rem;
                }

                #backToTop {
                    font-size: 0.64rem;
                    line-height: 1;
                }
            }

            @media (min-width: 768px) {
                .footer-accordion-icon {
                    display: none;
                }

                .footer-accordion-panel {
                    max-height: none !important;
                    overflow: visible !important;
                    padding-top: 0;
                }

                .footer-accordion-toggle {
                    cursor: default;
                }
            }

            @media (min-width: 1024px) {
                .footer-main-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    column-gap: 1.2rem;
                    row-gap: 0.55rem;
                    align-items: start;
                    padding-top: 0.55rem;
                    padding-bottom: 0.55rem;
                }

                .footer-section {
                    display: block;
                    min-width: 0;
                }

                .footer-section-support {
                    grid-column: auto;
                }

                .footer-accordion-toggle {
                    width: 100%;
                    justify-content: flex-start;
                    pointer-events: none;
                    margin-bottom: 0.12rem;
                }

                .footer-accordion-title-wrap {
                    display: block;
                }

                .footer-section-title {
                    font-size: 0.62rem;
                    letter-spacing: 0.12em;
                    white-space: nowrap;
                    opacity: 0.75;
                    font-weight: 700;
                    line-height: 1.05;
                }

                .footer-accordion-panel ul {
                    display: block;
                    margin: 0;
                }

                .footer-accordion-panel li {
                    display: block;
                    line-height: 1.18;
                }

                .footer-accordion-panel li + li::before {
                    content: none;
                    margin-right: 0;
                }

                .footer-accordion-panel li + li {
                    margin-top: 0.14rem;
                }

                .footer-accordion-panel address {
                    display: block;
                    line-height: 1.16;
                }

                .footer-accordion-panel address br {
                    display: block;
                }

                .footer-accordion-panel .pt-1 {
                    padding-top: 0;
                }

                .footer-main-grid .app-footer-link,
                .footer-main-grid .app-footer-muted {
                    font-size: 0.8rem;
                }

                .footer-bottom-bar {
                    align-items: center;
                    padding-top: 0.5rem;
                    padding-bottom: 0.5rem;
                }

                .footer-bottom-actions {
                    align-items: center;
                    gap: 0.4rem;
                }

                .footer-social-links {
                    gap: 0.22rem;
                }

                .app-footer-icon-link {
                    width: 1.6rem;
                    height: 1.6rem;
                }

                .app-footer-icon-link svg {
                    width: 0.85rem;
                    height: 0.85rem;
                }

                #backToTop {
                    line-height: 1;
                }
            }

            @media (min-width: 1440px) {
                .footer-section-support {
                    grid-column: auto;
                }
            }

            body.theme-dark .app-footer-icon-link {
                color: #d1fae5;
                border-color: rgba(110, 231, 183, 0.35);
            }

            body.theme-dark .app-footer-icon-link:hover {
                color: #bbf7d0;
                border-color: rgba(110, 231, 183, 0.65);
                background-color: rgba(110, 231, 183, 0.08);
            }

            body.theme-dark .footer-accordion-panel li + li::before {
                color: rgba(209, 250, 229, 0.7);
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

            body:not(.theme-dark) .text-green-700,
            body:not(.theme-dark) .text-green-800,
            body:not(.theme-dark) .text-emerald-700,
            body:not(.theme-dark) .text-emerald-800,
            body:not(.theme-dark) .text-emerald-900 {
                color: #059669 !important;
            }

            body:not(.theme-dark) .text-red-700,
            body:not(.theme-dark) .text-red-800 {
                color: #dc2626 !important;
            }

            .text-slate-800,
            .text-slate-700,
            .text-slate-600,
            .text-gray-700,
            .text-gray-600,
            .text-gray-500 {
                color: #1f2937 !important;
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
                color: #34d399 !important;
            }

            body.theme-dark .text-red-700,
            body.theme-dark .text-red-800 {
                color: #f87171 !important;
            }

            .org-logo-upload-trigger {
                background: rgba(236, 253, 245, 0.84) !important;
                border-color: rgba(16, 185, 129, 0.42) !important;
                color: #065f46 !important;
            }

            .org-logo-upload-trigger:hover {
                background: rgba(220, 252, 231, 0.94) !important;
            }

            .org-logo-upload-trigger-icon {
                background: rgba(255, 255, 255, 0.94) !important;
                color: #059669 !important;
                border: 1px solid rgba(16, 185, 129, 0.24);
            }

            .org-logo-upload-trigger-subtext {
                color: rgba(6, 95, 70, 0.76) !important;
            }

            body.theme-dark .org-logo-upload-trigger {
                background: rgba(2, 44, 34, 0.84) !important;
                border-color: rgba(110, 231, 183, 0.38) !important;
                color: #ecfdf5 !important;
            }

            body.theme-dark .org-logo-upload-trigger:hover {
                background: rgba(4, 69, 53, 0.9) !important;
            }

            body.theme-dark .org-logo-upload-trigger-icon {
                background: rgba(6, 78, 59, 0.9) !important;
                color: #a7f3d0 !important;
                border-color: rgba(110, 231, 183, 0.32);
            }

            body.theme-dark .org-logo-upload-trigger-subtext {
                color: rgba(167, 243, 208, 0.88) !important;
            }

            body.theme-dark .text-amber-700 {
                color: #fef08a !important;
            }

            .text-amber-200 {
                color: #92400e !important;
            }

            body.theme-dark .text-amber-200 {
                color: #fde68a !important;
            }

            .auth-notice {
                border: 1px solid rgba(16, 185, 129, 0.22);
                background: rgba(236, 253, 245, 0.82);
                color: #065f46;
            }

            .auth-notice a,
            .auth-notice h3 {
                color: #047857;
            }

            .auth-notice-warning {
                border-color: rgba(245, 158, 11, 0.35);
                background: rgba(255, 251, 235, 0.88);
                color: #92400e;
            }

            .auth-notice-warning a,
            .auth-notice-warning h3 {
                color: #92400e;
            }

            .auth-notice-success {
                border-color: rgba(16, 185, 129, 0.35);
            }

            .auth-notice-error {
                border-color: rgba(248, 113, 113, 0.35);
                background: rgba(254, 242, 242, 0.88);
                color: #991b1b;
            }

            .auth-notice-error a,
            .auth-notice-error h3 {
                color: #991b1b;
            }

            body.theme-dark .auth-notice {
                background: rgba(6, 78, 59, 0.34);
                border-color: rgba(110, 231, 183, 0.28);
                color: #d1fae5;
            }

            body.theme-dark .auth-notice a,
            body.theme-dark .auth-notice h3 {
                color: #6ee7b7;
            }

            body.theme-dark .auth-notice-warning {
                background: rgba(120, 53, 15, 0.18);
                border-color: rgba(251, 191, 36, 0.32);
                color: #fde68a;
            }

            body.theme-dark .auth-notice-warning a,
            body.theme-dark .auth-notice-warning h3 {
                color: #fcd34d;
            }

            body.theme-dark .auth-notice-error {
                background: rgba(127, 29, 29, 0.2);
                border-color: rgba(248, 113, 113, 0.34);
                color: #fecaca;
            }

            body.theme-dark .auth-notice-error a,
            body.theme-dark .auth-notice-error h3 {
                color: #fca5a5;
            }

            body.theme-dark .text-slate-900 {
                color: #f0fdf4 !important;
            }

            input, textarea, select {
                background: rgba(255, 255, 255, 0.72) !important;
                border-color: rgba(16, 185, 129, 0.32) !important;
                color: #064e3b !important;
            }

            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="number"],
            textarea,
            select {
                border: 1px solid rgba(16, 185, 129, 0.3) !important;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            input[type="text"]:focus,
            input[type="email"]:focus,
            input[type="password"]:focus,
            input[type="number"]:focus,
            textarea:focus,
            select:focus {
                outline: none;
                border-color: rgba(16, 185, 129, 0.6) !important;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
            }

            body.theme-dark input[type="text"],
            body.theme-dark input[type="email"],
            body.theme-dark input[type="password"],
            body.theme-dark input[type="number"],
            body.theme-dark textarea,
            body.theme-dark select {
                border: 1px solid rgba(52, 211, 153, 0.35) !important;
            }

            body.theme-dark input[type="text"]:focus,
            body.theme-dark input[type="email"]:focus,
            body.theme-dark input[type="password"]:focus,
            body.theme-dark input[type="number"]:focus,
            body.theme-dark textarea:focus,
            body.theme-dark select:focus {
                outline: none;
                border-color: rgba(52, 211, 153, 0.7) !important;
                box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.18);
            }

            input[readonly] {
                background: rgba(241, 245, 249, 0.75) !important;
                border-color: rgba(16, 185, 129, 0.2) !important;
                color: #64748b !important;
                cursor: default;
            }

            input[readonly]:focus {
                outline: none;
                border-color: rgba(16, 185, 129, 0.2) !important;
                box-shadow: none !important;
            }

            body.theme-dark input[readonly] {
                background: rgba(15, 23, 42, 0.35) !important;
                border-color: rgba(52, 211, 153, 0.24) !important;
                color: rgba(209, 250, 229, 0.68) !important;
            }

            body.theme-dark input[readonly]:focus {
                outline: none;
                border-color: rgba(52, 211, 153, 0.24) !important;
                box-shadow: none !important;
            }

            input[type="checkbox"] {
                appearance: auto !important;
                -webkit-appearance: checkbox !important;
                background: transparent !important;
                border: 1px solid rgba(16, 185, 129, 0.58) !important;
                accent-color: #059669;
            }

            body.theme-dark input,
            body.theme-dark textarea,
            body.theme-dark select {
                background: rgba(0, 0, 0, 0.2) !important;
                border-color: rgba(110, 231, 183, 0.35) !important;
                color: #f1fff7 !important;
            }

            body.theme-dark input[type="checkbox"] {
                background: transparent !important;
                border-color: rgba(167, 243, 208, 0.7) !important;
                accent-color: #34d399;
            }

            select {
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 0.6rem;
                appearance: none;
                -webkit-appearance: none;
                padding-right: 2rem !important;
                background-image: linear-gradient(45deg, transparent 50%, #047857 50%), linear-gradient(135deg, #047857 50%, transparent 50%);
                background-position: calc(100% - 16px) calc(50% - 2px), calc(100% - 10px) calc(50% - 2px);
                background-size: 6px 6px, 6px 6px;
                background-repeat: no-repeat;
            }

            select option {
                background-color: #ecfdf5;
                color: #064e3b;
            }

            select option:checked,
            select option:hover {
                background: linear-gradient(135deg, #059669, #047857);
                color: #f0fdf4;
            }

            body.theme-dark select option {
                background-color: #021d17;
                color: #d1fae5;
            }

            body.theme-dark select option:checked,
            body.theme-dark select option:hover {
                background: linear-gradient(135deg, #047857, #065f46);
                color: #ecfdf5;
            }

            select:hover {
                border-color: rgba(5, 150, 105, 0.55) !important;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
            }

            body.theme-dark select:hover {
                border-color: rgba(52, 211, 153, 0.65) !important;
                box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
            }

            body.theme-dark .report-export-btn {
                background: linear-gradient(135deg, rgba(6, 95, 70, 0.95), rgba(4, 120, 87, 0.98)) !important;
                border-color: rgba(110, 231, 183, 0.35) !important;
                color: #ecfdf5 !important;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.24);
            }

            body.theme-dark .report-export-btn:hover {
                background: linear-gradient(135deg, rgba(4, 120, 87, 1), rgba(6, 148, 111, 1)) !important;
                color: #ffffff !important;
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

            .theme-switch {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                width: 44px;
                height: 44px;
                cursor: pointer;
                transition: opacity 0.2s ease;
            }

            .theme-switch::before {
                content: '';
                position: absolute;
                top: 6px;
                left: 50%;
                transform: translateX(-50%);
                width: 20px;
                height: 32px;
                border-radius: 999px;
                border: 1px solid rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.2);
                transition: all 0.2s ease;
            }

            .theme-switch::after {
                content: '';
                position: absolute;
                top: 9px;
                left: 50%;
                transform: translateX(-50%);
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

            body.theme-dark .theme-switch {
                opacity: 1;
            }

            body.theme-dark .theme-switch::before {
                border-color: rgba(167, 243, 208, 0.5);
                background: rgba(255, 255, 255, 0.14);
            }

            body.theme-dark .theme-switch::after {
                top: 25px;
            }

            .hamburger-btn {
                border: 1px solid rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.14);
                min-width: 44px;
                min-height: 44px;
                border-radius: 0.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
            }

            .global-search-trigger {
                border: 1px solid rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.14);
                width: 1.7rem;
                height: 1.7rem;
                border-radius: 0.375rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            }

            .global-search-trigger:hover {
                background: rgba(16, 185, 129, 0.2);
                border-color: rgba(5, 150, 105, 0.58);
                transform: translateY(-1px);
            }

            .global-search-trigger .ui-icon {
                width: 0.85rem;
                height: 0.85rem;
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
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.25s ease;
            }

            .mobile-nav-panel.is-open {
                max-height: 300px;
            }

            .themed-scroll {
                scrollbar-width: thin;
                scrollbar-color: rgba(16, 185, 129, 0.7) rgba(16, 185, 129, 0.16);
            }

            .themed-scroll::-webkit-scrollbar {
                width: 10px;
                height: 10px;
            }

            .themed-scroll::-webkit-scrollbar-track {
                background: rgba(16, 185, 129, 0.16);
                border-radius: 999px;
            }

            .themed-scroll::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.85));
                border-radius: 999px;
                border: 2px solid rgba(16, 185, 129, 0.16);
            }

            .themed-scroll::-webkit-scrollbar-thumb:hover {
                background: linear-gradient(180deg, rgba(16, 185, 129, 1), rgba(4, 120, 87, 0.95));
            }

            body.theme-dark .themed-scroll {
                scrollbar-color: rgba(110, 231, 183, 0.8) rgba(16, 185, 129, 0.2);
            }

            body.theme-dark .themed-scroll::-webkit-scrollbar-track {
                background: rgba(16, 185, 129, 0.2);
            }

            body.theme-dark .themed-scroll::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg, rgba(52, 211, 153, 0.95), rgba(16, 185, 129, 0.9));
                border: 2px solid rgba(16, 185, 129, 0.2);
            }

            .updates-modal-overlay {
                position: fixed;
                inset: 0;
                z-index: 50;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                overflow-y: auto;
                background: linear-gradient(180deg, rgba(236, 253, 245, 0.74), rgba(209, 250, 229, 0.68));
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
            }

            .updates-modal-overlay > .glass {
                width: min(100%, 72rem);
                max-height: 90dvh;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            .modal-panel {
                position: relative;
                transform: translateY(0);
                transition: transform 0.2s ease;
                will-change: transform;
            }

            .modal-panel.is-dragging {
                transition: none;
            }

            .modal-drag-handle {
                width: 40px;
                height: 4px;
                margin: 0 auto 1rem;
                border-radius: 2px;
                background: rgba(148, 163, 184, 0.78);
                flex: 0 0 auto;
            }

            body.theme-dark .modal-drag-handle {
                background: rgba(203, 213, 225, 0.55);
            }

            body.theme-dark .updates-modal-overlay {
                background: rgba(2, 6, 23, 0.55);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }

            .updates-modal-overlay.hidden {
                display: none;
            }

            .global-search-results {
                display: grid;
                gap: 0.5rem;
            }

            .global-search-result {
                display: flex;
                width: 100%;
                align-items: flex-start;
                gap: 0.75rem;
                border-radius: 1rem;
                border: 1px solid rgba(16, 185, 129, 0.14);
                background: rgba(255, 255, 255, 0.72);
                padding: 0.8rem 0.9rem;
                text-align: left;
                transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            }

            .global-search-result:hover,
            .global-search-result.is-active {
                border-color: rgba(16, 185, 129, 0.45);
                background: rgba(16, 185, 129, 0.1);
                transform: translateY(-1px);
            }

            body.theme-dark .global-search-result {
                background: rgba(15, 23, 42, 0.5);
                border-color: rgba(110, 231, 183, 0.18);
            }

            body.theme-dark .global-search-result:hover,
            body.theme-dark .global-search-result.is-active {
                background: rgba(16, 185, 129, 0.18);
                border-color: rgba(110, 231, 183, 0.42);
            }

            .global-search-result-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                flex-shrink: 0;
                border-radius: 0.75rem;
                background: rgba(16, 185, 129, 0.12);
                color: #047857;
            }

            body.theme-dark .global-search-result-icon {
                background: rgba(52, 211, 153, 0.14);
                color: #d1fae5;
            }

            .global-search-result-meta {
                color: rgba(71, 85, 105, 0.95);
            }

            body.theme-dark .global-search-result-meta {
                color: rgba(209, 250, 229, 0.72);
            }

            .global-search-shortcut {
                border: 1px solid rgba(16, 185, 129, 0.18);
                border-radius: 999px;
                padding: 0.15rem 0.45rem;
                font-size: 0.7rem;
                color: #065f46;
                background: rgba(236, 253, 245, 0.78);
            }

            body.theme-dark .global-search-shortcut {
                border-color: rgba(110, 231, 183, 0.24);
                color: #d1fae5;
                background: rgba(15, 23, 42, 0.45);
            }

            .global-search-empty {
                border: 1px dashed rgba(16, 185, 129, 0.24);
                border-radius: 1rem;
                padding: 1rem;
                text-align: center;
                color: rgba(71, 85, 105, 0.95);
            }

            body.theme-dark .global-search-empty {
                border-color: rgba(110, 231, 183, 0.24);
                color: rgba(209, 250, 229, 0.72);
            }

            .global-search-overlay {
                z-index: 50;
            }

            .global-search-overlay .glass {
                width: min(100%, 44rem);
            }

            @media (max-width: 1023px) {
                .updates-modal-overlay {
                    align-items: flex-start;
                    padding: 0.6rem;
                }

                .updates-modal-overlay > .glass {
                    width: 100%;
                    max-height: calc(100dvh - 0.75rem);
                    border-radius: 0.85rem;
                }

                .global-search-overlay > .glass {
                    width: 100%;
                }

                .updates-modal-overlay .grid.md\:grid-cols-3,
                .updates-modal-overlay .grid.md\:grid-cols-2 {
                    grid-template-columns: 1fr;
                }

                .updates-modal-overlay .p-5 {
                    padding: 1rem !important;
                }

                .updates-modal-overlay .p-4 {
                    padding: 0.9rem !important;
                }
            }

            @media (max-width: 767px) {
                main {
                    padding-left: 0.6rem !important;
                    padding-right: 0.6rem !important;
                }

                .dashboard-shell {
                    padding-left: 0;
                    padding-right: 0;
                }

                .updates-modal-overlay .text-lg {
                    font-size: 1rem !important;
                    line-height: 1.3;
                }

                .updates-modal-overlay canvas {
                    max-height: 220px;
                }

                .glass table {
                    display: block;
                    width: 100%;
                    overflow-x: auto;
                    white-space: nowrap;
                    -webkit-overflow-scrolling: touch;
                }
            }

            .updates-status {
                font-size: 11px;
                border-radius: 999px;
                padding: 2px 8px;
                font-weight: 600;
            }

            .updates-status-approved,
            .updates-status-accepted {
                background: rgba(16, 185, 129, 0.22);
                color: #065f46;
            }

            .updates-status-rejected,
            .updates-status-declined,
            .updates-status-removed {
                background: rgba(239, 68, 68, 0.2);
                color: #991b1b;
            }

            .updates-status-pending {
                background: rgba(245, 158, 11, 0.2);
                color: #92400e;
            }

            body.theme-dark .updates-status-approved,
            body.theme-dark .updates-status-accepted {
                color: #bbf7d0;
            }

            body.theme-dark .updates-status-rejected,
            body.theme-dark .updates-status-declined,
            body.theme-dark .updates-status-removed {
                color: #fecaca;
            }

            body.theme-dark .updates-status-pending {
                color: #fde68a;
            }

            .dashboard-shell {
                max-width: 1320px;
                margin: 0 auto;
            }

            @media (min-width: 1280px) {
                .dashboard-shell {
                    padding-left: 0.5rem;
                    padding-right: 0.5rem;
                }
            }

            .dashboard-panel {
                position: relative;
                overflow: hidden;
            }

            .dashboard-panel::after {
                content: '';
                position: absolute;
                right: -3.5rem;
                bottom: -3.5rem;
                width: 9rem;
                height: 9rem;
                border-radius: 999px;
                background: radial-gradient(circle, rgba(60, 245, 177, 0.27) 0%, rgba(52, 211, 153, 0.18) 70%);
                pointer-events: none;
            }

            body.theme-dark .dashboard-panel::after {
                display: none;
            }

            .dashboard-kicker {
                margin-bottom: 0.55rem;
                color: rgba(236, 253, 245, 0.72);
                font-size: 0.76rem;
            }

            .dashboard-headline {
                color: #f0fdf4;
                font-size: clamp(1.4rem, 2.4vw, 2.35rem);
                line-height: 1.02;
                letter-spacing: -0.04em;
                text-wrap: balance;
            }

            .dashboard-copy {
                color: rgba(236, 253, 245, 0.8);
                font-size: 0.86rem;
                line-height: 1.5;
                max-width: 36rem;
            }

            .dashboard-stamp {
                color: rgba(236, 253, 245, 0.76);
                font-size: 0.72rem;
                white-space: nowrap;
            }

            .dashboard-metric-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.75rem;
            }

            .dashboard-metric-card {
                padding: 0.7rem 0.8rem;
                border-radius: 0.9rem;
                border: 1px solid rgba(110, 231, 183, 0.16);
                background: rgba(15, 23, 42, 0.12);
            }

            .dashboard-metric-value {
                color: #f0fdf4;
                font-size: 1.08rem;
                font-weight: 700;
                letter-spacing: -0.03em;
            }

            .dashboard-metric-label {
                margin-top: 0.15rem;
                color: rgba(236, 253, 245, 0.7);
                font-size: 0.69rem;
            }

            .dashboard-section-title {
                color: #f0fdf4;
                font-size: 0.98rem;
                font-weight: 600;
            }

            .dashboard-section-copy {
                color: rgba(236, 253, 245, 0.76);
                font-size: 0.77rem;
            }

            .dashboard-stat-list {
                display: grid;
                gap: 0.6rem;
            }

            .dashboard-stat-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding-bottom: 0.55rem;
                border-bottom: 1px solid rgba(110, 231, 183, 0.14);
            }

            .dashboard-stat-row:last-child {
                border-bottom: 0;
                padding-bottom: 0;
            }

            .dashboard-stat-label {
                color: rgba(236, 253, 245, 0.75);
                font-size: 0.76rem;
            }

            .dashboard-stat-value {
                color: #f0fdf4;
                font-weight: 600;
            }

            .dashboard-progress {
                height: 0.32rem;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.12);
                overflow: hidden;
            }

            .dashboard-progress > span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, rgba(110, 231, 183, 0.96), rgba(16, 185, 129, 0.88));
                transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .dashboard-feed-item {
                display: flex;
                align-items: flex-start;
                gap: 0.8rem;
                padding: 0.65rem 0.75rem;
                border-radius: 0.95rem;
                border: 1px solid rgba(110, 231, 183, 0.14);
                background: rgba(15, 23, 42, 0.12);
            }

            .dashboard-feed-dot {
                width: 0.65rem;
                height: 0.65rem;
                margin-top: 0.35rem;
                border-radius: 999px;
                flex: 0 0 auto;
                background: #34d399;
                box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.12);
            }

            .dashboard-feed-dot.warn {
                background: #fbbf24;
                box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.14);
            }

            .dashboard-feed-dot.danger {
                background: #f87171;
                box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.14);
            }

            .dashboard-feed-title {
                color: #f0fdf4;
                font-size: 0.83rem;
                font-weight: 600;
            }

            .dashboard-feed-meta,
            .dashboard-feed-body {
                color: rgba(236, 253, 245, 0.76);
                font-size: 0.72rem;
                line-height: 1.35;
            }

            .dashboard-table thead th {
                color: rgba(236, 253, 245, 0.8);
                font-weight: 500;
                font-size: 0.7rem;
            }

            .dashboard-table td,
            .dashboard-table th {
                padding-top: 0.45rem !important;
                padding-bottom: 0.45rem !important;
            }

            .dashboard-table tbody td {
                color: #f0fdf4;
                overflow-wrap: anywhere;
            }

            .trend-insight-grid {
                align-items: stretch;
            }

            .trend-insight-card {
                padding: 0.55rem 0.65rem;
                gap: 0.6rem;
            }

            .trend-insight-card .dashboard-feed-title {
                font-size: 0.8rem;
            }

            .trend-insight-card .dashboard-feed-meta,
            .trend-insight-card .dashboard-feed-body {
                font-size: 0.69rem;
                line-height: 1.3;
            }

            @media (max-width: 767px) {
                .dashboard-metric-grid {
                    grid-template-columns: 1fr;
                }
            }

            body:not(.theme-dark) .dashboard-panel {
                background: linear-gradient(145deg, rgba(244, 255, 238, 0.29), rgba(212, 245, 229, 0.18));
                border: 1px solid rgba(15, 23, 42, 0.1);
                color: #1f2937;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            }

            body:not(.theme-dark) .dashboard-panel::after {
                background: radial-gradient(circle, rgba(16, 185, 129, 0.06) 0%, rgba(16, 185, 129, 0) 70%);
            }

            body:not(.theme-dark) .dashboard-kicker,
            body:not(.theme-dark) .dashboard-stamp,
            body:not(.theme-dark) .dashboard-metric-label,
            body:not(.theme-dark) .dashboard-section-copy,
            body:not(.theme-dark) .dashboard-stat-label,
            body:not(.theme-dark) .dashboard-feed-meta,
            body:not(.theme-dark) .dashboard-feed-body {
                color: #4b5563;
            }

            body:not(.theme-dark) .dashboard-headline,
            body:not(.theme-dark) .dashboard-metric-value,
            body:not(.theme-dark) .dashboard-section-title,
            body:not(.theme-dark) .dashboard-stat-value,
            body:not(.theme-dark) .dashboard-feed-title,
            body:not(.theme-dark) .dashboard-table tbody td {
                color: #1f2937;
            }

            body:not(.theme-dark) .dashboard-copy {
                color: #374151;
            }

            body:not(.theme-dark) .dashboard-metric-card,
            body:not(.theme-dark) .dashboard-feed-item {
                background: rgba(247, 255, 252, 0.34);
                border-color: rgba(15, 23, 42, 0.1);
            }

            body:not(.theme-dark) .dashboard-stat-row {
                border-bottom-color: rgba(15, 23, 42, 0.1);
            }

            body:not(.theme-dark) .dashboard-progress {
                background: rgba(15, 23, 42, 0.08);
            }

            body:not(.theme-dark) .dashboard-table thead th {
                color: #334155;
            }

            body:not(.theme-dark) .dashboard-metric-label {
                color: #374151;
            }

            body:not(.theme-dark) .dashboard-table thead th {
                color: #1f2937;
            }

            body:not(.theme-dark) .dashboard-table tr {
                border-color: rgba(15, 23, 42, 0.12) !important;
            }

            body:not(.theme-dark) .dashboard-shell .text-green-300,
            body:not(.theme-dark) .dashboard-shell .text-emerald-300 {
                color: #035e41 !important;
            }

            body:not(.theme-dark) .dashboard-shell .text-red-300 {
                color: #a71515 !important;
            }

            body:not(.theme-dark) .dashboard-shell .text-indigo-100 {
                color: #065f46 !important;
            }

            body:not(.theme-dark) #financialSummaryModal .glass {
                background: rgba(255, 255, 255, 0.97);
                border-color: rgba(15, 23, 42, 0.1);
            }

            @media print {
                html,
                body {
                    background: #fff !important;
                    color: #000 !important;
                }

                nav,
                aside,
                header,
                footer,
                form,
                button,
                input,
                select,
                textarea,
                .theme-switch,
                .global-search-trigger,
                .hamburger-btn,
                .toast-container,
                .mobile-nav-panel,
                .nav-greeting,
                script {
                    display: none !important;
                }

                main,
                .glass,
                .bg-white.shadow.rounded,
                .bg-white.shadow.rounded.p-4,
                .bg-white.shadow.rounded.p-6,
                .dashboard-panel,
                .dashboard-metric-card,
                .dashboard-feed-item {
                    background: transparent !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                }

                .toast,
                .modal,
                .overlay {
                    display: none !important;
                }

                a {
                    color: inherit !important;
                    text-decoration: none !important;
                }

                table {
                    width: 100% !important;
                    border-collapse: collapse !important;
                }

                th,
                td {
                    color: #000 !important;
                }
            }
        </style>
    </head>
    <body class="min-h-screen <?= $user ? 'is-authenticated' : '' ?>" data-flash="<?= e($flashMessage) ?>" data-flash-type="<?= e($flashType) ?>">
        <script>
            (function () {
                const saved = localStorage.getItem('websys-theme');
                if (saved === 'dark') {
                    document.body.classList.add('theme-dark');
                }
            })();
        </script>
        <nav id="appNav" class="glass fixed top-0 inset-x-0 z-50 mx-1.5 sm:mx-2.5 mt-1.5 text-slate-800">
            <div class="max-w-7xl mx-auto px-3 py-2">
                <div class="flex items-center justify-between gap-2 min-w-0">
                    <a href="?page=home" class="nav-brand font-bold tracking-tight text-emerald-900 text-xl modern-title" aria-label="<?= e($navAppName) ?> home">
                        <span class="nav-logo" aria-hidden="true">
                            <img src="<?= e($logoLight) ?>" alt="" class="nav-logo-img nav-logo-light">
                            <img src="<?= e($logoDark) ?>" alt="" class="nav-logo-img nav-logo-dark">
                        </span>
                        <span class="nav-brand-text nav-wordmark" aria-hidden="true">
                            <span class="nav-wordmark-main">SOM</span>
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
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="?page=profile" class="nav-link <?= $isProfileActive ? 'nav-link-active' : '' ?>">Profile</a>
                            <?php endif; ?>
                            <div class="nav-utility-controls">
                                <button type="button" id="globalSearchOpen" class="global-search-trigger" aria-label="Open global search" title="Search (Ctrl+K)">
                                    <?= icon('search') ?>
                                </button>
                                <input type="checkbox" id="themeToggle" aria-label="Toggle dark mode">
                                <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
                                <a href="?page=logout" class="bg-indigo-900 text-white px-2.5 py-1 rounded hover:bg-indigo-950">Logout</a>
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
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="?page=profile" class="nav-link <?= $isProfileActive ? 'nav-link-active' : '' ?>">Profile</a>
                            <?php endif; ?>
                            <a href="?page=logout" class="bg-indigo-900 text-white px-3 py-2 rounded text-center hover:bg-indigo-950">Logout</a>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $isLoginActive ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-3 py-2 rounded text-center hover:bg-emerald-700 shadow-sm <?= $isRegisterActive ? 'ring-2 ring-emerald-300/70' : '' ?>">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <script>
            (function () {
                var nav = document.getElementById('appNav');
                if (!nav) {
                    return;
                }

                var updateScrolledState = function () {
                    nav.classList.toggle('scrolled', window.scrollY > 20);
                };

                updateScrolledState();
                window.addEventListener('scroll', updateScrolledState, { passive: true });
            })();
        </script>

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
            <script>
                (function () {
                    var modal = document.getElementById('globalSearchModal');
                    var openButtons = [document.getElementById('globalSearchOpen'), document.getElementById('globalSearchOpenMobile')].filter(function (button) { return Boolean(button); });
                    var closeButton = document.getElementById('globalSearchClose');
                    var input = document.getElementById('globalSearchInput');
                    var status = document.getElementById('globalSearchStatus');
                    var resultsContainer = document.getElementById('globalSearchResults');

                    if (!modal || !input || !status || !resultsContainer) {
                        return;
                    }

                    var requestId = 0;
                    var requestController = null;
                    var searchTimer = null;
                    var activeIndex = -1;
                    var currentResults = [];
                    var lastTrigger = null;

                    var typeLabels = {
                        user: 'User',
                        org: 'Organization',
                        announcement: 'Announcement',
                    };

                    var typeIcons = {
                        user: 'U',
                        org: 'O',
                        announcement: 'A',
                    };

                    var setBodyLocked = function (locked) {
                        document.body.style.overflow = locked ? 'hidden' : '';
                    };

                    var setStatus = function (message) {
                        status.textContent = message;
                    };

                    var openModal = function (trigger) {
                        lastTrigger = trigger || document.activeElement;
                        modal.classList.remove('hidden');
                        modal.setAttribute('aria-hidden', 'false');
                        setBodyLocked(true);
                        currentResults = [];
                        activeIndex = -1;
                        resultsContainer.innerHTML = '';
                        setStatus('Type at least 2 characters to search.');
                        window.setTimeout(function () {
                            input.focus();
                            input.select();
                        }, 0);
                    };

                    var closeModal = function () {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                        setBodyLocked(false);
                        input.value = '';
                        if (requestController) {
                            requestController.abort();
                            requestController = null;
                        }
                        if (searchTimer) {
                            window.clearTimeout(searchTimer);
                            searchTimer = null;
                        }
                        if (lastTrigger && typeof lastTrigger.focus === 'function') {
                            lastTrigger.focus();
                        }
                    };

                    var renderResults = function (results) {
                        currentResults = results;
                        activeIndex = results.length > 0 ? 0 : -1;
                        resultsContainer.innerHTML = '';

                        if (results.length === 0) {
                            resultsContainer.innerHTML = '<div class="global-search-empty">No results found.</div>';
                            return;
                        }

                        results.forEach(function (result, index) {
                            var link = document.createElement('a');
                            link.href = result.url;
                            link.className = 'global-search-result';
                            link.setAttribute('role', 'option');
                            link.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
                            link.dataset.resultIndex = String(index);

                            if (index === activeIndex) {
                                link.classList.add('is-active');
                            }

                            link.innerHTML =
                                '<span class="global-search-result-icon">' + (typeIcons[result.type] || '?') + '</span>' +
                                '<span class="min-w-0 flex-1">' +
                                    '<span class="block font-semibold text-slate-800 truncate">' + result.label + '</span>' +
                                    '<span class="block text-sm global-search-result-meta truncate">' + result.sublabel + '</span>' +
                                '</span>' +
                                '<span class="global-search-shortcut">' + (typeLabels[result.type] || 'Result') + '</span>';

                            link.addEventListener('mouseenter', function () {
                                activeIndex = index;
                                updateActiveState();
                            });

                            link.addEventListener('click', function () {
                                closeModal();
                            });

                            resultsContainer.appendChild(link);
                        });
                    };

                    var updateActiveState = function () {
                        var resultNodes = resultsContainer.querySelectorAll('.global-search-result');
                        resultNodes.forEach(function (node, index) {
                            var isActive = index === activeIndex;
                            node.classList.toggle('is-active', isActive);
                            node.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            if (isActive && typeof node.scrollIntoView === 'function') {
                                node.scrollIntoView({ block: 'nearest' });
                            }
                        });
                    };

                    var moveSelection = function (step) {
                        if (currentResults.length === 0) {
                            return;
                        }

                        activeIndex = (activeIndex + step + currentResults.length) % currentResults.length;
                        updateActiveState();
                    };

                    var search = function (query) {
                        var trimmed = query.trim();
                        if (trimmed.length < 2) {
                            if (requestController) {
                                requestController.abort();
                                requestController = null;
                            }
                            currentResults = [];
                            activeIndex = -1;
                            resultsContainer.innerHTML = '';
                            setStatus('Type at least 2 characters to search.');
                            return;
                        }

                        setStatus('Searching...');
                        var currentRequestId = ++requestId;

                        if (requestController) {
                            requestController.abort();
                        }

                        requestController = window.AbortController ? new AbortController() : null;

                        fetch('?action=search&q=' + encodeURIComponent(trimmed), {
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin',
                            signal: requestController ? requestController.signal : undefined,
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('Search request failed');
                            }

                            return response.json();
                        }).then(function (payload) {
                            if (currentRequestId !== requestId) {
                                return;
                            }

                            var results = Array.isArray(payload.results) ? payload.results : [];
                            if (results.length === 0) {
                                setStatus('No results found for "' + trimmed + '".');
                            } else {
                                setStatus('Showing ' + results.length + ' result' + (results.length === 1 ? '' : 's') + ' for "' + trimmed + '".');
                            }

                            renderResults(results);
                        }).catch(function (error) {
                            if (error && error.name === 'AbortError') {
                                return;
                            }

                            if (currentRequestId !== requestId) {
                                return;
                            }

                            currentResults = [];
                            activeIndex = -1;
                            resultsContainer.innerHTML = '<div class="global-search-empty">Search is temporarily unavailable.</div>';
                            setStatus('Search is temporarily unavailable.');
                        }).finally(function () {
                            if (currentRequestId === requestId) {
                                requestController = null;
                            }
                        });
                    };

                    var scheduleSearch = function () {
                        if (searchTimer) {
                            window.clearTimeout(searchTimer);
                        }

                        searchTimer = window.setTimeout(function () {
                            search(input.value);
                        }, 180);
                    };

                    openButtons.forEach(function (button) {
                        button.addEventListener('click', function () {
                            openModal(button);
                        });
                    });

                    if (closeButton) {
                        closeButton.addEventListener('click', closeModal);
                    }

                    modal.addEventListener('click', function (event) {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    input.addEventListener('input', scheduleSearch);
                    input.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            moveSelection(1);
                            return;
                        }

                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            moveSelection(-1);
                            return;
                        }

                        if (event.key === 'Enter' && currentResults.length > 0) {
                            event.preventDefault();
                            window.location.href = currentResults[activeIndex < 0 ? 0 : activeIndex].url;
                            return;
                        }

                        if (event.key === 'Escape') {
                            event.preventDefault();
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                            event.preventDefault();
                            openModal(document.getElementById('globalSearchOpen') || document.getElementById('globalSearchOpenMobile'));
                        }

                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                })();
            </script>
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
                        <div class="mt-4 flex justify-end">
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
    $footerRole = (string) ($footerUser['role'] ?? '');
    ?>
        </main>
        <footer class="app-footer" data-default-open="<?= ($footerUser === null ? 0 : 3) ?>">
            <div class="mx-auto w-full max-w-7xl">
                <div class="footer-main-grid grid grid-cols-1 gap-3 px-4 py-2 sm:grid-cols-2 lg:grid-cols-4 lg:py-3 text-sm">
                    <div class="footer-section">
                        <button type="button" class="footer-accordion-toggle" aria-expanded="false" aria-controls="footer-panel-platform">
                            <span class="footer-accordion-title-wrap">
                                <span class="footer-section-title text-xs font-semibold tracking-wide uppercase app-footer-muted">Platform</span>
                            </span>
                            <span class="footer-accordion-icon" aria-hidden="true"></span>
                        </button>
                        <div id="footer-panel-platform" class="footer-accordion-panel">
                            <ul class="space-y-0.5 app-footer-muted font-medium">
                                <li><a href="?page=home" class="app-footer-link">Home</a></li>
                                <?php if ($footerUser === null): ?>
                                    <li><a href="?page=login" class="app-footer-link">Login</a></li>
                                    <li><a href="?page=register" class="app-footer-link">Register</a></li>
                                <?php elseif ($footerRole === 'student'): ?>
                                    <li><a href="?page=dashboard" class="app-footer-link">Dashboard</a></li>
                                    <li><a href="?page=organizations" class="app-footer-link">Organizations</a></li>
                                    <li><a href="?page=profile" class="app-footer-link">Profile</a></li>
                                <?php elseif ($footerRole === 'owner'): ?>
                                    <li><a href="?page=dashboard" class="app-footer-link">Dashboard</a></li>
                                    <li><a href="?page=my_org" class="app-footer-link">My Organization</a></li>
                                    <li><a href="?page=profile" class="app-footer-link">Profile</a></li>
                                <?php elseif ($footerRole === 'admin'): ?>
                                    <li><a href="?page=dashboard" class="app-footer-link">Dashboard</a></li>
                                    <li><a href="?page=admin_orgs" class="app-footer-link">Admin Panel</a></li>
                                    <li><a href="?page=profile" class="app-footer-link">Profile</a></li>
                                <?php else: ?>
                                    <li><a href="?page=login" class="app-footer-link">Login</a></li>
                                    <li><a href="?page=register" class="app-footer-link">Register</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="footer-section">
                        <button type="button" class="footer-accordion-toggle" aria-expanded="false" aria-controls="footer-panel-resources">
                            <span class="footer-accordion-title-wrap">
                                <span class="footer-section-title text-xs font-semibold tracking-wide uppercase app-footer-muted">Resources</span>
                            </span>
                            <span class="footer-accordion-icon" aria-hidden="true"></span>
                        </button>
                        <div id="footer-panel-resources" class="footer-accordion-panel">
                            <ul class="space-y-0.5 app-footer-muted font-medium">
                                <li><a href="?page=organizations" class="app-footer-link">Organization Directory</a></li>
                                <li><a href="?page=organizations" class="app-footer-link">Financial Reports</a></li>
                                <li><a href="?page=register&amp;privacy=1" class="app-footer-link">Privacy Notice</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="footer-section">
                        <button type="button" class="footer-accordion-toggle" aria-expanded="false" aria-controls="footer-panel-governance">
                            <span class="footer-accordion-title-wrap">
                                <span class="footer-section-title text-xs font-semibold tracking-wide uppercase app-footer-muted">Governance &amp; Security</span>
                            </span>
                            <span class="footer-accordion-icon" aria-hidden="true"></span>
                        </button>
                        <div id="footer-panel-governance" class="footer-accordion-panel">
                            <ul class="space-y-0.5 app-footer-muted font-medium">
                                <?php if ($footerRole === 'admin'): ?>
                                    <li><a href="?page=admin_audit" class="app-footer-link">Audit Log Policy</a></li>
                                <?php endif; ?>
                                <li><a href="?page=register&amp;privacy=1" class="app-footer-link">Data Privacy Notice</a></li>
                                <li>
                                    <div class="flex items-center gap-2 app-footer-muted">
                                        <span class="relative flex h-2.5 w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-70"></span>
                                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                        </span>
                                        <span>All Systems Operational</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="footer-section footer-section-support">
                        <button type="button" class="footer-accordion-toggle" aria-expanded="false" aria-controls="footer-panel-support">
                            <span class="footer-accordion-title-wrap">
                                <span class="footer-section-title text-xs font-semibold tracking-wide uppercase app-footer-muted">Support</span>
                            </span>
                            <span class="footer-accordion-icon" aria-hidden="true"></span>
                        </button>
                        <div id="footer-panel-support" class="footer-accordion-panel">
                            <ul class="space-y-0.5 app-footer-muted font-medium">
                                <li>
                                    <address style="font-style: normal;">
                                        <strong>Student Affairs Office</strong><br>
                                        <a href="mailto:studentaffairs@campus.local" class="app-footer-link">studentaffairs@campus.local</a><br>
                                        <span class="app-footer-muted">2nd Floor, Student Services Building</span><br>
                                        <span class="app-footer-muted">Main Campus, City 1000</span>
                                    </address>
                                </li>
                                <?php if ($footerUser && ($footerUser['role'] ?? '') === 'student'): ?>
                                    <li class="pt-1">
                                        <form method="post" id="restartOnboardingForm" class="inline-flex">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="restart_onboarding">
                                            <button type="submit" class="app-footer-link underline decoration-dotted">Replay onboarding tour</button>
                                        </form>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom-bar px-4 py-2 border-t border-emerald-200/40 flex items-center justify-between flex-wrap">
                    <p class="text-xs app-footer-muted"><?php echo date('Y') > 2026 ? '&copy; 2026–' . date('Y') : '&copy; 2026'; ?> Student Organization Management System. All rights reserved.</p>
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

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var restartForm = document.getElementById('restartOnboardingForm');
                if (!restartForm) {
                    return;
                }

                var onboardingStorageKey = <?= json_encode('websys_onboarding_done_' . (string) (($footerUser['id'] ?? '') !== '' ? (int) $footerUser['id'] : 'guest')) ?>;
                var onboardingStepKey = <?= json_encode('websys_onboarding_step_' . (string) (($footerUser['id'] ?? '') !== '' ? (int) $footerUser['id'] : 'guest')) ?>;

                restartForm.addEventListener('submit', function () {
                    localStorage.removeItem(onboardingStorageKey);
                    sessionStorage.removeItem(onboardingStepKey);
                });
            });
        </script>

        <script src="static/js/image-cropper.js?v=<?= e((string) @filemtime(__DIR__ . '/../../static/js/image-cropper.js')) ?>"></script>

        <script>
            (function () {
                var container = document.getElementById('toast-container');
                if (!container) {
                    return;
                }

                var typeLabels = {
                    success: 'Success',
                    error: 'Error',
                    warning: 'Warning',
                    info: 'Info'
                };

                var normalizeType = function (type) {
                    var value = String(type || 'info').toLowerCase();
                    return Object.prototype.hasOwnProperty.call(typeLabels, value) ? value : 'info';
                };

                var dismissToast = function (toast) {
                    if (!toast || toast.dataset.toastState === 'leaving') {
                        return;
                    }

                    toast.dataset.toastState = 'leaving';
                    toast.classList.add('is-leaving');

                    var remove = function () {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    };

                    toast.addEventListener('animationend', remove, { once: true });
                    window.setTimeout(remove, 260);
                };

                var showToast = function (message, type, duration) {
                    var text = String(message || '').trim();
                    if (text === '') {
                        return null;
                    }

                    var toastType = normalizeType(type);
                    var displayDuration = Number(duration);
                    if (!Number.isFinite(displayDuration) || displayDuration < 0) {
                        displayDuration = 4000;
                    }

                    var toast = document.createElement('div');
                    toast.className = 'toast toast-' + toastType;
                    toast.setAttribute('role', 'status');
                    toast.setAttribute('aria-live', toastType === 'error' ? 'assertive' : 'polite');

                    var body = document.createElement('div');
                    body.className = 'toast-body';

                    var accent = document.createElement('div');
                    accent.className = 'toast-accent';

                    var content = document.createElement('div');
                    content.className = 'toast-content';

                    var title = document.createElement('p');
                    title.className = 'toast-title';
                    title.textContent = typeLabels[toastType];

                    var messageNode = document.createElement('div');
                    messageNode.className = 'toast-message';
                    messageNode.textContent = text;

                    content.appendChild(title);
                    content.appendChild(messageNode);

                    var closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'toast-close';
                    closeButton.setAttribute('aria-label', 'Dismiss notification');
                    closeButton.innerHTML = '&times;';
                    closeButton.addEventListener('click', function () {
                        dismissToast(toast);
                    });

                    body.appendChild(accent);
                    body.appendChild(content);
                    body.appendChild(closeButton);
                    toast.appendChild(body);

                    container.appendChild(toast);

                    if (displayDuration > 0) {
                        window.setTimeout(function () {
                            dismissToast(toast);
                        }, displayDuration);
                    }

                    return toast;
                };

                window.Toast = {
                    show: showToast,
                    dismiss: dismissToast
                };

                document.addEventListener('DOMContentLoaded', function () {
                    var flashMessage = document.body.dataset.flash || '';
                    if (flashMessage.trim() === '') {
                        return;
                    }

                    showToast(flashMessage, document.body.dataset.flashType || 'info', 4000);
                });
            })();
        </script>

        <script>
            function initCurrencyInput(inputEl) {
                if (!(inputEl instanceof HTMLInputElement) || inputEl.dataset.currencyInitialized === '1') {
                    return;
                }

                const parent = inputEl.parentElement;
                if (!parent) {
                    return;
                }

                let wrapper = parent;
                if (!parent.classList.contains('currency-input-wrap')) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'currency-input-wrap';
                    parent.insertBefore(wrapper, inputEl);
                    wrapper.appendChild(inputEl);
                }

                if (!wrapper.querySelector('.currency-prefix')) {
                    const prefix = document.createElement('span');
                    prefix.className = 'currency-prefix';
                    prefix.textContent = '₱';
                    wrapper.insertBefore(prefix, inputEl);
                }

                if (inputEl.type === 'number') {
                    inputEl.type = 'text';
                }
                inputEl.setAttribute('inputmode', 'decimal');
                inputEl.dataset.currencyInitialized = '1';

                const toRaw = function (value) {
                    const cleaned = String(value || '').replace(/[^\d.]/g, '');
                    if (cleaned === '') {
                        return '';
                    }

                    const firstDotIndex = cleaned.indexOf('.');
                    let integerPart = cleaned;
                    let decimalPart = '';

                    if (firstDotIndex !== -1) {
                        integerPart = cleaned.slice(0, firstDotIndex);
                        decimalPart = cleaned.slice(firstDotIndex + 1).replace(/\./g, '').slice(0, 2);
                    }

                    if (integerPart === '' && decimalPart !== '') {
                        integerPart = '0';
                    }

                    integerPart = integerPart.replace(/^0+(?=\d)/, '');
                    if (integerPart === '' && decimalPart === '') {
                        return '';
                    }

                    return decimalPart !== '' ? integerPart + '.' + decimalPart : integerPart;
                };

                const toFormatted = function (rawValue) {
                    if (rawValue === '') {
                        return '';
                    }

                    const parts = rawValue.split('.');
                    const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    if (parts.length < 2) {
                        return intPart;
                    }

                    return intPart + '.' + parts[1].slice(0, 2);
                };

                const syncCurrencyValue = function (sourceValue) {
                    const raw = toRaw(sourceValue);
                    inputEl.dataset.currencyRaw = raw;
                    inputEl.value = toFormatted(raw);
                };

                inputEl.addEventListener('input', function () {
                    syncCurrencyValue(inputEl.value);
                });

                inputEl.form?.addEventListener('submit', function (event) {
                    const raw = toRaw(inputEl.value);
                    inputEl.dataset.currencyRaw = raw;
                    inputEl.value = raw;

                    // Restore formatted display if submission is cancelled on the client side.
                    window.setTimeout(function () {
                        if (event.defaultPrevented) {
                            inputEl.value = toFormatted(inputEl.dataset.currencyRaw || '');
                        }
                    }, 0);
                });

                syncCurrencyValue(inputEl.value);
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('input[data-currency]').forEach(function (inputEl) {
                    initCurrencyInput(inputEl);
                });
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var toggleInputs = document.querySelectorAll('input[data-password-toggle]');

                var eyeOpenSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="ui-icon" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6 9.75-6 9.75 6 9.75 6-3.75 6-9.75 6-9.75-6-9.75-6z" /><circle cx="12" cy="12" r="2.25" /></svg>';
                var eyeSlashSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="ui-icon" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" /><path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 102.83 2.83" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.87 10.87 0 0112 4.88c6 0 9.75 7.12 9.75 7.12a19.27 19.27 0 01-3.04 4.13" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A19.18 19.18 0 002.25 12S6 19.12 12 19.12c1.79 0 3.35-.41 4.72-1.03" /></svg>';

                toggleInputs.forEach(function (input) {
                    var wrapper = input.parentElement;
                    if (!wrapper || !wrapper.classList.contains('relative') || wrapper.getAttribute('data-password-toggle-wrapper') !== '1') {
                        var newWrapper = document.createElement('div');
                        newWrapper.className = 'relative';
                        newWrapper.setAttribute('data-password-toggle-wrapper', '1');
                        input.parentNode.insertBefore(newWrapper, input);
                        newWrapper.appendChild(input);
                        wrapper = newWrapper;
                    }

                    if (wrapper.querySelector('button[data-password-toggle-btn]')) {
                        return;
                    }

                    input.classList.add('pr-10');

                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'absolute inset-y-0 right-0 px-3 inline-flex items-center text-slate-600 hover:text-slate-900';
                    button.setAttribute('data-password-toggle-btn', '1');
                    button.setAttribute('aria-label', 'Show password');
                    button.setAttribute('aria-pressed', 'false');
                    button.innerHTML = eyeOpenSvg;

                    button.addEventListener('click', function () {
                        var showing = input.type === 'text';
                        input.type = showing ? 'password' : 'text';
                        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                        button.innerHTML = showing ? eyeOpenSvg : eyeSlashSvg;
                    });

                    wrapper.appendChild(button);
                });
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');

                var restoreButton = function (button) {
                    if (!button || button.dataset.loadingState !== '1') {
                        return;
                    }

                    button.disabled = false;
                    button.classList.remove('btn-loading');
                    if (typeof button.dataset.originalHtml === 'string') {
                        button.innerHTML = button.dataset.originalHtml;
                    }
                    delete button.dataset.loadingState;
                    delete button.dataset.originalHtml;
                };

                postForms.forEach(function (form) {
                    if (form.hasAttribute('data-no-loading')) {
                        return;
                    }

                    var submitButtons = form.querySelectorAll('button[type="submit"]');
                    if (submitButtons.length === 0) {
                        return;
                    }

                    form.addEventListener('submit', function (event) {
                        var activeElement = document.activeElement;
                        var submitButton = activeElement instanceof HTMLButtonElement && activeElement.type === 'submit' && form.contains(activeElement)
                            ? activeElement
                            : submitButtons[0];

                        if (!submitButton || submitButton.dataset.loadingState === '1') {
                            return;
                        }

                        submitButton.dataset.loadingState = '1';
                        submitButton.dataset.originalHtml = submitButton.innerHTML;
                        submitButton.disabled = true;
                        submitButton.classList.add('btn-loading');
                        submitButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="btn-loading-spinner" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="42 18"></circle></svg><span>Processing...</span>';

                        // If a submit handler cancels submission, revert the button state.
                        window.setTimeout(function () {
                            if (event.defaultPrevented) {
                                restoreButton(submitButton);
                            }
                        }, 0);

                        // Safety net for long-running requests or navigation cancelled by the browser.
                        window.setTimeout(function () {
                            restoreButton(submitButton);
                        }, 15000);
                    });
                });

                window.addEventListener('pageshow', function () {
                    var loadingButtons = document.querySelectorAll('button[data-loading-state="1"]');
                    loadingButtons.forEach(function (button) {
                        restoreButton(button);
                    });
                });
            });
        </script>

        <script>
            (function () {
                const root = document.body;
                const key = 'websys-theme';
                const paginationScrollKey = 'websys-pagination-scroll-y';
                const csrfToken = <?= json_encode(csrfToken()) ?>;

                const savedScroll = sessionStorage.getItem(paginationScrollKey);
                if (savedScroll !== null) {
                    const y = Number.parseInt(savedScroll, 10);
                    if (!Number.isNaN(y) && y >= 0) {
                        window.scrollTo(0, y);
                    }
                    sessionStorage.removeItem(paginationScrollKey);
                }

                const paginationLinks = document.querySelectorAll('a[data-preserve-scroll="1"]');
                paginationLinks.forEach(function (link) {
                    link.addEventListener('click', function () {
                        sessionStorage.setItem(paginationScrollKey, String(window.scrollY || window.pageYOffset || 0));
                    });
                });

                const postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');
                postForms.forEach(function (form) {
                    let csrfInput = form.querySelector('input[name="_csrf"]');
                    if (!csrfInput) {
                        csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_csrf';
                        form.appendChild(csrfInput);
                    }
                    csrfInput.value = csrfToken;
                });

                const themeToggle = document.getElementById('themeToggle');
                if (themeToggle) {
                    themeToggle.checked = root.classList.contains('theme-dark');
                    themeToggle.addEventListener('change', function () {
                        root.classList.toggle('theme-dark', themeToggle.checked);
                        localStorage.setItem(key, themeToggle.checked ? 'dark' : 'light');
                    });
                }

                const navToggle = document.getElementById('navMenuToggle');
                const mobileNavMenu = document.getElementById('mobileNavMenu');
                if (navToggle && mobileNavMenu) {
                    const setMobileNavState = function (open) {
                        mobileNavMenu.classList.toggle('is-open', open);
                        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    };

                    navToggle.addEventListener('click', function () {
                        const isOpen = mobileNavMenu.classList.contains('is-open');
                        setMobileNavState(!isOpen);
                    });

                    const mobileMenuLinks = mobileNavMenu.querySelectorAll('a');
                    mobileMenuLinks.forEach(function (link) {
                        link.addEventListener('click', function () {
                            setMobileNavState(false);
                        });
                    });

                    window.addEventListener('resize', function () {
                        if (window.innerWidth >= 1024) {
                            setMobileNavState(false);
                        }
                    });
                }

                const updatesModal = document.getElementById('loginUpdatesModal');
                const closeUpdatesBtn = document.getElementById('closeLoginUpdatesModal');
                const closeUpdatesBtnFooter = document.getElementById('closeLoginUpdatesModalBtn');
                if (updatesModal) {
                    updatesModal.classList.remove('hidden');

                    const closeUpdates = function () {
                        updatesModal.classList.add('hidden');
                    };

                    if (closeUpdatesBtn) {
                        closeUpdatesBtn.addEventListener('click', closeUpdates);
                    }

                    if (closeUpdatesBtnFooter) {
                        closeUpdatesBtnFooter.addEventListener('click', closeUpdates);
                    }

                    updatesModal.addEventListener('click', function (event) {
                        if (event.target === updatesModal) {
                            closeUpdates();
                        }
                    });
                }

                document.addEventListener('click', function (event) {
                    const overlay = event.target instanceof Element ? event.target.closest('[data-modal-close]') : null;
                    if (!overlay || event.target !== overlay) {
                        return;
                    }

                    overlay.classList.add('hidden');
                    if (overlay.hasAttribute('aria-hidden')) {
                        overlay.setAttribute('aria-hidden', 'true');
                    }

                    const openModal = document.querySelector('[data-modal-close]:not(.hidden)');
                    if (!openModal) {
                        document.body.style.overflow = '';
                    }
                });

                if ('ontouchstart' in window) {
                    document.querySelectorAll('.modal-panel').forEach(function (panel) {
                        let startY = 0;
                        let lastY = 0;
                        let activeTouchId = null;
                        let isDragging = false;

                        const resetPanel = function (animated) {
                            panel.classList.remove('is-dragging');
                            panel.style.transition = animated ? 'transform 0.2s ease' : '';
                            panel.style.transform = animated ? 'translateY(0px)' : '';

                            if (animated) {
                                window.setTimeout(function () {
                                    if (!panel.classList.contains('is-dragging')) {
                                        panel.style.transition = '';
                                    }
                                }, 200);
                            }
                        };

                        const closePanel = function () {
                            const closeButton = panel.querySelector('[data-modal-close-button]');
                            if (closeButton && typeof closeButton.click === 'function') {
                                closeButton.click();
                            }
                        };

                        panel.addEventListener('touchstart', function (event) {
                            if (event.touches.length !== 1 || panel.scrollTop > 0) {
                                return;
                            }

                            const touch = event.touches[0];
                            const bounds = panel.getBoundingClientRect();
                            if ((touch.clientY - bounds.top) > 72) {
                                return;
                            }

                            activeTouchId = touch.identifier;
                            startY = touch.clientY;
                            lastY = touch.clientY;
                            isDragging = true;
                            panel.classList.add('is-dragging');
                            panel.style.transition = 'none';
                        }, { passive: true });

                        panel.addEventListener('touchmove', function (event) {
                            if (!isDragging) {
                                return;
                            }

                            const touch = Array.prototype.find.call(event.touches, function (item) {
                                return item.identifier === activeTouchId;
                            }) || event.touches[0];

                            if (!touch) {
                                return;
                            }

                            lastY = touch.clientY;
                            const deltaY = Math.max(0, lastY - startY);
                            panel.style.transform = 'translateY(' + deltaY + 'px)';

                            if (deltaY > 0) {
                                event.preventDefault();
                            }
                        }, { passive: false });

                        const finishDrag = function () {
                            if (!isDragging) {
                                return;
                            }

                            const deltaY = Math.max(0, lastY - startY);
                            isDragging = false;
                            activeTouchId = null;

                            if (deltaY > 80) {
                                closePanel();
                                panel.style.transform = '';
                                panel.style.transition = '';
                                panel.classList.remove('is-dragging');
                                return;
                            }

                            resetPanel(true);
                        };

                        panel.addEventListener('touchend', finishDrag);
                        panel.addEventListener('touchcancel', function () {
                            isDragging = false;
                            activeTouchId = null;
                            resetPanel(true);
                        });
                    });
                }

                const footerToggles = document.querySelectorAll('.footer-accordion-toggle');
                const footer = document.querySelector('footer.app-footer');
                const defaultOpenIndex = footer ? parseInt(footer.getAttribute('data-default-open') || '0', 10) : 0;
                const footerDesktopQuery = window.matchMedia('(min-width: 768px)');

                const setFooterPanels = function () {
                    const isDesktop = footerDesktopQuery.matches;
                    footerToggles.forEach(function (toggle, index) {
                        const panelId = toggle.getAttribute('aria-controls');
                        if (!panelId) {
                            return;
                        }

                        const panel = document.getElementById(panelId);
                        if (!panel) {
                            return;
                        }

                        const isOpen = isDesktop || index === defaultOpenIndex;
                        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        panel.classList.toggle('is-open', isOpen);
                    });
                };

                footerToggles.forEach(function (toggle) {
                    toggle.addEventListener('click', function () {
                        if (footerDesktopQuery.matches) {
                            return;
                        }

                        const panelId = toggle.getAttribute('aria-controls');
                        if (!panelId) {
                            return;
                        }

                        const panel = document.getElementById(panelId);
                        if (!panel) {
                            return;
                        }

                        const currentlyOpen = toggle.getAttribute('aria-expanded') === 'true';

                        footerToggles.forEach(function (itemToggle) {
                            const itemPanelId = itemToggle.getAttribute('aria-controls');
                            const itemPanel = itemPanelId ? document.getElementById(itemPanelId) : null;
                            itemToggle.setAttribute('aria-expanded', 'false');
                            if (itemPanel) {
                                itemPanel.classList.remove('is-open');
                            }
                        });

                        if (!currentlyOpen) {
                            toggle.setAttribute('aria-expanded', 'true');
                            panel.classList.add('is-open');
                        }
                    });
                });

                setFooterPanels();
                if (typeof footerDesktopQuery.addEventListener === 'function') {
                    footerDesktopQuery.addEventListener('change', setFooterPanels);
                } else if (typeof footerDesktopQuery.addListener === 'function') {
                    footerDesktopQuery.addListener(setFooterPanels);
                }
            })();
        </script>
        <?php if (!empty($_SESSION['show_onboarding'])): ?>
            <style>
                .onboarding-layer {
                    position: fixed;
                    inset: 0;
                    z-index: 10020;
                    pointer-events: none;
                }

                .onboarding-backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(2, 24, 18, 0.56);
                    backdrop-filter: blur(1px);
                }

                .onboarding-focus {
                    position: fixed;
                    border-radius: 1rem;
                    box-shadow: 0 0 0 3px rgba(110, 231, 183, 0.95), 0 0 0 9999px rgba(2, 24, 18, 0.45);
                    transition: top 0.2s ease, left 0.2s ease, width 0.2s ease, height 0.2s ease;
                    pointer-events: none;
                    z-index: 10021;
                }

                .onboarding-tooltip {
                    position: fixed;
                    width: min(22rem, calc(100vw - 1.5rem));
                    border-radius: 1rem;
                    border: 1px solid rgba(110, 231, 183, 0.26);
                    background: rgba(4, 24, 18, 0.96);
                    color: #ecfdf5;
                    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.35);
                    padding: 0.95rem 1rem 0.85rem;
                    pointer-events: auto;
                    z-index: 10022;
                }

                .onboarding-tooltip::before {
                    content: '';
                    position: absolute;
                    width: 0.8rem;
                    height: 0.8rem;
                    background: inherit;
                    border-left: 1px solid rgba(110, 231, 183, 0.26);
                    border-top: 1px solid rgba(110, 231, 183, 0.26);
                    transform: rotate(45deg);
                }

                .onboarding-tooltip[data-placement='bottom']::before {
                    top: -0.4rem;
                    left: 1.4rem;
                }

                .onboarding-tooltip[data-placement='top']::before {
                    bottom: -0.4rem;
                    left: 1.4rem;
                    transform: rotate(225deg);
                }

                .onboarding-title {
                    margin: 0;
                    font-size: 0.98rem;
                    font-weight: 700;
                    color: #d1fae5;
                }

                .onboarding-body {
                    margin: 0.45rem 0 0;
                    font-size: 0.86rem;
                    line-height: 1.5;
                    color: rgba(236, 253, 245, 0.82);
                }

                .onboarding-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.5rem;
                    margin-top: 0.85rem;
                }

                .onboarding-button {
                    border: 1px solid rgba(110, 231, 183, 0.2);
                    border-radius: 0.65rem;
                    padding: 0.5rem 0.85rem;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                }

                .onboarding-button-next {
                    background: linear-gradient(135deg, #10b981, #059669);
                    color: #f0fdf4;
                }

                .onboarding-button-done {
                    background: rgba(15, 23, 42, 0.35);
                    color: #ecfdf5;
                }

                .onboarding-tooltip-code {
                    display: inline-block;
                    margin-top: 0.5rem;
                    padding: 0.2rem 0.45rem;
                    border-radius: 999px;
                    background: rgba(16, 185, 129, 0.14);
                    color: #bbf7d0;
                    font-size: 0.72rem;
                }
            </style>
            <script>
                (function () {
                    const userStorageSuffix = <?= json_encode((string) (($footerUser['id'] ?? '') !== '' ? (int) $footerUser['id'] : 'guest')) ?>;
                    const storageKey = 'websys_onboarding_done_' + userStorageSuffix;
                    const stepStorageKey = 'websys_onboarding_step_' + userStorageSuffix;
                    if (localStorage.getItem(storageKey) === '1') {
                        return;
                    }

                    const steps = [
                        {
                            target: '#dashboardWelcomeMessage',
                            title: 'Welcome',
                            body: 'This area gives you a quick read on your organizations, current activity, and budget status.'
                        },
                        {
                            target: '.nav-organizations-link',
                            title: 'Browse organizations',
                            body: 'Open the organizations directory to see groups you can join and their visibility rules.'
                        },
                        {
                            target: '[data-tour="join-button"]:not([disabled])',
                            title: 'Request to join',
                            body: 'Use a join button on the organizations page when you want to become a member of a group.'
                        },
                        {
                            target: '#dashboardAnnouncementsSection',
                            title: 'Announcements',
                            body: 'Important updates and recent announcements appear here so you can stay informed.'
                        },
                        {
                            target: '#dashboardBudgetTransparencySection',
                            title: 'Budget transparency',
                            body: 'Check the finance status section to review income, expenses, and balance at a glance.'
                        }
                    ];

                    const stepPages = ['dashboard', 'dashboard', 'organizations', 'dashboard', 'dashboard'];
                    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
                    const root = document.createElement('div');
                    root.className = 'onboarding-layer';
                    root.innerHTML = '<div class="onboarding-backdrop" aria-hidden="true"></div>';

                    const focus = document.createElement('div');
                    focus.className = 'onboarding-focus hidden';

                    const tooltip = document.createElement('div');
                    tooltip.className = 'onboarding-tooltip';
                    tooltip.setAttribute('role', 'dialog');
                    tooltip.setAttribute('aria-live', 'polite');

                    root.appendChild(focus);
                    root.appendChild(tooltip);
                    document.body.appendChild(root);

                    let stepIndex = Number.parseInt(sessionStorage.getItem(stepStorageKey) || '0', 10);
                    if (!Number.isFinite(stepIndex) || stepIndex < 0) {
                        stepIndex = 0;
                    }

                    const normalizeTarget = function (selector) {
                        if (selector === '.nav-organizations-link') {
                            if (window.matchMedia('(max-width: 1023px)').matches) {
                                return document.querySelector('#mobileNavMenu .nav-organizations-link') || document.querySelector(selector);
                            }

                            return document.querySelector('#appNav .nav-desktop .nav-organizations-link') || document.querySelector(selector);
                        }

                        if (selector === '[data-tour="join-button"]:not([disabled])') {
                            return document.querySelector(selector) || document.querySelector('[data-tour="join-button"]');
                        }

                        return document.querySelector(selector);
                    };

                    const getStepElement = function (index) {
                        const step = steps[index];
                        if (!step) {
                            return null;
                        }

                        return normalizeTarget(step.target);
                    };

                    const navigateToStepPage = function (index) {
                        const page = stepPages[index] || 'dashboard';
                        sessionStorage.setItem(stepStorageKey, String(index));
                        const targetUrl = '?page=' + encodeURIComponent(page);
                        if (currentPage !== page) {
                            window.location.href = targetUrl;
                            return true;
                        }

                        return false;
                    };

                    const completeOnboarding = function () {
                        localStorage.setItem(storageKey, '1');
                        sessionStorage.removeItem(stepStorageKey);

                        return fetch('?action=complete_onboarding', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: 'action=complete_onboarding&_csrf=' + encodeURIComponent(<?= json_encode(csrfToken()) ?>),
                            credentials: 'same-origin'
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('Unable to complete onboarding');
                            }

                            return response.json();
                        }).catch(function () {
                            return null;
                        }).finally(function () {
                            root.remove();
                        });
                    };

                    const moveToNextAvailableStep = function (startIndex) {
                        for (let index = startIndex; index < steps.length; index += 1) {
                            const page = stepPages[index] || 'dashboard';
                            if (currentPage !== page) {
                                navigateToStepPage(index);
                                return true;
                            }

                            if (getStepElement(index)) {
                                stepIndex = index;
                                sessionStorage.setItem(stepStorageKey, String(index));
                                return false;
                            }
                        }

                        completeOnboarding();
                        return true;
                    };

                    const positionTooltip = function (element) {
                        const rect = element.getBoundingClientRect();
                        const spacing = 16;
                        const tooltipRect = tooltip.getBoundingClientRect();
                        const tooltipHeight = tooltipRect.height || 180;
                        const tooltipWidth = tooltipRect.width || 320;

                        focus.classList.remove('hidden');
                        focus.style.top = Math.max(8, rect.top - 6) + 'px';
                        focus.style.left = Math.max(8, rect.left - 6) + 'px';
                        focus.style.width = Math.min(window.innerWidth - 16, rect.width + 12) + 'px';
                        focus.style.height = Math.min(window.innerHeight - 16, rect.height + 12) + 'px';

                        const placeBelow = rect.bottom + spacing + tooltipHeight < window.innerHeight;
                        const top = placeBelow ? rect.bottom + spacing : Math.max(12, rect.top - spacing - tooltipHeight);
                        let left = rect.left;
                        if (left + tooltipWidth > window.innerWidth - 12) {
                            left = window.innerWidth - tooltipWidth - 12;
                        }
                        left = Math.max(12, left);

                        tooltip.dataset.placement = placeBelow ? 'bottom' : 'top';
                        tooltip.style.top = top + 'px';
                        tooltip.style.left = left + 'px';
                    };

                    const renderStep = function () {
                        const step = steps[stepIndex];
                        if (!step) {
                            sessionStorage.removeItem(stepStorageKey);
                            root.remove();
                            return;
                        }

                        if (stepIndex === 1 && window.matchMedia('(max-width: 1023px)').matches) {
                            const navToggle = document.getElementById('navMenuToggle');
                            const mobileNavMenu = document.getElementById('mobileNavMenu');
                            if (navToggle && mobileNavMenu) {
                                mobileNavMenu.classList.add('is-open');
                                navToggle.setAttribute('aria-expanded', 'true');
                            }
                        }

                        const page = stepPages[stepIndex] || 'dashboard';
                        if (currentPage !== page) {
                            navigateToStepPage(stepIndex);
                            return;
                        }

                        const element = getStepElement(stepIndex);
                        if (!element) {
                            if (moveToNextAvailableStep(stepIndex + 1)) {
                                return;
                            }

                            renderStep();
                            return;
                        }

                        const title = document.createElement('p');
                        title.className = 'onboarding-title';
                        title.textContent = step.title;

                        const body = document.createElement('p');
                        body.className = 'onboarding-body';
                        body.textContent = step.body;

                        const actions = document.createElement('div');
                        actions.className = 'onboarding-actions';

                        const nextButton = document.createElement('button');
                        nextButton.type = 'button';
                        nextButton.className = 'onboarding-button onboarding-button-next';

                        const isLastStep = stepIndex === steps.length - 1;
                        nextButton.textContent = isLastStep ? 'Done' : 'Next';

                        nextButton.addEventListener('click', function () {
                            if (isLastStep) {
                                completeOnboarding();
                                return;
                            }

                            const nextIndex = stepIndex + 1;
                            sessionStorage.setItem(stepStorageKey, String(nextIndex));
                            const nextPage = stepPages[nextIndex] || 'dashboard';
                            if (nextPage !== currentPage) {
                                window.location.href = '?page=' + encodeURIComponent(nextPage);
                                return;
                            }

                            stepIndex = nextIndex;
                            renderStep();
                        });

                        actions.appendChild(nextButton);

                        tooltip.innerHTML = '';
                        tooltip.appendChild(title);
                        tooltip.appendChild(body);
                        tooltip.appendChild(actions);

                        if (!isLastStep) {
                            const code = document.createElement('span');
                            code.className = 'onboarding-tooltip-code';
                            code.textContent = 'Step ' + (stepIndex + 1) + ' of ' + steps.length;
                            tooltip.appendChild(code);
                        }

                        element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                        window.setTimeout(function () {
                            positionTooltip(element);
                        }, 220);
                    };

                    const observer = typeof ResizeObserver !== 'undefined'
                        ? new ResizeObserver(function () {
                            const element = getStepElement(stepIndex);
                            if (element && !root.classList.contains('hidden')) {
                                positionTooltip(element);
                            }
                        })
                        : null;

                    window.addEventListener('scroll', function () {
                        const element = getStepElement(stepIndex);
                        if (element && !root.classList.contains('hidden')) {
                            positionTooltip(element);
                        }
                    }, { passive: true });

                    window.addEventListener('resize', function () {
                        const element = getStepElement(stepIndex);
                        if (element && !root.classList.contains('hidden')) {
                            positionTooltip(element);
                        }
                    }, { passive: true });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            localStorage.setItem(storageKey, '1');
                            sessionStorage.removeItem(stepStorageKey);
                            root.remove();
                        }
                    });

                    if (moveToNextAvailableStep(stepIndex)) {
                        return;
                    }

                    const initialElement = getStepElement(stepIndex);

                    if (initialElement && observer) {
                        observer.observe(initialElement);
                    }

                    renderStep();
                })();
            </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
}

function renderBreadcrumb(array $crumbs): void
{
    if ($crumbs === []) {
        return;
    }

    echo '<nav aria-label="breadcrumb" class="mb-3">';
    echo '<ol class="flex items-center gap-2 text-xs text-slate-500 whitespace-nowrap overflow-x-auto">';

    $lastIndex = count($crumbs) - 1;
    foreach ($crumbs as $index => $crumb) {
        $label = e((string) ($crumb['label'] ?? ''));
        $url = $crumb['url'] ?? null;

        echo '<li class="inline-flex items-center gap-2">';
        if ($url !== null && $url !== '') {
            echo '<a href="' . e((string) $url) . '" class="hover:text-slate-700">' . $label . '</a>';
        } else {
            echo '<span class="text-slate-700" aria-current="page">' . $label . '</span>';
        }

        if ($index < $lastIndex) {
            echo '<span aria-hidden="true" class="text-slate-400">/</span>';
        }

        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';
}

function renderEmptyState(string $icon, string $title, string $message, ?string $actionLabel = null, ?string $actionUrl = null): void
{
    $iconMarkup = match ($icon) {
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M16 16l5 5"></path></svg>',
        'folder' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5a2 2 0 012-2h4l1.8 2H18.5a2 2 0 012 2v8a2 2 0 01-2 2H5.5a2 2 0 01-2-2z"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8" cy="9" r="2.5"></circle><circle cx="16" cy="10" r="2.5"></circle><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 18c.8-2.2 2.8-3.5 5.1-3.5S12.9 15.8 13.7 18"></path><path stroke-linecap="round" stroke-linejoin="round" d="M13 18c.6-1.8 2.1-2.9 3.9-2.9 1.8 0 3.3 1.1 3.9 2.9"></path></svg>',
        'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5h16"></path><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V10"></path><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V6"></path><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v-3"></path></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 8l7.5 5 7.5-5"></path></svg>',
    };

    echo '<div class="glass empty-state">';
    echo '<div class="empty-state-icon">' . $iconMarkup . '</div>';
    echo '<h3 class="empty-state-title">' . e($title) . '</h3>';
    echo '<p class="empty-state-message">' . e($message) . '</p>';

    if ($actionLabel !== null && $actionLabel !== '' && $actionUrl !== null && $actionUrl !== '') {
        echo '<a href="' . e($actionUrl) . '" class="empty-state-action">' . e($actionLabel) . '</a>';
    }

    echo '</div>';
}

function renderSkeletonDashboard(): void
{
    echo '<div class="dashboard-shell space-y-3" aria-hidden="true">';

    echo '<section class="grid xl:grid-cols-12 gap-3">';
    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-24"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-2xl h-7"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-xl"></div>';
    echo '</div>';
    echo '<div class="dashboard-metric-grid mt-4">';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-36"></div>';
    echo '<div class="skeleton skeleton-text w-full max-w-sm"></div>';
    echo '</div>';
    echo '<div class="mt-4 space-y-3">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-4/5"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '<div class="skeleton skeleton-text w-2/3"></div>';
    echo '</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="grid xl:grid-cols-12 gap-3">';
    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-40"></div>';
    echo '<div class="skeleton skeleton-text w-64"></div>';
    echo '</div>';
    echo '<div class="dashboard-metric-grid mb-3">';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '<div class="skeleton skeleton-stat"></div>';
    echo '</div>';
    echo '<div class="skeleton skeleton-card"></div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-28"></div>';
    echo '<div class="skeleton skeleton-text w-52"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-5/6"></div>';
    echo '<div class="skeleton skeleton-text w-4/5"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-5 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-32"></div>';
    echo '<div class="skeleton skeleton-text w-60"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-7 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-36"></div>';
    echo '<div class="skeleton skeleton-text w-64"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-5/6"></div>';
    echo '<div class="skeleton skeleton-text w-3/4"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="glass dashboard-panel xl:col-span-12 p-4 md:p-4">';
    echo '<div class="space-y-2 mb-3">';
    echo '<div class="skeleton skeleton-text w-56"></div>';
    echo '<div class="skeleton skeleton-text w-80"></div>';
    echo '</div>';
    echo '<div class="space-y-2">';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-full"></div>';
    echo '<div class="skeleton skeleton-text w-11/12"></div>';
    echo '</div>';
    echo '</div>';
    echo '</section>';

    echo '</div>';
}
