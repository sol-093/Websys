<?php

declare(strict_types=1);

namespace Involve\Repositories;

use PDO;

final class OrganizationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allWithOwnerNames(): array
    {
        $rows = $this->profile('organizations.all_with_owner_names', fn() => $this->db
            ->query('SELECT o.*, u.name AS owner_name FROM organizations o LEFT JOIN users u ON u.id = o.owner_id ORDER BY o.name ASC')
            ->fetchAll());

        return $rows ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ownedByUser(int $ownerId): array
    {
        $rows = $this->profile('organizations.owned_by_user', function () use ($ownerId): array {
            $stmt = $this->db->prepare('SELECT * FROM organizations WHERE owner_id = ? ORDER BY name ASC');
            $stmt->execute([$ownerId]);

            return $stmt->fetchAll() ?: [];
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function ownedByUserAndId(int $ownerId, int $organizationId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM organizations WHERE owner_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$ownerId, $organizationId]);

        return $stmt->fetch();
    }

    /**
     * @return list<int>
     */
    public function membershipIdsForUser(int $userId): array
    {
        $rows = $this->profile('organizations.membership_ids_for_user', function () use ($userId): array {
            $stmt = $this->db->prepare('SELECT organization_id FROM organization_members WHERE user_id = ?');
            $stmt->execute([$userId]);

            return $stmt->fetchAll() ?: [];
        });

        return array_map('intval', array_column($rows, 'organization_id'));
    }

    private function profile(string $label, callable $callback): mixed
    {
        return function_exists('queryProfile') ? \queryProfile($label, $callback) : $callback();
    }

    /**
     * @return array<int, string>
     */
    public function joinRequestStatusForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT organization_id, status FROM organization_join_requests WHERE user_id = ?');
        $stmt->execute([$userId]);

        $statuses = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $statuses[(int) $row['organization_id']] = (string) $row['status'];
        }

        return $statuses;
    }
}
