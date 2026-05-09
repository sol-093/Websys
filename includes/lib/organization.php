<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - ORGANIZATION HELPERS
 * ================================================
 *
 * SECTION MAP:
 * 1. Ownership and Membership Helpers
 * 2. Institute and Program Options
 * 3. Visibility, Category, and Join Rules
 * 4. Organization Sorting and Filtering
 *
 * WORK GUIDE:
 * - Edit this file for organization rules reused by pages and workflows.
 * ================================================
 */

function getOwnedOrganizations(int $ownerId): array
{
    return (new Involve\Repositories\OrganizationRepository(db()))->ownedByUser($ownerId);
}

function getOwnedOrganizationById(int $ownerId, int $organizationId): ?array
{
    $org = (new Involve\Repositories\OrganizationRepository(db()))->ownedByUserAndId($ownerId, $organizationId);

    return $org ?: null;
}

function ensureOrganizationMember(PDO $db, int $organizationId, int $userId): void
{
    if ($organizationId <= 0 || $userId <= 0) {
        return;
    }

    $stmt = $db->prepare('SELECT 1 FROM organization_members WHERE organization_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$organizationId, $userId]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $insertStmt = $db->prepare('INSERT INTO organization_members (organization_id, user_id) VALUES (?, ?)');
    $insertStmt->execute([$organizationId, $userId]);
}

function removeIneligibleOrganizationMemberships(PDO $db, int $userId, ?string $newInstitute, ?string $newProgram): array
{
    if ($userId <= 0) {
        return [];
    }

    $normalizedInstitute = normalizeAcademicValue((string) $newInstitute);
    $normalizedProgram = normalizeAcademicValue((string) $newProgram);

    $stmt = $db->prepare(
        "SELECT om.organization_id, o.name, o.org_category, o.target_institute, o.target_program
         FROM organization_members om
         JOIN organizations o ON o.id = om.organization_id
         WHERE om.user_id = ?
           AND (o.owner_id IS NULL OR o.owner_id <> ?)"
    );
    $stmt->execute([$userId, $userId]);
    $memberships = $stmt->fetchAll() ?: [];

    $toRemoveIds = [];
    $removed = [];

    foreach ($memberships as $membership) {
        $category = (string) ($membership['org_category'] ?? 'collegewide');
        if ($category === 'collegewide') {
            continue;
        }

        $isEligible = true;
        if ($category === 'institutewide') {
            $targetInstitute = normalizeAcademicValue((string) ($membership['target_institute'] ?? ''));
            $isEligible = $targetInstitute !== '' && $normalizedInstitute !== '' && $targetInstitute === $normalizedInstitute;
        } elseif ($category === 'program_based') {
            $targetProgram = normalizeAcademicValue((string) ($membership['target_program'] ?? ''));
            $isEligible = $targetProgram !== '' && $normalizedProgram !== '' && $targetProgram === $normalizedProgram;
        }

        if ($isEligible) {
            continue;
        }

        $organizationId = (int) ($membership['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            continue;
        }

        $toRemoveIds[] = $organizationId;
        $removed[] = [
            'organization_id' => $organizationId,
            'organization_name' => (string) ($membership['name'] ?? ''),
            'org_category' => $category,
        ];
    }

    if ($toRemoveIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($toRemoveIds), '?'));
    $params = array_merge([$userId], $toRemoveIds);
    $deleteStmt = $db->prepare("DELETE FROM organization_members WHERE user_id = ? AND organization_id IN ($placeholders)");
    $deleteStmt->execute($params);

    return $removed;
}

function getInstituteOptions(): array
{
    return [
        'Institute of Computing and Digital Innovations',
        'Institute of Nursing',
        'Institute of Engineering',
        'Institute of Midwifery',
        'Institute of Science and Mathematics',
        'Institute of Behavioral Science',
    ];
}

function getProgramInstituteMap(): array
{
    return [
        'BS Information Systems' => 'Institute of Computing and Digital Innovations',
        'BS Data Science' => 'Institute of Computing and Digital Innovations',
        'BS Computer Science' => 'Institute of Computing and Digital Innovations',
        'BS Civil Engineering' => 'Institute of Engineering',
        'BS Psychology' => 'Institute of Behavioral Science',
        'BS Nursing' => 'Institute of Nursing',
        'BS Midwifery' => 'Institute of Midwifery',
        'BS Social Work' => 'Institute of Science and Mathematics',
    ];
}

function getProgramOptions(): array
{
    return array_keys(getProgramInstituteMap());
}

function getInstituteForProgram(?string $program): ?string
{
    $program = trim((string) $program);
    if ($program === '') {
        return null;
    }

    $programInstituteMap = getProgramInstituteMap();

    return $programInstituteMap[$program] ?? null;
}

function formatYearLevelLabel(?int $yearLevel): string
{
    $yearLevel = (int) $yearLevel;
    if ($yearLevel <= 0) {
        return 'Not set';
    }

    return match ($yearLevel) {
        1 => '1st Year',
        2 => '2nd Year',
        3 => '3rd Year',
        4 => '4th Year',
        default => $yearLevel . 'th Year',
    };
}

function getOrgCategoryOptions(): array
{
    return [
        'collegewide' => 'Collegewide (Open to all students)',
        'institutewide' => 'Institutewide (Per institute)',
        'program_based' => 'Program-based',
    ];
}

function normalizeAcademicValue(?string $value): string
{
    return strtolower(trim((string) $value));
}

function sortOrganizationsByCategory(array $organizations): array
{
    $order = ['collegewide' => 1, 'institutewide' => 2, 'program_based' => 3];
    usort($organizations, static function (array $a, array $b) use ($order): int {
        $aCategory = (string) ($a['org_category'] ?? 'collegewide');
        $bCategory = (string) ($b['org_category'] ?? 'collegewide');
        $aRank = $order[$aCategory] ?? 99;
        $bRank = $order[$bCategory] ?? 99;

        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $organizations;
}

function sortOrganizationsForDashboardPanel(array $organizations, array $user, array $memberOrganizationIds = []): array
{
    $order = ['collegewide' => 1, 'institutewide' => 2, 'program_based' => 3];
    $memberOrganizationIds = array_map('intval', $memberOrganizationIds);
    $userId = (int) ($user['id'] ?? 0);

    $isEligible = static function (array $org) use ($user, $memberOrganizationIds, $userId): bool {
        $orgId = (int) ($org['id'] ?? 0);
        if ($orgId > 0 && in_array($orgId, $memberOrganizationIds, true)) {
            return true;
        }

        if ($userId > 0 && (int) ($org['owner_id'] ?? 0) === $userId) {
            return true;
        }

        $role = (string) ($user['role'] ?? 'student');
        if ($role === 'admin') {
            return true;
        }

        return canUserJoinOrganization($org, $user);
    };

    usort($organizations, static function (array $a, array $b) use ($order, $isEligible): int {
        $aEligible = $isEligible($a) ? 1 : 0;
        $bEligible = $isEligible($b) ? 1 : 0;

        if ($aEligible !== $bEligible) {
            return $bEligible <=> $aEligible;
        }

        $aCategory = (string) ($a['org_category'] ?? 'collegewide');
        $bCategory = (string) ($b['org_category'] ?? 'collegewide');
        $aRank = $order[$aCategory] ?? 99;
        $bRank = $order[$bCategory] ?? 99;

        if ($aRank !== $bRank) {
            return $aRank <=> $bRank;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $organizations;
}

function applyOrganizationVisibilityForUser(array $organizations, array $user, array $memberOrganizationIds = []): array
{
    $role = (string) ($user['role'] ?? 'student');
    if ($role === 'admin') {
        return sortOrganizationsByCategory($organizations);
    }

    $memberOrganizationIds = array_map('intval', $memberOrganizationIds);
    $userId = (int) ($user['id'] ?? 0);
    $userInstitute = normalizeAcademicValue((string) ($user['institute'] ?? ''));
    $userProgram = normalizeAcademicValue((string) ($user['program'] ?? ''));

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
            return $userInstitute !== '' && normalizeAcademicValue((string) ($org['target_institute'] ?? '')) === $userInstitute;
        }

        if ($category === 'program_based') {
            return $userProgram !== '' && normalizeAcademicValue((string) ($org['target_program'] ?? '')) === $userProgram;
        }

        return true;
    }));

    return sortOrganizationsByCategory($filtered);
}

function getOrganizationVisibilityLabel(array $org): string
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'institutewide') {
        $institute = trim((string) ($org['target_institute'] ?? ''));
        return 'Institutewide' . ($institute !== '' ? ' • ' . $institute : '');
    }

    if ($category === 'program_based') {
        $program = trim((string) ($org['target_program'] ?? ''));
        return 'Program-based' . ($program !== '' ? ' • ' . $program : '');
    }

    return 'Collegewide';
}

function canUserJoinOrganization(array $org, array $user): bool
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'collegewide') {
        return true;
    }

    $userInstitute = normalizeAcademicValue((string) ($user['institute'] ?? ''));
    $userProgram = normalizeAcademicValue((string) ($user['program'] ?? ''));

    if ($category === 'institutewide') {
        $targetInstitute = normalizeAcademicValue((string) ($org['target_institute'] ?? ''));
        return $targetInstitute !== '' && $userInstitute !== '' && $targetInstitute === $userInstitute;
    }

    if ($category === 'program_based') {
        $targetProgram = normalizeAcademicValue((string) ($org['target_program'] ?? ''));
        return $targetProgram !== '' && $userProgram !== '' && $targetProgram === $userProgram;
    }

    return false;
}

function getJoinRestrictionLabel(array $org): string
{
    $category = (string) ($org['org_category'] ?? 'collegewide');
    if ($category === 'institutewide') {
        return 'Restricted (Institute mismatch)';
    }

    if ($category === 'program_based') {
        return 'Restricted (Program mismatch)';
    }

    return 'Restricted';
}
