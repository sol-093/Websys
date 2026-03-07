<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uiIcon(string $name, string $classes = 'ui-icon', bool $ariaHidden = true, ?string $label = null): string
{
    $icons = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9.75V21h13.5V9.75" />',
        'dashboard' => '<rect x="3" y="3" width="8" height="8" rx="1.5" /><rect x="13" y="3" width="8" height="5" rx="1.5" /><rect x="13" y="10" width="8" height="11" rx="1.5" /><rect x="3" y="13" width="8" height="8" rx="1.5" />',
        'orgs' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 21V8.25L12 3l7.5 5.25V21" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 21v-5.25h6V21" />',
        'students' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 7.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a5.25 5.25 0 0110.5 0" /><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 9.75a2.25 2.25 0 100-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 20.25a4.5 4.5 0 014.5-4.5" />',
        'requests' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15v10.5h-15z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5l7.5 5.25L19.5 7.5" />',
        'audit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3h7.5v3h3A1.5 1.5 0 0120.25 7.5v12A1.5 1.5 0 0118.75 21h-13.5A1.5 1.5 0 013.75 19.5v-12A1.5 1.5 0 015.25 6h3V3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 11.25h7.5M8.25 15h7.5" />',
        'my-org' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 21V6.75A1.5 1.5 0 017.5 5.25h9A1.5 1.5 0 0118 6.75V21" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9h4.5M9.75 12.75h4.5" />',
        'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5.25a1.5 1.5 0 01-1.5-1.5v-15a1.5 1.5 0 011.5-1.5H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 16.5L21 12l-4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9.75" />',
        'login' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5.25a1.5 1.5 0 01-1.5-1.5v-15a1.5 1.5 0 011.5-1.5H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 16.5L9.75 12l4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12H21" />',
        'register' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5a3.75 3.75 0 100-7.5 3.75 3.75 0 000 7.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0115 0" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.5h3m-1.5-1.5v3" />',
        'prev' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.75L8.25 12l6 5.25" />',
        'next' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 6.75L15.75 12l-6 5.25" />',
        'create' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5.25v13.5M5.25 12h13.5" />',
        'save' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 4.5h10.5l3 3V19.5a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5V4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5V9h6V4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15h7.5" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 16.5V19.5h3l9.75-9.75-3-3L4.5 16.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12.75 8.25l3 3" />',
        'delete' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 7.5V5.25h6V7.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5l.75 12.75h7.5L16.5 7.5" />',
        'view' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6 9.75-6 9.75 6 9.75 6-3.75 6-9.75 6-9.75-6-9.75-6z" /><circle cx="12" cy="12" r="2.25" />',
        'refresh' => '<path stroke-linecap="round" stroke-linejoin="round" d="M18.75 8.25V3.75h-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 10-2.197 5.303L18.75 15.75" />',
        'announce' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.25V8.625a1.5 1.5 0 011.125-1.45l10.5-2.625a1.5 1.5 0 011.875 1.456V18a1.5 1.5 0 01-1.875 1.456l-10.5-2.625A1.5 1.5 0 014.5 15.375V12" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 16.5v2.25a1.5 1.5 0 001.5 1.5h1.5" />',
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

    return ['path' => 'public/uploads/' . $filename, 'error' => null];
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
