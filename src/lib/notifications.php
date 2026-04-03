<?php

declare(strict_types=1);

function collectUserRequestUpdates(int $userId): array
{
    $updates = [];
    $db = db();

    $joinStmt = $db->prepare("SELECT o.name AS organization_name, r.status, COALESCE(r.updated_at, r.created_at) AS event_at
        FROM organization_join_requests r
        JOIN organizations o ON o.id = r.organization_id
        WHERE r.user_id = ? AND r.status IN ('approved', 'declined')
        ORDER BY event_at DESC
        LIMIT 5");
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
        LIMIT 5");
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
        LIMIT 5");
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
                        LIMIT 8");
        } else {
                $securityStmt = $db->prepare("SELECT id, event_type, event_data, created_at
                        FROM security_notifications
                        WHERE user_id = ?
                            AND event_type = 'membership_removed'
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ORDER BY created_at DESC
                        LIMIT 8");
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

    $cutoffTs = time() - (7 * 24 * 60 * 60);
    $updates = array_values(array_filter($updates, static function (array $item) use ($cutoffTs): bool {
        $eventAt = (string) ($item['event_at'] ?? '');
        $eventTs = strtotime($eventAt);
        return $eventTs !== false && $eventTs >= $cutoffTs;
    }));

    return array_slice($updates, 0, 8);
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
