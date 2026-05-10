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
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function adminList(string $query = '', int $limit = 25, int $offset = 0): array
    {
        return $this->profile('organizations.admin_list', function () use ($query, $limit, $offset): array {
            $where = '';
            $params = [];
            $query = trim($query);
            if ($query !== '') {
                $where = 'WHERE o.name LIKE ? OR u.name LIKE ?';
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
                $params = [$like, $like];
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM organizations o LEFT JOIN users u ON u.id = o.owner_id $where");
            $countStmt->execute($params);

            $stmt = $this->db->prepare("SELECT o.*, u.name AS owner_name,
                    (SELECT COUNT(*) FROM organization_members om WHERE om.organization_id = o.id) AS member_count,
                    (SELECT COUNT(*) FROM organization_join_requests ojr WHERE ojr.organization_id = o.id AND ojr.status = 'pending') AS pending_join_count
                FROM organizations o
                LEFT JOIN users u ON u.id = o.owner_id
                $where
                ORDER BY o.name ASC
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @param array<string, mixed> $user
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function visibleDirectoryForUser(array $user, string $query = '', int $limit = 25, int $offset = 0): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $membershipIds = $this->membershipIdsForUser($userId);
        $joinStatuses = $this->joinRequestStatusForUser($userId);
        $visibleOrganizations = $this->applyVisibilityForUser($this->allWithOwnerNames(), $user, $membershipIds);

        $query = strtolower(trim($query));
        if ($query !== '') {
            $visibleOrganizations = array_values(array_filter($visibleOrganizations, static fn(array $organization): bool => str_contains(strtolower((string) ($organization['name'] ?? '')), $query)));
        }

        $items = array_map(static function (array $organization) use ($user, $membershipIds, $joinStatuses): array {
            $organizationId = (int) $organization['id'];
            $isJoined = in_array($organizationId, $membershipIds, true);
            $requestStatus = (string) ($joinStatuses[$organizationId] ?? '');

            return [
                'id' => $organizationId,
                'name' => (string) ($organization['name'] ?? ''),
                'description' => (string) ($organization['description'] ?? ''),
                'owner_name' => $organization['owner_name'] ?? null,
                'visibility' => self::visibilityLabel($organization),
                'joined' => $isJoined,
                'join_request_status' => $requestStatus !== '' ? $requestStatus : null,
                'can_join' => !$isJoined && $requestStatus !== 'pending' && self::canUserJoin($organization, $user),
            ];
        }, $visibleOrganizations);

        return [
            'items' => array_slice($items, max(0, $offset), max(1, $limit)),
            'total' => count($items),
        ];
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

    public function create(
        string $name,
        string $description,
        string $category,
        ?string $targetInstitute,
        ?string $targetProgram,
        ?string $logoPath,
        float $logoCropX,
        float $logoCropY,
        float $logoZoom
    ): int {
        $stmt = $this->db->prepare('INSERT INTO organizations (name, description, org_category, target_institute, target_program, logo_path, logo_crop_x, logo_crop_y, logo_zoom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $description, $category, $targetInstitute, $targetProgram, $logoPath, $logoCropX, $logoCropY, $logoZoom]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function editState(int $organizationId): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT owner_id, logo_path, logo_crop_x, logo_crop_y, logo_zoom FROM organizations WHERE id = ? LIMIT 1');
        $stmt->execute([$organizationId]);
        $org = $stmt->fetch();

        return $org ?: null;
    }

    public function updateDetails(
        int $organizationId,
        string $name,
        string $description,
        string $category,
        ?string $targetInstitute,
        ?string $targetProgram,
        ?string $logoPath,
        float $logoCropX,
        float $logoCropY,
        float $logoZoom
    ): void {
        $stmt = $this->db->prepare('UPDATE organizations SET name = ?, description = ?, org_category = ?, target_institute = ?, target_program = ?, logo_path = ?, logo_crop_x = ?, logo_crop_y = ?, logo_zoom = ? WHERE id = ?');
        $stmt->execute([$name, $description, $category, $targetInstitute, $targetProgram, $logoPath, $logoCropX, $logoCropY, $logoZoom, $organizationId]);
    }

    public function delete(int $organizationId): void
    {
        $stmt = $this->db->prepare('DELETE FROM organizations WHERE id = ?');
        $stmt->execute([$organizationId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJoinTarget(int $organizationId): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, org_category, target_institute, target_program FROM organizations WHERE id = ? LIMIT 1');
        $stmt->execute([$organizationId]);
        $org = $stmt->fetch();

        return $org ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwnerAssignmentTarget(int $organizationId): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, name, org_category, target_institute, target_program FROM organizations WHERE id = ? LIMIT 1');
        $stmt->execute([$organizationId]);
        $org = $stmt->fetch();

        return $org ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAssignableOwner(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, role, institute, program FROM users WHERE id = ? AND role IN ('student', 'owner') LIMIT 1");
        $stmt->execute([$userId]);
        $owner = $stmt->fetch();

        return $owner ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeAnnouncementsForOrganization(int $organizationId, string $activeCutoff): array
    {
        return $this->profile('organizations.active_announcements_for_organization', function () use ($organizationId, $activeCutoff): array {
            $stmt = $this->db->prepare('SELECT * FROM announcements WHERE organization_id = ? AND (expires_at IS NULL OR expires_at >= ?) ORDER BY id DESC');
            $stmt->execute([$organizationId, $activeCutoff]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingJoinRequestsForOrganization(int $organizationId): array
    {
        return $this->profile('organizations.pending_join_requests_for_organization', function () use ($organizationId): array {
            $stmt = $this->db->prepare("SELECT r.id, r.created_at, u.name, u.email, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom
                FROM organization_join_requests r
                JOIN users u ON u.id = r.user_id
                WHERE r.organization_id = ? AND r.status = 'pending'
                ORDER BY r.created_at DESC");
            $stmt->execute([$organizationId]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function joinRequestList(int $organizationId, string $status = 'pending', int $limit = 25, int $offset = 0): array
    {
        return $this->profile('organizations.join_request_list', function () use ($organizationId, $status, $limit, $offset): array {
            if (!in_array($status, ['pending', 'approved', 'declined', 'all'], true)) {
                $status = 'pending';
            }

            $whereStatus = $status === 'all' ? '' : ' AND ojr.status = ?';
            $params = $status === 'all' ? [$organizationId] : [$organizationId, $status];

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM organization_join_requests ojr WHERE ojr.organization_id = ?$whereStatus");
            $countStmt->execute($params);

            $stmt = $this->db->prepare("SELECT ojr.*, u.name, u.email, u.institute, u.program, u.year_level, u.section
                FROM organization_join_requests ojr
                JOIN users u ON u.id = ojr.user_id
                WHERE ojr.organization_id = ?$whereStatus
                ORDER BY ojr.created_at DESC
                LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute($params);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function membersForOrganization(int $organizationId): array
    {
        return $this->profile('organizations.members_for_organization', function () use ($organizationId): array {
            $stmt = $this->db->prepare('SELECT u.id, u.name, u.email, u.profile_picture_path, u.profile_picture_crop_x, u.profile_picture_crop_y, u.profile_picture_zoom, om.joined_at,
                CASE WHEN o.owner_id = u.id THEN 1 ELSE 0 END AS is_owner
                FROM organization_members om
                JOIN users u ON u.id = om.user_id
                JOIN organizations o ON o.id = om.organization_id
                WHERE om.organization_id = ?
                ORDER BY CASE WHEN o.owner_id = u.id THEN 0 ELSE 1 END, u.name ASC');
            $stmt->execute([$organizationId]);

            return $stmt->fetchAll() ?: [];
        });
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function memberList(int $organizationId, int $limit = 25, int $offset = 0): array
    {
        return $this->profile('organizations.member_list', function () use ($organizationId, $limit, $offset): array {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ?');
            $countStmt->execute([$organizationId]);

            $stmt = $this->db->prepare('SELECT om.*, u.name, u.email, u.institute, u.program, u.year_level, u.section
                FROM organization_members om
                JOIN users u ON u.id = om.user_id
                WHERE om.organization_id = ?
                ORDER BY om.joined_at DESC
                LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset));
            $stmt->execute([$organizationId]);

            return [
                'items' => $stmt->fetchAll() ?: [],
                'total' => (int) $countStmt->fetchColumn(),
            ];
        });
    }

    public function isMember(int $organizationId, int $userId): bool
    {
        if ($organizationId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM organization_members WHERE organization_id = ? AND user_id = ?');
        $stmt->execute([$organizationId, $userId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function memberForOrganization(int $organizationId, int $userId): ?array
    {
        if ($organizationId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.name
             FROM organization_members om
             JOIN users u ON u.id = om.user_id
             WHERE om.organization_id = ? AND om.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$organizationId, $userId]);
        $member = $stmt->fetch();

        return $member ?: null;
    }

    public function removeMember(int $organizationId, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM organization_members WHERE organization_id = ? AND user_id = ?');
        $stmt->execute([$organizationId, $userId]);
    }

    public function createJoinRequest(int $organizationId, int $userId): int
    {
        $stmt = $this->db->prepare('INSERT INTO organization_join_requests (organization_id, user_id, status) VALUES (?, ?, ?)');
        $stmt->execute([$organizationId, $userId, 'pending']);

        return (int) $this->db->lastInsertId();
    }

    public function joinRequestStatus(int $organizationId, int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT status FROM organization_join_requests WHERE organization_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$organizationId, $userId]);
        $status = $stmt->fetchColumn();

        return $status !== false ? (string) $status : null;
    }

    public function resubmitJoinRequest(int $organizationId, int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE organization_id = ? AND user_id = ?');
        $stmt->execute(['pending', $organizationId, $userId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingJoinRequest(int $requestId, int $organizationId): ?array
    {
        if ($requestId <= 0 || $organizationId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM organization_join_requests WHERE id = ? AND organization_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$requestId, $organizationId, 'pending']);
        $request = $stmt->fetch();

        return $request ?: null;
    }

    public function markJoinRequestDecision(int $requestId, string $status): void
    {
        if (!in_array($status, ['approved', 'declined'], true)) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE organization_join_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $requestId]);
    }

    public function clearOwnerAssignment(int $organizationId): void
    {
        $stmt = $this->db->prepare('UPDATE organizations SET owner_id = NULL WHERE id = ?');
        $stmt->execute([$organizationId]);

        $stmt = $this->db->prepare('DELETE FROM owner_assignments WHERE organization_id = ?');
        $stmt->execute([$organizationId]);
    }

    public function createPendingOwnerAssignment(int $organizationId, int $studentId): int
    {
        $stmt = $this->db->prepare('INSERT INTO owner_assignments (organization_id, student_id, status) VALUES (?, ?, ?)');
        $stmt->execute([$organizationId, $studentId, 'pending']);

        return (int) $this->db->lastInsertId();
    }

    public function clearPendingOwnerAssignments(int $organizationId): void
    {
        $stmt = $this->db->prepare('DELETE FROM owner_assignments WHERE organization_id = ? AND status = ?');
        $stmt->execute([$organizationId, 'pending']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingOwnerAssignmentForStudent(int $assignmentId, int $studentId): ?array
    {
        if ($assignmentId <= 0 || $studentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM owner_assignments WHERE id = ? AND student_id = ? AND status = ? LIMIT 1');
        $stmt->execute([$assignmentId, $studentId, 'pending']);
        $assignment = $stmt->fetch();

        return $assignment ?: null;
    }

    public function markOwnerAssignmentDecision(int $assignmentId, string $status): void
    {
        if (!in_array($status, ['accepted', 'declined'], true)) {
            return;
        }

        $stmt = $this->db->prepare('UPDATE owner_assignments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$status, $assignmentId]);
    }

    public function setOwner(int $organizationId, int $ownerId): void
    {
        $stmt = $this->db->prepare('UPDATE organizations SET owner_id = ? WHERE id = ?');
        $stmt->execute([$ownerId, $organizationId]);
    }

    public function promoteStudentToOwner(int $userId): void
    {
        $stmt = $this->db->prepare("UPDATE users SET role = 'owner' WHERE id = ? AND role = 'student'");
        $stmt->execute([$userId]);
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
     * @param list<array<string, mixed>> $organizations
     * @param array<string, mixed> $user
     * @param list<int> $memberOrganizationIds
     * @return list<array<string, mixed>>
     */
    private function applyVisibilityForUser(array $organizations, array $user, array $memberOrganizationIds): array
    {
        if ((string) ($user['role'] ?? 'student') === 'admin') {
            return self::sortByCategory($organizations);
        }

        $memberOrganizationIds = array_map('intval', $memberOrganizationIds);
        $userId = (int) ($user['id'] ?? 0);
        $userInstitute = self::normalizeAcademic((string) ($user['institute'] ?? ''));
        $userProgram = self::normalizeAcademic((string) ($user['program'] ?? ''));

        $filtered = array_values(array_filter($organizations, static function (array $org) use ($userInstitute, $userProgram, $memberOrganizationIds, $userId): bool {
            $orgId = (int) ($org['id'] ?? 0);
            if ($orgId > 0 && in_array($orgId, $memberOrganizationIds, true)) {
                return true;
            }

            if ($userId > 0 && (int) ($org['owner_id'] ?? 0) === $userId) {
                return true;
            }

            $category = (string) ($org['org_category'] ?? 'collegewide');
            if ($category === 'collegewide') {
                return true;
            }

            if ($category === 'institutewide') {
                return $userInstitute !== '' && self::normalizeAcademic((string) ($org['target_institute'] ?? '')) === $userInstitute;
            }

            if ($category === 'program_based') {
                return $userProgram !== '' && self::normalizeAcademic((string) ($org['target_program'] ?? '')) === $userProgram;
            }

            return true;
        }));

        return self::sortByCategory($filtered);
    }

    /**
     * @param list<array<string, mixed>> $organizations
     * @return list<array<string, mixed>>
     */
    private static function sortByCategory(array $organizations): array
    {
        $order = ['collegewide' => 1, 'institutewide' => 2, 'program_based' => 3];
        usort($organizations, static function (array $left, array $right) use ($order): int {
            $leftRank = $order[(string) ($left['org_category'] ?? 'collegewide')] ?? 99;
            $rightRank = $order[(string) ($right['org_category'] ?? 'collegewide')] ?? 99;

            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $organizations;
    }

    /**
     * @param array<string, mixed> $org
     */
    private static function visibilityLabel(array $org): string
    {
        $category = (string) ($org['org_category'] ?? 'collegewide');
        if ($category === 'institutewide') {
            $institute = trim((string) ($org['target_institute'] ?? ''));
            return 'Institutewide' . ($institute !== '' ? ' - ' . $institute : '');
        }

        if ($category === 'program_based') {
            $program = trim((string) ($org['target_program'] ?? ''));
            return 'Program-based' . ($program !== '' ? ' - ' . $program : '');
        }

        return 'Collegewide';
    }

    /**
     * @param array<string, mixed> $org
     * @param array<string, mixed> $user
     */
    private static function canUserJoin(array $org, array $user): bool
    {
        $category = (string) ($org['org_category'] ?? 'collegewide');
        if ($category === 'collegewide') {
            return true;
        }

        if ($category === 'institutewide') {
            $targetInstitute = self::normalizeAcademic((string) ($org['target_institute'] ?? ''));
            $userInstitute = self::normalizeAcademic((string) ($user['institute'] ?? ''));
            return $targetInstitute !== '' && $userInstitute !== '' && $targetInstitute === $userInstitute;
        }

        if ($category === 'program_based') {
            $targetProgram = self::normalizeAcademic((string) ($org['target_program'] ?? ''));
            $userProgram = self::normalizeAcademic((string) ($user['program'] ?? ''));
            return $targetProgram !== '' && $userProgram !== '' && $targetProgram === $userProgram;
        }

        return false;
    }

    private static function normalizeAcademic(string $value): string
    {
        return strtolower(trim($value));
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
