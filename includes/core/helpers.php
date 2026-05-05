<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - SHARED CORE HELPERS
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Output, Redirects, Flashes, and Icons
 * 2. Request and CSRF Helpers
 * 3. Validation Helpers
 * 4. Security, Rate Limit, and Audit Helpers
 *
 * EDIT GUIDE:
 * - Edit this file for small cross-feature helpers that do not belong to one domain.
 * ================================================
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uiIcon(string $name, string $classes = 'ui-icon', bool $ariaHidden = true, ?string $label = null): string
{
    $icons = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9.75V21h13.5V9.75" />',
        'dashboard' => '<path d="M8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M19.4 3H15.6C15.0399 3 14.7599 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 19.4 3Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M19.4 14H15.6C15.0399 14 14.7599 14 14.546 14.109C14.3578 14.2049 14.2049 14.3578 14.109 14.546C14 14.7599 14 15.0399 14 15.6V19.4C14 19.9601 14 20.2401 14.109 20.454C14.2049 20.6422 14.3578 20.7951 14.546 20.891C14.7599 21 15.0399 21 15.6 21H19.4C19.9601 21 20.2401 21 20.454 20.891C20.6422 20.7951 20.7951 20.6422 20.891 20.454C21 20.2401 21 19.9601 21 19.4V15.6C21 15.0399 21 14.7599 20.891 14.546C20.7951 14.3578 20.6422 14.2049 20.454 14.109C20.2401 14 19.9601 14 19.4 14Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />',
        'orgs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />',
        'students' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 7.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a5.25 5.25 0 0110.5 0" /><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 9.75a2.25 2.25 0 100-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 20.25a4.5 4.5 0 014.5-4.5" />',
        'owner' => '<path d="M20 21C20 19.6044 20 18.9067 19.8278 18.3389C19.44 17.0605 18.4395 16.06 17.1611 15.6722C16.5933 15.5 15.8956 15.5 14.5 15.5H9.5C8.10444 15.5 7.40665 15.5 6.83886 15.6722C5.56045 16.06 4.56004 17.0605 4.17224 18.3389C4 18.9067 4 19.6044 4 21M16.5 7.5C16.5 9.98528 14.4853 12 12 12C9.51472 12 7.5 9.98528 7.5 7.5C7.5 5.01472 9.51472 3 12 3C14.4853 3 16.5 5.01472 16.5 7.5Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />',
        'students-owners' => '<path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" />',
        'requests' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15v10.5h-15z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5l7.5 5.25L19.5 7.5" />',
        'audit' => '<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 20v-9l-4 1.125V20h4Zm0 0h8m-8 0V6.66667M16 20v-9l4 1.125V20h-4Zm0 0V6.66667M18 8l-6-4-6 4m5 1h2m-2 3h2" />',
        'my-org' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />',
        'admin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5.25a1.5 1.5 0 01-1.5-1.5v-15a1.5 1.5 0 011.5-1.5H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 16.5L21 12l-4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9.75" />',
        'login' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5.25a1.5 1.5 0 01-1.5-1.5v-15a1.5 1.5 0 011.5-1.5H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 16.5L9.75 12l4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12H21" />',
        'register' => '<path d="M12 15.5H7.5C6.10444 15.5 5.40665 15.5 4.83886 15.6722C3.56045 16.06 2.56004 17.0605 2.17224 18.3389C2 18.9067 2 19.6044 2 21M19 21V15M16 18H22M14.5 7.5C14.5 9.98528 12.4853 12 10 12C7.51472 12 5.5 9.98528 5.5 7.5C5.5 5.01472 7.51472 3 10 3C12.4853 3 14.5 5.01472 14.5 7.5Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0115 0" />',
        'security' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2.25" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 21h10.5a1.5 1.5 0 001.5-1.5v-6a1.5 1.5 0 00-1.5-1.5H6.75a1.5 1.5 0 00-1.5 1.5v6a1.5 1.5 0 001.5 1.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12V7.5a3.75 3.75 0 017.5 0V12" />',
        'verify' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25 4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 9.75a9.75 9.75 0 01-9.75 9.75A9.75 9.75 0 010 9.75 9.75 9.75 0 019.75 0 9.75 9.75 0 0119.5 9.75z" />',
        'prev' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.75L8.25 12l6 5.25" />',
        'next' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 6.75L15.75 12l-6 5.25" />',
        'create' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5.25v13.5M5.25 12h13.5" />',
        'save' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5h10.5l3 3V19.5a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5V4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5V9h6V4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15h7.5" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 16.5V19.5h3l9.75-9.75-3-3L4.5 16.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12.75 8.25l3 3" />',
        'delete' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5V5.25h6V7.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5l.75 12.75h7.5L16.5 7.5" />',
        'view' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6 9.75-6 9.75 6 9.75 6-3.75 6-9.75 6-9.75-6-9.75-6z" /><circle cx="12" cy="12" r="2.25" />',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.75 8.25V3.75h-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 10-2.197 5.303L18.75 15.75" />',
        'announce' => '<path stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />',
        'search' => '<circle cx="10.5" cy="10.5" r="5.25" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 14.25L19.5 19.5" />',
        'open' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 6.75h7.5v7.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L8.25 15.75" /><path stroke-linecap="round" stroke-linejoin="round" d="M18 12v6a1.5 1.5 0 01-1.5 1.5h-10.5A1.5 1.5 0 014.5 18V7.5A1.5 1.5 0 016 6h6" />',
        'pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75l12 12-2.25 2.25-4.5-4.5-4.5 6V13.5L3.75 8.25 8.25 3.75z" />',
        'update' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v10.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 11.25L12 6.75l4.5 4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25h15" />',
        'approved' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15.75 9.75" /><circle cx="12" cy="12" r="9" />',
        'rejected' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5" /><circle cx="12" cy="12" r="9" />',
        'pending' => '<circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v4.5l3 1.5" />',
        'default' => '<circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5h.008v.008H12zM12 12.75v3" />',
    ];

    $pathMarkup = $icons[$name] ?? $icons['default'];
    $aria = $ariaHidden ? 'true' : 'false';
    $ariaLabel = $label !== null ? ' aria-label="' . e($label) . '" role="img"' : '';

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="' . e($classes) . '" aria-hidden="' . $aria . '"' . $ariaLabel . '>' . $pathMarkup . '</svg>';
}

