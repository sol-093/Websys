<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - NOTIFICATION HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Collect User Request Updates
 * 2. Queue Login Popup Messages
 *
 * WORK GUIDE:
 * - Edit this file for notification aggregation shown after login.
 * ================================================
 */

function collectUserRequestUpdates(int $userId, int $days = 7, int $limit = 8): array
{
    return (new Involve\Repositories\NotificationRepository(db()))->requestUpdates($userId, $days, $limit);
}

function collectUserAuditTrail(int $userId, int $days = 14, int $limit = 25): array
{
    $days = max(1, min(90, $days));
    $limit = max(1, min(100, $limit));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

    return (new Involve\Repositories\AuditRepository(db()))->userTrail($userId, $cutoff, $limit);
}

function formatAuditActionLabel(string $action): string
{
    $normalized = trim(str_replace(['.', '_'], ' ', strtolower($action)));
    if ($normalized === '') {
        return 'System Event';
    }

    return ucwords($normalized);
}

function getAuditActionFamily(string $action): string
{
    $action = strtolower($action);

    return match (true) {
        str_starts_with($action, 'auth.'), str_starts_with($action, 'profile.') => 'auth',
        str_starts_with($action, 'organization.'), str_starts_with($action, 'join_request.'), str_starts_with($action, 'assignment.') => 'organization',
        str_starts_with($action, 'finance.'), str_starts_with($action, 'budget.') => 'finance',
        str_starts_with($action, 'announcement.') => 'announcement',
        default => 'system',
    };
}

function queueMembershipRemovalNotification(int $userId, array $removedMemberships, string $reason = 'profile update'): void
{
    if ($userId <= 0 || $removedMemberships === []) {
        return;
    }

    $organizationNames = [];
    foreach ($removedMemberships as $membership) {
        $name = trim((string) ($membership['organization_name'] ?? ''));
        if ($name !== '') {
            $organizationNames[] = $name;
        }
    }

    if ($organizationNames === []) {
        return;
    }

    $organizationNames = array_values(array_unique($organizationNames));
    $payload = json_encode([
        'reason' => $reason,
        'organizations' => $organizationNames,
    ]);
    if (!is_string($payload) || $payload === '') {
        return;
    }

    $insertStmt = db()->prepare('INSERT INTO security_notifications (user_id, event_type, event_data, sent_at) VALUES (?, ?, ?, NULL)');
    $insertStmt->execute([$userId, 'membership_removed', $payload]);
}

function queueBudgetExpenseRequestNotification(int $userId, string $eventType, array $payload): void
{
    if ($userId <= 0 || !in_array($eventType, ['budget_expense_request_submitted', 'budget_expense_request_approved', 'budget_expense_request_rejected'], true)) {
        return;
    }

    $encodedPayload = json_encode($payload);
    if (!is_string($encodedPayload) || $encodedPayload === '') {
        return;
    }

    try {
        $insertStmt = db()->prepare('INSERT INTO security_notifications (user_id, event_type, event_data, sent_at) VALUES (?, ?, ?, NULL)');
        $insertStmt->execute([$userId, $eventType, $encodedPayload]);
    } catch (Throwable $e) {
        error_log('Unable to queue BudgetFlow notification: ' . $e->getMessage());
    }
}

function queueBudgetExpenseRequestSubmittedNotifications(PDO $db, int $requestId): void
{
    try {
        $request = getExpenseRequestById($db, $requestId);
        if (!$request) {
            return;
        }

        $payload = buildBudgetExpenseRequestNotificationPayload($request);
        $adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin'");
        foreach ($adminStmt->fetchAll() ?: [] as $admin) {
            queueBudgetExpenseRequestNotification((int) ($admin['id'] ?? 0), 'budget_expense_request_submitted', $payload);
        }
    } catch (Throwable $e) {
        error_log('Unable to queue BudgetFlow submission notifications: ' . $e->getMessage());
    }
}

function queueBudgetExpenseRequestDecisionNotification(PDO $db, int $requestId, string $eventType, ?string $adminNote = null): void
{
    try {
        $request = getExpenseRequestById($db, $requestId);
        if (!$request) {
            return;
        }

        $payload = buildBudgetExpenseRequestNotificationPayload($request);
        if ($adminNote !== null && trim($adminNote) !== '') {
            $payload['admin_note'] = trim($adminNote);
        }

        queueBudgetExpenseRequestNotification((int) ($request['requested_by'] ?? 0), $eventType, $payload);
    } catch (Throwable $e) {
        error_log('Unable to queue BudgetFlow decision notification: ' . $e->getMessage());
    }
}

function buildBudgetExpenseRequestNotificationPayload(array $request): array
{
    return [
        'request_id' => (int) ($request['id'] ?? 0),
        'organization_name' => (string) ($request['organization_name'] ?? 'Organization'),
        'budget_title' => (string) ($request['budget_title'] ?? 'Budget'),
        'line_item_name' => (string) ($request['line_item_name'] ?? 'Budget line'),
        'amount' => round((float) ($request['amount'] ?? 0), 2),
    ];
}

function buildUpdatesMarker(array $updates): string
{
    return sha1(json_encode($updates));
}

function queueLoginUpdatesPopup(int $userId): void
{
    $updates = collectUserRequestUpdates($userId);
    if (count($updates) === 0) {
        $_SESSION['login_updates_popup'] = [];
        return;
    }

    $marker = buildUpdatesMarker($updates);
    $cookieName = 'websys_updates_seen_' . $userId;
    $seenMarker = (string) ($_COOKIE[$cookieName] ?? '');

    if ($seenMarker !== '' && hash_equals($seenMarker, $marker)) {
        $_SESSION['login_updates_popup'] = [];
        return;
    }

    $_SESSION['login_updates_popup'] = $updates;
    setcookie($cookieName, $marker, [
        'expires' => time() + (60 * 60 * 24 * 30),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
