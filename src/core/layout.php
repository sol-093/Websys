<?php

declare(strict_types=1);

function renderHeader(string $title = 'Dashboard'): void
{
    $config = require __DIR__ . '/config.php';
    $user = currentUser();
    $flash = getFlash();
    $loginUpdates = $_SESSION['login_updates_popup'] ?? [];
    unset($_SESSION['login_updates_popup']);
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
                    radial-gradient(900px 420px at 0% 0%, rgba(16, 185, 129, 0.08), transparent 62%),
                    radial-gradient(900px 460px at 100% 0%, rgba(45, 212, 191, 0.07), transparent 64%),
                    linear-gradient(180deg, #fcfefd 0%, #f4f8f7 100%);
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
                max-width: 100%;
                overflow-x: auto;
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

            .nav-brand {
                min-width: 0;
                flex: 1 1 auto;
                max-width: calc(100% - 5.5rem);
                line-height: 1.15;
                overflow-wrap: anywhere;
                word-break: break-word;
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

            @media (min-width: 1024px) {
                .nav-brand {
                    flex: 1 1 17rem;
                    max-width: 22rem;
                }

                .nav-desktop {
                    display: flex !important;
                    gap: 0.85rem;
                }

                .nav-mobile {
                    display: none !important;
                }
            }

            @media (min-width: 1024px) and (max-width: 1180px) {
                .nav-brand {
                    max-width: 15rem;
                    font-size: 1.55rem;
                    line-height: 1.05;
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
                color: #bbf7d0 !important;
            }

            body.theme-dark .text-red-700,
            body.theme-dark .text-red-800 {
                color: #fecaca !important;
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

            .theme-switch {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
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
                z-index: 60;
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
                max-height: calc(100dvh - 1.25rem);
                overflow: hidden;
            }

            body.theme-dark .updates-modal-overlay {
                background: rgba(2, 6, 23, 0.55);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }

            .updates-modal-overlay.hidden {
                display: none;
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
            .updates-status-declined {
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
            body.theme-dark .updates-status-declined {
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
                background: radial-gradient(circle, rgba(52, 211, 153, 0.22) 0%, rgba(52, 211, 153, 0) 70%);
                pointer-events: none;
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
                background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(250, 252, 251, 0.98));
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
                background: rgba(255, 255, 255, 0.94);
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
                color: #059669 !important;
            }

            body:not(.theme-dark) .dashboard-shell .text-red-300 {
                color: #dc2626 !important;
            }

            body:not(.theme-dark) .dashboard-shell .text-indigo-100 {
                color: #065f46 !important;
            }

            body:not(.theme-dark) #financialSummaryModal .glass {
                background: rgba(255, 255, 255, 0.97);
                border-color: rgba(15, 23, 42, 0.1);
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
        <nav class="glass sticky top-3 z-20 mx-2 sm:mx-3 mt-3 text-slate-800">
            <div class="max-w-7xl mx-auto px-4 py-3">
                <div class="flex items-center justify-between gap-2 min-w-0">
                    <a href="?page=home" class="nav-brand font-bold tracking-tight text-emerald-900 text-xl modern-title"><?= e($config['app_name']) ?></a>
                    <div class="nav-desktop hidden lg:flex gap-4 text-sm items-center">
                        <a href="?page=home" class="nav-link <?= $currentPage === 'home' ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                                <a href="?page=admin_audit" class="nav-link <?= $currentPage === 'admin_audit' ? 'nav-link-active' : '' ?>">Audit Logs</a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                                <a href="?page=my_org" class="nav-link <?= $currentPage === 'my_org' ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="?page=profile" class="nav-link <?= $currentPage === 'profile' ? 'nav-link-active' : '' ?>">Profile</a>
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
                    <div class="nav-mobile lg:hidden flex items-center gap-2 nav-mobile-controls">
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

                <div id="mobileNavMenu" class="mobile-nav-panel hidden lg:hidden">
                    <div class="flex flex-col gap-3 text-sm">
                        <a href="?page=home" class="nav-link <?= $currentPage === 'home' ? 'nav-link-active' : '' ?>">Home</a>
                        <?php if ($user): ?>
                            <a href="?page=dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'nav-link-active' : '' ?>">Dashboard</a>
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="?page=admin_orgs" class="nav-link <?= $currentPage === 'admin_orgs' ? 'nav-link-active' : '' ?>">Manage Orgs</a>
                                <a href="?page=admin_students" class="nav-link <?= $currentPage === 'admin_students' ? 'nav-link-active' : '' ?>">Students</a>
                                <a href="?page=admin_requests" class="nav-link <?= $currentPage === 'admin_requests' ? 'nav-link-active' : '' ?>">Requests</a>
                                <a href="?page=admin_audit" class="nav-link <?= $currentPage === 'admin_audit' ? 'nav-link-active' : '' ?>">Audit Logs</a>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                                <a href="?page=my_org" class="nav-link <?= $currentPage === 'my_org' ? 'nav-link-active' : '' ?>">My Organization</a>
                            <?php endif; ?>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="?page=profile" class="nav-link <?= $currentPage === 'profile' ? 'nav-link-active' : '' ?>">Profile</a>
                            <?php endif; ?>
                            <div class="text-xs text-slate-600">Hi, <?= e($user['name']) ?> (<?= e($user['role']) ?>)</div>
                            <a href="?page=logout" class="bg-indigo-900 text-white px-3 py-2 rounded text-center hover:bg-indigo-950">Logout</a>
                        <?php else: ?>
                            <a href="?page=login" class="nav-link <?= $currentPage === 'login' ? 'nav-link-active' : '' ?>">Login</a>
                            <a href="?page=register" class="bg-emerald-600 text-white px-3 py-2 rounded text-center hover:bg-emerald-700 shadow-sm">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto p-3 sm:p-4 lg:p-6">
            <?php if ($flash): ?>
                <div class="glass mb-4 rounded px-4 py-3 text-white <?= $flash['type'] === 'error' ? 'border-red-300/60 bg-red-500/20' : 'border-emerald-300/60 bg-emerald-500/20' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($user && is_array($loginUpdates) && count($loginUpdates) > 0): ?>
                <div id="loginUpdatesModal" class="updates-modal-overlay hidden">
                    <div class="glass w-full max-w-2xl p-5">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <h2 class="text-lg font-semibold icon-label"><?= uiIcon('update', 'ui-icon') ?><span>Request Updates</span></h2>
                                <p class="text-sm text-slate-600">Latest approval/rejection results related to your requests.</p>
                            </div>
                            <button type="button" id="closeLoginUpdatesModal" class="text-slate-600 hover:text-slate-900 text-xl leading-none">&times;</button>
                        </div>
                        <div class="space-y-2 max-h-[55vh] overflow-auto pr-1">
                            <?php foreach ($loginUpdates as $item): ?>
                                <?php
                                    $status = strtolower((string) ($item['status'] ?? ''));
                                    $statusClass = 'updates-status updates-status-' . preg_replace('/[^a-z]/', '', $status);
                                    $statusIcon = match ($status) {
                                        'approved', 'accepted' => 'approved',
                                        'rejected', 'declined' => 'rejected',
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
                            <button type="button" id="closeLoginUpdatesModalBtn" class="bg-indigo-900 text-white px-3 py-2 rounded hover:bg-indigo-950">Close</button>
                        </div>
                    </div>
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
                    navToggle.addEventListener('click', function () {
                        const isOpen = !mobileNavMenu.classList.contains('hidden');
                        mobileNavMenu.classList.toggle('hidden', isOpen);
                        navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
                    });

                    window.addEventListener('resize', function () {
                        if (window.innerWidth >= 1024) {
                            mobileNavMenu.classList.add('hidden');
                            navToggle.setAttribute('aria-expanded', 'false');
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
            })();
        </script>
        </main>
    </body>
    </html>
    <?php
}