function icon(string $name, string $classes = 'ui-icon', bool $ariaHidden = true, ?string $label = null): string
{
    return uiIcon($name, $classes, $ariaHidden, $label);
}

function getNameInitials(string $label, int $maxLetters = 2): string
{
    $normalized = trim($label);
    if ($normalized === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/', $normalized) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= $maxLetters) {
            break;
        }
    }

    if ($initials === '') {
        $initials = strtoupper(substr($normalized, 0, $maxLetters));
    }

    return substr($initials, 0, $maxLetters);
}

function renderProfilePlaceholder(string $label, string $entity = 'user', string $size = 'md'): string
{
    $sizes = [
        'xs' => 'w-6 h-6 text-[10px]',
        'sm' => 'w-8 h-8 text-xs',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-12 h-12 text-base',
    ];

    $palette = $entity === 'organization'
        ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
        : 'bg-emerald-100 text-emerald-800 border-emerald-200';

    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $initials = e(getNameInitials($label));
    $ariaLabel = $entity === 'organization' ? 'Organization profile placeholder' : 'User profile placeholder';

    return '<span class="inline-flex shrink-0 items-center justify-center rounded-full border font-semibold ' . $sizeClasses . ' ' . $palette . '" aria-label="' . e($ariaLabel) . '">' . $initials . '</span>';
}

