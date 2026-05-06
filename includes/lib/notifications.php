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
    $updates = [];
    $db = db();
    $days = max(1, min(90, $days));
    $limit = max(1, min(50, $limit));
    $sourceLimit = max(5, $limit);

    $joinStmt = $db->prepare("SELECT o.name AS organization_name, r.status, COALESCE(r.updated_at, r.created_at) AS event_at
        FROM organization_join_requests r
        JOIN organizations o ON o.id = r.organization_id
        WHERE r.user_id = ? AND r.status IN ('approved', 'declined')
        ORDER BY event_at DESC
        LIMIT {$sourceLimit}");
    $joinStmt->execute([$userId]);
    foreach ($joinStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Join Request',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

    $financeStmt = $db->prepare("SELECT o.name AS organization_name, r.status, r.action_type, COALESCE(r.updated_at, r.created_at) AS event_at
        FROM transaction_change_requests r
        JOIN organizations o ON o.id = r.organization_id
        WHERE r.requested_by = ? AND r.status IN ('approved', 'rejected')
        ORDER BY event_at DESC
        LIMIT {$sourceLimit}");
    $financeStmt->execute([$userId]);
    foreach ($financeStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Finance ' . ucfirst((string) $row['action_type']) . ' Request',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

    $assignmentStmt = $db->prepare("SELECT o.name AS organization_name, a.status, COALESCE(a.updated_at, a.created_at) AS event_at
        FROM owner_assignments a
        JOIN organizations o ON o.id = a.organization_id
        WHERE a.student_id = ? AND a.status IN ('accepted', 'declined')
        ORDER BY event_at DESC
        LIMIT {$sourceLimit}");
    $assignmentStmt->execute([$userId]);
    foreach ($assignmentStmt->fetchAll() as $row) {
        $updates[] = [
            'kind' => 'Organization Assignment',
            'status' => (string) $row['status'],
            'message' => 'Organization: ' . (string) $row['organization_name'],
            'event_at' => (string) $row['event_at'],
        ];
    }

        $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
                $securityStmt = $db->prepare("SELECT id, event_type, event_data, created_at
                        FROM security_notifications
                        WHERE user_id = ?
                            AND event_type = 'membership_removed'
                            AND created_at >= datetime('now', '-30 day')
                        ORDER BY created_at DESC
                        LIMIT {$sourceLimit}");
        } else {
                $securityStmt = $db->prepare("SELECT id, event_type, event_data, created_at
                        FROM security_notifications
                        WHERE user_id = ?
                            AND event_type = 'membership_removed'
                            AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                        ORDER BY created_at DESC
                        LIMIT {$sourceLimit}");
        }
        $securityStmt->execute([$userId]);
    foreach ($securityStmt->fetchAll() as $row) {
        $eventDataRaw = (string) ($row['event_data'] ?? '');
        $eventData = json_decode($eventDataRaw, true);
        if (!is_array($eventData)) {
            $eventData = [];
        }

        $removedOrganizations = [];
        if (isset($eventData['organizations']) && is_array($eventData['organizations'])) {
            foreach ($eventData['organizations'] as $orgName) {
                $name = trim((string) $orgName);
                if ($name !== '') {
                    $removedOrganizations[] = $name;
                }
            }
        }

        $reason = trim((string) ($eventData['reason'] ?? 'profile eligibility changed'));
        $message = 'Some memberships were removed after your ' . $reason . '.';
        if ($removedOrganizations !== []) {
            $message = 'Removed from: ' . implode(', ', $removedOrganizations);
        }

        $updates[] = [
            'kind' => 'Membership Eligibility',
            'status' => 'removed',
            'message' => $message,
            'event_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    usort($updates, static function (array $a, array $b): int {
        return strcmp((string) $b['event_at'], (string) $a['event_at']);
    });

    $cutoffTs = time() - ($days * 24 * 60 * 60);
    $updates = array_values(array_filter($updates, static function (array $item) use ($cutoffTs): bool {
        $eventAt = (string) ($item['event_at'] ?? '');
        $eventTs = strtotime($eventAt);
        return $eventTs !== false && $eventTs >= $cutoffTs;
    }));

    return array_slice($updates, 0, $limit);
}

function collectUserAuditTrail(int $userId, int $days = 14, int $limit = 25): array
{
    $db = db();
    $days = max(1, min(90, $days));
    $limit = max(1, min(100, $limit));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        "SELECT action, entity_type, entity_id, details, ip_address, user_agent, created_at
         FROM audit_logs
         WHERE user_id = ? AND created_at >= ?
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId, $cutoff]);

    return $stmt->fetchAll();
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
        str_starts_with($action, 'finance.') => 'finance',
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
