<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestUpdates(int $userId, int $days = 7, int $limit = 8): array
    {
        if ($userId <= 0) {
            return [];
        }

        $days = max(1, min(90, $days));
        $limit = max(1, min(50, $limit));
        $sourceLimit = max(5, $limit);
        $updates = [];

        foreach ($this->joinRequestUpdates($userId, $sourceLimit) as $row) {
            $updates[] = [
                'kind' => 'Join Request',
                'status' => (string) $row['status'],
                'message' => 'Organization: ' . (string) $row['organization_name'],
                'event_at' => (string) $row['event_at'],
            ];
        }

        foreach ($this->financeRequestUpdates($userId, $sourceLimit) as $row) {
            $updates[] = [
                'kind' => 'Finance ' . ucfirst((string) $row['action_type']) . ' Request',
                'status' => (string) $row['status'],
                'message' => 'Organization: ' . (string) $row['organization_name'],
                'event_at' => (string) $row['event_at'],
            ];
        }

        foreach ($this->assignmentUpdates($userId, $sourceLimit) as $row) {
            $updates[] = [
                'kind' => 'Organization Assignment',
                'status' => (string) $row['status'],
                'message' => 'Organization: ' . (string) $row['organization_name'],
                'event_at' => (string) $row['event_at'],
            ];
        }

        foreach ($this->securityWorkflowUpdates($userId, $days, $sourceLimit) as $row) {
            $updates[] = $this->formatSecurityWorkflowUpdate($row);
        }

        usort($updates, static function (array $left, array $right): int {
            return strcmp((string) $right['event_at'], (string) $left['event_at']);
        });

        $cutoffTs = time() - ($days * 24 * 60 * 60);
        $updates = array_values(array_filter($updates, static function (array $item) use ($cutoffTs): bool {
            $eventAt = (string) ($item['event_at'] ?? '');
            $eventTs = strtotime($eventAt);
            return $eventTs !== false && $eventTs >= $cutoffTs;
        }));

        return array_slice($updates, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function joinRequestUpdates(int $userId, int $limit): array
    {
        $stmt = $this->db->prepare("SELECT o.name AS organization_name, r.status, COALESCE(r.updated_at, r.created_at) AS event_at
            FROM organization_join_requests r
            JOIN organizations o ON o.id = r.organization_id
            WHERE r.user_id = ? AND r.status IN ('approved', 'declined')
            ORDER BY event_at DESC
            LIMIT " . max(1, $limit));
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function financeRequestUpdates(int $userId, int $limit): array
    {
        $stmt = $this->db->prepare("SELECT o.name AS organization_name, r.status, r.action_type, COALESCE(r.updated_at, r.created_at) AS event_at
            FROM transaction_change_requests r
            JOIN organizations o ON o.id = r.organization_id
            WHERE r.requested_by = ? AND r.status IN ('approved', 'rejected')
            ORDER BY event_at DESC
            LIMIT " . max(1, $limit));
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignmentUpdates(int $userId, int $limit): array
    {
        $stmt = $this->db->prepare("SELECT o.name AS organization_name, a.status, COALESCE(a.updated_at, a.created_at) AS event_at
            FROM owner_assignments a
            JOIN organizations o ON o.id = a.organization_id
            WHERE a.student_id = ? AND a.status IN ('accepted', 'declined')
            ORDER BY event_at DESC
            LIMIT " . max(1, $limit));
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function securityWorkflowUpdates(int $userId, int $days, int $limit): array
    {
        $eventTypes = [
            'membership_removed',
            'budget_expense_request_submitted',
            'budget_expense_request_approved',
            'budget_expense_request_rejected',
        ];
        $placeholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $driver = (string) $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->db->prepare("SELECT id, event_type, event_data, created_at
                FROM security_notifications
                WHERE user_id = ?
                    AND event_type IN ({$placeholders})
                    AND created_at >= datetime('now', '-{$days} day')
                ORDER BY created_at DESC
                LIMIT " . max(1, $limit));
        } else {
            $stmt = $this->db->prepare("SELECT id, event_type, event_data, created_at
                FROM security_notifications
                WHERE user_id = ?
                    AND event_type IN ({$placeholders})
                    AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                ORDER BY created_at DESC
                LIMIT " . max(1, $limit));
        }
        $stmt->execute(array_merge([$userId], $eventTypes));

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatSecurityWorkflowUpdate(array $row): array
    {
        $eventType = (string) ($row['event_type'] ?? '');
        $eventDataRaw = (string) ($row['event_data'] ?? '');
        $eventData = json_decode($eventDataRaw, true);
        if (!is_array($eventData)) {
            $eventData = [];
        }

        if (str_starts_with($eventType, 'budget_expense_request_')) {
            $status = match ($eventType) {
                'budget_expense_request_approved' => 'approved',
                'budget_expense_request_rejected' => 'rejected',
                default => 'pending',
            };
            $organizationName = trim((string) ($eventData['organization_name'] ?? 'Organization'));
            $lineItemName = trim((string) ($eventData['line_item_name'] ?? 'Budget line'));
            $amount = isset($eventData['amount']) ? (float) $eventData['amount'] : 0.0;
            $message = $organizationName . ' - ' . $lineItemName;
            if ($amount > 0) {
                $message .= ' - PHP' . number_format($amount, 2);
            }
            if ($status === 'rejected') {
                $note = trim((string) ($eventData['admin_note'] ?? ''));
                if ($note !== '') {
                    $message .= ' - Note: ' . $note;
                }
            }

            return [
                'kind' => 'Budget Expense Request',
                'status' => $status,
                'message' => $message,
                'event_at' => (string) ($row['created_at'] ?? ''),
            ];
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

        return [
            'kind' => 'Membership Eligibility',
            'status' => 'removed',
            'message' => $message,
            'event_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