function renderProfileMedia(
    string $label,
    ?string $imagePath,
    string $entity = 'user',
    string $size = 'md',
    ?float $cropX = null,
    ?float $cropY = null,
    ?float $zoom = null
): string
{
    $sizes = [
        'xs' => 'w-6 h-6',
        'sm' => 'w-8 h-8',
        'md' => 'w-10 h-10',
        'lg' => 'w-12 h-12',
    ];

    $normalizedPath = trim((string) $imagePath);
    if ($normalizedPath === '') {
        return renderProfilePlaceholder($label, $entity, $size);
    }

    $sizeClasses = $sizes[$size] ?? $sizes['md'];
    $alt = $entity === 'organization' ? 'Organization profile picture' : 'User profile picture';
    $cropX = $cropX === null ? 50.0 : max(0.0, min(100.0, $cropX));
    $cropY = $cropY === null ? 50.0 : max(0.0, min(100.0, $cropY));
    $zoom = $zoom === null ? 1.0 : max(0.5, min(2.5, $zoom));
    $wrapperStyle = sprintf('--media-crop-x:%s%%;--media-crop-y:%s%%;--media-zoom:%s;', rtrim(rtrim(number_format($cropX, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format($cropY, 2, '.', ''), '0'), '.'), rtrim(rtrim(number_format($zoom, 2, '.', ''), '0'), '.'));

    return '<span class="inline-flex shrink-0 overflow-hidden rounded-full border border-emerald-200/50 bg-slate-100 ' . $sizeClasses . '" style="' . e($wrapperStyle) . '"><img src="' . e($normalizedPath) . '" alt="' . e($alt) . '" class="h-full w-full object-cover" style="object-position: var(--media-crop-x) var(--media-crop-y); transform: scale(var(--media-zoom)); transform-origin: center center;" loading="lazy"></span>';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    $token = (string) ($_POST['_csrf'] ?? '');
    return verifyCsrfToken($token);
}

function verifyCsrfToken(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function csrfMiddleware(): void
{
    if (!isPost()) {
        return;
    }

    if (verifyCsrf()) {
        return;
    }

    setFlash('error', 'Invalid form session. Please try again.');
    $fallbackPage = (string) ($_GET['page'] ?? (currentUser() ? 'dashboard' : 'login'));
    redirect('?page=' . urlencode($fallbackPage));
}

function rateLimitIsBlocked(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $_SESSION['rate_limit'] = $_SESSION['rate_limit'] ?? [];

    $entry = $_SESSION['rate_limit'][$key] ?? null;
    if (!is_array($entry)) {
        return false;
    }

    $start = (int) ($entry['start'] ?? 0);
    $count = (int) ($entry['count'] ?? 0);

    if (($now - $start) >= $windowSeconds) {
        unset($_SESSION['rate_limit'][$key]);
        return false;
    }

    return $count >= $maxAttempts;
}

function rateLimitIncrement(string $key, int $windowSeconds): void
{
    $now = time();
    $_SESSION['rate_limit'] = $_SESSION['rate_limit'] ?? [];

    $entry = $_SESSION['rate_limit'][$key] ?? null;
    if (!is_array($entry)) {
        $_SESSION['rate_limit'][$key] = ['start' => $now, 'count' => 1];
        return;
    }

    $start = (int) ($entry['start'] ?? 0);
    if (($now - $start) >= $windowSeconds) {
        $_SESSION['rate_limit'][$key] = ['start' => $now, 'count' => 1];
        return;
    }

    $_SESSION['rate_limit'][$key]['count'] = ((int) ($entry['count'] ?? 0)) + 1;
}

function rateLimitClear(string $key): void
{
    if (isset($_SESSION['rate_limit'][$key])) {
        unset($_SESSION['rate_limit'][$key]);
    }
}

function validateAndStoreReceiptUpload(array $file, string $uploadDir): array
{
    if (empty($file['name']) || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        return ['path' => null, 'error' => null];
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Receipt upload failed. Please try again.'];
    }

    $maxBytes = 5 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return ['path' => null, 'error' => 'Receipt must be between 1 byte and 5MB.'];
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['path' => null, 'error' => 'Receipt file type is not allowed. Use JPG, PNG, or PDF.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string) $finfo->file((string) $file['tmp_name']);
    $allowedMimeMap = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
    ];

    if (!in_array($mimeType, $allowedMimeMap[$extension], true)) {
        return ['path' => null, 'error' => 'Receipt content does not match the selected file type.'];
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'receipt_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return ['path' => null, 'error' => 'Unable to save uploaded receipt. Please try again.'];
    }

    return ['path' => 'uploads/' . $filename, 'error' => null];
}

function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Password must include at least one special character.';
    }

    return null;
}

function auditLog(?int $userId, string $action, string $entityType = 'system', ?int $entityId = null, ?string $details = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500),
        ]);
    } catch (Throwable $e) {
    }
}
