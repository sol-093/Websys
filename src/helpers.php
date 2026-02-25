<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
