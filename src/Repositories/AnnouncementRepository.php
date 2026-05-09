<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class AnnouncementRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeWithOrganization(string $activeCutoff, int $limit = 30): array
    {
        return $this->profile('announcements.active_with_organization', function () use ($activeCutoff, $limit): array {
            $stmt = $this->db->prepare('SELECT a.*, o.name AS organization_name
                FROM announcements a
                JOIN organizations o ON o.id = a.organization_id
                WHERE (a.expires_at IS NULL OR a.expires_at >= ?)
                ORDER BY a.is_pinned DESC, COALESCE(a.pinned_at, a.created_at) DESC, a.created_at DESC, a.id DESC
                LIMIT ' . max(1, $limit));
            $stmt->execute([$activeCutoff]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeForOrganization(int $organizationId, string $activeCutoff): array
    {
        return $this->profile('announcements.active_for_organization', function () use ($organizationId, $activeCutoff): array {
            $stmt = $this->db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND (expires_at IS NULL OR expires_at >= ?) ORDER BY id DESC');
            $stmt->execute([$organizationId, $activeCutoff]);

            return $stmt->fetchAll() ?: [];
        });
    }

    public function create(int $organizationId, string $title, string $content, ?string $label, int $durationDays, string $expiresAt): int
    {
        $label = trim((string) $label);

        $stmt = $this->db->prepare('INSERT INTO announcements (organization_id, title, content, label, duration_days, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$organizationId, $title, $content, ($label !== '' ? $label : null), $durationDays, $expiresAt]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteForOrganization(int $announcementId, int $organizationId): void
    {
        $stmt = $this->db->prepare('DELETE FROM announcements WHERE id = ? AND organization_id = ?');
        $stmt->execute([$announcementId, $organizationId]);
    }

    public function exists(int $announcementId): bool
    {
        if ($announcementId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$announcementId]);

        return (bool) $stmt->fetch();
    }

    public function pinExclusive(int $announcementId): void
    {
        $this->db->exec('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE is_pinned = 1');

        $stmt = $this->db->prepare('UPDATE announcements SET is_pinned = 1, pinned_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$announcementId]);
    }

    public function unpin(int $announcementId): void
    {
        $stmt = $this->db->prepare('UPDATE announcements SET is_pinned = 0, pinned_at = NULL WHERE id = ?');
        $stmt->execute([$announcementId]);
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }
}
